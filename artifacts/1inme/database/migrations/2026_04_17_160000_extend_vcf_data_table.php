<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand vcf_data into a full vCard 3.0 record. Adds name parts (prefix,
 * middle, suffix, nickname), avatar, org/role, dates, and JSON columns
 * for unbounded multi-value fields (emails, phones, urls, addresses,
 * social profiles). The existing single-value columns are kept so legacy
 * rows continue to render and download without migration of data — the
 * model hydrates them into the JSON arrays at read time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vcf_data', function (Blueprint $table) {
            $table->string('prefix', 50)->nullable()->after('link_id');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('suffix', 50)->nullable()->after('last_name');
            $table->string('nickname', 100)->nullable()->after('suffix');
            $table->string('photo_path', 500)->nullable()->after('nickname');
            $table->string('department', 255)->nullable()->after('organization');
            $table->string('role', 255)->nullable()->after('title');
            $table->date('birthday')->nullable()->after('role');
            $table->date('anniversary')->nullable()->after('birthday');
            // Multi-value fields. Each is an array of small objects.
            $table->jsonb('emails')->nullable()->after('email');
            $table->jsonb('phones')->nullable()->after('phone_work');
            $table->jsonb('urls')->nullable()->after('website');
            $table->jsonb('addresses')->nullable()->after('country');
            $table->jsonb('social_profiles')->nullable()->after('addresses');
        });
    }

    public function down(): void
    {
        Schema::table('vcf_data', function (Blueprint $table) {
            $table->dropColumn([
                'prefix', 'middle_name', 'suffix', 'nickname', 'photo_path',
                'department', 'role', 'birthday', 'anniversary',
                'emails', 'phones', 'urls', 'addresses', 'social_profiles',
            ]);
        });
    }
};
