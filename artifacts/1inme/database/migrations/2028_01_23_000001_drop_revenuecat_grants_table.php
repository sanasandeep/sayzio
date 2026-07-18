<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the now-unused revenuecat_grants table.
 *
 * All server-side RevenueCat code (controller, route, model, config, env
 * keys) was removed in a prior cleanup, leaving this idempotency ledger
 * with no model and no writer. The table is dropped here via a new
 * additive migration rather than by editing the original create migration,
 * which is retained as history.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('revenuecat_grants');
    }

    public function down(): void
    {
        // Intentionally irreversible: the backing feature has been removed.
        // The original create migration remains as history if the schema
        // ever needs to be recreated manually.
    }
};
