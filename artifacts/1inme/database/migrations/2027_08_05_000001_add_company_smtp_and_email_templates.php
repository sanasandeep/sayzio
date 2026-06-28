<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-billing-company outbound mail: SMTP transport columns on
 * billing_companies (so a creator's client-facing accounting emails can be
 * delivered from their own server, falling back to the platform MailSettings
 * SMTP when unset/disabled) and a company_email_templates table holding the
 * creator's per-company subject/body overrides for those emails (layered over
 * the admin/global override and the registry default).
 *
 * Additive + idempotent (hasTable / hasColumn guards) so it can replay safely
 * over the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_companies')) {
            Schema::table('billing_companies', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_companies', 'smtp_enabled')) {
                    $table->boolean('smtp_enabled')->default(false)->after('notes');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_host')) {
                    $table->string('smtp_host', 255)->nullable()->after('smtp_enabled');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_port')) {
                    $table->unsignedInteger('smtp_port')->nullable()->after('smtp_host');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_encryption')) {
                    $table->string('smtp_encryption', 8)->nullable()->after('smtp_port'); // tls|ssl|none
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_username')) {
                    $table->string('smtp_username', 255)->nullable()->after('smtp_encryption');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_password_enc')) {
                    $table->text('smtp_password_enc')->nullable()->after('smtp_username');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_from_address')) {
                    $table->string('smtp_from_address', 255)->nullable()->after('smtp_password_enc');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_from_name')) {
                    $table->string('smtp_from_name', 190)->nullable()->after('smtp_from_address');
                }
                if (!Schema::hasColumn('billing_companies', 'smtp_verified_at')) {
                    $table->timestamp('smtp_verified_at')->nullable()->after('smtp_from_name');
                }
            });
        }

        if (!Schema::hasTable('company_email_templates')) {
            Schema::create('company_email_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('billing_company_id');
                $table->string('template_key', 64);   // EmailTemplateRegistry key
                $table->string('subject', 255)->nullable();
                $table->text('body')->nullable();
                $table->string('format', 8)->default('html'); // html|text
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['billing_company_id', 'template_key'], 'company_email_tpl_unique');
                $table->index('billing_company_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_email_templates');

        if (Schema::hasTable('billing_companies')) {
            Schema::table('billing_companies', function (Blueprint $table) {
                foreach ([
                    'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption',
                    'smtp_username', 'smtp_password_enc', 'smtp_from_address',
                    'smtp_from_name', 'smtp_verified_at',
                ] as $col) {
                    if (Schema::hasColumn('billing_companies', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
