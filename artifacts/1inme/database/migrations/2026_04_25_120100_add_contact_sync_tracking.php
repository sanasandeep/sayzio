<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Set whenever we write the row locally (controller create/update).
            // Compared against last_synced_at to find rows the scheduler still
            // needs to push out — covers updates, not just brand-new contacts.
            $table->timestampTz('locally_modified_at')->nullable()->after('last_synced_at');
            $table->index(['user_id', 'locally_modified_at']);
        });

        // Tombstones for deletions we still need to propagate to Google.
        // We can't keep them on `contacts` (the row is gone), so we park
        // them here and the sync pass drains them with retries.
        Schema::create('contact_deletion_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_contacts_account_id')->constrained()->cascadeOnDelete();
            $table->string('google_resource_name', 191);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['user_id']);
            $table->index(['google_contacts_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_deletion_tombstones');
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'locally_modified_at']);
            $table->dropColumn('locally_modified_at');
        });
    }
};
