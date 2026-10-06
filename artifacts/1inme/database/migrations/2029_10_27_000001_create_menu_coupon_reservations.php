<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_coupon_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->unique()->constrained('menu_order_coupons')->cascadeOnDelete();
            $table->string('order_type', 16);
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
            $table->index(['order_type', 'order_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('menu_coupon_reservations'); }
};
