<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant Menu biolink-family page type (Task #1536).
 *
 * A `restaurant_menu` link owns exactly one `restaurant_menus` config row,
 * which fans out into categories → items. In "order" mode the owner also
 * defines `restaurant_tables` (each with a printable per-table QR token);
 * guests place `restaurant_orders` (with `restaurant_order_items`) that the
 * owner works through a status pipeline. No online payment is involved —
 * guests pay staff directly — so there are no money/charge columns beyond
 * captured prices for the owner's reference.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_menus', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('link_id')->unique();
            $t->unsignedBigInteger('user_id')->index();
            // 'display' = read-only menu; 'order' = order-at-table.
            $t->string('mode', 16)->default('display');
            $t->string('currency', 3)->default('USD');
            $t->string('accent_color', 16)->default('#7c3aed');
            // Optional free-form config: ordering instructions, tax note,
            // whether table is required, etc.
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('restaurant_menu_categories', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('menu_id')->index();
            $t->string('name');
            $t->text('description')->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('restaurant_menu_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('menu_id')->index();
            $t->unsignedBigInteger('category_id')->index();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2)->default(0);
            // Per-item currency override; falls back to the menu currency.
            $t->string('currency', 3)->nullable();
            $t->string('photo_url', 1024)->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_sold_out')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('restaurant_tables', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('menu_id')->index();
            $t->string('label');
            // Short opaque token embedded in the table QR (e.g. ?t=ab12cd).
            $t->string('code', 32)->unique();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('restaurant_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('menu_id')->index();
            $t->unsignedBigInteger('link_id')->index();
            $t->unsignedBigInteger('table_id')->nullable()->index();
            // Opaque token the guest uses to poll their own order status.
            $t->string('public_token', 40)->unique();
            // new → accepted → preparing → ready → completed (+ cancelled).
            $t->string('status', 16)->default('new')->index();
            $t->string('table_label')->nullable();
            $t->string('customer_name')->nullable();
            $t->text('customer_note')->nullable();
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->json('meta')->nullable();
            $t->timestamps();
        });

        Schema::create('restaurant_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->index();
            $t->unsignedBigInteger('item_id')->nullable();
            // Snapshot name/price so an order is stable even if the menu
            // item is later edited or deleted.
            $t->string('name');
            $t->decimal('unit_price', 10, 2)->default(0);
            $t->integer('quantity')->default(1);
            $t->decimal('line_total', 10, 2)->default(0);
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
        Schema::dropIfExists('restaurant_orders');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('restaurant_menu_items');
        Schema::dropIfExists('restaurant_menu_categories');
        Schema::dropIfExists('restaurant_menus');
    }
};
