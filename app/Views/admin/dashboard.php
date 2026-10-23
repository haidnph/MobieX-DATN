<!doctype html>
<html lang="vi" class="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>MobileX Admin - Quản trị hệ thống</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
      rel="stylesheet"
    />
    <style>
      * {
        box-sizing: border-box;
      }
      body {
        margin: 0;
        background: #0b1326;
        color: #dae2fd;
        font-family: "Plus Jakarta Sans", sans-serif;
      }
      .material-symbols-outlined {
        font-variation-settings:
          "FILL" 0,
          "wght" 500,
          "GRAD" 0,
          "opsz" 24;
      }
      ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }
      ::-webkit-scrollbar-thumb {
        background: #394256;
        border-radius: 8px;
      }
      .sidebar {
        background: #060e20;
        border-right: 1px solid #252d40;
      }
      .nav {
        color: #aeb5c9;
        transition: 0.2s;
      }
      .nav:hover {
        background: #1b2438;
        color: #fff;
      }
      .nav.active {
        background: #142a50;
        color: #adc6ff;
        border-right: 2px solid #4d8eff;
      }
      .card {
        background: #171f33;
        border: 1px solid #30394d;
      }
      .input,
      .select {
        background: #0f172b;
        border: 1px solid #394256;
        color: #dae2fd;
      }
      .input:focus,
      .select:focus {
        outline: 0;
        border-color: #4d8eff;
        box-shadow: 0 0 0 3px #4d8eff20;
      }
      .btn-blue {
        background: #4d8eff;
        color: #fff;
      }
      .btn-blue:hover {
        filter: brightness(1.1);
      }
      .btn-green {
        background: #00b954;
        color: #04150c;
      }
      .btn-dark {
        background: #222a3d;
        border: 1px solid #3b455b;
      }
      .btn-danger {
        background: #ff57571a;
        color: #ff8b8b;
        border: 1px solid #ff575740;
      }
      .badge {
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
      }
      .green {
        background: #00b9541c;
        color: #4ae176;
      }
      .blue {
        background: #4d8eff1c;
        color: #adc6ff;
      }
      .yellow {
        background: #facc151a;
        color: #facc15;
      }
      .red {
        background: #ff57571c;
        color: #ff8b8b;
      }
      .purple {
        background: #a78bfa1c;
        color: #c4b5fd;
      }
      .table th {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #8c909f;
        background: #111a2d;
      }
      .table td {
        border-top: 1px solid #30394d50;
        font-size: 12px;
      }
      .table tr:hover td {
        background: #20283b80;
      }
      .page {
        display: none;
      }
      .page.active {
        display: block;
      }
      .stat:hover {
        transform: translateY(-2px);
        border-color: #4d8eff66;
      }
      .stat {
        transition: 0.2s;
      }
      .modal-bg {
        display: none;
        position: fixed;
        inset: 0;
        background: #0009;
        z-index: 100;
        align-items: center;
        justify-content: center;
        padding: 20px;
      }
      .modal-bg.show {
        display: flex;
      }
      .modal {
        width: min(720px, 100%);
        max-height: 90vh;
        overflow: auto;
        background: #111a2d;
        border: 1px solid #414b61;
        border-radius: 14px;
        box-shadow: 0 25px 80px #0009;
      }
      .toast {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 200;
        transform: translateY(120px);
        transition: 0.3s;
        background: #171f33;
        border: 1px solid #4d8eff;
        color: #dae2fd;
        padding: 13px 17px;
        border-radius: 10px;
      }
      .toast.show {
        transform: translateY(0);
      }

      /* ===== SEARCH ALIGNMENT FIX ===== */
      .top .search-box {
        position: relative;
        height: 40px;
        display: flex;
        align-items: center;
      }

      .top .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        line-height: 1;
        pointer-events: none;
      }

      /* ===== MOBILEX LAYOUT FIX ===== */
      html,
      body {
        min-height: 100%;
      }

      body {
        overflow-x: hidden;
      }

      .main {
        margin-left: 250px;
        min-height: 100vh;
        width: calc(100% - 250px);
      }

      .top {
        position: fixed;
        top: 0;
        left: 250px;
        right: 0;
        height: 80px;
        z-index: 80;
      }

      .content {
        width: 100%;
        min-height: 100vh;
        padding-top: 104px !important;
        padding-left: 32px;
        padding-right: 32px;
        padding-bottom: 40px;
      }

      .page {
        width: 100%;
      }

      .page-header {
        min-height: 52px;
      }

      .page-header h1 {
        line-height: 1.25;
      }

      .page-header p {
        line-height: 1.5;
      }

      .card {
        min-width: 0;
      }

      .table {
        min-width: 720px;
      }

      .card:has(.table) {
        overflow-x: auto;
      }

      @media (max-width: 900px) {
        .main {
          margin-left: 76px !important;
          width: calc(100% - 76px);
        }

        .top {
          left: 76px !important;
        }

        .content {
          padding-top: 104px !important;
          padding-left: 20px;
          padding-right: 20px;
        }
      }

      @media (max-width: 650px) {
        .main {
          margin-left: 0 !important;
          width: 100%;
        }

        .top {
          left: 0 !important;
        }

        .content {
          padding-top: 96px !important;
          padding-left: 16px;
          padding-right: 16px;
        }

        .page-header {
          align-items: flex-start;
          flex-direction: column;
        }

        .page-header button {
          width: 100%;
        }
      }

      @media (max-width: 900px) {
        .sidebar {
          width: 76px;
        }
        .sidebar .txt,
        .sidebar .section {
          display: none;
        }
        .main {
          margin-left: 76px !important;
        }
        .brandname {
          display: none;
        }
      }
      @media (max-width: 650px) {
        .sidebar {
          display: none;
        }
        .main {
          margin-left: 0 !important;
        }
        .top {
          left: 0 !important;
        }
        .mobile-btn {
          display: block !important;
        }
        .content {
          padding: 18px !important;
        }
        .grid4 {
          grid-template-columns: 1fr 1fr;
        }
        .hide-sm {
          display: none;
        }
      }

      /* FINAL LAYOUT NORMALIZATION */
      html,
      body {
        min-height: 100%;
      }

      body {
        overflow-x: hidden;
      }

      .main-content {
        margin-left: 220px;
        padding-top: 94px !important;
        min-height: 100vh;
      }

      .top {
        z-index: 100;
      }

      .search-box {
        height: 40px;
        display: flex;
        align-items: center;
      }

      .search-box .search-icon {
        top: 50%;
        transform: translateY(-50%);
        line-height: 1;
      }

      .search-box input {
        height: 40px;
        line-height: 1;
      }

      @media (max-width: 650px) {
        .main-content {
          margin-left: 0;
          padding-top: 82px !important;
          padding-left: 14px;
          padding-right: 14px;
        }
      }
    </style>
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
          Tổng quan
        </div>
        <a
          class="nav active flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="dashboard"
          ><span class="material-symbols-outlined">dashboard</span
          ><span class="txt text-sm">Dashboard & Thống kê</span></a
        >
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-5"
        >
          Sản phẩm
        </div>
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="categories"
          ><span class="material-symbols-outlined">category</span
          ><span class="txt text-sm">Danh mục & Thương hiệu</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="attributes"
          ><span class="material-symbols-outlined">tune</span
          ><span class="txt text-sm">Thuộc tính & Giá trị</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="phones"
          ><span class="material-symbols-outlined">phone_iphone</span
          ><span class="txt text-sm">Sản phẩm Điện thoại</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="accessories"
          ><span class="material-symbols-outlined">cable</span
          ><span class="txt text-sm">Sản phẩm Phụ kiện</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="inventory"
          ><span class="material-symbols-outlined">warehouse</span
          ><span class="txt text-sm">Kho & Tồn kho</span></a
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
          ><span class="txt text-sm">Đơn hàng</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="coupons"
          ><span class="material-symbols-outlined">confirmation_number</span
          ><span class="txt text-sm">Mã giảm giá</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="promotions"
          ><span class="material-symbols-outlined">campaign</span
          ><span class="txt text-sm">Banner & Khuyến mãi</span></a
        >
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="reviews"
          ><span class="material-symbols-outlined">reviews</span
          ><span class="txt text-sm">Đánh giá</span></a
        >
        <div
          class="section text-[10px] uppercase tracking-widest text-[#737c90] px-3 mb-2 mt-5"
        >
          Hệ thống
        </div>
        <a
          class="nav flex gap-3 items-center px-3 py-3 rounded-lg mb-1"
          data-page="users"
          ><span class="material-symbols-outlined">manage_accounts</span
          ><span class="txt text-sm">Người dùng & Phân quyền</span></a
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
          <h2 id="pageTitle" class="font-bold">Dashboard & Thống kê</h2>
        </div>
        <div class="relative hidden sm:flex items-center w-60 h-10 search-box">
          <span
            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#727c91] text-[19px] leading-none pointer-events-none search-icon"
            >search</span
          >
          <input
            class="input rounded-lg h-10 w-full py-0 pl-10 pr-4 text-xs leading-none"
            placeholder="Tìm nhanh..."
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
              <h1 class="text-3xl font-extrabold mt-1">Tổng quan hệ thống</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Theo dõi doanh thu, đơn hàng và tồn kho.
              </p>
            </div>
            <select class="select rounded-lg px-3 py-2 text-xs">
              <option>Tháng này</option>
              <option>7 ngày</option>
              <option>Năm nay</option>
            </select>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 grid4 mb-5"></div>
          <div class="grid lg:grid-cols-3 gap-5">
            <div class="card rounded-xl p-5 lg:col-span-2">
              <div class="flex justify-between mb-5">
                <div>
                  <b class="text-lg">Doanh thu</b>
                  <p class="text-xs text-[#7e879c]">Doanh thu theo tháng</p>
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
              <b class="text-lg">Cảnh báo tồn kho</b>
              <p class="text-xs text-[#7e879c] mt-1 mb-5">
                Sản phẩm dưới 5 chiếc
              </p>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-xs">iPhone 16 Pro 256GB</span
                  ><span class="badge red">2 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">Galaxy S25 Ultra 512GB</span
                  ><span class="badge red">3 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">Cáp Type-C 240W</span
                  ><span class="badge yellow">4 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">AirPods Pro 3</span
                  ><span class="badge red">1 chiếc</span>
                </div>
              </div>
              <button
                class="btn-dark w-full mt-6 py-2 rounded-lg text-xs"
                onclick="go('inventory')"
              >
                Xem kho hàng
              </button>
            </div>
          </div>
        </section>

        <!-- CATEGORIES -->
        <section id="categories" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quản lý Danh mục &amp; Thương hiệu
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Danh mục đa cấp và hãng sản xuất
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('category')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Thêm danh mục
            </button>
          </div>
          <div class="grid lg:grid-cols-2 gap-5">
            <div class="card rounded-xl overflow-hidden">
              <div class="p-5 flex justify-between">
                <b>Danh mục sản phẩm</b
                ><span class="badge blue">8 danh mục</span>
              </div>
              <table class="table w-full">
                <thead>
                  <tr>
                    <th class="p-3 text-left">Danh mục</th>
                    <th class="p-3 text-left">Danh mục con</th>
                    <th class="p-3 text-left">Trạng thái</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="p-3 font-semibold">Điện thoại</td>
                    <td class="p-3 text-[#9da6bb]">iPhone, Samsung, Xiaomi</td>
                    <td class="p-3">
                      <span class="badge green">Hiển thị</span>
                    </td>
                    <td class="p-3">⋮</td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Phụ kiện</td>
                    <td class="p-3 text-[#9da6bb]">Cáp sạc, Ốp lưng, Sạc</td>
                    <td class="p-3">
                      <span class="badge green">Hiển thị</span>
                    </td>
                    <td class="p-3">⋮</td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Laptop</td>
                    <td class="p-3 text-[#9da6bb]">MacBook, ASUS</td>
                    <td class="p-3">
                      <span class="badge green">Hiển thị</span>
                    </td>
                    <td class="p-3">⋮</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="card rounded-xl overflow-hidden">
              <div class="p-5 flex justify-between">
                <b>Hãng sản xuất</b
                ><button
                  class="btn-blue rounded-lg px-3 py-2 text-xs"
                  onclick="openModal('brand')"
                >
                  + Thêm hãng
                </button>
              </div>
              <table class="table w-full">
                <thead>
                  <tr>
                    <th class="p-3 text-left">Hãng</th>
                    <th class="p-3 text-left">Sản phẩm</th>
                    <th class="p-3 text-left">Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="p-3 font-semibold">Apple</td>
                    <td class="p-3">84</td>
                    <td class="p-3">
                      <span class="badge green">Hoạt động</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Samsung</td>
                    <td class="p-3">61</td>
                    <td class="p-3">
                      <span class="badge green">Hoạt động</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold">Xiaomi</td>
                    <td class="p-3">38</td>
                    <td class="p-3">
                      <span class="badge green">Hoạt động</span>
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
                Quản lý Thuộc tính &amp; Giá trị
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Thiết lập thuộc tính dùng cho biến thể SKU
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('attribute')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Thêm thuộc tính
            </button>
          </div>
          <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">palette</span>
              </div>
              <b class="block mt-4">Màu sắc</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                Đen, Titan, Trắng, Xanh
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chỉnh sửa →
              </button>
            </div>
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">storage</span>
              </div>
              <b class="block mt-4">Dung lượng</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                64GB, 128GB, 256GB, 512GB, 1TB
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chỉnh sửa →
              </button>
            </div>
            <div class="card rounded-xl p-5">
              <div
                class="w-10 h-10 rounded-lg bg-[#4d8eff18] text-[#adc6ff] flex items-center justify-center"
              >
                <span class="material-symbols-outlined">bolt</span>
              </div>
              <b class="block mt-4">Công suất sạc</b>
              <p class="text-xs text-[#7e879c] mt-2 leading-6">
                20W, 33W, 67W, 120W, 240W
              </p>
              <button
                class="text-[#adc6ff] text-xs mt-4"
                onclick="openModal('attribute')"
              >
                Chỉnh sửa →
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
                Chỉnh sửa →
              </button>
            </div>
          </div>
        </section>

        <!-- PHONES -->
        <section id="phones" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">
                Quản lý Sản phẩm Điện thoại
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Thông tin chung, SKU, giá nhập, giá bán và thông số
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('phone')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Thêm điện thoại
            </button>
          </div>
          <div class="card rounded-xl p-4 mb-4 flex flex-wrap gap-2">
            <input
              class="input rounded-lg px-3 py-2 text-xs flex-1 min-w-48"
              placeholder="Tìm tên điện thoại..."
            /><select class="select rounded-lg px-3 py-2 text-xs">
              <option>Tất cả thương hiệu</option>
              <option>Apple</option>
              <option>Samsung</option></select
            ><select class="select rounded-lg px-3 py-2 text-xs">
              <option>Tất cả trạng thái</option>
              <option>Đang bán</option>
              <option>Ẩn</option>
            </select>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Sản phẩm</th>
                  <th class="p-4 text-left">SKU / Biến thể</th>
                  <th class="p-4 text-left">Giá nhập</th>
                  <th class="p-4 text-left">Giá bán</th>
                  <th class="p-4 text-left">Tồn</th>
                  <th class="p-4 text-left">Trạng thái</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4">
                    <b>iPhone 16 Pro Max</b>
                    <div class="text-[10px] text-[#7e879c]">Apple • 2026</div>
                  </td>
                  <td class="p-4 text-xs">
                    IP16PM-BLK-256<br /><span class="text-[#7e879c]"
                      >Đen / 256GB</span
                    >
                  </td>
                  <td class="p-4">27.800.000đ</td>
                  <td class="p-4 font-bold">34.990.000đ</td>
                  <td class="p-4">28</td>
                  <td class="p-4"><span class="badge green">Đang bán</span></td>
                  <td class="p-4">✎</td>
                </tr>
                <tr>
                  <td class="p-4">
                    <b>Galaxy S25 Ultra</b>
                    <div class="text-[10px] text-[#7e879c]">Samsung • 2026</div>
                  </td>
                  <td class="p-4 text-xs">
                    S25U-TIT-512<br /><span class="text-[#7e879c]"
                      >Titan / 512GB</span
                    >
                  </td>
                  <td class="p-4">24.500.000đ</td>
                  <td class="p-4 font-bold">31.490.000đ</td>
                  <td class="p-4">3</td>
                  <td class="p-4"><span class="badge yellow">Sắp hết</span></td>
                  <td class="p-4">✎</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ACCESSORIES -->
        <section id="accessories" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quản lý Sản phẩm Phụ kiện</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Phân loại phụ kiện, giá bán và thông số kỹ thuật
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('accessory')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Thêm phụ kiện
            </button>
          </div>
          <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">cable</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tổng phụ kiện</div>
              <b class="text-2xl mt-1 block">184</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">sell</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Đang bán</div>
              <b class="text-2xl mt-1 block">162</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">warning</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Sắp hết hàng</div>
              <b class="text-2xl mt-1 block">12</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Tên phụ kiện</th>
                  <th class="p-4 text-left">Phân loại</th>
                  <th class="p-4 text-left">Giá bán</th>
                  <th class="p-4 text-left">Tồn kho</th>
                  <th class="p-4 text-left">Trạng thái</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">Cáp USB-C to USB-C 240W</td>
                  <td class="p-4">Cáp sạc</td>
                  <td class="p-4 font-bold">490.000đ</td>
                  <td class="p-4">4</td>
                  <td class="p-4"><span class="badge yellow">Sắp hết</span></td>
                  <td class="p-4">✎</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Ốp MagSafe iPhone 16 Pro</td>
                  <td class="p-4">Ốp lưng</td>
                  <td class="p-4 font-bold">890.000đ</td>
                  <td class="p-4">86</td>
                  <td class="p-4"><span class="badge green">Đang bán</span></td>
                  <td class="p-4">✎</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- INVENTORY -->
        <section id="inventory" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quản lý Kho &amp; Tồn kho</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Nhập hàng, tăng tồn kho biến thể và cập nhật giá vốn
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('stock')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >move_to_inbox</span
              >
              Nhập hàng
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
              <div class="text-xs text-[#7e879c] mt-3">Tổng SKU</div>
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
              <div class="text-xs text-[#7e879c] mt-3">Tổng tồn</div>
              <b class="text-2xl mt-1 block">8.426</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">warning</span></span
                ><span class="badge red">Cảnh báo</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Dưới 5 chiếc</div>
              <b class="text-2xl mt-1 block">42</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">payments</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Giá trị kho</div>
              <b class="text-2xl mt-1 block">86,4 Tỷ</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-5 flex justify-between">
              <b>Lịch sử nhập kho</b
              ><span class="text-xs text-[#7e879c]">Tháng 10/2026</span>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Mã phiếu</th>
                  <th class="p-4 text-left">Nhà cung cấp</th>
                  <th class="p-4 text-left">Số SKU</th>
                  <th class="p-4 text-left">Tổng tiền</th>
                  <th class="p-4 text-left">Ngày nhập</th>
                  <th class="p-4 text-left">Người nhập</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-bold">PN-00182</td>
                  <td class="p-4">Apple Distribution VN</td>
                  <td class="p-4">24</td>
                  <td class="p-4 font-bold">1.284.000.000đ</td>
                  <td class="p-4">02/10/2026</td>
                  <td class="p-4">Admin</td>
                </tr>
                <tr>
                  <td class="p-4 font-bold">PN-00181</td>
                  <td class="p-4">Samsung Electronics</td>
                  <td class="p-4">18</td>
                  <td class="p-4 font-bold">642.500.000đ</td>
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
              <h1 class="text-2xl font-extrabold">Quản lý Đơn hàng</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Theo dõi tiến trình từ xác nhận đến hoàn thành
              </p>
            </div>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="card rounded-xl p-4">
              <span class="badge blue">Tất cả</span
              ><b class="text-xl block mt-3">156</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge yellow">Chờ xác nhận</span
              ><b class="text-xl block mt-3">38</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge purple">Đóng gói</span
              ><b class="text-xl block mt-3">27</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge blue">Đang giao</span
              ><b class="text-xl block mt-3">42</b>
            </div>
            <div class="card rounded-xl p-4">
              <span class="badge green">Hoàn thành</span
              ><b class="text-xl block mt-3">49</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex flex-wrap gap-2">
              <input
                class="input rounded-lg px-3 py-2 text-xs flex-1"
                placeholder="Tìm mã đơn / khách hàng..."
              /><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Tất cả trạng thái</option>
                <option>Chờ xác nhận</option>
                <option>Đóng gói</option>
                <option>Đang giao</option>
                <option>Hoàn thành</option>
                <option>Đã hủy</option></select
              ><input type="date" class="input rounded-lg px-3 py-2 text-xs" />
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Mã đơn</th>
                  <th class="p-4 text-left">Khách hàng</th>
                  <th class="p-4 text-left">Sản phẩm</th>
                  <th class="p-4 text-left">Tổng tiền</th>
                  <th class="p-4 text-left">Trạng thái</th>
                  <th class="p-4">Cập nhật</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-bold">#MX10482</td>
                  <td class="p-4">Nguyễn Minh</td>
                  <td class="p-4">iPhone 16 Pro Max</td>
                  <td class="p-4 font-bold">34.990.000đ</td>
                  <td class="p-4">
                    <span class="badge green">Hoàn thành</span>
                  </td>
                  <td class="p-4">
                    <button
                      class="btn-dark px-2 py-1 rounded text-[10px]"
                      onclick="openModal('order')"
                    >
                      Tiến trình
                    </button>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-bold">#MX10481</td>
                  <td class="p-4">Trần Hoàng</td>
                  <td class="p-4">Galaxy S25 Ultra</td>
                  <td class="p-4 font-bold">31.490.000đ</td>
                  <td class="p-4"><span class="badge blue">Đang giao</span></td>
                  <td class="p-4">
                    <button
                      class="btn-dark px-2 py-1 rounded text-[10px]"
                      onclick="openModal('order')"
                    >
                      Tiến trình
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
              <h1 class="text-2xl font-extrabold">Quản lý Mã giảm giá</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Coupon theo phần trăm hoặc số tiền cố định
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('coupon')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Tạo mã giảm giá
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
              <div class="text-xs text-[#7e879c] mt-3">Mã đang hoạt động</div>
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
                Lượt sử dụng tháng này
              </div>
              <b class="text-2xl mt-1 block">2.841</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">payments</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Giá trị đã giảm</div>
              <b class="text-2xl mt-1 block">184,5 Tr</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Mã</th>
                  <th class="p-4 text-left">Loại giảm</th>
                  <th class="p-4 text-left">Đơn tối thiểu</th>
                  <th class="p-4 text-left">Đã dùng</th>
                  <th class="p-4 text-left">Thời hạn</th>
                  <th class="p-4">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-extrabold text-[#adc6ff]">MOBILE10</td>
                  <td class="p-4">Giảm 10%</td>
                  <td class="p-4">500.000đ</td>
                  <td class="p-4">382 / 1.000</td>
                  <td class="p-4">01/10 → 31/10</td>
                  <td class="p-4">
                    <span class="badge green">Đang chạy</span>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-extrabold text-[#adc6ff]">NEW500</td>
                  <td class="p-4">Giảm 500.000đ</td>
                  <td class="p-4">10.000.000đ</td>
                  <td class="p-4">128 / 500</td>
                  <td class="p-4">01/10 → 15/10</td>
                  <td class="p-4">
                    <span class="badge green">Đang chạy</span>
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
                Quản lý Banner &amp; Khuyến mãi
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Slider trang chủ và Flash Sale theo khung giờ
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('banner')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >add</span
              >
              Thêm banner
            </button>
          </div>
          <div class="grid lg:grid-cols-2 gap-5">
            <div class="card rounded-xl p-5">
              <div class="flex justify-between mb-4">
                <b>Banner trang chủ</b
                ><span class="badge green">3 đang hiển thị</span>
              </div>
              <div class="space-y-3">
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-blue-600 to-cyan-500 flex items-center justify-between"
                >
                  <div>
                    <b>iPhone 16 Series</b>
                    <div class="text-[10px] opacity-80 mt-1">Đang hiển thị</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('Đã thay đổi trạng thái banner')"
                  >
                    Bật/Tắt
                  </button>
                </div>
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-purple-600 to-blue-500 flex items-center justify-between"
                >
                  <div>
                    <b>Samsung Galaxy S25</b>
                    <div class="text-[10px] opacity-80 mt-1">Đang hiển thị</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('Đã thay đổi trạng thái banner')"
                  >
                    Bật/Tắt
                  </button>
                </div>
                <div
                  class="rounded-xl p-4 bg-gradient-to-r from-orange-600 to-red-500 flex items-center justify-between"
                >
                  <div>
                    <b>Flash Sale 10.10</b>
                    <div class="text-[10px] opacity-80 mt-1">Tắt</div>
                  </div>
                  <button
                    class="bg-black/20 px-3 py-1.5 rounded text-[10px]"
                    onclick="toast('Đã thay đổi trạng thái banner')"
                  >
                    Bật/Tắt
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
                  + Tạo Flash Sale
                </button>
              </div>
              <div class="bg-[#0f172b] rounded-xl p-4">
                <div class="flex justify-between">
                  <span class="badge red">LIVE</span><b>20:00 - 22:00</b>
                </div>
                <h3 class="font-bold mt-3">Flash Sale Cuối Ngày</h3>
                <p class="text-xs text-[#7e879c] mt-1">
                  24 sản phẩm • Giảm đến 35%
                </p>
                <div class="mt-4 h-2 bg-[#30394d] rounded-full">
                  <div class="h-2 w-[72%] bg-[#4ae176] rounded-full"></div>
                </div>
                <div class="text-[10px] text-[#7e879c] mt-2">
                  72% sản phẩm đã bán
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- REVIEWS -->
        <section id="reviews" class="page">
          <div class="page-header flex justify-between items-end mb-6 gap-3">
            <div>
              <h1 class="text-2xl font-extrabold">Quản lý Đánh giá</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Duyệt hoặc ẩn đánh giá vi phạm tiêu chuẩn cộng đồng
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
              <div class="text-xs text-[#7e879c] mt-3">Tổng đánh giá</div>
              <b class="text-2xl mt-1 block">12.486</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">star</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Điểm trung bình</div>
              <b class="text-2xl mt-1 block">4.7</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">pending</span></span
                ><span class="badge red">Cảnh báo</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Chờ duyệt</div>
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
              <div class="text-xs text-[#7e879c] mt-3">Đã ẩn</div>
              <b class="text-2xl mt-1 block">82</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex gap-2">
              <select class="select rounded-lg px-3 py-2 text-xs">
                <option>Tất cả</option>
                <option>Chờ duyệt</option>
                <option>Đã duyệt</option>
                <option>Đã ẩn</option></select
              ><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Tất cả số sao</option>
                <option>1 sao</option>
                <option>2 sao</option>
                <option>3 sao</option>
              </select>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Khách hàng</th>
                  <th class="p-4 text-left">Sản phẩm</th>
                  <th class="p-4 text-left">Đánh giá</th>
                  <th class="p-4 text-left">Nội dung</th>
                  <th class="p-4 text-left">Trạng thái</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">Nguyễn An</td>
                  <td class="p-4">iPhone 16 Pro</td>
                  <td class="p-4 text-yellow-400">★★★★★</td>
                  <td class="p-4 max-w-xs">Sản phẩm tốt, giao hàng nhanh...</td>
                  <td class="p-4"><span class="badge green">Đã duyệt</span></td>
                  <td class="p-4">
                    <button
                      class="btn-danger px-2 py-1 rounded text-[10px]"
                      onclick="toast('Đã ẩn đánh giá')"
                    >
                      Ẩn
                    </button>
                  </td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Trần B</td>
                  <td class="p-4">Cáp Type-C</td>
                  <td class="p-4 text-yellow-400">★★</td>
                  <td class="p-4 max-w-xs">Nội dung cần kiểm duyệt...</td>
                  <td class="p-4">
                    <span class="badge yellow">Chờ duyệt</span>
                  </td>
                  <td class="p-4">
                    <button
                      class="btn-green px-2 py-1 rounded text-[10px]"
                      onclick="toast('Đã duyệt đánh giá')"
                    >
                      Duyệt
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
                Quản lý Người dùng &amp; Phân quyền
              </h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Khách hàng, nhân viên và vai trò hệ thống
              </p>
            </div>
            <button
              class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold"
              onclick="openModal('user')"
            >
              <span class="material-symbols-outlined align-middle text-base"
                >person_add</span
              >
              Thêm nhân viên
            </button>
          </div>
          <div class="grid md:grid-cols-3 gap-4 mb-5">
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">group</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tổng khách hàng</div>
              <b class="text-2xl mt-1 block">24.680</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">badge</span></span
                >
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Nhân viên</div>
              <b class="text-2xl mt-1 block">26</b>
            </div>
            <div class="card stat rounded-xl p-4">
              <div class="flex justify-between">
                <span class="text-[#adc6ff]"
                  ><span class="material-symbols-outlined">lock</span></span
                ><span class="badge red">Cảnh báo</span>
              </div>
              <div class="text-xs text-[#7e879c] mt-3">Tài khoản bị khóa</div>
              <b class="text-2xl mt-1 block">14</b>
            </div>
          </div>
          <div class="card rounded-xl overflow-hidden">
            <div class="p-4 flex gap-2">
              <input
                class="input rounded-lg px-3 py-2 text-xs flex-1"
                placeholder="Tìm tên / email / số điện thoại..."
              /><select class="select rounded-lg px-3 py-2 text-xs">
                <option>Tất cả vai trò</option>
                <option>Admin</option>
                <option>Kế toán/Kho</option>
                <option>Nhân viên bán hàng</option>
                <option>Khách hàng</option>
              </select>
            </div>
            <table class="table w-full">
              <thead>
                <tr>
                  <th class="p-4 text-left">Người dùng</th>
                  <th class="p-4 text-left">Email</th>
                  <th class="p-4 text-left">Vai trò</th>
                  <th class="p-4 text-left">Trạng thái</th>
                  <th class="p-4 text-left">Ngày tạo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="p-4 font-semibold">Admin MobileX</td>
                  <td class="p-4">admin@mobilex.vn</td>
                  <td class="p-4"><span class="badge purple">Admin</span></td>
                  <td class="p-4">
                    <span class="badge green">Hoạt động</span>
                  </td>
                  <td class="p-4">01/01/2026</td>
                  <td class="p-4">✎</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Nguyễn Kho</td>
                  <td class="p-4">kho@mobilex.vn</td>
                  <td class="p-4">
                    <span class="badge blue">Kế toán/Kho</span>
                  </td>
                  <td class="p-4">
                    <span class="badge green">Hoạt động</span>
                  </td>
                  <td class="p-4">12/02/2026</td>
                  <td class="p-4">✎</td>
                </tr>
                <tr>
                  <td class="p-4 font-semibold">Trần Sales</td>
                  <td class="p-4">sales@mobilex.vn</td>
                  <td class="p-4"><span class="badge blue">Bán hàng</span></td>
                  <td class="p-4">
                    <span class="badge green">Hoạt động</span>
                  </td>
                  <td class="p-4">04/03/2026</td>
                  <td class="p-4">✎</td>
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
            <h3 id="modalTitle" class="font-bold text-lg">Thêm mới</h3>
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
              Hủy</button
            ><button class="btn-blue px-4 py-2 rounded-lg text-xs font-bold">
              Lưu thông tin
            </button>
          </div>
        </form>
      </div>
    </div>
    <div id="toast" class="toast">Đã lưu thay đổi</div>

    <script>
      const configs = {
        category: {
          title: "Thêm danh mục",
          sub: "Tạo danh mục cha hoặc danh mục con",
          fields: [
            ["Tên danh mục", "text", "Điện thoại"],
            ["Danh mục cha", "select", "Không có|Điện thoại|Phụ kiện|Laptop"],
            ["Slug", "text", "dien-thoai"],
            ["Trạng thái", "select", "Hiển thị|Ẩn"],
          ],
        },
        brand: {
          title: "Thêm hãng sản xuất",
          sub: "Tạo hãng mới cho sản phẩm",
          fields: [
            ["Tên hãng", "text", "Apple"],
            ["Mô tả", "text", "Nhà sản xuất thiết bị"],
            ["Trạng thái", "select", "Hoạt động|Tạm ngưng"],
          ],
        },
        attribute: {
          title: "Thêm thuộc tính",
          sub: "Ví dụ: Màu sắc, Dung lượng, Công suất sạc",
          fields: [
            ["Tên thuộc tính", "text", "Màu sắc"],
            ["Kiểu giá trị", "select", "Danh sách lựa chọn|Văn bản|Số"],
            ["Các giá trị", "text", "Đen, Trắng, Titan, Xanh"],
            ["Áp dụng cho", "select", "Điện thoại|Phụ kiện|Tất cả"],
          ],
        },
        phone: {
          title: "Thêm điện thoại",
          sub: "Tạo sản phẩm và các biến thể SKU",
          fields: [
            ["Tên sản phẩm", "text", "iPhone 16 Pro Max"],
            ["Thương hiệu", "select", "Apple|Samsung|Xiaomi|OPPO"],
            ["Danh mục", "select", "iPhone|Samsung|Android"],
            ["Mã sản phẩm", "text", "IP16PM"],
            ["Giá nhập mặc định", "text", "27800000"],
            ["Giá bán mặc định", "text", "34990000"],
            ["Màu sắc", "text", "Đen, Titan, Trắng"],
            ["Dung lượng", "text", "256GB, 512GB, 1TB"],
            ["Thông số kỹ thuật", "text", "Chip, màn hình, camera, pin..."],
          ],
        },
        accessory: {
          title: "Thêm phụ kiện",
          sub: "Cấu hình thông tin phụ kiện",
          fields: [
            ["Tên phụ kiện", "text", "Cáp USB-C 240W"],
            [
              "Loại phụ kiện",
              "select",
              "Cáp sạc|Ốp lưng|Củ sạc|Tai nghe|Pin dự phòng",
            ],
            ["Giá nhập", "text", "250000"],
            ["Giá bán", "text", "490000"],
            ["SKU", "text", "CAP240W"],
            ["Thông số ngắn", "text", "USB-C to USB-C, 240W"],
          ],
        },
        stock: {
          title: "Phiếu nhập hàng",
          sub: "Tăng tồn kho và cập nhật giá vốn",
          fields: [
            ["Mã phiếu", "text", "PN-00183"],
            ["Nhà cung cấp", "text", "Nhà phân phối MobileX"],
            ["Sản phẩm/SKU", "text", "IP16PM-BLK-256"],
            ["Số lượng nhập", "text", "50"],
            ["Giá vốn/chiếc", "text", "27800000"],
            ["Ghi chú", "text", "Nhập lô mới"],
          ],
        },
        order: {
          title: "Cập nhật tiến trình đơn",
          sub: "Xác nhận → Đóng gói → Bàn giao → Hoàn thành",
          fields: [
            ["Mã đơn", "text", "#MX10482"],
            [
              "Trạng thái",
              "select",
              "Xác nhận|Đóng gói|Bàn giao vận chuyển|Hoàn thành|Đã hủy",
            ],
            ["Mã vận đơn", "text", "GHN123456789"],
            ["Ghi chú", "text", "Đã bàn giao đơn vị vận chuyển"],
          ],
        },
        coupon: {
          title: "Tạo mã giảm giá",
          sub: "Cấu hình điều kiện và thời hạn sử dụng",
          fields: [
            ["Mã coupon", "text", "MOBILE10"],
            ["Loại giảm", "select", "Phần trăm (%)|Giảm tiền cố định"],
            ["Giá trị giảm", "text", "10"],
            ["Đơn tối thiểu", "text", "500000"],
            ["Giới hạn lượt dùng", "text", "1000"],
            ["Ngày bắt đầu", "date", ""],
            ["Ngày kết thúc", "date", ""],
          ],
        },
        banner: {
          title: "Thêm banner",
          sub: "Cấu hình slider hiển thị trên trang chủ",
          fields: [
            ["Tiêu đề banner", "text", "iPhone 16 Series"],
            ["Hình ảnh", "text", "banner-iphone.jpg"],
            ["Link đích", "text", "/san-pham/iphone-16"],
            ["Thứ tự", "text", "1"],
            ["Trạng thái", "select", "Hiển thị|Ẩn"],
          ],
        },
        flash: {
          title: "Tạo Flash Sale",
          sub: "Thiết lập khung giờ và danh sách sản phẩm",
          fields: [
            ["Tên chương trình", "text", "Flash Sale Cuối Ngày"],
            ["Khung giờ", "text", "20:00 - 22:00"],
            ["Mức giảm tối đa", "text", "35"],
            ["Sản phẩm", "text", "iPhone 16, Galaxy S25, AirPods"],
            ["Ngày chạy", "date", ""],
            ["Trạng thái", "select", "Bật|Tắt"],
          ],
        },
        user: {
          title: "Thêm nhân viên",
          sub: "Tạo tài khoản và phân quyền",
          fields: [
            ["Họ tên", "text", "Nguyễn Văn A"],
            ["Email", "email", "staff@mobilex.vn"],
            ["Số điện thoại", "text", "0900000000"],
            ["Vai trò", "select", "Admin|Kế toán/Kho|Nhân viên bán hàng"],
            ["Mật khẩu", "password", ""],
            ["Trạng thái", "select", "Hoạt động|Khóa"],
          ],
        },
      };
      // Initialize the admin layout safely after the DOM is ready.
      window.addEventListener("DOMContentLoaded", () => {
        go("dashboard");
        window.scrollTo(0, 0);
      });

      document
        .querySelectorAll(".nav")
        .forEach((n) => n.addEventListener("click", () => go(n.dataset.page)));
      function go(id) {
        document
          .querySelectorAll(".page")
          .forEach((p) => p.classList.remove("active"));
        document.getElementById(id).classList.add("active");
        document
          .querySelectorAll(".nav")
          .forEach((n) => n.classList.toggle("active", n.dataset.page === id));
        document.getElementById("pageTitle").textContent =
          document.querySelector(`[data-page="${id}"] .txt`)?.textContent ||
          "Dashboard & Thống kê";
        window.scrollTo(0, 0);
      }
      function toggleSide() {
        let s = document.querySelector(".sidebar");
        s.style.display = s.style.display === "none" ? "flex" : "none";
      }
      function openModal(type) {
        let c = configs[type];
        document.getElementById("modalTitle").textContent = c.title;
        document.getElementById("modalSub").textContent = c.sub;
        document.getElementById("modalFields").innerHTML = c.fields
          .map(
            (f, i) =>
              `<label class="${f[0] === "Thông số kỹ thuật" || f[0] === "Thông số ngắn" || f[0] === "Ghi chú" || f[0] === "Sản phẩm" ? "md:col-span-2" : ""}"><span class="block text-[11px] font-semibold text-[#aeb5c9] mb-1.5">${f[0]}</span>${
                f[1] === "select"
                  ? `<select class="select w-full rounded-lg px-3 py-2.5 text-xs">${f[2]
                      .split("|")
                      .map((x) => `<option>${x}</option>`)
                      .join("")}</select>`
                  : `<input type="${f[1]}" value="${f[2]}" class="input w-full rounded-lg px-3 py-2.5 text-xs">`
              }</label>`,
          )
          .join("");
        document.getElementById("modalBg").classList.add("show");
      }
      function closeModal() {
        document.getElementById("modalBg").classList.remove("show");
      }
      function submitModal(e) {
        e.preventDefault();
        closeModal();
        toast("Đã lưu thông tin thành công");
      }
      function toast(msg) {
        let t = document.getElementById("toast");
        t.textContent = msg;
        t.classList.add("show");
        setTimeout(() => t.classList.remove("show"), 2200);
      }
    </script>
  </body>
</html>
