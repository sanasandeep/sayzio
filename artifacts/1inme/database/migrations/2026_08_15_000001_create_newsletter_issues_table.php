<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_issues', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255);
            $table->longText('body_html');
            $table->string('status', 20)->default('queued'); // queued, sending, sent, failed
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_email', 190)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_issues');
    }
};
