<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refund / cancellation (Task #1764). A creator can refund+cancel a
        // paid order from the Orders dashboard; we stamp when and why so the
        // order history stays auditable and the buyer-facing pages can explain
        // the status. `status` gains a `refunded` value (see ProductOrder).
        Schema::table('product_orders', function (Blueprint $table) {
            $table->timestampTz('refunded_at')->nullable()->after('fulfilled_at');
            $table->string('refund_reason', 280)->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refund_reason']);
        });
    }
};
