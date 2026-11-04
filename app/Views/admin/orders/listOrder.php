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
