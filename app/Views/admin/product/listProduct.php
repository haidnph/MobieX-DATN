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
