<!-- CATEGORIES -->
<section class="page" id="categories">
<div class="page-header flex justify-between items-end mb-6 gap-3">
<div>
<h1 class="text-2xl font-extrabold">
                Quản lý Danh mục &amp; Thương hiệu
              </h1>
<p class="text-xs text-[#7e879c] mt-1">
                Danh mục đa cấp và hãng sản xuất
              </p>
</div>
<button class="btn-blue px-4 py-2.5 rounded-lg text-xs font-bold" onclick="openModal('category')">
<span class="material-symbols-outlined align-middle text-base">add</span>
              Thêm danh mục
            </button>
</div>
<div class="grid lg:grid-cols-2 gap-5">
<div class="card rounded-xl overflow-hidden">
<div class="p-5 flex justify-between">
<b>Danh mục sản phẩm</b><span class="badge blue">8 danh mục</span>
</div>
<table class="table w-full">
<thead>
<tr>
<th class="p-3 text-left">Danh mục</th>
<th class="p-3 text-left">Danh mục con</th>
<th class="p-3 text-left">Trạng thái</th>
<th></th>
</tr>
</thead>
<tbody>
<tr>
<td class="p-3 font-semibold">Điện thoại</td>
<td class="p-3 text-[#9da6bb]">iPhone, Samsung, Xiaomi</td>
<td class="p-3">
<span class="badge green">Hiển thị</span>
</td>
<td class="p-3">⋮</td>
</tr>
<tr>
<td class="p-3 font-semibold">Phụ kiện</td>
<td class="p-3 text-[#9da6bb]">Cáp sạc, Ốp lưng, Sạc</td>
<td class="p-3">
<span class="badge green">Hiển thị</span>
</td>
<td class="p-3">⋮</td>
</tr>
<tr>
<td class="p-3 font-semibold">Laptop</td>
<td class="p-3 text-[#9da6bb]">MacBook, ASUS</td>
<td class="p-3">
<span class="badge green">Hiển thị</span>
</td>
<td class="p-3">⋮</td>
</tr>
</tbody>
</table>
</div>
<div class="card rounded-xl overflow-hidden">
<div class="p-5 flex justify-between">
<b>Hãng sản xuất</b><button class="btn-blue rounded-lg px-3 py-2 text-xs" onclick="openModal('brand')">
                  + Thêm hãng
                </button>
</div>
<table class="table w-full">
<thead>
<tr>
<th class="p-3 text-left">Hãng</th>
<th class="p-3 text-left">Sản phẩm</th>
<th class="p-3 text-left">Trạng thái</th>
</tr>
</thead>
<tbody>
<tr>
<td class="p-3 font-semibold">Apple</td>
<td class="p-3">84</td>
<td class="p-3">
<span class="badge green">Hoạt động</span>
</td>
</tr>
<tr>
<td class="p-3 font-semibold">Samsung</td>
<td class="p-3">61</td>
<td class="p-3">
<span class="badge green">Hoạt động</span>
</td>
</tr>
<tr>
<td class="p-3 font-semibold">Xiaomi</td>
<td class="p-3">38</td>
<td class="p-3">
<span class="badge green">Hoạt động</span>
</td>
</tr>
</tbody>
</table>
</div>
</div>
</section>
