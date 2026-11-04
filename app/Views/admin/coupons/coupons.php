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
