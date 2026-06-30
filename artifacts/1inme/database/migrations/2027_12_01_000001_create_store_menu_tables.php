<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store Menu biolink-family page type (Task #3072).
 *
 * A `store_menu` link owns exactly one `store_menus` config row, which fans
 * out into categories → products. In "order" mode guests submit
 * `store_orders` (with `store_order_items`) — an order *request*, never a
 * paid checkout — that the owner works through a status pipeline. There is
 * NO online payment and, unlike the restaurant menu, NO physical
 * tables / per-table QR concept and no tax/coupon columns: a single shared
 * store link/QR serves every visitor.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_menus')) {
            Schema::create('store_menus', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id')->unique();
                $t->unsignedBigInteger('user_id')->index();
                // 'display' = read-only catalog; 'order' = cart + order request.
                $t->string('mode', 16)->default('display');
                $t->string('currency', 3)->default('USD');
                $t->string('accent_color', 16)->default('#3d6bff');
                // Optional free-form config: accepting-orders toggle, optional
                // WhatsApp number, ordering instructions, etc.
                $t->json('settings')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('store_categories')) {
            Schema::create('store_categories', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('menu_id')->index();
                $t->string('name');
                $t->text('description')->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('store_products')) {
            Schema::create('store_products', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('menu_id')->index();
                $t->unsignedBigInteger('category_id')->index();
                $t->string('name');
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                // Per-product currency override; falls back to the store currency.
                $t->string('currency', 3)->nullable();
                $t->string('photo_url', 1024)->nullable();
                $t->integer('sort_order')->default(0);
                // Simple available/out-of-stock toggle (no stock counts).
                $t->boolean('is_out_of_stock')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('store_orders')) {
            Schema::create('store_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('menu_id')->index();
                $t->unsignedBigInteger('link_id')->index();
                // Opaque token the customer uses to poll their own request.
                $t->string('public_token', 40)->unique();
                // new → accepted → packing → ready → completed (+ cancelled).
                $t->string('status', 16)->default('new')->index();
                $t->string('customer_name')->nullable();
                // Free-form contact (phone / email / handle) — no validation
                // beyond length; the owner contacts the customer off-platform.
                $t->string('customer_contact')->nullable();
                $t->text('customer_note')->nullable();
                $t->decimal('subtotal', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->string('currency', 3)->default('USD');
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('store_order_items')) {
            Schema::create('store_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                $t->unsignedBigInteger('product_id')->nullable();
                // Snapshot name/price so a request is stable even if the
                // product is later edited or deleted.
                $t->string('name');
                $t->decimal('unit_price', 10, 2)->default(0);
                $t->integer('quantity')->default(1);
                $t->decimal('line_total', 10, 2)->default(0);
                $t->text('note')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_products');
        Schema::dropIfExists('store_categories');
        Schema::dropIfExists('store_menus');
    }
};
