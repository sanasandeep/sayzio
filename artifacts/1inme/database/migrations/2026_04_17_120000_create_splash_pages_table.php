<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('splash_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('title', 160)->nullable();
            $t->text('description')->nullable();
            $t->string('cta_label', 60)->nullable();
            $t->string('cta_url', 2000)->nullable();
            $t->boolean('auto_redirect')->default(false);
            $t->unsignedSmallInteger('countdown')->default(5);
            $t->string('logo', 500)->nullable();
            $t->string('favicon', 500)->nullable();
            $t->string('og_image', 500)->nullable();
            $t->text('custom_css')->nullable();
            $t->text('custom_js')->nullable();
            $t->unsignedInteger('usage_count')->default(0);
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index('project_id');
        });

        Schema::table('links', function (Blueprint $t) {
            $t->foreignId('splash_page_id')->nullable()->after('project_id')->constrained('splash_pages')->nullOnDelete();
            $t->boolean('splash_enabled')->default(false)->after('splash_page_id');
            $t->index('splash_page_id');
        });

        // ----- Migrate existing per-link splash JSON into standalone splash_pages -----
        // We migrate ANY populated splash array (enabled OR disabled) so users
        // do not lose draft splash content. The link's `splash_enabled` boolean
        // is taken from the old `enabled` flag, preserving prior behaviour.
        DB::table('links')->whereNotNull('settings')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $settings = json_decode($row->settings, true) ?: [];
                $sp = $settings['splash'] ?? null;
                if (!is_array($sp) || empty(array_filter($sp, fn($v) => $v !== null && $v !== ''))) continue;

                $wasEnabled = !empty($sp['enabled']);
                $title = $sp['title'] ?? null;
                $name = $title ?: ('Splash for link #' . $row->id);
                $id = DB::table('splash_pages')->insertGetId([
                    'user_id'       => $row->user_id,
                    'project_id'    => $row->project_id ?? null,
                    'name'          => mb_substr($name, 0, 120),
                    'title'         => $title ? mb_substr($title, 0, 160) : null,
                    'description'   => $sp['description']   ?? null,
                    'cta_label'     => isset($sp['cta_label'])   ? mb_substr($sp['cta_label'], 0, 60) : null,
                    'cta_url'       => isset($sp['cta_url'])     ? mb_substr($sp['cta_url'], 0, 2000) : null,
                    'auto_redirect' => !empty($sp['auto_redirect']),
                    'countdown'     => isset($sp['countdown']) ? max(0, min(120, (int) $sp['countdown'])) : 5,
                    'logo'          => $sp['logo']          ?? null,
                    'favicon'       => $sp['favicon']       ?? null,
                    'og_image'      => $sp['og_image']      ?? null,
                    'custom_css'    => $sp['custom_css']    ?? null,
                    'custom_js'     => $sp['custom_js']     ?? null,
                    'usage_count'   => $wasEnabled ? 1 : 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                unset($settings['splash']);
                DB::table('links')->where('id', $row->id)->update([
                    'splash_page_id' => $id,
                    'splash_enabled' => $wasEnabled,
                    'settings'       => json_encode($settings),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $t) {
            $t->dropForeign(['splash_page_id']);
            $t->dropColumn(['splash_page_id', 'splash_enabled']);
        });
        Schema::dropIfExists('splash_pages');
    }
};
