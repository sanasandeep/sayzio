<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Form;
use Illuminate\Console\Command;

/**
 * Repair missing and duplicate `id` values inside `forms.fields` (jsonb).
 *
 * Why this exists: the builder UI always stamps a unique id
 * (type + '_' + random) on every field it creates, but forms whose `fields`
 * JSON was written straight into the database by a seeder or import script
 * can carry blank or repeated ids. Those forms broke the builder canvas
 * (Alpine's x-for threw on a duplicate/undefined key and never repainted),
 * and they still corrupt anything that keys off field id.
 *
 * IMPORTANT — why duplicates are opt-in:
 * submission answers are stored as `form_submissions.data[<field id>]`
 * (see FormController::analytics) and section membership is stored as
 * `field.parent = <section id>`. Renaming a field id therefore ORPHANS that
 * field's historical answers. Blank ids are safe to fill because no
 * meaningful answer can be keyed to an empty string, so those are repaired by
 * default. Renaming duplicates loses history for whichever copy gives up the
 * id, so it happens only under --include-duplicates.
 *
 * Duplicate strategy: the copy that KEEPS the shared id is the first one that
 * something actually points at via `parent` (otherwise simply the first
 * occurrence). That preserves both the section→children links and the larger
 * share of submission history; later copies are re-stamped.
 *
 * The command is idempotent: a form with unique, non-empty ids is skipped, so
 * a completed run is a no-op and an interrupted run can be resumed.
 */
class BackfillFormFieldIds extends Command
{
    protected $signature = 'forms:backfill-field-ids
        {--chunk=200 : Forms loaded per batch}
        {--form= : Repair a single form id (useful for a first cautious run)}
        {--include-duplicates : Also re-stamp duplicate ids. Orphans those fields\' existing submission answers}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Repair blank/duplicate field ids inside forms.fields so the builder canvas and id-keyed exports behave.';

    public function handle(): int
    {
        $chunk    = max(25, (int) $this->option('chunk'));
        $dry      = (bool) $this->option('dry-run');
        $withDups = (bool) $this->option('include-duplicates');
        $onlyForm = $this->option('form');

        if ($dry) {
            $this->info('DRY RUN — nothing will be written.');
        }
        if (!$withDups) {
            $this->line('Blank ids only. Pass --include-duplicates to also re-stamp duplicates (see --help).');
        } else {
            $this->warn('Duplicates WILL be re-stamped. Affected fields lose their existing submission answers.');
        }

        $scanned = $blankFixed = $dupFixed = $formsChanged = $dupSkipped = 0;

        $query = Form::query()->whereNotNull('fields')->orderBy('id');
        if ($onlyForm !== null && $onlyForm !== '') {
            $query->where('id', (int) $onlyForm);
        }

        $query->chunkById($chunk, function ($forms) use (
            $dry, $withDups, &$scanned, &$blankFixed, &$dupFixed, &$formsChanged, &$dupSkipped
        ) {
            foreach ($forms as $form) {
                $scanned++;
                $fields = $form->fields;
                if (!is_array($fields) || $fields === []) {
                    continue;
                }

                $result = self::repair($fields, $withDups);
                if ($result['blank'] === 0 && $result['dups'] === 0) {
                    if ($result['dupsSkipped'] > 0) {
                        $dupSkipped += $result['dupsSkipped'];
                        $this->line(sprintf(
                            '  form #%d "%s": %d duplicate id(s) left alone (re-run with --include-duplicates)',
                            $form->id, $form->title, $result['dupsSkipped']
                        ));
                    }
                    continue;
                }

                $blankFixed  += $result['blank'];
                $dupFixed    += $result['dups'];
                $dupSkipped  += $result['dupsSkipped'];
                $formsChanged++;

                $this->line(sprintf(
                    '  form #%d "%s": %d blank, %d duplicate re-stamped',
                    $form->id, $form->title, $result['blank'], $result['dups']
                ));

                if (!$dry) {
                    $form->fields = $result['fields'];
                    $form->save();
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '%s %d form(s) scanned, %d changed — %d blank id(s) filled, %d duplicate(s) re-stamped.',
            $dry ? 'Would change:' : 'Done:', $scanned, $formsChanged, $blankFixed, $dupFixed
        ));
        if ($dupSkipped > 0) {
            $this->warn(sprintf('%d duplicate id(s) left untouched. Re-run with --include-duplicates to re-stamp them.', $dupSkipped));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{fields:array,blank:int,dups:int,dupsSkipped:int}
     */
    private static function repair(array $fields, bool $withDups): array
    {
        // Ids something points at via `parent`: these must survive a
        // duplicate collision or section membership breaks.
        $referenced = [];
        foreach ($fields as $f) {
            $p = is_array($f) ? trim((string) ($f['parent'] ?? '')) : '';
            if ($p !== '') {
                $referenced[$p] = true;
            }
        }

        $taken = [];
        foreach ($fields as $f) {
            $id = is_array($f) ? trim((string) ($f['id'] ?? '')) : '';
            if ($id !== '') {
                $taken[$id] = true;
            }
        }

        // For each repeated id, decide which index keeps it: the first one
        // that is referenced as a parent, else the first occurrence.
        $occurrences = [];
        foreach ($fields as $i => $f) {
            $id = is_array($f) ? trim((string) ($f['id'] ?? '')) : '';
            if ($id !== '') {
                $occurrences[$id][] = $i;
            }
        }
        $keeper = [];
        foreach ($occurrences as $id => $idxs) {
            if (count($idxs) < 2) {
                continue;
            }
            $keeper[$id] = $idxs[0];
            if (isset($referenced[$id])) {
                foreach ($idxs as $i) {
                    $type = $fields[$i]['type'] ?? '';
                    if ($type === 'section') { $keeper[$id] = $i; break; }
                }
            }
        }

        $blank = $dups = $dupsSkipped = 0;

        foreach ($fields as $i => $f) {
            if (!is_array($f)) {
                continue;
            }
            $id = trim((string) ($f['id'] ?? ''));

            if ($id === '') {
                $fields[$i]['id'] = self::mintId((string) ($f['type'] ?? 'text'), $taken);
                $blank++;
                continue;
            }

            if (isset($keeper[$id]) && $keeper[$id] !== $i) {
                if (!$withDups) {
                    $dupsSkipped++;
                    continue;
                }
                $fields[$i]['id'] = self::mintId((string) ($f['type'] ?? 'text'), $taken);
                $dups++;
            }
        }

        return ['fields' => $fields, 'blank' => $blank, 'dups' => $dups, 'dupsSkipped' => $dupsSkipped];
    }

    /**
     * Mint an id in the same shape the builder JS uses
     * (type + '_' + 5 base36 chars), unique within this form.
     */
    private static function mintId(string $type, array &$taken): string
    {
        $type = preg_replace('/[^a-z0-9_]/i', '', $type) ?: 'text';
        do {
            $candidate = $type . '_' . substr(base_convert(bin2hex(random_bytes(5)), 16, 36), 0, 5);
        } while (isset($taken[$candidate]));

        $taken[$candidate] = true;
        return $candidate;
    }
}
