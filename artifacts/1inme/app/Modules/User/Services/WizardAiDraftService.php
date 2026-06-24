<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiMindQueryService;
use App\Services\Biolink\AiBiolinkBuilderService;

/**
 * Shared "auto-draft my Link in Bio with AI" core for the guided wizard.
 *
 * Both the web wizard (User\Controllers\BiolinkWizardController::finishAi) and
 * the mobile API wizard (Api\Controllers\BiolinkWizardController::aiGenerate)
 * funnel through here so the answers → description, vault-files → images/files,
 * Minds → grounding, Link creation, AI build, and resource-attachment all live
 * in exactly one place.
 *
 * Reuses AiBiolinkBuilderService verbatim for the actual generation, so the
 * `biolink_builder` credit charge + auto-refund-on-failure behaviour is shared
 * with the manual "Build with AI" intake screen. Callers are responsible for
 * the upfront plan-cap check; this service builds the page.
 */
class WizardAiDraftService
{
    public function __construct(
        protected AiBiolinkBuilderService $builder,
        protected AiMindQueryService $minds,
        protected TemplateService $templates,
    ) {}

    /**
     * Generate a biolink Link for $owner from the wizard inputs using AI.
     *
     * @param array<string,mixed> $answers           Collected, sanitised answers.
     * @param list<int>           $mindIds           Selected AI Brain (Mind) ids.
     * @param bool                $includePlatform   Fold in the platform Mind.
     * @param list<int>           $fileIds           Selected vault file ids.
     * @param array<string,mixed>|null $templateSnapshot Starting-design snapshot;
     *        when present it is seeded first and the AI draft is appended on top
     *        (preserving the template's theme) instead of replacing the page.
     */
    public function generate(
        User $owner,
        string $category,
        string $pageType,
        ?string $industry,
        array $answers,
        array $mindIds,
        bool $includePlatform,
        array $fileIds,
        ?array $templateSnapshot = null,
    ): Link {
        $description = BiolinkWizardQuestions::describeForAi($category, $pageType, $industry, $answers);

        // Split the chosen vault files into image URLs (avatars/covers/photos)
        // and document/other file URLs, the two channels the builder accepts.
        [$imageUrls, $fileUrls, $resolvedFileIds] = $this->resolveVaultFiles($owner, $fileIds);

        // Any image answers the user typed/uploaded are also valid image inputs.
        foreach (BiolinkWizardQuestions::questions($category, $pageType, $industry) as $q) {
            if (($q['type'] ?? 'text') !== 'image') continue;
            $val = $answers[$q['key']] ?? null;
            if (is_string($val) && trim($val) !== '') {
                $imageUrls[] = trim($val);
            }
        }
        $imageUrls = array_values(array_unique($imageUrls));

        // URL answers become supplied destination links for the builder.
        $linkUrls = [];
        foreach (BiolinkWizardQuestions::questions($category, $pageType, $industry) as $q) {
            if (($q['type'] ?? 'text') !== 'url') continue;
            $val = $answers[$q['key']] ?? null;
            if (is_string($val) && trim($val) !== '') {
                $linkUrls[] = trim($val);
            }
        }

        // Resolve the selected Minds and pull grounding context for the brief.
        [$grounding, $resolvedMindIds] = $this->resolveGrounding($owner, $mindIds, $includePlatform, $description);

        // Create the link up front so the builder can paint onto it. If the
        // build throws (parse failure, insufficient credits) we delete the
        // empty link so it never lingers in the dashboard — the builder has
        // already refunded any spent credits by then.
        $title = BiolinkWizardQuestions::resolveTitle($answers);
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => mb_substr($title, 0, 255),
            'is_active' => true,
        ]);

        // When the user picked a starting design, seed it first so the AI draft
        // layers on top of it (append, preserving the template's theme) rather
        // than replacing the page. "Start from scratch" keeps the replace path.
        $seedTemplate = is_array($templateSnapshot) && !empty($templateSnapshot['blocks']);
        if ($seedTemplate) {
            $this->templates->applyPageToLink($link, $templateSnapshot, /*replace*/ true);
        }

        try {
            $this->builder->generate(
                $owner, $link, $description, $linkUrls, $imageUrls, $fileUrls, $grounding,
                /*replaceBlocks*/ !$seedTemplate,
            );
        } catch (\Throwable $e) {
            $link->delete();
            throw $e;
        }

        // Attach the brains + files that fed the build as resources on the
        // page, so the user (and a later refine pass) can see what it was
        // built from. Stored additively under settings['wizard_resources'].
        $this->attachResources($link, $resolvedMindIds, $resolvedFileIds);

        return $link;
    }

    /**
     * Map selected vault file ids (scoped to the owner) to image vs. file
     * URLs, plus the subset of ids that actually resolved.
     *
     * @param list<int> $fileIds
     * @return array{0:list<string>,1:list<string>,2:list<int>}
     */
    private function resolveVaultFiles(User $owner, array $fileIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $fileIds))));
        if (!$ids) {
            return [[], [], []];
        }

        $files = UserFile::where('user_id', $owner->id)
            ->whereIn('id', $ids)
            ->get();

        $images = [];
        $docs = [];
        $resolved = [];
        foreach ($files as $file) {
            $resolved[] = (int) $file->id;
            if ($file->type === 'image') {
                $images[] = $file->url_path;
            } else {
                $docs[] = $file->url_path;
            }
        }

        return [$images, $docs, $resolved];
    }

    /**
     * Resolve the chosen Minds and retrieve a grounding context block keyed
     * off the page brief. Returns [contextText, resolvedMindIds].
     *
     * @param list<int> $mindIds
     * @return array{0:string,1:list<int>}
     */
    private function resolveGrounding(User $owner, array $mindIds, bool $includePlatform, string $query): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mindIds))));
        if (!$ids && !$includePlatform) {
            return ['', []];
        }

        $minds = $this->minds->resolveMindsForUser($owner, $ids, $includePlatform);
        if (!$minds) {
            return ['', []];
        }

        $resolvedIds = array_values(array_map(static fn (AiMind $m) => (int) $m->id, $minds));

        $retrieved = $this->minds->retrieveContext($owner, $minds, $query);
        $context = is_string($retrieved['context'] ?? null) ? $retrieved['context'] : '';

        return [$context, $resolvedIds];
    }

    /**
     * Record the brains + files that built the page on the link's settings,
     * non-destructively (preserves any existing settings keys).
     *
     * @param list<int> $mindIds
     * @param list<int> $fileIds
     */
    private function attachResources(Link $link, array $mindIds, array $fileIds): void
    {
        if (!$mindIds && !$fileIds) {
            return;
        }

        $settings = $link->settings ?? [];
        $settings['wizard_resources'] = [
            'ai_mind_ids' => array_values($mindIds),
            'file_ids'    => array_values($fileIds),
            'source'      => 'wizard_ai_draft',
        ];
        $link->settings = $settings;
        $link->save();
    }
}
