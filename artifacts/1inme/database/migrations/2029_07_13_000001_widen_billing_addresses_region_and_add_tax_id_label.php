<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen billing_addresses.region from varchar(8) to varchar(100) so users
 * outside India/US can type their state/region name freely instead of being
 * limited to two-letter codes.
 *
 * Also add tax_id_label (varchar 100, nullable) to hold the user-supplied
 * label when tax_id_kind = 'OTHER' (e.g. "ABN", "EIN", "PAN…").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('billing_addresses', 'region')) {
                $table->string('region', 100)->nullable()->change();
            }
            if (!Schema::hasColumn('billing_addresses', 'tax_id_label')) {
                $table->string('tax_id_label', 100)->nullable()->after('tax_id_kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('billing_addresses', 'tax_id_label')) {
                $table->dropColumn('tax_id_label');
            }
            if (Schema::hasColumn('billing_addresses', 'region')) {
                $table->string('region', 8)->nullable()->change();
            }
        });
    }
};
