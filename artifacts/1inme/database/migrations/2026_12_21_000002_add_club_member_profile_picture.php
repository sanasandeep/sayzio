<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('club_memberships',function(Blueprint $t){$t->foreignId('profile_photo_file_id')->nullable()->constrained('user_files')->nullOnDelete();});}
 public function down(): void {Schema::table('club_memberships',fn(Blueprint $t)=>$t->dropConstrainedForeignId('profile_photo_file_id'));}
};
