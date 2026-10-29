<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$titles = ['register' => 'Tạo tài khoản', 'login' => 'Đăng nhập', 'forgot-password' => 'Khôi phục mật khẩu', 'reset-password' => 'Đặt lại mật khẩu'];
$isLogin = $action === 'login';
$isRegister = $action === 'register';
$isRecovery = $action === 'forgot-password' || $action === 'reset-password';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MobileX | <?= $escape($titles[$action] ?? 'Tài khoản') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/public/assets/css/site.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#070d1b] font-['Plus_Jakarta_Sans'] text-white antialiased">
  <?php if ($isLogin): ?>
  <main class="relative grid min-h-screen overflow-hidden lg:grid-cols-[1.08fr_.92fr]">
    <section class="relative hidden overflow-hidden border-r border-white/10 bg-[#0b1427] px-12 py-10 lg:flex lg:flex-col xl:px-20">
      <div class="pointer-events-none absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-blue-500/20 blur-[110px]"></div>
      <div class="pointer-events-none absolute -right-16 bottom-0 h-80 w-80 rounded-full bg-emerald-400/10 blur-[100px]"></div>
      <a href="?page=home" class="relative z-10 text-2xl font-extrabold tracking-tight">Mobile<span class="text-blue-400">X</span></a>
      <div class="relative z-10 my-auto max-w-xl py-14">
        <span class="inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-4 py-2 text-xs font-bold uppercase tracking-[.18em] text-emerald-300"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>Trải nghiệm thành viên</span>
        <h1 class="mt-8 text-5xl font-extrabold leading-[1.12] tracking-tight xl:text-6xl">Công nghệ mới.<br><span class="bg-gradient-to-r from-blue-300 via-cyan-200 to-emerald-300 bg-clip-text text-transparent">Đặc quyền của bạn.</span></h1>
        <p class="mt-6 max-w-lg text-base leading-8 text-slate-400">Đăng nhập MobileX để theo dõi đơn hàng, lưu thiết bị yêu thích và nhận ưu đãi dành riêng cho bạn.</p>
        <div class="mt-12 grid max-w-lg grid-cols-3 gap-3">
          <div class="rounded-2xl border border-white/10 bg-white/[.04] p-4"><span class="text-xl">✦</span><p class="mt-3 text-sm font-semibold">Hàng chính hãng</p><p class="mt-1 text-xs text-slate-500">An tâm chọn mua</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/[.04] p-4"><span class="text-xl">⌁</span><p class="mt-3 text-sm font-semibold">Giao nhanh</p><p class="mt-1 text-xs text-slate-500">Theo dõi đơn dễ dàng</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/[.04] p-4"><span class="text-xl">◇</span><p class="mt-3 text-sm font-semibold">Ưu đãi riêng</p><p class="mt-1 text-xs text-slate-500">Dành cho thành viên</p></div>
        </div>
      </div>
      <p class="relative z-10 text-xs text-slate-600">© 2025 MobileX. Mua sắm công nghệ theo cách của bạn.</p>
      <div class="pointer-events-none absolute bottom-20 right-10 hidden h-56 w-36 rotate-12 rounded-[2.5rem] border border-blue-200/20 bg-gradient-to-br from-blue-400/20 via-slate-900 to-emerald-300/10 shadow-[0_0_100px_rgba(59,130,246,.18)] xl:block"><div class="mx-auto mt-3 h-1.5 w-12 rounded-full bg-white/20"></div><div class="m-4 mt-12 h-24 rounded-2xl border border-white/10 bg-gradient-to-br from-blue-400/20 to-emerald-300/10"></div><div class="mx-4 h-2 rounded-full bg-white/10"></div><div class="mx-4 mt-2 h-2 w-2/3 rounded-full bg-white/10"></div></div>
    </section>

    <section class="relative flex items-center justify-center px-5 py-10 sm:px-10">
      <div class="pointer-events-none absolute right-0 top-0 h-80 w-80 rounded-full bg-blue-500/10 blur-[100px]"></div>
      <div class="relative w-full max-w-md">
        <a href="?page=home" class="text-xl font-extrabold tracking-tight lg:hidden">Mobile<span class="text-blue-400">X</span></a>
        <div class="mt-10 rounded-[2rem] border border-white/10 bg-white/[.035] p-6 shadow-2xl backdrop-blur-xl sm:p-9 lg:mt-0">
          <div class="mb-8">
            <p class="text-sm font-semibold text-blue-300">Chào mừng trở lại</p>
            <h2 class="mt-2 text-3xl font-extrabold tracking-tight">Đăng nhập</h2>
            <p class="mt-2 text-sm text-slate-400">Nhập thông tin để tiếp tục với tài khoản MobileX.</p>
          </div>
          <?php if (!empty($error)): ?><p role="alert" class="mb-5 rounded-xl border border-red-400/20 bg-red-400/10 p-3 text-sm text-red-200"><?= $escape($error) ?></p><?php endif; ?>
          <?php if (!empty($message)): ?><p role="status" class="mb-5 rounded-xl border border-blue-400/20 bg-blue-400/10 p-3 text-sm text-blue-200"><?= $escape($message) ?></p><?php endif; ?>
          <form method="post" action="?page=login" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>">
            <label class="block text-sm font-semibold text-slate-200">Địa chỉ email
              <span class="mt-2 flex items-center rounded-xl border border-white/10 bg-[#080f1e] transition focus-within:border-blue-400/70 focus-within:ring-4 focus-within:ring-blue-400/10"><span class="pl-4 text-slate-500">✉</span><input required type="email" name="email" autocomplete="email" value="<?= $escape($old['email'] ?? '') ?>" placeholder="ban@email.com" class="w-full bg-transparent px-3 py-3.5 text-white outline-none placeholder:text-slate-600"></span>
            </label>
            <label class="block text-sm font-semibold text-slate-200">Mật khẩu
              <span class="mt-2 flex items-center rounded-xl border border-white/10 bg-[#080f1e] transition focus-within:border-blue-400/70 focus-within:ring-4 focus-within:ring-blue-400/10"><span class="pl-4 text-slate-500">♙</span><input required type="password" name="password" autocomplete="current-password" placeholder="Nhập mật khẩu" class="min-w-0 flex-1 bg-transparent px-3 py-3.5 text-white outline-none placeholder:text-slate-600"><button type="button" data-toggle-password class="mr-2 rounded-lg px-2 py-1 text-xs font-bold text-blue-300 hover:bg-white/5" aria-label="Hiện mật khẩu">Hiện</button></span>
            </label>
            <div class="flex items-center justify-between gap-3 text-sm">
              <label class="flex cursor-pointer items-center gap-2 text-slate-400"><input type="checkbox" name="remember" value="1" <?= !empty($old['remember']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-blue-400">Ghi nhớ đăng nhập</label>
              <a href="?page=forgot-password" class="font-semibold text-blue-300 hover:text-blue-200">Quên mật khẩu?</a>
            </div>
            <button class="group flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-400 px-4 py-3.5 font-bold text-white shadow-lg shadow-blue-500/20 transition hover:-translate-y-0.5 hover:shadow-blue-500/30">Đăng nhập <span class="transition group-hover:translate-x-1">→</span></button>
          </form>
          <div class="my-7 flex items-center gap-4 text-xs text-slate-600"><span class="h-px flex-1 bg-white/10"></span>ĐĂNG NHẬP AN TOÀN<span class="h-px flex-1 bg-white/10"></span></div>
          <p class="text-center text-sm text-slate-400">Chưa có tài khoản? <a class="font-bold text-blue-300 hover:text-blue-200" href="?page=register">Tạo tài khoản</a></p>
        </div>
        <p class="mt-6 text-center text-xs leading-6 text-slate-600">Bằng việc tiếp tục, bạn đồng ý với Điều khoản dịch vụ và Chính sách bảo mật của MobileX.</p>
        <a href="?page=home" class="mt-5 block text-center text-sm text-slate-500 hover:text-white">← Quay lại cửa hàng</a>
      </div>
    </section>
  </main>
  <script src="/public/assets/js/auth-form.js"></script>
  <?php elseif ($isRegister): ?>
  <main class="mx-auto flex min-h-screen max-w-lg items-center px-5 py-10">
    <section class="w-full rounded-[2rem] border border-white/10 bg-white/[.035] p-7 shadow-2xl sm:p-10">
      <a href="?page=home" class="text-xl font-extrabold">Mobile<span class="text-blue-400">X</span></a>
      <h1 class="mt-8 text-3xl font-extrabold">Tạo tài khoản</h1><p class="mt-2 text-sm text-slate-400">Tham gia MobileX để lưu thiết bị và theo dõi đơn hàng.</p>
      <?php if (!empty($error)): ?><p role="alert" class="mt-5 rounded-xl bg-red-950 p-3 text-sm text-red-200"><?= $escape($error) ?></p><?php endif; ?>
      <form method="post" action="?page=register" class="mt-7 space-y-5"><input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>">
        <label class="block text-sm font-semibold">Họ và tên<input required maxlength="120" name="name" autocomplete="name" class="mt-2 w-full rounded-xl border border-white/10 bg-[#080f1e] px-4 py-3.5 text-white outline-none focus:border-blue-400"></label>
        <label class="block text-sm font-semibold">Địa chỉ email<input required type="email" name="email" autocomplete="email" value="<?= $escape($old['email'] ?? '') ?>" placeholder="ban@email.com" class="mt-2 w-full rounded-xl border border-white/10 bg-[#080f1e] px-4 py-3.5 text-white outline-none focus:border-blue-400"></label>
        <label class="block text-sm font-semibold">Mật khẩu<span class="mt-2 flex rounded-xl border border-white/10 bg-[#080f1e] focus-within:border-blue-400"><input required type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Ít nhất 8 ký tự" class="min-w-0 flex-1 bg-transparent px-4 py-3.5 text-white outline-none"><button type="button" data-toggle-password class="px-3 text-xs font-bold text-blue-300" aria-label="Hiện mật khẩu">Hiện</button></span></label>
        <button class="w-full rounded-xl bg-gradient-to-r from-blue-500 to-blue-400 px-4 py-3.5 font-bold text-white">Tạo tài khoản</button>
      </form>
      <p class="mt-6 text-center text-sm text-slate-400">Đã có tài khoản? <a href="?page=login" class="font-bold text-blue-300">Đăng nhập</a></p>
    </section>
  </main><script src="/public/assets/js/auth-form.js"></script>
  <?php else: ?>
  <main class="mx-auto flex min-h-screen max-w-lg items-center px-5 py-10"><section class="w-full rounded-[2rem] border border-white/10 bg-white/[.035] p-8 shadow-2xl sm:p-10">
    <a href="?page=home" class="text-xl font-extrabold">Mobile<span class="text-blue-400">X</span></a>
    <h1 class="mt-8 text-3xl font-extrabold"><?= $escape($titles[$action] ?? 'Khôi phục mật khẩu') ?></h1>
    <?php if (!empty($error)): ?><p role="alert" class="mt-5 rounded-xl bg-red-950 p-3 text-sm text-red-200"><?= $escape($error) ?></p><?php endif; ?>
    <?php if (!empty($message)): ?><p role="status" class="mt-5 rounded-xl border border-blue-400/20 bg-blue-400/10 p-4 text-sm leading-6 text-blue-100"><?= $escape($message) ?></p><?php endif; ?>
    <?php if ($action === 'forgot-password'): ?>
      <form method="post" action="?page=forgot-password" class="mt-6 space-y-3"><input type="hidden" name="_csrf" value="<?= $escape($csrf) ?>"><label class="block text-sm font-semibold text-slate-200">Email tài khoản<input type="email" name="email" autocomplete="email" placeholder="ban@email.com" class="mt-2 w-full rounded-xl border border-white/10 bg-[#080f1e] px-4 py-3.5 text-white outline-none focus:border-blue-400"></label><button type="submit" disabled class="w-full cursor-not-allowed rounded-xl bg-slate-700 px-4 py-3.5 font-bold text-slate-400">Gửi liên kết (chưa khả dụng)</button></form>
    <?php endif; ?>
    <ol class="mt-7 space-y-4 text-sm text-slate-300"><li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-500/15 font-bold text-blue-300">1</span>Nhập email tài khoản để nhận liên kết xác minh.</li><li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-500/15 font-bold text-blue-300">2</span>Mở liên kết dùng một lần trong email và còn hạn.</li><li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-500/15 font-bold text-blue-300">3</span>Đặt mật khẩu mới rồi đăng nhập lại.</li></ol>
    <a href="?page=login" class="mt-8 inline-flex rounded-xl bg-blue-500 px-5 py-3 font-bold text-white">Quay lại đăng nhập</a>
  </section></main>
  <?php endif; ?>
</body>
</html>
