<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Managed list of accounts that can never be deleted or suspended
 * (Task: Protected accounts). An entry is keyed by lowercased email,
 * so it covers both the web `User` and the matching back-office `Admin`
 * (the two pools are bridged by email).
 *
 * `locked` marks the two required, hard-locked seeds — the superadmin
 * and the demo account — which can never be removed from protection,
 * even by another superadmin. Non-locked entries can be added/removed
 * from the admin panel by a superadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('protected_accounts')) {
            Schema::create('protected_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Lowercased email the protection applies to.
                $table->string('email', 191)->unique();

                // Hard-locked seeds (superadmin + demo) can never be removed.
                $table->boolean('locked')->default(false);

                // Human label shown in the admin list (e.g. "Superadmin").
                $table->string('label', 191)->nullable();

                // Admin who added the entry (null for seeded rows).
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
            });
        }

        // Idempotently seed the two required, hard-locked protected
        // accounts. updateOrInsert keeps the seed safe to re-run and
        // re-asserts the lock on every deploy.
        $seeds = [
            ['email' => 'sanasandeep@gmail.com',  'label' => 'Superadmin'],
            ['email' => 'official1inme@gmail.com', 'label' => 'Demo account'],
        ];

        foreach ($seeds as $seed) {
            DB::table('protected_accounts')->updateOrInsert(
                ['email' => $seed['email']],
                [
                    'locked'     => true,
                    'label'      => $seed['label'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_accounts');
    }
};
