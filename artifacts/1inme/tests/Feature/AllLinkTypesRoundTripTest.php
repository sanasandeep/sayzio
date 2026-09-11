<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every link type the product offers, created, updated and opened.
 *
 * The site sells eighteen kinds of link. Before this, no test made one of
 * each: CreateLinkTypeCoverageTest walks the catalog but stops at the picker,
 * and ApiNewPageTypeCreateTest creates six of them through the API. Type by
 * type the coverage ran from very deep (short links, biolinks, events) to
 * nothing at all -- `vcf` had no feature test of any kind, and `resume` and
 * `ai_chat` had no test that created one.
 *
 * So the three things a link does, for all eighteen:
 *
 *   create   through the real web path, with the smallest payload the form
 *            actually accepts
 *   update   through links.update, and the type survives it
 *   open     at /{alias}, and the public page answers
 *
 * Driven off LinkTypeCategories::types(), never a list written here. A
 * nineteenth type added to the catalog joins this test the moment it exists,
 * and fails it until it can be created, updated and opened -- which is the
 * point. A hand-written list in a test about lists drifting would be its own
 * punchline.
 */
class AllLinkTypesRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // A File Share is the one type that cannot be made without somewhere
        // to put the file. UserFile::assertS3Configured() checks the config,
        // not the connection, and refuses before it writes anything -- which
        // is right, and means the web upload path is unreachable in a test
        // until the disk is declared.
        //
        // So: declare it, then hand the declaration a fake. Storage::fake
        // swaps the resolved disk for a local one in a temp directory while
        // the config still reads as S3, so the guard is satisfied by the
        // shape it actually checks and every byte written stays on this
        // machine. Nothing here reaches a real bucket, and the credentials
        // are obvious nonsense so that stays true if anyone reads them.
        config([
            'filesystems.disks.user_files' => [
                'driver' => 's3',
                'key' => 'round-trip-test-key',
                'secret' => 'round-trip-test-secret',
                'region' => 'us-east-1',
                'bucket' => 'round-trip-test-bucket',
            ],
        ]);

        \Illuminate\Support\Facades\Storage::fake('user_files');
    }

    /**
     * Every type in the catalog, with the minimum payload links.store needs.
     *
     * Only the fields the validator actually demands per type. Everything
     * else a type owns -- a menu's categories, a deck's slides, a kit's
     * assets -- is edited afterwards in that type's own editor, which is not
     * what this test is about.
     */
    private function payloadFor(string $type): array
    {
        $base = ['type' => $type, 'title' => 'Round trip ' . $type];

        return $base + match ($type) {
            'url' => ['long_url' => 'https://example.com/round-trip'],
            'text' => ['text_content' => 'A text page made by the round-trip test.'],
            default => [],
        };
    }

    /**
     * The three types that do not post to links.store.
     *
     * They have their own Step 2 form and their own controller, because each
     * one carries a payload the generic form has nowhere to put: an upload, a
     * date range, a person's contact details.
     */
    private function ownStorePath(string $type): ?array
    {
        return match ($type) {
            'file' => ['user.links.file.store', [
                'title' => 'Round trip file',
                'file' => \Illuminate\Http\UploadedFile::fake()->create('round-trip.pdf', 12, 'application/pdf'),
            ]],
            'ics' => ['user.links.ics.store', [
                'event_name' => 'Round trip event',
                'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
                'end_date' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
            ]],
            'vcf' => ['user.links.vcf.store', [
                'title' => 'Round trip card',
                'first_name' => 'Round',
                'last_name' => 'Trip',
            ]],
            default => null,
        };
    }

    public function test_every_catalog_type_can_be_created(): void
    {
        $failed = [];

        foreach ($this->catalogTypes() as $type) {
            $link = $this->createOne($type, $failed);

            if ($link) {
                $this->assertSame($type, $link->type, "a {$type} link was stored with type {$link->type}");
            }
        }

        $this->assertSame([], $failed, $this->report(
            'These link types could not be created through the path the product offers.',
            $failed
        ));
    }

    public function test_every_catalog_type_survives_an_update(): void
    {
        $failed = [];

        foreach ($this->catalogTypes() as $type) {
            $link = $this->createOne($type, $failed);

            if (! $link) {
                continue;
            }

            $response = $this->actingAs($this->user)->put(route('user.links.update', $link), [
                'title' => 'Renamed ' . $type,
                'alias' => $link->alias,
            ]);

            $link->refresh();

            if ($response->status() >= 400) {
                $failed[$type] = 'update returned ' . $response->status();

                continue;
            }

            if ($link->title !== 'Renamed ' . $type) {
                $failed[$type] = 'update did not persist the new title (still "' . $link->title . '")';

                continue;
            }

            // The update path never accepts `type`, and must not lose it.
            if ($link->type !== $type) {
                $failed[$type] = "update changed the type to {$link->type}";
            }
        }

        $this->assertSame([], $failed, $this->report(
            'These link types did not survive an edit.',
            $failed
        ));
    }

    /**
     * And the public page answers.
     *
     * Deliberately loose about WHAT it answers with: a short link redirects, a
     * vCard is a download, a page renders, and several page types fall back to
     * the generic biolink view when their companion row has not been filled in
     * yet -- which is correct, and is why a freshly created link is never a
     * dead end. What this rejects is 404 and 500: a type the product will sell
     * you and then refuse to open.
     */
    public function test_every_catalog_type_opens_at_its_alias(): void
    {
        $failed = [];

        foreach ($this->catalogTypes() as $type) {
            $link = $this->createOne($type, $failed);

            if (! $link) {
                continue;
            }

            $status = $this->get('/' . $link->alias)->status();

            if ($status >= 400) {
                $failed[$type] = "opening /{$link->alias} returned {$status}";
            }
        }

        $this->assertSame([], $failed, $this->report(
            'These link types can be created but not opened.',
            $failed
        ));
    }

    /**
     * The type lists have to agree with each other.
     *
     * There are four of them: the catalog, the picker's pre-select list, and
     * the `in:` rules on chooseType and store. Only the picker's cards are
     * generated from the catalog; the rest are hand-written strings, and one
     * of them had already drifted -- the pre-select list was missing six
     * types, so a user whose last link was a Reviews Page came back to Step 1
     * with the wrong card selected.
     *
     * This compares the two validation rules and the pre-select list against
     * the catalog, by reading them out of the controller rather than
     * restating them here.
     */
    public function test_the_type_lists_all_agree_with_the_catalog(): void
    {
        $catalog = $this->catalogTypes();
        sort($catalog);

        $source = (string) file_get_contents(
            app_path('Modules/User/Controllers/LinkController.php')
        );

        preg_match_all("/'type'\s*=>\s*'required\|in:([^']+)'/", $source, $rules);

        $this->assertGreaterThanOrEqual(2, count($rules[1]), 'the type validation rules are no longer where this test looks for them');

        foreach ($rules[1] as $i => $rule) {
            $listed = explode(',', $rule);
            sort($listed);

            $this->assertSame($catalog, $listed, sprintf(
                "Type validation rule #%d does not match the catalog.\n\n"
                . "missing from the rule : %s\n"
                . "in the rule only      : %s\n\n"
                . "LinkTypeCategories is the catalog the picker draws its cards from. A type\n"
                . "in the catalog but not in the rule is a card the user can click and then\n"
                . "cannot submit; a type in the rule but not the catalog is unreachable.",
                $i + 1,
                implode(', ', array_diff($catalog, $listed)) ?: '(none)',
                implode(', ', array_diff($listed, $catalog)) ?: '(none)'
            ));
        }
    }

    /**
     * The remembered-type shortcut works for every type, not most of them.
     *
     * Step 1 pre-selects the type you picked last time. That list was written
     * by hand and stopped at twelve, so for Reviews, Resume, Bizs Profile,
     * Calendar, Brand Kit and Updates the shortcut silently did nothing --
     * and worse than nothing, since it fell through to whatever you had made
     * before that instead.
     */
    public function test_the_picker_remembers_every_type(): void
    {
        $failed = [];

        foreach ($this->catalogTypes() as $type) {
            $html = $this->actingAs($this->user)
                ->withSession(['links.last_type' => $type])
                ->get(route('user.links.create'))
                ->assertOk()
                ->getContent();

            // The selection is Alpine's, not a `checked` attribute: the
            // picker component is seeded with `type: '<the remembered one>'`
            // and drives the cards from there. So the honest thing to assert
            // is what the server actually put in the page.
            if (! preg_match('/linkTypePicker\(\{\s*type:\s*\'' . preg_quote($type, '/') . '\'/', $html)) {
                preg_match('/linkTypePicker\(\{\s*type:\s*\'([^\']*)\'/', $html, $got);

                $failed[$type] = sprintf(
                    'the picker opened on "%s" instead',
                    $got[1] ?? '(nothing)'
                );
            }
        }

        $this->assertSame([], $failed, $this->report(
            'Step 1 does not remember these types.',
            $failed
        ));
    }

    /**
     * The catalog's type keys.
     *
     * types() is keyed by value and holds the whole card definition -- label,
     * icon, badge, blurb -- so the keys are the list, and reading them here
     * means this test never restates one.
     *
     * @return list<string>
     */
    private function catalogTypes(): array
    {
        return array_keys(LinkTypeCategories::types());
    }

    /** Create one link of this type, recording why if it could not be done. */
    private function createOne(string $type, array &$failed): ?Link
    {
        $own = $this->ownStorePath($type);

        [$route, $payload] = $own ?? ['user.links.store', $this->payloadFor($type)];

        $before = Link::where('user_id', $this->user->id)->max('id') ?? 0;

        $response = $this->actingAs($this->user)->post(route($route), $payload);

        $link = Link::where('user_id', $this->user->id)->where('id', '>', $before)->latest('id')->first();

        if (! $link) {
            $failed[$type] = sprintf(
                'no link was created (%s returned %d%s)',
                $route,
                $response->status(),
                $this->whyRejected()
            );

            return null;
        }

        return $link;
    }

    /**
     * Whatever the validator or the controller said about it, if anything.
     *
     * A rejected create is the interesting case and a bare status code says
     * almost nothing about it, so this digs the messages out of the session --
     * defensively, because what lands there is a ViewErrorBag on a validation
     * failure and a plain array of flash messages when a controller turns the
     * request away itself (a plan gate, most often).
     */
    private function whyRejected(): string
    {
        $said = [];

        $errors = session('errors');

        if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
            foreach ($errors->getMessages() as $messages) {
                $said[] = $messages[0];
            }
        } elseif (is_array($errors)) {
            $said[] = implode('; ', \Illuminate\Support\Arr::flatten($errors));
        }

        foreach (['error', 'status', 'message'] as $key) {
            if (is_string($flash = session($key)) && $flash !== '') {
                $said[] = $flash;
            }
        }

        return $said ? '; ' . implode('; ', $said) : '';
    }

    /** @param array<string,string> $failed */
    private function report(string $headline, array $failed): string
    {
        $lines = [];

        foreach ($failed as $type => $why) {
            $lines[] = sprintf('  %-18s %s', $type, $why);
        }

        return $headline . "\n\n" . implode("\n", $lines) . "\n";
    }
}
