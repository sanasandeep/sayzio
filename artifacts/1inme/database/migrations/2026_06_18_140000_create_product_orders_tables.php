<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One combined order per checkout. A cart of several products from the
        // SAME creator (multi-creator carts are out of scope) collapses into a
        // single order with many product_order_items. Amounts are gross cents —
        // the platform takes 0%, only the provider fee applies. `public_token`
        // gates the thank-you + digital-download URLs for the buyer.
        if (!Schema::hasTable('product_orders')) {
            Schema::create('product_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
                $table->string('status', 20)->default('pending'); // pending|paid|fulfilled|cancelled
                $table->integer('subtotal_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_charge_id', 191)->nullable();
                $table->boolean('contains_physical')->default(false);
                $table->boolean('contains_digital')->default(false);
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->string('public_token', 64)->unique();
                $table->timestampTz('paid_at')->nullable();
                $table->timestampTz('fulfilled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['creator_user_id', 'status']);
                $table->index(['buyer_user_id', 'status']);
            });
        }

        // Line items. We snapshot name/price/type/file at purchase time so the
        // order stays correct even if the creator later edits or deletes the
        // originating biolink Product block.
        if (!Schema::hasTable('product_order_items')) {
            Schema::create('product_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('product_orders')->cascadeOnDelete();
                $table->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
                $table->unsignedBigInteger('block_id')->nullable();
                $table->string('name', 191);
                $table->integer('unit_price_cents')->default(0);
                $table->integer('quantity')->default(1);
                $table->string('currency', 3)->default('USD');
                $table->string('product_type', 16)->default('digital'); // digital|physical
                $table->text('digital_file_url')->nullable();
                $table->text('image_url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
        Schema::dropIfExists('product_orders');
    }
};
