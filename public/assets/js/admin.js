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
          "Tổng quan & thống kê";
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
