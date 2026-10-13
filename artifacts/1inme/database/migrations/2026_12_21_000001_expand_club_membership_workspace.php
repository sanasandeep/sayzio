<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('club_memberships',function(Blueprint $t){$t->timestamp('renewed_at')->nullable();$t->json('invoice_history')->nullable();$t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();});
  Schema::create('club_membership_reminders',function(Blueprint $t){$t->id();$t->foreignId('club_membership_id')->constrained()->cascadeOnDelete();$t->date('expiry');$t->unsignedInteger('lead_days');$t->timestamp('sent_at')->nullable();$t->timestamps();$t->unique(['club_membership_id','expiry','lead_days'],'club_reminder_unique');});
 }
 public function down(): void {Schema::dropIfExists('club_membership_reminders');Schema::table('club_memberships',function(Blueprint $t){$t->dropConstrainedForeignId('invoice_id');$t->dropColumn(['renewed_at','invoice_history']);});}
};
