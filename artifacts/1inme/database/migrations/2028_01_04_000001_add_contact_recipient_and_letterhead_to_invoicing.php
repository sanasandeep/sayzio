<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3522: extends client invoices/receipts so a recipient can be an
 * existing Contact/lead (not just a VaultClient), and adds letterhead image
 * support — one set per BillingCompany (the default) plus a per-invoice
 * override, rendered as the PDF page background with margin/orientation
 * controls.
 *
 * Everything is ADDITIVE and guarded (shared RDS — additive only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('vault_client_id');
                $table->index('contact_id');
            }
            if (!Schema::hasColumn('invoices', 'recipient_name')) {
                $table->string('recipient_name', 190)->nullable()->after('recipient_email');
            }
            if (!Schema::hasColumn('invoices', 'recipient_address')) {
                $table->text('recipient_address')->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_path')) {
                $table->string('letterhead_path', 255)->nullable()->after('recipient_address');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_orientation')) {
                // 'portrait' or 'landscape'; null = inherit from the billing company.
                $table->string('letterhead_orientation', 12)->nullable()->after('letterhead_path');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_margin_top')) {
                $table->unsignedSmallInteger('letterhead_margin_top')->nullable()->after('letterhead_orientation');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_margin_right')) {
                $table->unsignedSmallInteger('letterhead_margin_right')->nullable()->after('letterhead_margin_top');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_margin_bottom')) {
                $table->unsignedSmallInteger('letterhead_margin_bottom')->nullable()->after('letterhead_margin_right');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_margin_left')) {
                $table->unsignedSmallInteger('letterhead_margin_left')->nullable()->after('letterhead_margin_bottom');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_width')) {
                $table->unsignedInteger('letterhead_width')->nullable()->after('letterhead_margin_left');
            }
            if (!Schema::hasColumn('invoices', 'letterhead_height')) {
                $table->unsignedInteger('letterhead_height')->nullable()->after('letterhead_width');
            }
        });

        if (Schema::hasTable('billing_companies')) {
            Schema::table('billing_companies', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_companies', 'letterhead_path')) {
                    $table->string('letterhead_path', 255)->nullable()->after('logo_path');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_orientation')) {
                    $table->string('letterhead_orientation', 12)->nullable()->default('portrait')->after('letterhead_path');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_margin_top')) {
                    $table->unsignedSmallInteger('letterhead_margin_top')->nullable()->after('letterhead_orientation');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_margin_right')) {
                    $table->unsignedSmallInteger('letterhead_margin_right')->nullable()->after('letterhead_margin_top');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_margin_bottom')) {
                    $table->unsignedSmallInteger('letterhead_margin_bottom')->nullable()->after('letterhead_margin_right');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_margin_left')) {
                    $table->unsignedSmallInteger('letterhead_margin_left')->nullable()->after('letterhead_margin_bottom');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_width')) {
                    $table->unsignedInteger('letterhead_width')->nullable()->after('letterhead_margin_left');
                }
                if (!Schema::hasColumn('billing_companies', 'letterhead_height')) {
                    $table->unsignedInteger('letterhead_height')->nullable()->after('letterhead_width');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                'contact_id', 'recipient_name', 'recipient_address',
                'letterhead_path', 'letterhead_orientation',
                'letterhead_margin_top', 'letterhead_margin_right',
                'letterhead_margin_bottom', 'letterhead_margin_left',
                'letterhead_width', 'letterhead_height',
            ] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('billing_companies')) {
            Schema::table('billing_companies', function (Blueprint $table) {
                foreach ([
                    'letterhead_path', 'letterhead_orientation',
                    'letterhead_margin_top', 'letterhead_margin_right',
                    'letterhead_margin_bottom', 'letterhead_margin_left',
                    'letterhead_width', 'letterhead_height',
                ] as $col) {
                    if (Schema::hasColumn('billing_companies', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
