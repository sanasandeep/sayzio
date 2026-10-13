<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('club_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('form_submissions')->nullOnDelete();
            $table->string('name',150); $table->string('email'); $table->string('phone',30);
            $table->string('status',20)->default('pending');
            $table->date('expires_at')->nullable(); $table->timestamp('joined_at')->nullable();
            $table->json('custom_fields')->nullable(); $table->timestamps();
            $table->unique(['link_id','email']);
            $table->index(['link_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('club_memberships'); }
};
