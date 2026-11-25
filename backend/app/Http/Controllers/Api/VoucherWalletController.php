<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CouponRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class VoucherWalletController extends Controller {
 private function reason(Coupon $c, ?int $user = null): ?string {
  if (!$c->is_active) return 'Voucher ngừng hoạt động';
  if ($c->start_at && now()->lt($c->start_at)) return 'Chưa đến thời gian sử dụng';
  if ($c->end_at && now()->gt($c->end_at)) return 'Voucher hết hạn';
  if ($user && app(CouponRules::class)->customerLimitReached($c,$user)) return 'Đã hết lượt cá nhân hoặc đang giữ cho đơn hàng';
  if ($c->usage_limit !== null && $c->used_count + $c->reserved_count >= $c->usage_limit) return 'Voucher hết lượt';
  return null;
 }
 private function row(Coupon $c, ?int $user = null): array {
  return array_merge($c->only(['id','code','discount_type','discount_value','min_order_amount','max_discount','start_at','end_at','usage_limit','max_uses_per_customer']), [
   'products'=>$c->products()->select('products.id','products.name')->get()->map(fn($p)=>$p->only(['id','name'])),
   'remaining'=>$c->usage_limit === null ? null : max(0,$c->usage_limit-$c->used_count-$c->reserved_count),
   'reason'=>$this->reason($c,$user),
   'saved'=>$user ? DB::table('user_coupons')->where('user_id',$user)->where('coupon_id',$c->id)->exists() : false,
  ]);
 }
 public function available(Request $r) {
  $user = $r->user('sanctum')?->id;
  return response()->json(['data'=>Coupon::where('show_on_home',true)->where('is_active',true)->get()->filter(fn($c)=>!$this->reason($c))->map(fn($c)=>$this->row($c,$user))->values()]);
 }
 public function collect(Request $r, int $id) {
  $c=Coupon::findOrFail($id); $user=$r->user()->id;
  abort_unless($c->show_on_home,404);
  if ($reason=$this->reason($c,$user)) return response()->json(['message'=>$reason],422);
  DB::table('user_coupons')->insertOrIgnore(['user_id'=>$user,'coupon_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
  return response()->json(['message'=>'Đã lưu Voucher']);
 }
 public function wallet(Request $r) {
  $user=$r->user()->id;
  $ids=DB::table('user_coupons')->where('user_id',$user)->pluck('coupon_id');
  return response()->json(['data'=>Coupon::withTrashed()->whereIn('id',$ids)->get()->map(function($c) use($user){$row=$this->row($c,$user); if($c->trashed()) $row['reason']='Voucher ngừng hoạt động'; return $row;})]);
 }
}
