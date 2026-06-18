<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared "generate the biolink page" core for the guided wizard.
 *
 * Both the web wizard (User\Controllers\BiolinkWizardController::finish) and
 * the mobile API wizard (Api\Controllers\BiolinkWizardController::generate)
 * funnel through here so the recipe → Link → blocks generation lives in
 * exactly one place. Callers are responsible for validating the answers and
 * enforcing plan caps before invoking this; the service only builds the page.
 */
class BiolinkWizardGenerator
{
    public function __construct(private TemplateService $templates) {}

    /**
     * Create a biolink Link for $owner and paint the recipe's blocks onto it.
     *
     * Runs inside a transaction so a failed apply (e.g. an unknown block type)
     * rolls back and never leaves an empty link sitting in the dashboard. The
     * Link title is derived from the answers via BiolinkWizardQuestions so web
     * and mobile name the page identically.
     */
    public function generate(User $owner, string $category, string $pageType, ?string $industry, array $answers): Link
    {
        $title = BiolinkWizardQuestions::resolveTitle($answers);

        return DB::transaction(function () use ($owner, $category, $pageType, $industry, $answers, $title) {
            $link = Link::create([
                'user_id'   => $owner->id,
                'type'      => 'biolink',
                'alias'     => Link::generateAlias(),
                'title'     => mb_substr($title, 0, 255),
                'is_active' => true,
            ]);

            $snapshot = BiolinkPageRecipes::build($category, $pageType, $industry, $answers);
            $this->templates->applyPageToLink($link, $snapshot, /*replace*/ true);

            return $link;
        });
    }
}
