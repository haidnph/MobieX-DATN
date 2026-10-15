<!doctype html>
<html lang="vi" class="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>MobileX Admin - Quáº£n trá»‹ há»‡ thá»‘ng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="/public/assets/css/admin.css">
  </head>
  <body>
    <aside
      class="sidebar fixed left-0 top-0 bottom-0 w-[250px] z-50 flex flex-col"
    >
      <div class="h-20 px-5 flex items-center border-b border-[#30394d]">
        <div
          class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#4d8eff] to-[#00b954] flex items-center justify-center"
        >
          <span class="material-symbols-outlined text-white">smartphone</span>
        </div>
        <div class="brandname ml-3">
          <b class="text-xl">Mobile<span class="text-[#adc6ff]">X</span></b>
          <div class="text-[9px] uppercase tracking-[.2em] text-[#7e879c]">
            Admin Control
          </div>
        </div>
      </div>
      <nav class="p-3 overflow-y-auto flex-1">
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-2"
        >
          Tá»•ng quan
        </div>
        <a
          class="nav active flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="dashboard"
          ><span class="material-symbols-outlined">dashboard</span
          ><span class="txt text-sm">Dashboard & Thá»‘ng kÃª</span></a
        >
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-5"
        >
          Sáº£n pháº©m
        </div>
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="categories"
          ><span class="material-symbols-outlined">category</span
          ><span class="txt text-sm">Danh má»¥c & ThÆ°Æ¡ng hiá»‡u</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="attributes"
          ><span class="material-symbols-outlined">tune</span
          ><span class="txt text-sm">Thuá»™c tÃ­nh & GiÃ¡ trá»‹</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="phones"
          ><span class="material-symbols-outlined">phone_iphone</span
          ><span class="txt text-sm">Sáº£n pháº©m Äiá»‡n thoáº¡i</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="accessories"
          ><span class="material-symbols-outlined">cable</span
          ><span class="txt text-sm">Sáº£n pháº©m Phá»¥ kiá»‡n</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="inventory"
          ><span class="material-symbols-outlined">warehouse</span
          ><span class="txt text-sm">Kho & Tá»“n kho</span></a
        >
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-5"
        >
          Kinh doanh
        </div>
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="orders"
          ><span class="material-symbols-outlined">shopping_bag</span
          ><span class="txt text-sm">ÄÆ¡n hÃ ng</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="coupons"
          ><span class="material-symbols-outlined">confirmation_number</span
          ><span class="txt text-sm">MÃ£ giáº£m giÃ¡</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="promotions"
          ><span class="material-symbols-outlined">campaign</span
          ><span class="txt text-sm">Banner & Khuyáº¿n mÃ£i</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="reviews"
          ><span class="material-symbols-outlined">reviews</span
          ><span class="txt text-sm">ÄÃ¡nh giÃ¡</span></a
        >
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-5"
        >
          Há»‡ thá»‘ng
        </div>
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="users"
          ><span class="material-symbols-outlined">manage_accounts</span
          ><span class="txt text-sm">NgÆ°á»i dÃ¹ng & PhÃ¢n quyá»n</span></a
        >
      </nav>
      <div class="p-3 border-t border-[#30394d]">
        <div class="bg-[#111a2d] rounded-xl p-2 flex items-center gap-3">
          <div
            class="w-9 h-9 rounded-full bg-gradient-to-br from-[#4d8eff] to-[#00b954] flex items-center justify-center font-bold"
          >
            A
          </div>
          <div class="txt">
            <b class="text-xs">Admin MobileX</b>
            <div class="text-[10px] text-[#7e879c]">Administrator</div>
          </div>
        </div>
      </div>
    </aside>

    <main class="main ml-[250px] min-h-screen">
      <header
        class="top fixed left-[250px] right-0 top-0 h-20 z-40 bg-[#060e20eF] backdrop-blur-xl border-b border-[#30394d] px-5 lg:px-8 flex items-center gap-4"
      >
        <button
          class="mobile-btn hidden p-2 rounded-lg bg-[#171f33]"
          onclick="toggleSide()"
        >
          <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="flex-1">
          <div class="text-xs text-[#7e879c]">MobileX / Admin</div>
          <h2 id="pageTitle" class="font-bold">Dashboard & Thá»‘ng kÃª</h2>
        </div>
        <div class="relative hidden sm:flex items-center w-60 h-10 search-box">
          <span
            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#727c91] text-[19px] leading-none pointer-events-none search-icon"
            >search</span
          >
          <input
            class="input rounded-lg h-10 w-full py-0 pl-10 pr-4 text-xs leading-none"
            placeholder="TÃ¬m nhanh..."
          />
        </div>
        <button class="p-2 rounded-lg hover:bg-[#222a3d] relative">
          <span class="material-symbols-outlined">notifications</span
          ><i
            class="absolute top-1 right-1 w-2 h-2 bg-[#4ae176] rounded-full"
          ></i>
        </button>
        <div
          class="w-9 h-9 rounded-full bg-gradient-to-br from-[#4d8eff] to-[#00b954] flex items-center justify-center font-bold text-sm"
        >
          A
        </div>
      </header>

      <div class="content pt-28 p-5 lg:p-8">
        <!-- DASHBOARD -->
        <section id="dashboard" class="page active">
          <div class="flex justify-between items-end mb-6">
            <div>
              <div
                class="text-[#4ae176] text-[10px] uppercase tracking-widest font-bold"
              >
                Live system
              </div>
              <h1 class="text-3xl font-extrabold mt-1">Tá»•ng quan há»‡ thá»‘ng</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Theo dÃµi doanh thu, Ä‘Æ¡n hÃ ng vÃ  tá»“n kho.
              </p>
            </div>
            <select class="select rounded-lg px-3 py-2 text-xs">
              <option>ThÃ¡ng nÃ y</option>
              <option>7 ngÃ y</option>
              <option>NÄƒm nay</option>
            </select>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 grid4 mb-5"></div>
          <div class="grid lg:grid-cols-3 gap-5">
            <div class="card rounded-xl p-5 lg:col-span-2">
              <div class="flex justify-between mb-5">
                <div>
                  <b class="text-lg">Doanh thu</b>
                  <p class="text-xs text-[#7e879c]">Doanh thu theo thÃ¡ng</p>
                </div>
                <span class="badge green">+12.8%</span>
              </div>
              <div
                class="h-64 flex items-end gap-3 px-2 border-b border-[#30394d]"
              >
                <div class="h-[45%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[54%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[50%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[63%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[69%] flex-1 bg-[#4d8eff88] rounded-t"></div>
                <div class="h-[75%] flex-1 bg-[#4d8effaa] rounded-t"></div>
                <div
                  class="h-[88%] flex-1 bg-[#4d8eff] rounded-t shadow-[0_0_20px_#4d8eff55]"
                ></div>
              </div>
              <div class="flex justify-between text-[9px] text-[#737c90] mt-2">
                <span>T4</span><span>T5</span><span>T6</span><span>T7</span
                ><span>T8</span><span>T9</span><span>T10</span>
              </div>
            </div>
            <div class="card rounded-xl p-5">
              <b class="text-lg">Cáº£nh bÃ¡o tá»“n kho</b>
              <p class="text-xs text-[#7e879c] mt-1 mb-5">
                Sáº£n pháº©m dÆ°á»›i 5 chiáº¿c
              </p>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-xs">iPhone 16 Pro 256GB</span
                  ><span class="badge red">2 chiáº¿c</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">Galaxy S25 Ultra 512GB</span
                  ><span class="badge red">3 chiáº¿c</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">CÃ¡p Type-C 240W</span
                  ><span class="badge yellow">4 chiáº¿c</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">AirPods Pro 3</span
                  ><span class="badge red">1 chiáº¿c</span>
                </div>
              </div>
              <button
                class="btn-dark w-full mt-6 py-2 rounded-lg text-xs"
                onclick="go('inventory')"
              >
                Xem kho hÃ ng
              </button>
            </div>
          </div>
        </section>

        <!-- CATEGORIES -->
        <section id="categories" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quáº£n lÃ½ Danh má»¥c &amp; ThÆ°Æ¡ng hiá»‡u
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Danh má»¥c Ä‘a cáº¥p vÃ  hÃ£ng sáº£n xuáº¥t
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('category')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              ThÃªm danh má»¥c
            </button>
          </div>
          <div class="grid lg:grid-cols-2 gap-5">
            <div class="card rounded-xl overflow-hidden">
              <div class="p-5 flex justify-between">
                <b>Danh má»¥c sáº£n pháº©m</b
                ><span class="badge blue">8 danh má»¥c</span>
              </div>
              <table class="table w-full">
                <thead>
                  <tr>
                    <th class="p-3 text-left">Danh má»¥c</th>
                    <th class="p-3 text-left">Danh má»¥c con</th>
                    <th class="p-3 text-left">Tráº¡ng thÃ¡i</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="p-3 font-semibold">Äiá»‡n thoáº¡i</td>
                    <td class="p-3 text-[#9da6bb]">iPhone, Samsung, Xiaomi</td>
                    <td class="p-3">
                      <span class="badge green">Hiá»ƒn thá»‹</span>
                    </td>
                    <td class="p-3">â‹®</td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Phá»¥ kiá»‡n</td>
                    <td class="p-3 text-[#9da6bb]">CÃ¡p sáº¡c, á»p lÆ°ng, Sáº¡c</td>
                    <td class="p-3">
                      <span class="badge green">Hiá»ƒn thá»‹</span>
                    </td>
                    <td class="p-3">â‹®</td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Laptop</td>
                    <td class="p-3 text-[#9da6bb]">MacBook, ASUS</td>
                    <td class="p-3">
                      <span class="badge green">Hiá»ƒn thá»‹</span>
                    </td>
                    <td class="p-3">â‹®</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="card rounded-xl overflow-hidden">
              <div class="p-5 flex justify-between">
                <b>HÃ£ng sáº£n xuáº¥t</b
                ><button
                  class="btn-blue rounded-lg px-3 py-2 text-xs"
                  onclick="openModal('brand')"
                >
                  + ThÃªm hÃ£ng
                </button>
              </div>
              <table class="table w-full">
                <thead>
                  <tr>
                    <th class="p-3 text-left">HÃ£ng</th>
                    <th class="p-3 text-left">Sáº£n pháº©m</th>
                    <th class="p-3 text-left">Tráº¡ng thÃ¡i</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="p-3 font-semibold">Apple</td>
                    <td class="p-3">84</td>
                    <td class="p-3">
                      <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Samsung</td>
                    <td class="p-3">61</td>
                    <td class="p-3">
                      <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Xiaomi</td>
                    <td class="p-3">38</td>
                    <td class="p-3">
                      <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- ATTRIBUTES -->
        <section id="attributes" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quáº£n lÃ½ Thuá»™c tÃ­nh &amp; GiÃ¡ trá»‹
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Thiáº¿t láº­p thuá»™c tÃ­nh dÃ¹ng cho biáº¿n thá»ƒ SKU
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('attribute')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              ThÃªm thuá»™c tÃ­nh
            </button>
          </div>
          <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">palette</span>
              </div>
              <b class="block mt-4">MÃ u sáº¯c</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                Äen, Titan, Tráº¯ng, Xanh
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chá»‰nh sá»­a â†’
              </button>
            </div>
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">storage</span>
              </div>
              <b class="block mt-4">Dung lÆ°á»£ng</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                64GB, 128GB, 256GB, 512GB, 1TB
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chá»‰nh sá»­a â†’
              </button>
            </div>
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">bolt</span>
              </div>
              <b class="block mt-4">CÃ´ng suáº¥t sáº¡c</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                20W, 33W, 67W, 120W, 240W
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chá»‰nh sá»­a â†’
              </button>
            </div>
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">memory</span>
              </div>
              <b class="block mt-4">RAM</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                4GB, 6GB, 8GB, 12GB, 16GB
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chá»‰nh sá»­a â†’
              </button>
            </div>
          </div>
        </section>

        <!-- PHONES -->
        <section id="phones" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quáº£n lÃ½ Sáº£n pháº©m Äiá»‡n thoáº¡i
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                ThÃ´ng tin chung, SKU, giÃ¡ nháº­p, giÃ¡ bÃ¡n vÃ  thÃ´ng sá»‘
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('phone')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              ThÃªm Ä‘iá»‡n thoáº¡i
            </button>
          </div>
          <div class="card rounded-xl p-4 mb-4 flex flex-wrap gap-2">
            <input
              class="input rounded-lg px-3 py-2 text-xs flex-1 min-w-48"
              placeholder="TÃ¬m tÃªn Ä‘iá»‡n thoáº¡i..."
            /><select class="select rounded-lg px-3 py-2 text-xs">
              <option>Táº¥t cáº£ thÆ°Æ¡ng hiá»‡u</option>
              <option>Apple</option>
              <option>Samsung</option></select
            ><select class="select rounded-lg px-3 py-2 text-xs">
              <option>Táº¥t cáº£ tráº¡ng thÃ¡i</option>
              <option>Äang bÃ¡n</option>
              <option>áº¨n</option>
            </select>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Sáº£n pháº©m</th>
                  <th class="p-4 text-left">SKU / Biáº¿n thá»ƒ</th>
                  <th class="p-4 text-left">GiÃ¡ nháº­p</th>
                  <th class="p-4 text-left">GiÃ¡ bÃ¡n</th>
                  <th class="p-4 text-left">Tá»“n</th>
                  <th class="p-4 text-left">Tráº¡ng thÃ¡i</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4">
                    <b>iPhone 16 Pro Max</b>
                    <div class="text-[10px] text-[#7e879c]">Apple â€¢ 2026</div>
                  </td>
                  <td class="p-4 text-xs">
                    IP16PM-BLK-256<br /><span class="text-[#7e879c]"
                      >Äen / 256GB</span
                    >
                  </td>
                  <td class="p-4">27.800.000Ä‘</td>
                  <td class="p-4 font-bold">34.990.000Ä‘</td>
                  <td class="p-4">28</td>
                  <td class="p-4"><span class="badge green">Äang bÃ¡n</span></td>
                  <td class="p-4">âœŽ</td>
                </tr>
                <tr>
                  <td class="p-4">
                    <b>Galaxy S25 Ultra</b>
                    <div class="text-[10px] text-[#7e879c]">Samsung â€¢ 2026</div>
                  </td>
                  <td class="p-4 text-xs">
                    S25U-TIT-512<br /><span class="text-[#7e879c]"
                      >Titan / 512GB</span
                    >
                  </td>
                  <td class="p-4">24.500.000Ä‘</td>
                  <td class="p-4 font-bold">31.490.000Ä‘</td>
                  <td class="p-4">3</td>
                  <td class="p-4"><span class="badge yellow">Sáº¯p háº¿t</span></td>
                  <td class="p-4">âœŽ</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ACCESSORIES -->
        <section id="accessories" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quáº£n lÃ½ Sáº£n pháº©m Phá»¥ kiá»‡n</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                PhÃ¢n loáº¡i phá»¥ kiá»‡n, giÃ¡ bÃ¡n vÃ  thÃ´ng sá»‘ ká»¹ thuáº­t
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('accessory')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              ThÃªm phá»¥ kiá»‡n
            </button>
          </div>
          <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">cable</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tá»•ng phá»¥ kiá»‡n</div>
              <b class="text-2xl mt-1 block">184</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">sell</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Äang bÃ¡n</div>
              <b class="text-2xl mt-1 block">162</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">warning</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Sáº¯p háº¿t hÃ ng</div>
              <b class="text-2xl mt-1 block">12</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">TÃªn phá»¥ kiá»‡n</th>
                  <th class="p-4 text-left">PhÃ¢n loáº¡i</th>
                  <th class="p-4 text-left">GiÃ¡ bÃ¡n</th>
                  <th class="p-4 text-left">Tá»“n kho</th>
                  <th class="p-4 text-left">Tráº¡ng thÃ¡i</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">CÃ¡p USB-C to USB-C 240W</td>
                  <td class="p-4">CÃ¡p sáº¡c</td>
                  <td class="p-4 font-bold">490.000Ä‘</td>
                  <td class="p-4">4</td>
                  <td class="p-4"><span class="badge yellow">Sáº¯p háº¿t</span></td>
                  <td class="p-4">âœŽ</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">á»p MagSafe iPhone 16 Pro</td>
                  <td class="p-4">á»p lÆ°ng</td>
                  <td class="p-4 font-bold">890.000Ä‘</td>
                  <td class="p-4">86</td>
                  <td class="p-4"><span class="badge green">Äang bÃ¡n</span></td>
                  <td class="p-4">âœŽ</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- INVENTORY -->
        <section id="inventory" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quáº£n lÃ½ Kho &amp; Tá»“n kho</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Nháº­p hÃ ng, tÄƒng tá»“n kho biáº¿n thá»ƒ vÃ  cáº­p nháº­t giÃ¡ vá»‘n
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('stock')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >move_to_inbox</span
              >
              Nháº­p hÃ ng
            </button>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined"
                    >inventory_2</span
                  ></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tá»•ng SKU</div>
              <b class="text-2xl mt-1 block">326</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined"
                    >warehouse</span
                  ></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tá»•ng tá»“n</div>
              <b class="text-2xl mt-1 block">8.426</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">warning</span></span
                ><span class="badge red">Cáº£nh bÃ¡o</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">DÆ°á»›i 5 chiáº¿c</div>
              <b class="text-2xl mt-1 block">42</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">payments</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">GiÃ¡ trá»‹ kho</div>
              <b class="text-2xl mt-1 block">86,4 Tá»·</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-5 flex justify-between">
              <b>Lá»‹ch sá»­ nháº­p kho</b
              ><span class="text-xs text-[#7e879c]">ThÃ¡ng 10/2026</span>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">MÃ£ phiáº¿u</th>
                  <th class="p-4 text-left">NhÃ  cung cáº¥p</th>
                  <th class="p-4 text-left">Sá»‘ SKU</th>
                  <th class="p-4 text-left">Tá»•ng tiá»n</th>
                  <th class="p-4 text-left">NgÃ y nháº­p</th>
                  <th class="p-4 text-left">NgÆ°á»i nháº­p</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-bold">PN-00182</td>
                  <td class="p-4">Apple Distribution VN</td>
                  <td class="p-4">24</td>
                  <td class="p-4 font-bold">1.284.000.000Ä‘</td>
                  <td class="p-4">02/10/2026</td>
                  <td class="p-4">Admin</td>
                </tr>
                <tr>
                  <td class="p-4 font-bold">PN-00181</td>
                  <td class="p-4">Samsung Electronics</td>
                  <td class="p-4">18</td>
                  <td class="p-4 font-bold">642.500.000Ä‘</td>
                  <td class="p-4">01/10/2026</td>
                  <td class="p-4">Kho</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ORDERS -->
        <section id="orders" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quáº£n lÃ½ ÄÆ¡n hÃ ng</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Theo dÃµi tiáº¿n trÃ¬nh tá»« xÃ¡c nháº­n Ä‘áº¿n hoÃ n thÃ nh
              </p>
            </div>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="card rounded-xl p-4">
              <span class="badge blue">Táº¥t cáº£</span
              ><b class="text-xl block mt-3">156</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge yellow">Chá» xÃ¡c nháº­n</span
              ><b class="text-xl block mt-3">38</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge purple">ÄÃ³ng gÃ³i</span
              ><b class="text-xl block mt-3">27</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge blue">Äang giao</span
              ><b class="text-xl block mt-3">42</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge green">HoÃ n thÃ nh</span
              ><b class="text-xl block mt-3">49</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex flex-wrap gap-2">
              <input
                class="input rounded-lg px-3 py-2 text-xs flex-1"
                placeholder="TÃ¬m mÃ£ Ä‘Æ¡n / khÃ¡ch hÃ ng..."
              /><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Táº¥t cáº£ tráº¡ng thÃ¡i</option>
                <option>Chá» xÃ¡c nháº­n</option>
                <option>ÄÃ³ng gÃ³i</option>
                <option>Äang giao</option>
                <option>HoÃ n thÃ nh</option>
                <option>ÄÃ£ há»§y</option></select
              ><input type="date" class="input rounded-lg px-3 py-2 text-xs" />
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">MÃ£ Ä‘Æ¡n</th>
                  <th class="p-4 text-left">KhÃ¡ch hÃ ng</th>
                  <th class="p-4 text-left">Sáº£n pháº©m</th>
                  <th class="p-4 text-left">Tá»•ng tiá»n</th>
                  <th class="p-4 text-left">Tráº¡ng thÃ¡i</th>
                  <th class="p-4">Cáº­p nháº­t</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-bold">#MX10482</td>
                  <td class="p-4">Nguyá»…n Minh</td>
                  <td class="p-4">iPhone 16 Pro Max</td>
                  <td class="p-4 font-bold">34.990.000Ä‘</td>
                  <td class="p-4">
                    <span class="badge green">HoÃ n thÃ nh</span>
                  </td>
                  <td class="p-4">
                    <button
                      class="btn-dark px-2 py-1 rounded text-[10px]"
                      onclick="openModal('order')"
                    >
                      Tiáº¿n trÃ¬nh
                    </button>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-bold">#MX10481</td>
                  <td class="p-4">Tráº§n HoÃ ng</td>
                  <td class="p-4">Galaxy S25 Ultra</td>
                  <td class="p-4 font-bold">31.490.000Ä‘</td>
                  <td class="p-4"><span class="badge blue">Äang giao</span></td>
                  <td class="p-4">
                    <button
                      class="btn-dark px-2 py-1 rounded text-[10px]"
                      onclick="openModal('order')"
                    >
                      Tiáº¿n trÃ¬nh
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- COUPONS -->
        <section id="coupons" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quáº£n lÃ½ MÃ£ giáº£m giÃ¡</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Coupon theo pháº§n trÄƒm hoáº·c sá»‘ tiá»n cá»‘ Ä‘á»‹nh
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('coupon')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Táº¡o mÃ£ giáº£m giÃ¡
            </button>
          </div>
          <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined"
                    >confirmation_number</span
                  ></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">MÃ£ Ä‘ang hoáº¡t Ä‘á»™ng</div>
              <b class="text-2xl mt-1 block">18</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined"
                    >local_offer</span
                  ></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">
                LÆ°á»£t sá»­ dá»¥ng thÃ¡ng nÃ y
              </div>
              <b class="text-2xl mt-1 block">2.841</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">payments</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">GiÃ¡ trá»‹ Ä‘Ã£ giáº£m</div>
              <b class="text-2xl mt-1 block">184,5 Tr</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">MÃ£</th>
                  <th class="p-4 text-left">Loáº¡i giáº£m</th>
                  <th class="p-4 text-left">ÄÆ¡n tá»‘i thiá»ƒu</th>
                  <th class="p-4 text-left">ÄÃ£ dÃ¹ng</th>
                  <th class="p-4 text-left">Thá»i háº¡n</th>
                  <th class="p-4">Tráº¡ng thÃ¡i</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-extrabold text-[#adc6ff]">MOBILE10</td>
                  <td class="p-4">Giáº£m 10%</td>
                  <td class="p-4">500.000Ä‘</td>
                  <td class="p-4">382 / 1.000</td>
                  <td class="p-4">01/10 â†’ 31/10</td>
                  <td class="p-4">
                    <span class="badge green">Äang cháº¡y</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-extrabold text-[#adc6ff]">NEW500</td>
                  <td class="p-4">Giáº£m 500.000Ä‘</td>
                  <td class="p-4">10.000.000Ä‘</td>
                  <td class="p-4">128 / 500</td>
                  <td class="p-4">01/10 â†’ 15/10</td>
                  <td class="p-4">
                    <span class="badge green">Äang cháº¡y</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- PROMOTIONS -->
        <section id="promotions" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quáº£n lÃ½ Banner &amp; Khuyáº¿n mÃ£i
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Slider trang chá»§ vÃ  Flash Sale theo khung giá»
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('banner')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              ThÃªm banner
            </button>
          </div>
          <div class="grid lg:grid-cols-2 gap-5">
            <div class="card rounded-xl p-5">
              <div class="flex justify-between mb-4">
                <b>Banner trang chá»§</b
                ><span class="badge green">3 Ä‘ang hiá»ƒn thá»‹</span>
              </div>
              <div class="space-y-3">
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-blue-600 to-cyan-500 flex items-center justify-between"
                >
                  <div>
                    <b>iPhone 16 Series</b>
                    <div class="text-[10px] opacity-80 mt-1">Äang hiá»ƒn thá»‹</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('ÄÃ£ thay Ä‘á»•i tráº¡ng thÃ¡i banner')"
                  >
                    Báº­t/Táº¯t
                  </button>
                </div>
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-between"
                >
                  <div>
                    <b>Samsung Galaxy S25</b>
                    <div class="text-[10px] opacity-80 mt-1">Äang hiá»ƒn thá»‹</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('ÄÃ£ thay Ä‘á»•i tráº¡ng thÃ¡i banner')"
                  >
                    Báº­t/Táº¯t
                  </button>
                </div>
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-orange-600 to-red-500 flex items-center justify-between"
                >
                  <div>
                    <b>Flash Sale 10.10</b>
                    <div class="text-[10px] opacity-80 mt-1">Táº¯t</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('ÄÃ£ thay Ä‘á»•i tráº¡ng thÃ¡i banner')"
                  >
                    Báº­t/Táº¯t
                  </button>
                </div>
              </div>
            </div>
            <div class="card rounded-xl p-5">
              <div class="flex justify-between mb-4">
                <b>Flash Sale</b
                ><button
                  class="btn-blue px-3 py-2 rounded-lg text-xs"
                  onclick="openModal('flash')"
                >
                  + Táº¡o Flash Sale
                </button>
              </div>
              <div class="bg-[#0f172b] rounded-xl p-4">
                <div class="flex justify-between">
                  <span class="badge red">LIVE</span><b>20:00 - 22:00</b>
                </div>
                <h3 class="font-bold mt-3">Flash Sale Cuá»‘i NgÃ y</h3>
                <p class="text-xs text-[#7e879c] mt-1">
                  24 sáº£n pháº©m â€¢ Giáº£m Ä‘áº¿n 35%
                </p>
                <div class="mt-4 h-2 bg-[#30394d] rounded-full">
                  <div class="h-2 w-[72%] bg-[#4ae176] rounded-full"></div>
                </div>
                <div class="text-[10px] text-[#7e879c] mt-2">
                  72% sáº£n pháº©m Ä‘Ã£ bÃ¡n
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- REVIEWS -->
        <section id="reviews" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quáº£n lÃ½ ÄÃ¡nh giÃ¡</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Duyá»‡t hoáº·c áº©n Ä‘Ã¡nh giÃ¡ vi pháº¡m tiÃªu chuáº©n cá»™ng Ä‘á»“ng
              </p>
            </div>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">reviews</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tá»•ng Ä‘Ã¡nh giÃ¡</div>
              <b class="text-2xl mt-1 block">12.486</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">star</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Äiá»ƒm trung bÃ¬nh</div>
              <b class="text-2xl mt-1 block">4.7</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">pending</span></span
                ><span class="badge red">Cáº£nh bÃ¡o</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Chá» duyá»‡t</div>
              <b class="text-2xl mt-1 block">38</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined"
                    >visibility_off</span
                  ></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">ÄÃ£ áº©n</div>
              <b class="text-2xl mt-1 block">82</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex gap-2">
              <select class="select rounded-lg px-3 py-2 text-xs">
                <option>Táº¥t cáº£</option>
                <option>Chá» duyá»‡t</option>
                <option>ÄÃ£ duyá»‡t</option>
                <option>ÄÃ£ áº©n</option></select
              ><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Táº¥t cáº£ sá»‘ sao</option>
                <option>1 sao</option>
                <option>2 sao</option>
                <option>3 sao</option>
              </select>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">KhÃ¡ch hÃ ng</th>
                  <th class="p-4 text-left">Sáº£n pháº©m</th>
                  <th class="p-4 text-left">ÄÃ¡nh giÃ¡</th>
                  <th class="p-4 text-left">Ná»™i dung</th>
                  <th class="p-4 text-left">Tráº¡ng thÃ¡i</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">Nguyá»…n An</td>
                  <td class="p-4">iPhone 16 Pro</td>
                  <td class="p-4 text-yellow-400">â˜…â˜…â˜…â˜…â˜…</td>
                  <td class="p-4 max-w-xs">Sáº£n pháº©m tá»‘t, giao hÃ ng nhanh...</td>
                  <td class="p-4"><span class="badge green">ÄÃ£ duyá»‡t</span></td>
                  <td class="p-4">
                    <button
                      class="btn-danger px-2 py-1 rounded text-[10px]"
                      onclick="toast('ÄÃ£ áº©n Ä‘Ã¡nh giÃ¡')"
                    >
                      áº¨n
                    </button>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Tráº§n B</td>
                  <td class="p-4">CÃ¡p Type-C</td>
                  <td class="p-4 text-yellow-400">â˜…â˜…</td>
                  <td class="p-4 max-w-xs">Ná»™i dung cáº§n kiá»ƒm duyá»‡t...</td>
                  <td class="p-4">
                    <span class="badge yellow">Chá» duyá»‡t</span>
                  </td>
                  <td class="p-4">
                    <button
                      class="btn-green px-2 py-1 rounded text-[10px]"
                      onclick="toast('ÄÃ£ duyá»‡t Ä‘Ã¡nh giÃ¡')"
                    >
                      Duyá»‡t
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- USERS -->
        <section id="users" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quáº£n lÃ½ NgÆ°á»i dÃ¹ng &amp; PhÃ¢n quyá»n
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                KhÃ¡ch hÃ ng, nhÃ¢n viÃªn vÃ  vai trÃ² há»‡ thá»‘ng
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('user')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >person_add</span
              >
              ThÃªm nhÃ¢n viÃªn
            </button>
          </div>
          <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">group</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tá»•ng khÃ¡ch hÃ ng</div>
              <b class="text-2xl mt-1 block">24.680</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">badge</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">NhÃ¢n viÃªn</div>
              <b class="text-2xl mt-1 block">26</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">lock</span></span
                ><span class="badge red">Cáº£nh bÃ¡o</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">TÃ i khoáº£n bá»‹ khÃ³a</div>
              <b class="text-2xl mt-1 block">14</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex gap-2">
              <input
                class="input rounded-lg px-3 py-2 text-xs flex-1"
                placeholder="TÃ¬m tÃªn / email / sá»‘ Ä‘iá»‡n thoáº¡i..."
              /><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Táº¥t cáº£ vai trÃ²</option>
                <option>Admin</option>
                <option>Káº¿ toÃ¡n/Kho</option>
                <option>NhÃ¢n viÃªn bÃ¡n hÃ ng</option>
                <option>KhÃ¡ch hÃ ng</option>
              </select>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">NgÆ°á»i dÃ¹ng</th>
                  <th class="p-4 text-left">Email</th>
                  <th class="p-4 text-left">Vai trÃ²</th>
                  <th class="p-4 text-left">Tráº¡ng thÃ¡i</th>
                  <th class="p-4 text-left">NgÃ y táº¡o</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">Admin MobileX</td>
                  <td class="p-4">admin@mobilex.vn</td>
                  <td class="p-4"><span class="badge purple">Admin</span></td>
                  <td class="p-4">
                    <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                  </td>
                  <td class="p-4">01/01/2026</td>
                  <td class="p-4">âœŽ</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Nguyá»…n Kho</td>
                  <td class="p-4">kho@mobilex.vn</td>
                  <td class="p-4">
                    <span class="badge blue">Káº¿ toÃ¡n/Kho</span>
                  </td>
                  <td class="p-4">
                    <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                  </td>
                  <td class="p-4">12/02/2026</td>
                  <td class="p-4">âœŽ</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Tráº§n Sales</td>
                  <td class="p-4">sales@mobilex.vn</td>
                  <td class="p-4"><span class="badge blue">BÃ¡n hÃ ng</span></td>
                  <td class="p-4">
                    <span class="badge green">Hoáº¡t Ä‘á»™ng</span>
                  </td>
                  <td class="p-4">04/03/2026</td>
                  <td class="p-4">âœŽ</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>

    <!-- MODAL -->
    <div
      id="modalBg"
      class="modal-bg"
      onclick="if (event.target === this) closeModal();"
    >
      <div class="modal">
        <div
          class="p-5 border-b border-[#30394d] flex justify-between items-center"
        >
          <div>
            <h3 id="modalTitle" class="font-bold text-lg">ThÃªm má»›i</h3>
            <p id="modalSub" class="text-[11px] text-[#7e879c] mt-1"></p>
          </div>
          <button onclick="closeModal()" class="p-2 hover:bg-[#222a3d] rounded">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form id="modalForm" class="p-5" onsubmit="submitModal(event)">
          <div id="modalFields" class="grid md:grid-cols-2 gap-4"></div>
          <div
            class="flex justify-end gap-2 mt-6 pt-5 border-t border-[#30394d]"
          >
            <button
              type="button"
              class="btn-dark px-4 py-2 rounded-lg text-xs"
              onclick="closeModal()"
            >
              Há»§y</button
            ><button class="btn-blue px-4 py-2 rounded-lg text-xs font-bold">
              LÆ°u thÃ´ng tin
            </button>
          </div>
        </form>
      </div>
    </div>
    <div id="toast" class="toast">ÄÃ£ lÆ°u thay Ä‘á»•i</div>

    <script src="/public/assets/js/admin.js"></script>
  </body>
</html>
