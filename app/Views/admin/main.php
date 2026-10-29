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

        <?php include __DIR__ . '/stats/dashboard.php'; ?>
        <?php include __DIR__ . '/category/listCategory.php'; ?>
        <?php include __DIR__ . '/brand/listBrand.php'; ?>
        <?php include __DIR__ . '/color/listColor.php'; ?>
        <?php include __DIR__ . '/size/listSize.php'; ?>
        <?php include __DIR__ . '/product/listProduct.php'; ?>
        <?php include __DIR__ . '/orders/listOrder.php'; ?>

        <?php
        // Các phần chưa có thư mục riêng trong cây thư mục hiện tại:
        // inventory, coupons, promotions, reviews, users.
        ?>

        <?php include __DIR__ . '/inventory/inventory.php'; ?>
        <?php include __DIR__ . '/coupons/coupons.php'; ?>
        <?php include __DIR__ . '/promotions/promotions.php'; ?>
        <?php include __DIR__ . '/review/reviews.php'; ?>
        <?php include __DIR__ . '/users/users.php'; ?>

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
