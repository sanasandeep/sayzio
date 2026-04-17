<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('link_id')->nullable()->constrained('links')->nullOnDelete();
            $t->string('name', 160);
            $t->string('type', 24);                 // text, url, phone, sms, email, whatsapp, facetime, location, wifi, event, vcard, crypto, paypal, upi, epc, pix
            $t->jsonb('payload');                   // type-specific data
            $t->jsonb('design');                    // visual config
            $t->string('preview_url', 500)->nullable();
            $t->unsignedInteger('downloads')->default(0);
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index('project_id');
            $t->index('link_id');
            $t->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
