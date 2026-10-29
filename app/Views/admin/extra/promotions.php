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
