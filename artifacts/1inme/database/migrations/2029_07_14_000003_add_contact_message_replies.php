<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_message_replies')) {
            Schema::create('contact_message_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('subject', 500);
                $table->text('body');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contact_messages') && !Schema::hasColumn('contact_messages', 'replied_at')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->timestamp('replied_at')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('contact_messages') && !Schema::hasColumn('contact_messages', 'contact_channel')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->string('contact_channel', 32)->nullable()->after('ip');
            });
        }

        if (Schema::hasTable('contact_messages') && !Schema::hasColumn('contact_messages', 'contact_phone')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->string('contact_phone', 40)->nullable()->after('contact_channel');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_replies');

        if (Schema::hasTable('contact_messages')) {
            $drops = array_filter(
                ['replied_at', 'contact_channel', 'contact_phone'],
                fn ($col) => Schema::hasColumn('contact_messages', $col)
            );
            if ($drops) {
                Schema::table('contact_messages', function (Blueprint $table) use ($drops) {
                    $table->dropColumn($drops);
                });
            }
        }
    }
};
