<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('custom_plan_requests')) {
            return;
        }

        Schema::create('custom_plan_requests', function (Blueprint $table) {
            $table->id();

            // Requester identity
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();

            // Requirements fields
            $table->text('requirements')->nullable();
            $table->string('expected_volume')->nullable();
            $table->string('budget')->nullable();
            $table->string('preferred_cycle')->nullable(); // monthly | annual | either
            $table->text('message')->nullable();

            // Link to a signed-in user at submission time (null = anonymous)
            $table->unsignedBigInteger('user_id')->nullable();

            // Status lifecycle: new → reviewing → approved → paid | declined
            $table->string('status')->default('new');

            // Admin-side fields (set on approval)
            $table->text('admin_notes')->nullable();
            $table->string('decline_reason')->nullable();

            // Resolved offer fields (set on approval)
            $table->unsignedBigInteger('provisioned_plan_id')->nullable(); // the internal Plan created
            $table->unsignedBigInteger('invoice_id')->nullable();          // pending invoice issued
            $table->string('assigned_email')->nullable();                  // email the offer targets
            $table->string('offer_cycle')->nullable();                     // monthly | annual

            // Operator who handled the request
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index('email');
            $table->index('assigned_email');
            $table->index('status');
            $table->index('user_id');
            $table->index('provisioned_plan_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_plan_requests');
    }
};
