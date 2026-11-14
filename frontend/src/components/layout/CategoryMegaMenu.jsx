// src/components/layout/CategoryMegaMenu.jsx
// Modern E-commerce Mega Menu — Compact, Clean, Premium
// Two-column Master-Detail layout with root categories on left, subcategories grid on right.

import { useState, useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useLanguage } from '../../contexts/LanguageContext'

export function buildCategoryTree(flatCategories) {
  if (!Array.isArray(flatCategories)) return []
  const map = {}
  const roots = []

  flatCategories.forEach((cat) => {
    map[cat.id] = { ...cat, children: [] }
  })

  flatCategories.forEach((cat) => {
    if (cat.parent_id && map[cat.parent_id]) {
      map[cat.parent_id].children.push(map[cat.id])
    } else {
      roots.push(map[cat.id])
    }
  })

  return roots
}

export function getCategoryIcon(slug, name) {
  const s = (slug || '').toLowerCase()
  const n = (name || '').toLowerCase()
  if (s.includes('dien-thoai') || s.includes('phone') || n.includes('điện thoại')) return 'smartphone'
  if (s.includes('phu-kien') || s.includes('accessory') || n.includes('phụ kiện')) return 'headphones'
  if (s.includes('may-tinh-bang') || s.includes('tablet') || s.includes('ipad') || n.includes('máy tính')) return 'tablet_mac'
  if (s.includes('smartwatch') || s.includes('dong-ho') || n.includes('đồng hồ')) return 'watch'
  return 'category'
}

export const CATEGORY_TRANSLATIONS = {
  'dien-thoai': {
    vi: 'Điện thoại',
    en: 'Smartphones',
    'zh-CN': '手机',
  },
  'phu-kien': {
    vi: 'Phụ kiện',
    en: 'Accessories',
    'zh-CN': '配件',
  },
  'may-tinh-bang': {
    vi: 'Máy tính bảng',
    en: 'Tablets',
    'zh-CN': '平板电脑',
  },
  'smartwatch': {
    vi: 'Đồng hồ thông minh',
    en: 'Smartwatches',
    'zh-CN': '智能手表',
  },
  'iphone': {
    vi: 'iPhone',
    en: 'iPhone',
    'zh-CN': 'iPhone',
  },
  'android': {
    vi: 'Android',
    en: 'Android',
    'zh-CN': 'Android',
  },
  'samsung-galaxy': {
    vi: 'Samsung Galaxy',
    en: 'Samsung Galaxy',
    'zh-CN': '三星 Galaxy',
  },
  'xiaomi-mi': {
    vi: 'Xiaomi Mi',
    en: 'Xiaomi Mi',
    'zh-CN': '小米系列',
  },
  'sac': {
    vi: 'Củ sạc',
    en: 'Chargers',
    'zh-CN': '充电器',
  },
  'cap-sac': {
    vi: 'Cáp sạc',
    en: 'Charging Cables',
    'zh-CN': '充电线',
  },
  'op-lung': {
    vi: 'Ốp lưng',
    en: 'Cases & Covers',
    'zh-CN': '手机保护壳',
  },
  'tai-nghe': {
    vi: 'Tai nghe',
    en: 'Headphones',
    'zh-CN': '耳机音频',
  },
  'loa-bluetooth': {
    vi: 'Loa Bluetooth',
    en: 'Bluetooth Speakers',
    'zh-CN': '蓝牙音箱',
  },
  'phu-kien-bao-ve': {
    vi: 'Bảo vệ màn hình & thân máy',
    en: 'Screen & Body Protection',
    'zh-CN': '屏幕机身保护',
  },
  'kinh-cuong-luc-nano': {
    vi: 'Kính cường lực Nano 9D',
    en: 'Privacy Tempered Glass 9D',
    'zh-CN': '9D 防窥纳米钢化膜',
  },
}

export const CATEGORY_UI = {
  vi: {
    categoryBtn: 'Danh mục',
    categoryTitle: 'Danh mục sản phẩm',
    allProducts: 'Tất cả sản phẩm',
    viewAllPrefix: 'Xem tất cả',
    genuineNote: '100% Hàng chính hãng',
    deliveryNote: 'Giao nhanh toàn quốc',
    loading: 'Đang tải danh mục...',
    explorePrefix: 'Khám phá các sản phẩm',
    exploreSuffix: 'chính hãng tại MobileX.',
    viewBtnPrefix: 'Xem sản phẩm',
  },
  en: {
    categoryBtn: 'Categories',
    categoryTitle: 'Product Categories',
    allProducts: 'All Products',
    viewAllPrefix: 'View all',
    genuineNote: '100% Genuine Products',
    deliveryNote: 'Fast Nationwide Delivery',
    loading: 'Loading categories...',
    explorePrefix: 'Explore genuine',
    exploreSuffix: 'at MobileX.',
    viewBtnPrefix: 'View',
  },
  'zh-CN': {
    categoryBtn: '商品分类',
    categoryTitle: '商品全部分类',
    allProducts: '全部商品',
    viewAllPrefix: '查看全部',
    genuineNote: '100% 正品保障',
    deliveryNote: '全国极速配送',
    loading: '正在加载分类...',
    explorePrefix: '前往 MobileX 选购正品',
    exploreSuffix: '。',
    viewBtnPrefix: '查看',
  },
}

export function getLocalizedCategoryName(cat, lang = 'vi') {
  if (!cat) return ''
  const slug = (cat.slug || '').toLowerCase().trim()
  if (CATEGORY_TRANSLATIONS[slug]?.[lang]) {
    return CATEGORY_TRANSLATIONS[slug][lang]
  }
  const name = (cat.name || '').toLowerCase().trim()
  for (const [key, trans] of Object.entries(CATEGORY_TRANSLATIONS)) {
    if (slug.includes(key) || name.includes(key)) {
      if (trans[lang]) return trans[lang]
    }
  }
  return cat.name
}


export default function CategoryMegaMenu({
  categories = [],
  isOpen = false,
  onToggle,
  onClose,
  categoryMenuRef,
}) {
  const { lang } = useLanguage()
  const ui = CATEGORY_UI[lang] || CATEGORY_UI.vi
  const categoryTree = useMemo(() => buildCategoryTree(categories), [categories])

  const [activeParentId, setActiveParentId] = useState(null)

  // Determine current active root category
  const selectedParent = useMemo(() => {
    if (!categoryTree.length) return null
    return (
      categoryTree.find((c) => c.id === activeParentId) || categoryTree[0]
    )
  }, [categoryTree, activeParentId])

  return (
    <div ref={categoryMenuRef} className="relative shrink-0">
      {/* ── Trigger Button ── */}
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={isOpen}
        aria-haspopup="true"
        aria-label={ui.categoryTitle}
        className={`flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs xl:text-sm font-semibold transition-all duration-200 border whitespace-nowrap ${
          isOpen
            ? 'bg-primary/10 text-primary border-primary/30 ring-2 ring-primary/20 shadow-xs'
            : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container border-transparent'
        }`}
      >
        <span className="material-symbols-outlined text-lg">grid_view</span>
        <span>{ui.categoryBtn}</span>
        <span
          className={`material-symbols-outlined text-base transition-transform duration-200 ${
            isOpen ? 'rotate-180 text-primary' : 'text-outline'
          }`}
        >
          expand_more
        </span>
      </button>

      {/* ── Mega Menu Panel ── */}
      {isOpen && (
        <div
          role="region"
          aria-label="Menu danh mục sản phẩm"
          className="absolute top-full left-0 mt-2 w-[580px] lg:w-[620px] bg-surface-container-high/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden z-50 animate-in fade-in zoom-in-95 duration-150"
        >
          {/* Header Bar */}
          <div className="flex items-center justify-between px-4 py-2.5 border-b border-outline-variant/20 bg-surface-container/60">
            <span className="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-2">
              <span className="material-symbols-outlined text-sm text-primary">
                category
              </span>
              {ui.categoryTitle}
            </span>
            <Link
              to="/products"
              onClick={onClose}
              className="text-xs font-semibold text-primary hover:underline flex items-center gap-0.5 group"
            >
              <span>{ui.allProducts}</span>
              <span className="material-symbols-outlined text-sm group-hover:translate-x-0.5 transition-transform">
                chevron_right
              </span>
            </Link>
          </div>

          {/* Master - Detail Body */}
          <div className="flex h-[280px]">
            {/* Left Column: Root Categories (Master List) */}
            <div className="w-48 lg:w-52 shrink-0 border-r border-outline-variant/15 p-2 space-y-1 bg-surface-container/30 overflow-y-auto">
              {categoryTree.map((cat) => {
                const isSelected = selectedParent?.id === cat.id
                return (
                  <button
                    key={cat.id}
                    type="button"
                    onMouseEnter={() => setActiveParentId(cat.id)}
                    onClick={() => setActiveParentId(cat.id)}
                    className={`w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150 text-left ${
                      isSelected
                        ? 'bg-primary/10 text-primary font-bold shadow-xs'
                        : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/60'
                    }`}
                  >
                    <span className="flex items-center gap-2.5 truncate">
                      <span
                        className={`material-symbols-outlined text-base ${
                          isSelected ? 'text-primary' : 'text-outline'
                        }`}
                      >
                        {getCategoryIcon(cat.slug, cat.name)}
                      </span>
                      <span className="truncate">{getLocalizedCategoryName(cat, lang)}</span>
                    </span>
                    <span
                      className={`material-symbols-outlined text-xs transition-transform ${
                        isSelected ? 'text-primary translate-x-0.5' : 'text-outline/60'
                      }`}
                    >
                      chevron_right
                    </span>
                  </button>
                )
              })}
            </div>

            {/* Right Column: Subcategories & Details Grid */}
            <div className="flex-1 p-3.5 flex flex-col justify-between overflow-y-auto bg-surface-container-high/40">
              {selectedParent ? (
                <div>
                  {/* Selected Category Header & View All Link */}
                  <div className="flex items-center justify-between pb-2 mb-2.5 border-b border-outline-variant/15">
                    <span className="text-xs font-bold text-on-surface flex items-center gap-1.5">
                      <span className="w-1.5 h-1.5 rounded-full bg-primary" />
                      {getLocalizedCategoryName(selectedParent, lang)}
                    </span>
                    <Link
                      to={`/products?category_id=${selectedParent.id}`}
                      onClick={onClose}
                      className="text-[11px] font-semibold text-primary hover:underline flex items-center gap-0.5"
                    >
                      <span>{ui.viewAllPrefix} {getLocalizedCategoryName(selectedParent, lang)}</span>
                      <span className="material-symbols-outlined text-xs">
                        arrow_forward
                      </span>
                    </Link>
                  </div>

                  {/* Subcategories Grid (2-Column Layout) */}
                  {selectedParent.children && selectedParent.children.length > 0 ? (
                    <div className="grid grid-cols-2 gap-2">
                      {selectedParent.children.map((child) => (
                        <div key={child.id} className="space-y-1">
                          <Link
                            to={`/products?category_id=${child.id}`}
                            onClick={onClose}
                            className="px-2.5 py-1.5 rounded-xl bg-surface-container/70 hover:bg-primary/10 border border-outline-variant/15 hover:border-primary/30 transition-all flex items-center justify-between group"
                          >
                            <span className="text-xs font-medium text-on-surface group-hover:text-primary transition-colors truncate">
                              {getLocalizedCategoryName(child, lang)}
                            </span>
                            <span className="material-symbols-outlined text-[13px] text-outline group-hover:text-primary group-hover:translate-x-0.5 transition-all shrink-0">
                              arrow_forward
                            </span>
                          </Link>

                          {/* Subchildren pills if available (e.g. Kính cường lực) */}
                          {child.children && child.children.length > 0 && (
                            <div className="pl-2 flex flex-wrap gap-1">
                              {child.children.map((grand) => (
                                <Link
                                  key={grand.id}
                                  to={`/products?category_id=${grand.id}`}
                                  onClick={onClose}
                                  className="text-[10px] text-outline hover:text-primary transition-colors py-0.5"
                                >
                                  • {getLocalizedCategoryName(grand, lang)}
                                </Link>
                              ))}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : (
                    /* Clean Fallback State when category has no subcategories */
                    <div className="py-6 flex flex-col items-center justify-center text-center">
                      <span className="material-symbols-outlined text-3xl text-outline mb-1.5">
                        {getCategoryIcon(selectedParent.slug, selectedParent.name)}
                      </span>
                      <p className="text-xs text-on-surface-variant mb-3">
                        {ui.explorePrefix} {getLocalizedCategoryName(selectedParent, lang)} {ui.exploreSuffix}
                      </p>
                      <Link
                        to={`/products?category_id=${selectedParent.id}`}
                        onClick={onClose}
                        className="px-3.5 py-1.5 rounded-xl bg-primary-container text-on-primary-container text-xs font-bold hover:bg-primary hover:text-on-primary transition-all shadow-xs"
                      >
                        {ui.viewBtnPrefix} {getLocalizedCategoryName(selectedParent, lang)} →
                      </Link>
                    </div>
                  )}
                </div>
              ) : (
                <div className="py-8 text-center text-xs text-on-surface-variant">
                  {ui.loading}
                </div>
              )}

              {/* Bottom Quick Feature Note */}
              <div className="pt-2 mt-2 border-t border-outline-variant/10 flex items-center justify-between text-[10px] text-outline">
                <span className="flex items-center gap-1">
                  <span className="material-symbols-outlined text-xs text-primary">verified</span>
                  {ui.genuineNote}
                </span>
                <span className="flex items-center gap-1">
                  <span className="material-symbols-outlined text-xs text-primary">local_shipping</span>
                  {ui.deliveryNote}
                </span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
