<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_issue_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscriber_id');
            $table->unsignedBigInteger('issue_id');
            $table->timestamp('unsubscribed_at');
            $table->timestamps();

            $table->unique(['subscriber_id', 'issue_id']);
            $table->index('issue_id');
            $table->index('subscriber_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_issue_unsubscribes');
    }
};
