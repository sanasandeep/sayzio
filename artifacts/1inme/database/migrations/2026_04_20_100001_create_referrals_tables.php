<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('settings');
            }
            if (!Schema::hasColumn('users', 'referrer_id')) {
                $table->unsignedBigInteger('referrer_id')->nullable()->index()->after('referral_code');
            }
            if (!Schema::hasColumn('users', 'referral_code_used')) {
                $table->string('referral_code_used', 32)->nullable()->after('referrer_id');
            }
        });

        if (!Schema::hasTable('referrals')) {
            Schema::create('referrals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('referrer_id')->index();
                $table->unsignedBigInteger('referred_user_id')->nullable()->index();
                $table->string('code_used', 32);
                $table->string('status', 20)->default('signed_up'); // clicked|signed_up|converted|rewarded
                $table->timestamp('signed_up_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
                $table->unique('referred_user_id');
            });
        }

        if (!Schema::hasTable('referral_rewards')) {
            Schema::create('referral_rewards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('referral_id')->index();
                $table->string('type', 20); // referrer | referred | signup
                $table->integer('days_granted');
                $table->unsignedBigInteger('plan_id_basis')->nullable();
                $table->timestamp('granted_at')->useCurrent();
                $table->timestamps();
                $table->unique(['referral_id', 'type']);
            });
        }

        // Backfill referral codes for existing users.
        $userIds = DB::table('users')->whereNull('referral_code')->pluck('id');
        foreach ($userIds as $id) {
            $code = $this->generateUniqueCode();
            DB::table('users')->where('id', $id)->update(['referral_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referrals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'referrer_id', 'referral_code_used']);
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtolower(Str::random(8));
        } while (DB::table('users')->where('referral_code', $code)->exists());
        return $code;
    }
};
