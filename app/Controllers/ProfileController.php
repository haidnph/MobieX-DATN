<?php
declare(strict_types=1);
require_once __DIR__.'/../Core/Database.php';require_once __DIR__.'/../Core/View.php';require_once __DIR__.'/../Support/Csrf.php';
final class ProfileController
{
 public function handle():void
 {
  $uid=(int)($_SESSION['user']['id']??0);if(!$uid){header('Location: index.php?page=login');exit;}$db=Database::connection();$error=null;$message=null;
  if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
   try{if(!Csrf::valid($_POST['_csrf']??null))throw new RuntimeException('Phiên không hợp lệ.');$action=(string)($_POST['action']??'profile');
    if($action==='profile'){$name=trim((string)($_POST['name']??''));$phone=trim((string)($_POST['phone']??''));if($name===''||mb_strlen($name)>255||($phone!==''&&!preg_match('/^[0-9+ .()-]{7,20}$/',$phone)))throw new InvalidArgumentException('Tên hoặc số điện thoại chưa hợp lệ.');$db->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([$name,$phone,$uid]);$_SESSION['user']['name']=$name;$message='Đã cập nhật hồ sơ.';}
    elseif($action==='password'){$old=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$q=$db->prepare('SELECT password FROM users WHERE id=?');$q->execute([$uid]);if(!password_verify($old,(string)$q->fetchColumn()))throw new InvalidArgumentException('Mật khẩu hiện tại chưa đúng.');if(strlen($new)<8)throw new InvalidArgumentException('Mật khẩu mới cần ít nhất 8 ký tự.');$db->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);$message='Đã đổi mật khẩu.';}
    elseif($action==='address'){$id=(int)($_POST['address_id']??0);$full=trim((string)($_POST['full_name']??''));$phone=trim((string)($_POST['address_phone']??''));$city=trim((string)($_POST['city']??''));$line=trim((string)($_POST['address_line']??''));$district=trim((string)($_POST['district']??''));$ward=trim((string)($_POST['ward']??''));$label=trim((string)($_POST['label']??''));if(!$full||!preg_match('/^[0-9+ .()-]{9,20}$/',$phone)||!$city||!$line||mb_strlen($line)>255)throw new InvalidArgumentException('Vui lòng nhập đủ thông tin địa chỉ hợp lệ.');$db->beginTransaction();if(isset($_POST['is_default']))$db->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$uid]);if($id){$q=$db->prepare('UPDATE user_addresses SET label=?,full_name=?,phone=?,city=?,district=?,ward=?,address_line=?,is_default=? WHERE id=? AND user_id=?');$q->execute([$label,$full,$phone,$city,$district,$ward,$line,isset($_POST['is_default'])?1:0,$id,$uid]);}else{$q=$db->prepare('INSERT INTO user_addresses(user_id,label,full_name,phone,city,district,ward,address_line,is_default) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$uid,$label,$full,$phone,$city,$district,$ward,$line,isset($_POST['is_default'])?1:0]);}$db->commit();$message='Đã lưu địa chỉ.';}
    elseif($action==='delete-address'){$db->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')->execute([(int)($_POST['address_id']??0),$uid]);$message='Đã xóa địa chỉ.';}
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e instanceof PDOException?'Không lưu được dữ liệu. Kiểm tra database.':$e->getMessage();}
  }
  $q=$db->prepare('SELECT id,name,email,phone FROM users WHERE id=?');$q->execute([$uid]);$user=$q->fetch(PDO::FETCH_ASSOC)?:[];$q=$db->prepare('SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC');$q->execute([$uid]);
  View::render('store/profile.php','MobileX | Hồ sơ cá nhân',['user'=>$user,'addresses'=>$q->fetchAll(PDO::FETCH_ASSOC),'csrf'=>Csrf::token(),'error'=>$error,'message'=>$message]);
 }
}
