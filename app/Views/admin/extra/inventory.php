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
