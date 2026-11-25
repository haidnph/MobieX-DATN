<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('coupons', function(Blueprint $t){ $t->boolean('show_on_home')->default(false); });
  Schema::create('user_coupons', function(Blueprint $t){
   $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
   $t->timestamps(); $t->primary(['user_id','coupon_id']);
  });
 }
 public function down(): void { Schema::dropIfExists('user_coupons'); Schema::table('coupons', fn(Blueprint $t)=>$t->dropColumn('show_on_home')); }
};
