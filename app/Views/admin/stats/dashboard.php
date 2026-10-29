<!-- DASHBOARD -->
        <section id="dashboard" class="page active">
          <div class="flex justify-between items-end mb-6">
            <div>
              <div
                class="text-[#4ae176] text-[10px] uppercase tracking-widest font-bold"
              >
                Live system
              </div>
              <h1 class="text-3xl font-extrabold mt-1">Tổng quan hệ thống</h1>
              <p class="text-xs text-[#7e879c] mt-1">
                Theo dõi doanh thu, đơn hàng và tồn kho.
              </p>
            </div>
            <select class="select rounded-lg px-3 py-2 text-xs">
              <option>Tháng này</option>
              <option>7 ngày</option>
              <option>Năm nay</option>
            </select>
          </div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 grid4 mb-5"></div>
          <div class="grid lg:grid-cols-3 gap-5">
            <div class="card rounded-xl p-5 lg:col-span-2">
              <div class="flex justify-between mb-5">
                <div>
                  <b class="text-lg">Doanh thu</b>
                  <p class="text-xs text-[#7e879c]">Doanh thu theo tháng</p>
                </div>
                <span class="badge green">+12.8%</span>
              </div>
              <div
                class="h-64 flex items-end gap-3 px-2 border-b border-[#30394d]"
              >
                <div class="h-[45%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[54%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[50%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[63%] flex-1 bg-[#4d8eff66] rounded-t"></div>
                <div class="h-[69%] flex-1 bg-[#4d8eff88] rounded-t"></div>
                <div class="h-[75%] flex-1 bg-[#4d8effaa] rounded-t"></div>
                <div
                  class="h-[88%] flex-1 bg-[#4d8eff] rounded-t shadow-[0_0_20px_#4d8eff55]"
                ></div>
              </div>
              <div class="flex justify-between text-[9px] text-[#737c90] mt-2">
                <span>T4</span><span>T5</span><span>T6</span><span>T7</span
                ><span>T8</span><span>T9</span><span>T10</span>
              </div>
            </div>
            <div class="card rounded-xl p-5">
              <b class="text-lg">Cảnh báo tồn kho</b>
              <p class="text-xs text-[#7e879c] mt-1 mb-5">
                Sản phẩm dưới 5 chiếc
              </p>
              <div class="space-y-4">
                <div class="flex justify-between items-center">
                  <span class="text-xs">iPhone 16 Pro 256GB</span
                  ><span class="badge red">2 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">Galaxy S25 Ultra 512GB</span
                  ><span class="badge red">3 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">Cáp Type-C 240W</span
                  ><span class="badge yellow">4 chiếc</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-xs">AirPods Pro 3</span
                  ><span class="badge red">1 chiếc</span>
                </div>
              </div>
              <button
                class="btn-dark w-full mt-6 py-2 rounded-lg text-xs"
                onclick="go('inventory')"
              >
                Xem kho hàng
              </button>
            </div>
          </div>
        </section>
