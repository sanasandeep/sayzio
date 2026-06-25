<?php

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->jsonb('extra')->nullable()->after('sections');
        });
        Schema::table('site_page_revisions', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('sections');
        });

        // Seed dummy About content (story sections + founder/co-founders/team/milestones).
        $about = SitePage::firstOrNew(['slug' => 'about']);
        $about->title = $about->title ?: 'About Sayzio';
        $about->meta_description = $about->meta_description
            ?: 'The Sayzio story — who we are, why we built it, and the people behind the product.';
        $aboutSections = is_array($about->sections) ? $about->sections : [];
        $needsStory = empty($aboutSections);
        if (!$needsStory) {
            // Replace if it still matches the previously-seeded simple about copy.
            $first = trim((string)($aboutSections[0]['heading'] ?? ''));
            $needsStory = in_array($first, ['Our mission', 'Built for creators'], true) && count($aboutSections) <= 6;
        }
        if ($needsStory) {
            $about->sections = SitePagesContent::aboutSectionsDefault();
        }
        if (!is_array($about->extra) || empty($about->extra)) {
            $about->extra = SitePagesContent::aboutExtraDefault();
        }
        $about->save();

        // Seed dummy Contact content (intro sections + address/email/phone/hours/social/map).
        $contact = SitePage::firstOrNew(['slug' => 'contact']);
        $contact->title = $contact->title ?: 'Contact us';
        $contact->meta_description = $contact->meta_description
            ?: 'Get in touch with the Sayzio team — sales, support, partnerships and press.';
        $contactSections = is_array($contact->sections) ? $contact->sections : [];
        if (empty($contactSections)) {
            $contact->sections = SitePagesContent::contactSectionsDefault();
        }
        if (!is_array($contact->extra) || empty($contact->extra)) {
            $contact->extra = SitePagesContent::contactExtraDefault();
        }
        $contact->save();
    }

    public function down(): void
    {
        Schema::table('site_page_revisions', function (Blueprint $table) {
            $table->dropColumn('extra');
        });
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn('extra');
        });
    }
};
