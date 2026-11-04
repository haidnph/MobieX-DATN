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
