<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contacts', 'contact_type')) {
            Schema::table('contacts', function (Blueprint $table) {
                // 'personal' | 'brand' | null (unlabeled)
                $table->string('contact_type', 16)->nullable();
            });
        }

        if (!Schema::hasTable('dialer_connection_labels')) {
            Schema::create('dialer_connection_labels', function (Blueprint $table) {
                $table->id();
                // The labeling viewer; label is independent of follow direction.
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('other_user_id');
                $table->string('category', 16); // 'personal' | 'brand'
                $table->timestamps();
                $table->unique(['user_id', 'other_user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dialer_connection_labels');
        if (Schema::hasColumn('contacts', 'contact_type')) {
            Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn('contact_type'));
        }
    }
};
