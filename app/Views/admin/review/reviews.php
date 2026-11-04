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
