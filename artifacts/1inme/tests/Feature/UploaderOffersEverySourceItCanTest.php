<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\UploadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared uploader must offer every source it is actually capable of.
 *
 * Two separate bugs hid working code behind conditions that were never
 * revisited, and both showed up on the page-background settings panel:
 *
 *  1. The whole source row sat behind `@if($allowAlternateSources && !$multiple)`.
 *     A multi-file field (the background slideshow) therefore lost URL, My Files
 *     AND Stock -- even though injectVaultFile(), which all three funnel
 *     through, already appends rather than replaces when `multiple` is set:
 *
 *         const start = this.multiple ? Array.from(this.$refs.input.files || []) : [];
 *
 *     The capability was built; only the row was hidden.
 *
 *  2. Stock is offered when $browseType === 'image', and $browseType was
 *     sniffed from $accept. UploadPolicy describes a field with `extensions`
 *     and never sets `accept`, so $accept fell back to the catch-all wildcard,
 *     the sniff never matched, and Stock appeared on exactly one field in the
 *     app: the one include that passes browseType by hand.
 *
 * Plus the reason anyone noticed: every label and tab in the partial was a
 * hard-coded text-white/xx, invisible on the light settings page.
 */
class UploaderOffersEverySourceItCanTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);

        return User::factory()->create();
    }

    private function render(string $policyKey, string $name): string
    {
        return view('user.partials.dropzone-input', [
            'name'   => $name,
            'policy' => UploadPolicy::for($policyKey, $this->user()),
        ])->render();
    }

    /** The slideshow takes many files; that is not a reason to lose three sources. */
    public function test_a_multi_file_field_still_offers_url_my_files_and_stock(): void
    {
        $html = $this->render('link.slideshow_image', 'slideshow_images');

        // Match the rendered button, not the word: the Alpine component
        // defines loadStock()/pickStock(), so a bare "Stock" always matches.
        foreach ([
            'URL'      => 'fa-link mr-1"></i>URL',
            'My Files' => 'fa-folder-open mr-1"></i>My Files',
            'Stock'    => 'fa-images mr-1"></i>Stock',
        ] as $source => $button) {
            $this->assertStringContainsString(
                $button,
                $html,
                "a multi-file uploader must still offer {$source}; injectVaultFile already appends"
            );
        }
    }

    /**
     * Stock on an image field, worked out from the policy's own extension list
     * rather than from an `accept` string UploadPolicy never sets.
     */
    public function test_stock_is_offered_on_image_fields_without_being_told_twice(): void
    {
        foreach ([
            'link.bg_fallback_image' => 'bg_fallback_image',
            'link.slideshow_image'   => 'slideshow_images',
            'link.seo_image'         => 'seo_image',
        ] as $policyKey => $name) {
            $this->assertStringContainsString(
                'fa-images mr-1"></i>Stock',
                $this->render($policyKey, $name),
                "{$policyKey} lists image extensions, so it should offer Stock"
            );
        }
    }

    /** A video field should not be offered an image library. */
    public function test_stock_is_not_offered_where_it_makes_no_sense(): void
    {
        $this->assertStringNotContainsString(
            'fa-images mr-1"></i>Stock',
            $this->render('link.video_file', 'video_file'),
            'the stock library is images; a video field must not show it'
        );
    }

    /**
     * No colour in this partial may be hard-coded white.
     *
     * It renders on light and dark pages alike, and the settings page is
     * light. A guard rather than a screenshot, because the failure mode is
     * text that is present, correct, and unreadable.
     */
    public function test_the_uploader_has_no_hard_coded_white_ink(): void
    {
        $source = file_get_contents(
            base_path('resources/views/user/partials/dropzone-input.blade.php')
        );

        preg_match_all('/text-white\/\d+/', $source, $m);

        $this->assertSame(
            [],
            $m[0],
            "hard-coded white text renders invisible on the light settings page; "
            . "use the dz-ink-* classes, which read the theme's tokens. Found: "
            . implode(', ', array_unique($m[0]))
        );
    }

    /** The source row must not be gated on how many files the field takes. */
    public function test_the_source_row_is_not_gated_on_multiple(): void
    {
        $source = file_get_contents(
            base_path('resources/views/user/partials/dropzone-input.blade.php')
        );

        $this->assertStringNotContainsString(
            '$allowAlternateSources && !$multiple',
            $source,
            'a multi-file field loses URL, My Files and Stock to this guard, '
            . 'for no reason: all three append correctly'
        );
    }

    /**
     * The case that only showed up on the live site.
     *
     * A holder of `user.files.access_any` -- an owner, or anyone on an
     * unlimited plan -- gets a policy with `extensions => []` and
     * `accept => ''`, because their uploads are not filtered. Every sniff for
     * "is this an image field" therefore came back empty, and Stock vanished
     * for exactly the accounts most likely to be using it. The suite missed it
     * because a factory user holds no such permission.
     *
     * `kind` is read from the CONTEXT rather than the resolved policy, so it
     * survives the override: a background image field is an image field no
     * matter who is looking at it.
     */
    public function test_an_unrestricted_account_still_sees_the_image_library(): void
    {
        $this->assertSame('image', UploadPolicy::kindOf(
            UploadPolicy::CONTEXTS['link.bg_fallback_image']['extensions']
        ));
        $this->assertSame('video', UploadPolicy::kindOf(
            UploadPolicy::CONTEXTS['link.video_file']['extensions']
        ));

        // An unfiltered policy keeps its kind even with nothing else to go on.
        $unfiltered = [
            'key' => 'link.bg_fallback_image',
            'max_mb' => 10240,
            'extensions' => [],
            'multiple' => false,
            'accept' => '',
            'kind' => 'image',
        ];

        $html = view('user.partials.dropzone-input', [
            'name'   => 'bg_fallback_image',
            'policy' => $unfiltered,
        ])->render();

        $this->assertStringContainsString(
            'fa-images mr-1"></i>Stock',
            $html,
            'blanking the extension list must not take the image library with it'
        );
    }

    /** Every context resolves to a kind, and it never changes per user. */
    public function test_every_upload_context_declares_what_it_is_for(): void
    {
        $unclassified = [];
        foreach (UploadPolicy::CONTEXTS as $key => $ctx) {
            if (empty($ctx['extensions'])) {
                continue; // deliberately open-ended, e.g. link.file_share
            }
            if (UploadPolicy::kindOf($ctx['extensions']) === 'all') {
                $unclassified[] = $key;
            }
        }

        $this->assertSame([], $unclassified,
            'these contexts list extensions but map to no kind: '
            . implode(', ', $unclassified));
    }
}
