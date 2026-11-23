# HƯỚNG DẪN CẤU HÌNH & TÍCH HỢP CLOUDINARY CHO MOBIEX (v6.0)

Tài liệu này hướng dẫn chi tiết cách cấu hình và sử dụng dịch vụ lưu trữ đám mây **Cloudinary** trên hệ thống thương mại điện tử MobieX, đồng thời làm rõ cơ chế lưu trữ lai (Hybrid Storage) đảm bảo tính toàn vẹn và tương thích ngược 100% với toàn bộ dữ liệu hiện có.

---

## 1. TỔNG QUAN KIẾN TRÚC LƯU TRỮ (HYBRID MEDIA ARCHITECTURE)

Hệ thống quản lý tài nguyên (Media Storage) của MobieX được thiết kế theo mô hình Unified Service Driver:

```
                        ┌───────────────────────────────┐
                        │      Client / Admin UI        │
                        └───────────────┬───────────────┘
                                        │ (Upload Request)
                                        ▼
                        ┌───────────────────────────────┐
                        │     MediaStorageService       │
                        │    (App\Services\Media...)    │
                        └───────┬───────────────┬───────┘
                                │               │
          [Driver: local]       │               │ [Driver: cloudinary]
                                ▼               ▼
                    ┌──────────────────┐ ┌──────────────────┐
                    │  Storage (Local) │ │ Cloudinary Cloud │
                    │  /public/storage │ │  res.cloudinary  │
                    └──────────────────┘ └────────┬─────────┘
                                                  │ (Failure / Fallback)
                                                  ▼
                                         [Fallback to Local]
```

### Điểm nổi bật:
1. **Driver mặc định là `local`**: Khi cài mới hoặc không thiết lập thông tin Cloudinary, MobieX tự động lưu trên ổ cứng cục bộ (`storage/app/public` symlinked qua `public/storage`), không phụ thuộc vào internet hay dịch vụ ngoài.
2. **Tương thích ngược tuyệt đối (Zero Breaking Changes)**:
   - Mọi URL ảnh cũ dạng `/storage/...` hoặc đường dẫn tương đối vẫn được hiển thị bình thường.
   - Khi kích hoạt Cloudinary, các ảnh mới sẽ nhận CDN URL bảo mật (`https://res.cloudinary.com/...`).
   - Accessor `image_url` và `mobile_image_url` của `Banner` và `ProductImage` tự động nhận diện và trả về URL chính xác cho cả 2 loại lưu trữ.
3. **Cơ chế Safe Fallback**: Nếu `MEDIA_STORAGE_DRIVER=cloudinary` nhưng thiếu cấu hình hoặc kết nối Cloudinary bị lỗi/hết băng thông, hệ thống sẽ tự động chuyển sang lưu tạm thời trên đĩa local và ghi log cảnh báo thay vì ngắt quãng trải nghiệm của người dùng.

---

## 2. CÁCH LẤY CREDENTIALS TỪ CLOUDINARY

1. Truy cập [https://cloudinary.com](https://cloudinary.com) và đăng ký tài khoản miễn phí.
2. Đăng nhập vào trang quản trị Cloudinary Console / Dashboard.
3. Trong mục **Product Environment Credentials**, sao chép 3 thông số:
   - **Cloud Name** (Tên cloud của bạn, ví dụ: `mobiex-cloud`)
   - **API Key** (Chuỗi số, ví dụ: `123456789012345`)
   - **API Secret** (Chuỗi ký tự bảo mật, ví dụ: `abcdefghijklmnopqrstuvwxyz_0123`)
4. Hoặc bạn có thể sao chép chuỗi **API Environment variable (CLOUDINARY_URL)** có dạng:
   `cloudinary://<api_key>:<api_secret>@<cloud_name>`

---

## 3. CẤU HÌNH BIẾN MÔI TRƯỜNG (.env)

Mở file `backend/.env` trên máy chủ hoặc môi trường phát triển và thêm/cập nhật các biến sau:

### Lựa chọn A — Sử dụng thông số riêng lẻ (Khuyến nghị):
```env
# Media Storage Driver (local hoặc cloudinary)
MEDIA_STORAGE_DRIVER=cloudinary
MEDIA_FALLBACK_TO_LOCAL=true

# Cloudinary Credentials
CLOUDINARY_CLOUD_NAME=ten_cloud_cua_ban
CLOUDINARY_API_KEY=api_key_cua_ban
CLOUDINARY_API_SECRET=api_secret_cua_ban
CLOUDINARY_FOLDER=mobiex
CLOUDINARY_SECURE=true
```

### Lựa chọn B — Sử dụng chuỗi CLOUDINARY_URL:
```env
MEDIA_STORAGE_DRIVER=cloudinary
MEDIA_FALLBACK_TO_LOCAL=true
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
CLOUDINARY_FOLDER=mobiex
CLOUDINARY_SECURE=true
```

### Quay lại Local Storage bất cứ lúc nào:
Chỉ cần đổi:
```env
MEDIA_STORAGE_DRIVER=local
```
Toàn bộ hệ thống sẽ lập tức quay về lưu trữ cục bộ mà không cần sửa code.

> [!CAUTION]
> **Tuyệt đối không đưa API Key và API Secret của Cloudinary vào Frontend (Vite) hay commit vào Git.** Thông tin Cloudinary chỉ được đặt trong file `backend/.env` bảo mật trên server.

---

## 4. CÔNG CỤ DI CHUYỂN TÀI NGUYÊN AN TOÀN (SAFE MIGRATION TOOL)

MobieX cung cấp sẵn lệnh Artisan chuyên dụng để quét và di chuyển các hình ảnh đang lưu cục bộ lên Cloudinary mà **không làm mất file gốc**:

### Chạy ở chế độ mô phỏng (Dry-run - Mặc định an toàn):
```bash
cd backend
php artisan media:migrate-cloudinary
```
Lệnh sẽ liệt kê toàn bộ banner và ảnh sản phẩm trên đĩa cục bộ, tính toán dung lượng và trạng thái mà không thay đổi bất kỳ dữ liệu nào.

### Chạy chuyển đổi thực tế (Live Migration):
```bash
cd backend
php artisan media:migrate-cloudinary --run
```
Quy trình thực hiện:
1. Đọc file từ thư mục cục bộ `storage/app/public/...`.
2. Upload lên Cloudinary vào thư mục `mobiex/banners` hoặc `mobiex/products`.
3. Cập nhật bản ghi trong cơ sở dữ liệu (`storage_provider = 'cloudinary'`, `public_id`, `image_path`).
4. **Bảo lưu file cục bộ** làm bản sao dự phòng (Backup preservation).

### Các tùy chọn nâng cao:
- Chỉ di chuyển ảnh sản phẩm:
  ```bash
  php artisan media:migrate-cloudinary --type=products --run
  ```
- Chỉ di chuyển banner:
  ```bash
  php artisan media:migrate-cloudinary --type=banners --run
  ```
- Giới hạn số lượng (thử nghiệm trước 5 ảnh):
  ```bash
  php artisan media:migrate-cloudinary --limit=5 --run
  ```

---

## 5. TỐI ƯU HÓA HÌNH ẢNH TRÊN CLOUDINARY (CDN & WEBP)

Hệ thống Media Storage của MobieX tự động áp dụng các tối ưu hóa hàng đầu của Cloudinary:
- `fetch_format: auto` (`f_auto`): Tự động chuyển đổi định dạng ảnh tối ưu nhất cho trình duyệt của khách hàng (WebP, AVIF, JPEG XL).
- `quality: auto` (`q_auto`): Tự động cân bằng giữa độ sắc nét và kích thước file tải xuống, giúp trang chủ và trang chi tiết sản phẩm load nhanh gấp 3-5 lần.
- CDN toàn cầu: Tự động phân phối qua mạng lưới CDN đa vùng của Cloudinary.

---

## 6. KIỂM TRA VÀ NGHIỆM THU

Sau khi cấu hình:
1. Đăng nhập bằng tài khoản admin local được cấu hình qua `RBAC_ADMIN_*`.
2. Vào **Quản lý Banner** (`/admin/banners`) -> Nhấn **Tạo banner mới** -> Chọn một file ảnh và tải lên.
3. Kiểm tra kết quả trả về trong DevTools Network:
   - Nếu Driver là `cloudinary`: Trả về URL Cloudinary bảo mật (`https://res.cloudinary.com/...`).
   - Nếu Driver là `local`: Trả về URL `/storage/banners/...`.
4. Xem trước trên Storefront Client (`/`) để đảm bảo banner hiển thị sắc nét và mượt mà.
