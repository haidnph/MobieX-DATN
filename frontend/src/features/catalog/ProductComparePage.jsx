import { useState, useEffect, useMemo, useCallback } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useCompare, FALLBACK_PHONE_SVG, getProductThumbnail } from '../../contexts/CompareContext'
import { useCart } from '../../contexts/CartContext'
import { useLanguage } from '../../contexts/LanguageContext'
import { useToast } from '../../contexts/ToastContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Button from '../../components/common/Button'

export const COMPARE_UI = {
  vi: {
    badge: 'Công cụ đối chiếu cấu hình',
    title: 'So sánh sản phẩm',
    subtitle: 'Đặt các dòng máy cạnh nhau để dễ dàng đánh giá cấu hình, màn hình, hiệu năng, camera và giá bán.',
    onlyDiff: 'Chỉ xem điểm khác biệt',
    diffCount: 'điểm khác biệt',
    noDiffsFound: 'Không có điểm khác biệt thực tế nào giữa các thông số phần cứng được chọn.',
    updatingSpecs: 'Đang cập nhật danh mục thông số kỹ thuật.',
    addDevice: 'Thêm máy khác',
    clearAll: 'Xóa tất cả',
    shareLink: 'Chia sẻ liên kết',
    linkCopied: 'Đã sao chép liên kết so sánh vào bộ nhớ tạm!',
    printView: 'In / PDF',
    emptyTitle: 'Chưa có sản phẩm nào để so sánh',
    emptySubtitle: 'Chọn tối đa 4 dòng điện thoại đặt cạnh nhau để so sánh chi tiết về thông số kỹ thuật, giá bán và cấu hình.',
    exploreCatalog: 'Khám phá danh sách sản phẩm',
    bestPriceBadge: 'Giá tốt nhất',
    compareInfoCol: 'Thông số kỹ thuật',
    addToCart: 'Thêm vào giỏ',
    inStock: 'Còn hàng',
    outOfStock: 'Tạm hết hàng',
    deviceCountSuffix: 'máy',
    standardVariant: 'Phiên bản tiêu chuẩn',
    differenceBadge: 'Khác biệt',
    notSpecified: 'Chưa cập nhật',
    searchModalTitle: 'Chọn máy thêm vào so sánh',
    searchPlaceholder: 'Nhập tên điện thoại (iPhone 16, Galaxy S24, Xiaomi...)',
    searching: 'Đang tìm kiếm...',
    noProductsFound: 'Không tìm thấy sản phẩm phù hợp',
    typeKeyword: 'Gõ từ khóa để tìm sản phẩm',
    alreadyAdded: 'Đã thêm',
    selectToCompare: 'Chọn so sánh',
    recommendedPhones: 'Gợi ý các dòng máy flagship nổi bật:',
    sections: {
      overview: '1. Tổng quan & Giá bán',
      display: '2. Màn hình & Hiển thị',
      performance: '3. Hiệu năng & Bộ nhớ',
      camera: '4. Hệ thống Camera',
      battery: '5. Pin & Nguồn sạc',
      durability: '6. Thiết kế & Độ bền',
      connectivity: '7. Kết nối & Cổng giao tiếp',
      others: '8. Thông số kỹ thuật khác',
    },
    fields: {
      brand: 'Thương hiệu',
      variants: 'Các phiên bản đang kinh doanh',
      stock: 'Tình trạng kho hàng',
      price: 'Giá niêm yết',
    },
  },
  en: {
    badge: 'Device Comparison Tool',
    title: 'Product Comparison',
    subtitle: 'Place devices side by side to compare specifications, display, performance, cameras, and pricing.',
    onlyDiff: 'Show differences only',
    diffCount: 'differences',
    noDiffsFound: 'No actual differences found between the selected hardware specifications.',
    updatingSpecs: 'Updating specification list.',
    addDevice: 'Add Device',
    clearAll: 'Clear All',
    shareLink: 'Share Link',
    linkCopied: 'Comparison link copied to clipboard!',
    printView: 'Print / PDF',
    emptyTitle: 'No products selected for comparison',
    emptySubtitle: 'Compare up to 4 smartphones side by side to evaluate specs, cameras, battery life, and prices.',
    exploreCatalog: 'Explore Products',
    bestPriceBadge: 'Best Value',
    compareInfoCol: 'Specifications',
    addToCart: 'Add to Cart',
    inStock: 'In Stock',
    outOfStock: 'Out of Stock',
    deviceCountSuffix: 'devices',
    standardVariant: 'Standard Edition',
    differenceBadge: 'Difference',
    notSpecified: 'Not specified',
    searchModalTitle: 'Add Device to Comparison',
    searchPlaceholder: 'Search phone name (iPhone 16, Galaxy S24, Xiaomi...)',
    searching: 'Searching...',
    noProductsFound: 'No matching products found',
    typeKeyword: 'Type a keyword to find devices',
    alreadyAdded: 'Added',
    selectToCompare: 'Select',
    recommendedPhones: 'Recommended flagship devices:',
    sections: {
      overview: '1. Overview & Pricing',
      display: '2. Display & Screen',
      performance: '3. Performance & Memory',
      camera: '4. Camera System',
      battery: '5. Battery & Charging',
      durability: '6. Design & Durability',
      connectivity: '7. Connectivity & Ports',
      others: '8. Other Specifications',
    },
    fields: {
      brand: 'Brand',
      variants: 'Commercial Product Variants',
      stock: 'Stock Status',
      price: 'List Price',
    },
  },
  'zh-CN': {
    badge: '机型参数对比工具',
    title: '商品参数对比',
    subtitle: '并排对比多款手机的屏幕、处理器性能、影像系统、电池续航及价格配置。',
    onlyDiff: '仅看差异项',
    diffCount: '处差异',
    noDiffsFound: '所选硬件参数之间暂无实际差异。',
    updatingSpecs: '正在更新参数列表。',
    addDevice: '添加对比机型',
    clearAll: '清空全部',
    shareLink: '分享对比链接',
    linkCopied: '对比链接已复制到剪贴板！',
    printView: '打印 / PDF',
    emptyTitle: '暂无对比商品',
    emptySubtitle: '最多可同时选择4款手机进行详细硬件参数、性能与价格对比。',
    exploreCatalog: '去挑选商品',
    bestPriceBadge: '最优价格',
    compareInfoCol: '对比项目',
    addToCart: '加入购物车',
    inStock: '现货充足',
    outOfStock: '暂时缺货',
    deviceCountSuffix: '款',
    standardVariant: '标准版',
    differenceBadge: '不同',
    notSpecified: '未提供',
    searchModalTitle: '选择要加入对比的机型',
    searchPlaceholder: '输入手机名称 (iPhone 16, Galaxy S24, 小米...)',
    searching: '正在搜索...',
    noProductsFound: '未找到相关机型',
    typeKeyword: '输入关键词搜索商品',
    alreadyAdded: '已加入',
    selectToCompare: '选择对比',
    recommendedPhones: '热门旗舰机型推荐：',
    sections: {
      overview: '1. 概览与价格',
      display: '2. 屏幕与显示',
      performance: '3. 性能与存储',
      camera: '4. 影像与摄像',
      battery: '5. 电池与充电',
      durability: '6. 外观与防护',
      connectivity: '7. 连接与接口',
      others: '8. 其它参数',
    },
    fields: {
      brand: '品牌',
      variants: '实际在售规格版本',
      stock: '库存状态',
      price: '参考售价',
    },
  },
}

/**
 * Normalizes Vietnamese/English strings by removing accents, spaces, and punctuation
 * to ensure 100% accurate key-matching regardless of naming variations.
 */
export function normalizeSpecKey(str) {
  if (!str || typeof str !== 'string') return ''
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/đ/g, 'd')
    .replace(/[^a-z0-9]/g, '')
}

/**
 * Aliases mapping for common specification keys.
 * Ensures that whether a product spec is stored under `screen_size`, `display_size`, or `kich-thuoc-man-hinh`,
 * they all align seamlessly into the identical comparison row.
 */
export const SPEC_ALIASES = {
  display_size: [
    'display_size',
    'screen_size',
    'kich_thuoc_man_hinh',
    'kich-thuoc-man-hinh',
    'man_hinh',
    'man-hinh',
    'screen',
  ],
  display_resolution: [
    'display_resolution',
    'screen_resolution',
    'do_phan_giai_man_hinh',
    'do-phan-giai-man-hinh',
    'do_phan_giai',
    'do-phan-giai',
    'resolution',
  ],
  screen_technology: [
    'screen_technology',
    'screen_type',
    'display_type',
    'cong_nghe_man_hinh',
    'cong-nghe-man-hinh',
    'panel_type',
    'loai_man_hinh',
  ],
  refresh_rate: [
    'refresh_rate',
    'tan_so_quet',
    'tan-so-quet',
    'scan_rate',
    'hz',
  ],
  chipset: [
    'chipset',
    'cpu',
    'processor',
    'vi_xu_ly',
    'vi-xu-ly',
    'chip',
    'soc',
  ],
  ram: [
    'ram',
    'memory',
    'bo_nho_ram',
    'bo-nho-ram',
    'dung_luong_ram',
    'dung-luong-ram',
  ],
  internal_storage: [
    'internal_storage',
    'storage',
    'rom',
    'bo_nho_trong',
    'bo-nho-trong',
    'dung_luong_bo_nho',
    'dung-luong-bo-nho',
    'capacity',
  ],
  rear_camera: [
    'rear_camera',
    'main_camera',
    'camera_sau',
    'camera-sau',
    'back_camera',
    'camera_chinh',
  ],
  front_camera: [
    'front_camera',
    'selfie_camera',
    'camera_truoc',
    'camera-truoc',
    'camera_selfie',
    'selfie',
  ],
  battery_capacity: [
    'battery_capacity',
    'battery',
    'dung_luong_pin',
    'dung-luong-pin',
    'pin',
  ],
  charging_power: [
    'charging_power',
    'charging_speed',
    'fast_charging',
    'cong_suat_sac',
    'cong-suat-sac',
    'sac_nhanh',
    'sac-nhanh',
  ],
  operating_system: [
    'operating_system',
    'os',
    'he_dieu_hanh',
    'he-dieu-hanh',
  ],
  water_resistance: [
    'water_resistance',
    'waterproof',
    'khang_nuoc_bui',
    'khang-nuoc-bui',
    'chong_nuoc',
    'ip_rating',
  ],
  weight: [
    'weight',
    'trong_luong',
    'trong-luong',
    'khoi_luong',
    'khoi-luong',
  ],
  connector_type: [
    'connector_type',
    'port',
    'chuan_ket_noi',
    'chuan-ket-noi',
    'cong_ket_noi',
    'cong-ket-noi',
    'charging_port',
  ],
  material: [
    'material',
    'chat_lieu',
    'chat-lieu',
    'khung_vien',
    'khung-vien',
  ],
}

export const CANONICAL_NAMES = {
  display_size: ['kichthuocmanhinh', 'manhinh', 'screensize', 'displaysize'],
  display_resolution: ['dophangiaimanhinh', 'dophangiai', 'screenresolution', 'displayresolution', 'resolution'],
  screen_technology: ['congnghemanhinh', 'loaimanhinh', 'screentechnology', 'screentype'],
  refresh_rate: ['tansoquet', 'refreshrate', 'tanso'],
  chipset: ['vixuly', 'chipset', 'cpu', 'processor', 'chip'],
  ram: ['ram', 'bonhoram', 'dungluongram', 'memory'],
  internal_storage: ['bonhotrong', 'dungluongbonho', 'storage', 'rom', 'internalstorage', 'capacity'],
  rear_camera: ['camerasau', 'camerachinh', 'rearcamera', 'maincamera'],
  front_camera: ['cameratruoc', 'cameraselfie', 'frontcamera', 'selfiecamera'],
  battery_capacity: ['dungluongpin', 'pin', 'batterycapacity', 'battery'],
  charging_power: ['congsuatsac', 'sacnhanh', 'chargingpower', 'chargingspeed', 'fastcharging'],
  operating_system: ['hedieuhanh', 'operatingsystem', 'os'],
  water_resistance: ['khangnuocbui', 'chongnuoc', 'waterresistance', 'waterproof'],
  weight: ['trongluong', 'khoiluong', 'weight'],
  connector_type: ['chuanketnoi', 'congketnoi', 'connectortype', 'port'],
  material: ['chatlieu', 'khungvien', 'material'],
}

export const SPEC_GROUPS = [
  {
    id: 'display',
    titleKey: 'display',
    codes: ['display_size', 'display_resolution', 'screen_technology', 'refresh_rate'],
  },
  {
    id: 'performance',
    titleKey: 'performance',
    codes: ['chipset', 'ram', 'internal_storage'],
  },
  {
    id: 'camera',
    titleKey: 'camera',
    codes: ['rear_camera', 'front_camera'],
  },
  {
    id: 'battery',
    titleKey: 'battery',
    codes: ['battery_capacity', 'charging_power'],
  },
  {
    id: 'durability',
    titleKey: 'durability',
    codes: ['operating_system', 'water_resistance', 'weight', 'material'],
  },
  {
    id: 'connectivity',
    titleKey: 'connectivity',
    codes: ['connector_type'],
  },
]

/**
 * Extracts the real combinations of RAM and Storage from variants.
 * Crucial rule: Never create Cartesian cross-products of disjoint RAM and ROM.
 * Each configuration reflects an actual, physical commercial variant that exists in the database.
 */
export function getProductConfigurations(product) {
  if (!product || !Array.isArray(product.variants) || product.variants.length === 0) return []
  const configs = []
  const seen = new Set()

  product.variants.forEach((v) => {
    const sig = (v.option_signature || v.name || v.sku || '').toLowerCase()

    // 1. Extract RAM
    const ramMatch = sig.match(/ram:([0-9]+[gt]b)/i) || sig.match(/([0-9]+)\s*gb\s*ram/i)
    // 2. Extract Storage / ROM
    const romMatch =
      sig.match(/storage:([0-9]+[gt]b)/i) ||
      sig.match(/capacity:([0-9]+[gt]b)/i) ||
      sig.match(/([0-9]+)\s*(gb|tb)/i)

    let label = ''
    if (ramMatch && romMatch) {
      label = `${ramMatch[1].toUpperCase()} RAM / ${romMatch[1].toUpperCase()} ROM`
    } else if (romMatch) {
      label = `${romMatch[1].toUpperCase()} ROM`
    } else if (ramMatch) {
      label = `${ramMatch[1].toUpperCase()} RAM`
    } else if (v.name && v.name !== 'Phiên bản tiêu chuẩn') {
      label = v.name
    }

    if (label && !seen.has(label)) {
      seen.add(label)
      configs.push(label)
    }
  })

  return configs
}

/**
 * High-performance image component with:
 * - Instant placeholder shimmer to eliminate visual lag and CLS
 * - Smooth opacity transition on load
 * - Bulletproof SVG fallback on 404/network error with zero infinite loops
 */
export function CompareImage({ src, alt, className = '' }) {
  const [loaded, setLoaded] = useState(false)
  const [error, setError] = useState(false)

  const finalSrc = error || !src || src === '/placeholder-phone.png' ? FALLBACK_PHONE_SVG : src

  return (
    <div className={`relative overflow-hidden flex items-center justify-center bg-surface-container-high ${className}`}>
      {/* Shimmer skeleton while image is loading */}
      {!loaded && !error && (
        <div className="absolute inset-0 bg-gradient-to-r from-surface-container via-surface-container-highest to-surface-container animate-pulse flex items-center justify-center">
          <span className="material-symbols-outlined text-outline/30 text-2xl animate-pulse">
            smartphone
          </span>
        </div>
      )}

      <img
        src={finalSrc}
        alt={alt || 'Sản phẩm'}
        loading="eager"
        decoding="async"
        fetchPriority="high"
        onLoad={() => setLoaded(true)}
        onError={() => {
          setError(true)
          setLoaded(true)
        }}
        className={`max-h-full max-w-full object-contain p-1 transition-opacity duration-300 ${
          loaded ? 'opacity-100' : 'opacity-0'
        }`}
      />
    </div>
  )
}

export default function ProductComparePage() {
  const { compareItems, removeFromCompare, clearCompare, addToCompare, replaceCompareItems, maxItems } =
    useCompare()
  const { addToCart } = useCart()
  const { showToast } = useToast()
  const { lang } = useLanguage()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const t = COMPARE_UI[lang] || COMPARE_UI.vi

  const [loading, setLoading] = useState(false)
  const [productsData, setProductsData] = useState([])
  const [definitions, setDefinitions] = useState([])
  const [onlyDifferences, setOnlyDifferences] = useState(false)
  const [addingToCartId, setAddingToCartId] = useState(null)

  // Search to add more products
  const [showSearchModal, setShowSearchModal] = useState(false)
  const [searchKeyword, setSearchKeyword] = useState('')
  const [searchResults, setSearchResults] = useState([])
  const [searching, setSearching] = useState(false)

  // 1. Sync URL query params with compare items
  useEffect(() => {
    const idsParam = searchParams.get('ids')
    if (idsParam) {
      const urlIds = idsParam
        .split(',')
        .map((id) => Number(id.trim()))
        .filter((id) => !isNaN(id) && id > 0)

      const currentIds = compareItems.map((item) => Number(item.id))
      const isDifferent =
        urlIds.length !== currentIds.length || urlIds.some((id) => !currentIds.includes(id))

      if (isDifferent && urlIds.length > 0) {
        // Fetch products to initialize comparison from URL
        axiosClient
          .get(EP.PRODUCT_COMPARE, { params: { ids: urlIds.slice(0, maxItems).join(',') } })
          .then((res) => {
            const fetched = res.data?.data?.products || []
            if (fetched.length > 0) {
              replaceCompareItems(fetched)
            }
          })
          .catch((err) => console.error('Failed to sync compare items from URL', err))
      }
    }
  }, [searchParams, maxItems, replaceCompareItems])

  // 2. Keep URL query param ?ids= in sync with compareItems
  useEffect(() => {
    if (compareItems.length > 0) {
      const currentParam = searchParams.get('ids')
      const newParam = compareItems.map((item) => item.id).join(',')
      if (currentParam !== newParam) {
        setSearchParams({ ids: newParam }, { replace: true })
      }
    } else if (searchParams.has('ids')) {
      searchParams.delete('ids')
      setSearchParams(searchParams, { replace: true })
    }
  }, [compareItems, searchParams, setSearchParams])

  // 3. Image Preloader: Cache images into browser memory as soon as items are present
  useEffect(() => {
    compareItems.forEach((item) => {
      const url = item.image_url
      if (url && typeof url === 'string' && url.startsWith('http')) {
        const img = new Image()
        img.src = url
      }
    })
  }, [compareItems])

  // 4. Fetch full specification matrix from backend
  useEffect(() => {
    if (compareItems.length === 0) {
      setProductsData([])
      setDefinitions([])
      return
    }

    const ids = compareItems.map((item) => item.id).join(',')
    setLoading(true)

    axiosClient
      .get(EP.PRODUCT_COMPARE, { params: { ids } })
      .then((res) => {
        const data = res.data?.data || {}
        setProductsData(data.products || [])
        setDefinitions(data.comparable_definitions || [])
      })
      .catch((err) => {
        console.error('Failed to load compare data', err)
        showToast('Không thể tải dữ liệu so sánh sản phẩm', 'error')
      })
      .finally(() => {
        setLoading(false)
      })
  }, [compareItems, showToast])

  // 5. Search products in modal with debounce
  useEffect(() => {
    if (!showSearchModal) return

    if (!searchKeyword.trim()) {
      // Suggest top flagship models (iPhone 16 Pro Max, Samsung S24 Ultra, Xiaomi 14 Pro, iPhone 15)
      setSearching(true)
      axiosClient
        .get(EP.PRODUCT_COMPARE, { params: { ids: '1,2,3,4' } })
        .then((res) => {
          const list = res.data?.data?.products || []
          setSearchResults(list)
        })
        .catch(() => {
          axiosClient
            .get(EP.PRODUCTS, { params: { per_page: 5, sort: 'popular' } })
            .then((r) => setSearchResults(r.data?.data?.data || []))
            .catch(() => setSearchResults([]))
        })
        .finally(() => setSearching(false))
      return
    }

    const timer = setTimeout(() => {
      setSearching(true)
      axiosClient
        .get(EP.PRODUCTS, { params: { keyword: searchKeyword.trim(), per_page: 6 } })
        .then((res) => {
          const list = res.data?.data?.data || res.data?.data || []
          setSearchResults(list)
        })
        .catch((err) => console.error(err))
        .finally(() => setSearching(false))
    }, 280)

    return () => clearTimeout(timer)
  }, [searchKeyword, showSearchModal])

  // Quick add to cart
  const handleQuickAddToCart = async (product) => {
    try {
      setAddingToCartId(product.id)
      const targetVariant = product.variants?.[0]
      if (!targetVariant) {
        showToast('Sản phẩm chưa có phiên bản kinh doanh', 'error')
        return
      }
      await addToCart(targetVariant.id, 1)
      showToast(`Đã thêm "${product.name}" vào giỏ hàng`, 'success')
    } catch (err) {
      showToast(err?.response?.data?.message || 'Không thể thêm vào giỏ hàng', 'error')
    } finally {
      setAddingToCartId(null)
    }
  }

  // Copy share link
  const handleShare = () => {
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(window.location.href)
      showToast(t.linkCopied, 'success')
    } else {
      showToast(window.location.href, 'info')
    }
  }

  // Print view
  const handlePrint = () => {
    window.print()
  }

  // Optimistic merged products: use productsData if available, otherwise fallback to compareItems
  const displayProducts = useMemo(() => {
    return compareItems.map((item) => {
      const fullData = productsData.find((p) => Number(p.id) === Number(item.id))
      return fullData || item
    })
  }, [compareItems, productsData])

  /**
   * Complete, robust specification extractor supporting:
   * 1. Direct code match (e.g. display_size)
   * 2. Alias codes match (e.g. screen_size, kich-thuoc-man-hinh)
   * 3. Vietnamese name normalization (e.g. "Kích thước màn hình" -> "kichthuocmanhinh")
   * 4. Variant fallback for RAM and Internal Storage (extracted from option_signature)
   * 5. Clean decimal formatting (6.9000 -> 6.9, 4685.0000 -> 4685)
   */
  const getProductSpec = useCallback(
    (product, defCode) => {
      if (!product) {
        return { raw: null, display: t.notSpecified, isValid: false, isMissing: true }
      }

      // Check product.specifications if available
      if (Array.isArray(product.specifications) && product.specifications.length > 0) {
        const aliases = SPEC_ALIASES[defCode] || [defCode]
        const canonicalNormNames = CANONICAL_NAMES[defCode] || [normalizeSpecKey(defCode)]

        const matchedSpec = product.specifications.find((s) => {
          const specCode = s.definition?.code || s.code
          if (specCode && aliases.includes(specCode)) return true

          const normDefName = normalizeSpecKey(s.definition?.name)
          if (normDefName && canonicalNormNames.includes(normDefName)) return true

          const normSpecName = normalizeSpecKey(s.specification_name)
          if (normSpecName && canonicalNormNames.includes(normSpecName)) return true

          return false
        })

        if (matchedSpec) {
          // A. Numeric value with unit
          if (matchedSpec.numeric_value !== null && matchedSpec.numeric_value !== undefined) {
            const num = Number(matchedSpec.numeric_value)
            if (!isNaN(num)) {
              const unit = matchedSpec.definition?.unit ? ` ${matchedSpec.definition.unit}` : ''
              return {
                raw: num,
                display: `${num}${unit}`,
                isValid: true,
                isMissing: false,
              }
            }
          }

          // B. Boolean value
          if (matchedSpec.value_boolean !== null && matchedSpec.value_boolean !== undefined) {
            const bVal = Boolean(matchedSpec.value_boolean)
            const bDisplay = bVal
              ? lang === 'en'
                ? 'Yes'
                : lang === 'zh-CN'
                  ? '支持'
                  : 'Có'
              : lang === 'en'
                ? 'No'
                : lang === 'zh-CN'
                  ? '不支持'
                  : 'Không'
            return {
              raw: bVal,
              display: bDisplay,
              isValid: true,
              isMissing: false,
            }
          }

          // C. Text value
          const textVal = (
            matchedSpec.value_text ||
            matchedSpec.value ||
            matchedSpec.specification_value ||
            ''
          ).trim()

          if (textVal && textVal !== '—') {
            return {
              raw: textVal,
              display: textVal,
              isValid: true,
              isMissing: false,
            }
          }
        }
      }

      // Fallback: If requested spec is RAM or Storage, check product variants
      if (Array.isArray(product.variants) && product.variants.length > 0) {
        if (defCode === 'internal_storage' || defCode === 'storage') {
          const storages = new Set()
          product.variants.forEach((v) => {
            const sig = (v.option_signature || v.name || v.sku || '').toLowerCase()
            const match =
              sig.match(/storage:([0-9]+[gt]b)/i) ||
              sig.match(/capacity:([0-9]+[gt]b)/i) ||
              sig.match(/([0-9]+)\s*(gb|tb)/i)
            if (match) {
              const val = match[1].toUpperCase()
              storages.add(val.endsWith('GB') || val.endsWith('TB') ? val : `${val}${match[2].toUpperCase()}`)
            }
          })
          if (storages.size > 0) {
            const listStr = Array.from(storages).join(', ')
            return {
              raw: listStr,
              display: listStr,
              isValid: true,
              isMissing: false,
            }
          }
        }

        if (defCode === 'ram') {
          const rams = new Set()
          product.variants.forEach((v) => {
            const sig = (v.option_signature || v.name || v.sku || '').toLowerCase()
            const match = sig.match(/ram:([0-9]+gb)/i) || sig.match(/([0-9]+)\s*gb\s*ram/i)
            if (match) {
              rams.add(match[1].toUpperCase())
            }
          })
          if (rams.size > 0) {
            const listStr = Array.from(rams).join(', ')
            return {
              raw: listStr,
              display: listStr,
              isValid: true,
              isMissing: false,
            }
          }
        }
      }

      // No data found in database
      return {
        raw: null,
        display: t.notSpecified,
        isValid: false,
        isMissing: true,
      }
    },
    [lang, t.notSpecified]
  )

  /**
   * Determine if a specification has REAL differences among products.
   * STRICT RULE: Empty, null, or missing ("Chưa cập nhật") values are NOT counted as differences.
   * Only rows where at least two devices have actual valid data AND those values differ will return true.
   */
  const hasDifference = useCallback(
    (defCode) => {
      if (displayProducts.length < 2) return false
      const specs = displayProducts.map((p) => getProductSpec(p, defCode))
      // Filter out missing or empty values
      const validSpecs = specs.filter((s) => s.isValid && s.raw !== null && s.raw !== '')

      // Need at least 2 real values to contrast against each other
      if (validSpecs.length < 2) return false

      const firstVal = String(validSpecs[0].raw).trim().toLowerCase()
      return validSpecs.some((s) => String(s.raw).trim().toLowerCase() !== firstVal)
    },
    [displayProducts, getProductSpec]
  )

  // Enrich definitions list with RAM and Storage if not already in definitions
  const allDefinitions = useMemo(() => {
    const list = [...definitions]
    const existingCodes = new Set(list.map((d) => d.code))

    if (!existingCodes.has('ram')) {
      list.push({
        id: 'canonical_ram',
        code: 'ram',
        name: lang === 'zh-CN' ? '运行内存 (RAM)' : lang === 'en' ? 'RAM' : 'Bộ nhớ RAM',
        value_type: 'text',
        is_comparable: true,
        sort_order: 1.5,
      })
    }

    if (!existingCodes.has('internal_storage') && !existingCodes.has('storage')) {
      list.push({
        id: 'canonical_internal_storage',
        code: 'internal_storage',
        name: lang === 'zh-CN' ? '机身存储 (ROM)' : lang === 'en' ? 'Internal Storage' : 'Bộ nhớ trong',
        value_type: 'text',
        is_comparable: true,
        sort_order: 1.6,
      })
    }

    return list.sort((a, b) => (a.sort_order || 99) - (b.sort_order || 99))
  }, [definitions, lang])

  // Filter definitions based on Only Differences toggle
  const visibleDefinitions = useMemo(() => {
    if (!onlyDifferences) return allDefinitions
    return allDefinitions.filter((def) => hasDifference(def.code))
  }, [allDefinitions, onlyDifferences, hasDifference])

  // Overview differences detection
  const isBrandDiff = useMemo(() => {
    if (displayProducts.length < 2) return false
    const brands = displayProducts.map((p) => (p.brand?.name || p.brand_name || '').trim().toLowerCase())
    const validBrands = brands.filter((b) => b !== '')
    return validBrands.length >= 2 && validBrands.some((b) => b !== validBrands[0])
  }, [displayProducts])

  const isVariantsDiff = useMemo(() => {
    if (displayProducts.length < 2) return false
    const prices = displayProducts.map((p) =>
      Number(p.base_price || p.min_price || p.price || p.variants?.[0]?.price || 0)
    )
    return prices.some((price) => price !== prices[0])
  }, [displayProducts])

  const isStockDiff = useMemo(() => {
    if (displayProducts.length < 2) return false
    const stocks = displayProducts.map((p) =>
      p.variants?.reduce(
        (sum, v) => sum + (v.inventory?.available_quantity || v.available_quantity || 0),
        0
      ) || 0
    )
    return stocks.some((s) => s !== stocks[0])
  }, [displayProducts])

  // Count total differences across all definitions + overview rows
  const totalDifferencesCount = useMemo(() => {
    const specDiffs = allDefinitions.filter((def) => hasDifference(def.code)).length
    const overviewDiffs = (isBrandDiff ? 1 : 0) + (isVariantsDiff ? 1 : 0) + (isStockDiff ? 1 : 0)
    return specDiffs + overviewDiffs
  }, [allDefinitions, hasDifference, isBrandDiff, isVariantsDiff, isStockDiff])

  // Determine best (minimum) price among compared products
  const minComparedPrice = useMemo(() => {
    const list = productsData.length > 0 ? productsData : compareItems
    const validPrices = list
      .map((p) => Number(p.base_price || p.min_price || p.price || p.variants?.[0]?.price || 0))
      .filter((price) => price > 0)
    return validPrices.length > 0 ? Math.min(...validPrices) : 0
  }, [productsData, compareItems])

  // Group definitions into categorized sections
  const groupedDefinitions = useMemo(() => {
    const assignedCodes = new Set()
    const groups = []

    SPEC_GROUPS.forEach((grp) => {
      const matched = visibleDefinitions.filter((def) => {
        const aliases = SPEC_ALIASES[def.code] || [def.code]
        return grp.codes.some((c) => aliases.includes(c) || c === def.code)
      })

      if (matched.length > 0) {
        matched.forEach((d) => assignedCodes.add(d.code))
        groups.push({
          id: grp.id,
          title: t.sections[grp.titleKey] || grp.id,
          definitions: matched,
        })
      }
    })

    // Catch any remaining unassigned definitions
    const remaining = visibleDefinitions.filter((def) => !assignedCodes.has(def.code))
    if (remaining.length > 0) {
      groups.push({
        id: 'others',
        title: t.sections.others,
        definitions: remaining,
      })
    }

    return groups
  }, [visibleDefinitions, t.sections])

  // Empty state
  if (compareItems.length === 0) {
    return (
      <div className="max-w-4xl mx-auto py-20 px-4 text-center animate-fade-in">
        <div className="w-24 h-24 mx-auto rounded-3xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary mb-6 shadow-inner">
          <span className="material-symbols-outlined text-5xl">compare_arrows</span>
        </div>
        <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface mb-3">{t.emptyTitle}</h1>
        <p className="text-sm md:text-base text-on-surface-variant max-w-lg mx-auto mb-8 leading-relaxed">
          {t.emptySubtitle}
        </p>
        <Button variant="primary" size="lg" onClick={() => navigate('/products')} className="rounded-2xl shadow-lg">
          <span className="material-symbols-outlined text-xl">storefront</span>
          {t.exploreCatalog}
        </Button>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fade-in">
      {/* Header Bar */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-6 border-b border-outline-variant/20 print:hidden">
        <div>
          <div className="flex items-center gap-2 text-xs font-bold text-primary uppercase tracking-wider mb-1">
            <span className="material-symbols-outlined text-base">balance</span>
            {t.badge}
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface flex items-center gap-2">
            <span>{t.title}</span>
            <span className="text-sm font-semibold px-2.5 py-0.5 rounded-full bg-primary/15 text-primary">
              {compareItems.length}/{maxItems} {t.deviceCountSuffix}
            </span>
          </h1>
          <p className="text-xs md:text-sm text-on-surface-variant mt-1 max-w-2xl">{t.subtitle}</p>
        </div>

        {/* Toolbar */}
        <div className="flex items-center flex-wrap gap-2.5">
          {/* Difference Toggle */}
          <label className="flex items-center gap-2 text-xs font-semibold text-on-surface cursor-pointer select-none bg-surface-container px-3.5 py-2.5 rounded-xl border border-outline-variant/20 hover:border-primary/40 transition-colors shadow-xs">
            <input
              type="checkbox"
              checked={onlyDifferences}
              onChange={(e) => setOnlyDifferences(e.target.checked)}
              className="rounded text-primary focus:ring-primary h-4 w-4"
            />
            <span>{t.onlyDiff}</span>
            {totalDifferencesCount > 0 && (
              <span className="ml-1 px-1.5 py-0.5 rounded-full bg-primary text-on-primary text-[10px] font-bold">
                {totalDifferencesCount} {t.diffCount}
              </span>
            )}
          </label>

          {/* Add device button if slot available */}
          {compareItems.length < maxItems && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowSearchModal(true)}
              className="rounded-xl font-semibold"
            >
              <span className="material-symbols-outlined text-base">add</span>
              {t.addDevice}
            </Button>
          )}

          {/* Share Link */}
          <Button
            variant="secondary"
            size="sm"
            onClick={handleShare}
            className="rounded-xl font-medium"
            title={t.shareLink}
          >
            <span className="material-symbols-outlined text-base">share</span>
            <span className="hidden sm:inline">{t.shareLink}</span>
          </Button>

          {/* Print / Export */}
          <Button
            variant="ghost"
            size="sm"
            onClick={handlePrint}
            className="rounded-xl font-medium text-on-surface-variant hover:text-on-surface"
            title={t.printView}
          >
            <span className="material-symbols-outlined text-base">print</span>
            <span className="hidden sm:inline">{t.printView}</span>
          </Button>

          {/* Clear All */}
          <Button
            variant="ghost"
            size="sm"
            onClick={clearCompare}
            className="text-error hover:bg-error/10 rounded-xl font-semibold"
          >
            <span className="material-symbols-outlined text-base">delete_sweep</span>
            {t.clearAll}
          </Button>
        </div>
      </div>

      {/* Comparison Matrix Table */}
      <div className="overflow-x-auto rounded-3xl border border-outline-variant/25 bg-surface-container-low shadow-md">
        <table className="w-full text-left border-collapse min-w-[760px] table-fixed">
          {/* Sticky Product Cards Header */}
          <thead className="sticky top-16 lg:top-20 z-20 backdrop-blur-xl bg-surface-container/95 border-b border-outline-variant/25 shadow-xs">
            <tr>
              <th className="p-4 w-52 md:w-64 text-xs font-bold uppercase tracking-wider text-on-surface-variant align-middle bg-surface-container">
                <div className="flex items-center gap-2">
                  <span className="material-symbols-outlined text-primary text-base">view_column</span>
                  <span>{t.compareInfoCol}</span>
                </div>
              </th>

              {displayProducts.map((p) => {
                const primaryImg = getProductThumbnail(p)
                const price = Number(p.base_price || p.min_price || p.price || p.variants?.[0]?.price || 0)
                const isBestPrice = minComparedPrice > 0 && price === minComparedPrice && displayProducts.length > 1

                return (
                  <th
                    key={p.id}
                    className="p-4 w-60 md:w-72 align-top border-l border-outline-variant/15 text-center relative bg-surface-container"
                  >
                    {/* Quick remove button */}
                    <button
                      type="button"
                      onClick={() => removeFromCompare(p.id)}
                      title="Bỏ khỏi so sánh"
                      className="absolute top-2.5 right-2.5 w-7 h-7 rounded-full bg-surface-container-highest/80 text-on-surface-variant hover:text-error hover:bg-error/15 flex items-center justify-center transition-all z-10 print:hidden"
                    >
                      <span className="material-symbols-outlined text-base">close</span>
                    </button>

                    <div className="flex flex-col items-center">
                      <Link to={`/products/${p.slug || p.id}`} className="group block mb-2 w-full">
                        {/* High-speed Image container */}
                        <div className="w-24 h-24 md:w-28 md:h-28 mx-auto rounded-2xl overflow-hidden bg-surface-container-highest/60 p-2 flex items-center justify-center group-hover:scale-105 transition-transform duration-300 shadow-xs border border-outline-variant/20">
                          <CompareImage src={primaryImg} alt={p.name} className="w-full h-full rounded-xl" />
                        </div>

                        <span className="text-[11px] font-bold text-primary block mt-2 uppercase tracking-wide">
                          {p.brand?.name || p.brand_name || 'MobieX'}
                        </span>
                        <h3 className="text-xs md:text-sm font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-2 px-1">
                          {p.name}
                        </h3>
                      </Link>

                      {/* Pricing with Best Price badge */}
                      <div className="mb-2.5 flex flex-col items-center gap-1">
                        <div className="text-sm md:text-base font-extrabold text-primary">
                          {formatCurrencyVND(price)}
                        </div>
                        {isBestPrice && (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] font-extrabold uppercase tracking-wide">
                            <span className="material-symbols-outlined text-[12px]">verified</span>
                            {t.bestPriceBadge}
                          </span>
                        )}
                      </div>

                      {/* Add to Cart button */}
                      <Button
                        variant="primary"
                        size="sm"
                        className="w-full text-xs rounded-xl shadow-xs print:hidden"
                        loading={addingToCartId === p.id}
                        disabled={addingToCartId === p.id}
                        onClick={() => handleQuickAddToCart(p)}
                      >
                        <span className="material-symbols-outlined text-base">add_shopping_cart</span>
                        {t.addToCart}
                      </Button>
                    </div>
                  </th>
                )
              })}

              {/* Add slot placeholder if fewer than 4 */}
              {displayProducts.length < maxItems && (
                <th className="p-4 w-48 md:w-56 align-middle border-l border-outline-variant/15 text-center bg-surface-container/30 print:hidden">
                  <button
                    type="button"
                    onClick={() => setShowSearchModal(true)}
                    className="w-full py-10 border-2 border-dashed border-outline-variant/50 hover:border-primary rounded-2xl flex flex-col items-center justify-center gap-2 text-on-surface-variant hover:text-primary transition-all group hover:bg-primary/[0.03]"
                  >
                    <div className="w-10 h-10 rounded-full bg-primary/10 group-hover:bg-primary/20 flex items-center justify-center text-primary transition-colors">
                      <span className="material-symbols-outlined text-xl">add</span>
                    </div>
                    <span className="text-xs font-semibold">
                      {t.addDevice} ({displayProducts.length}/{maxItems})
                    </span>
                  </button>
                </th>
              )}
            </tr>
          </thead>

          {/* Table Body */}
          <tbody className="divide-y divide-outline-variant/10 text-xs md:text-sm">
            {/* Section 1: Overview & Pricing (Shown if Full Compare or if any overview field differs) */}
            {(!onlyDifferences || isBrandDiff || isVariantsDiff || isStockDiff) && (
              <>
                <tr className="bg-surface-container/70">
                  <td
                    colSpan={displayProducts.length + 1 + (displayProducts.length < maxItems ? 1 : 0)}
                    className="p-3.5 text-xs font-bold text-primary uppercase tracking-wider"
                  >
                    {t.sections.overview}
                  </td>
                </tr>

                {/* Field: Brand */}
                {(!onlyDifferences || isBrandDiff) && (
                  <tr className={isBrandDiff && onlyDifferences ? 'bg-primary/[0.04]' : ''}>
                    <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30 flex items-center justify-between gap-2">
                      <span>{t.fields.brand}</span>
                      {isBrandDiff && (
                        <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-tighter shrink-0">
                          {t.differenceBadge}
                        </span>
                      )}
                    </td>
                    {displayProducts.map((p) => (
                      <td
                        key={p.id}
                        className={`p-4 text-center border-l border-outline-variant/10 font-medium ${
                          isBrandDiff ? 'text-primary font-bold' : 'text-on-surface'
                        }`}
                      >
                        {p.brand?.name || p.brand_name || (
                          <span className="inline-flex items-center gap-1.5 text-xs text-on-surface-variant/40 italic">
                            <span className="w-1.5 h-1.5 rounded-full bg-outline-variant/60" />
                            {t.notSpecified}
                          </span>
                        )}
                      </td>
                    ))}
                    {displayProducts.length < maxItems && <td className="border-l border-outline-variant/10 print:hidden" />}
                  </tr>
                )}

                {/* Field: Commercial Selling Variants */}
                {(!onlyDifferences || isVariantsDiff) && (
                  <tr className={isVariantsDiff && onlyDifferences ? 'bg-primary/[0.04]' : ''}>
                    <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30 flex items-center justify-between gap-2">
                      <span>{t.fields.variants}</span>
                      {isVariantsDiff && (
                        <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-tighter shrink-0">
                          {t.differenceBadge}
                        </span>
                      )}
                    </td>
                    {displayProducts.map((p) => (
                      <td key={p.id} className="p-4 text-center border-l border-outline-variant/10">
                        {p.variants && p.variants.length > 0 ? (
                          <div className="flex flex-col gap-1 items-center">
                            {p.variants.map((v) => (
                              <span
                                key={v.id}
                                className="inline-block px-2.5 py-1 rounded-lg bg-surface-container-high text-[11px] font-mono text-on-surface border border-outline-variant/15"
                              >
                                {v.name || v.option_signature || v.sku} • {formatCurrencyVND(v.price)}
                              </span>
                            ))}
                          </div>
                        ) : (
                          <span className="text-on-surface-variant italic">{t.standardVariant}</span>
                        )}
                      </td>
                    ))}
                    {displayProducts.length < maxItems && <td className="border-l border-outline-variant/10 print:hidden" />}
                  </tr>
                )}

                {/* Field: Stock Status */}
                {(!onlyDifferences || isStockDiff) && (
                  <tr className={isStockDiff && onlyDifferences ? 'bg-primary/[0.04]' : ''}>
                    <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30 flex items-center justify-between gap-2">
                      <span>{t.fields.stock}</span>
                      {isStockDiff && (
                        <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-tighter shrink-0">
                          {t.differenceBadge}
                        </span>
                      )}
                    </td>
                    {displayProducts.map((p) => {
                      const totalStock = p.variants?.reduce(
                        (sum, v) => sum + (v.inventory?.available_quantity || v.available_quantity || 0),
                        0
                      )
                      const hasStock = totalStock > 0 || !p.variants || p.variants.length === 0

                      return (
                        <td key={p.id} className="p-4 text-center border-l border-outline-variant/10">
                          {hasStock ? (
                            <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-full">
                              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                              {totalStock > 0 ? `${t.inStock} (${totalStock})` : t.inStock}
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-xs font-bold text-error bg-error/10 px-2.5 py-1 rounded-full">
                              <span className="material-symbols-outlined text-xs">block</span>
                              {t.outOfStock}
                            </span>
                          )}
                        </td>
                      )
                    })}
                    {displayProducts.length < maxItems && <td className="border-l border-outline-variant/10 print:hidden" />}
                  </tr>
                )}
              </>
            )}

            {/* Sections 2..N: Categorized Specifications */}
            {loading ? (
              // Shimmer skeleton rows while fetching deep specs
              Array.from({ length: 6 }).map((_, sIdx) => (
                <tr key={`skeleton-${sIdx}`} className="animate-pulse">
                  <td className="p-4 bg-surface-container/30">
                    <div className="h-4 bg-surface-container-highest rounded-md w-3/4" />
                  </td>
                  {displayProducts.map((p) => (
                    <td key={p.id} className="p-4 text-center border-l border-outline-variant/10">
                      <div className="h-4 bg-surface-container-highest rounded-md w-1/2 mx-auto" />
                    </td>
                  ))}
                  {displayProducts.length < maxItems && (
                    <td className="border-l border-outline-variant/10 print:hidden" />
                  )}
                </tr>
              ))
            ) : groupedDefinitions.length === 0 ? (
              <tr>
                <td
                  colSpan={displayProducts.length + 1 + (displayProducts.length < maxItems ? 1 : 0)}
                  className="p-8 text-center text-on-surface-variant italic"
                >
                  {onlyDifferences ? t.noDiffsFound : t.updatingSpecs}
                </td>
              </tr>
            ) : (
              groupedDefinitions.map((group) => (
                <tbody key={group.id} className="divide-y divide-outline-variant/10">
                  {/* Group Section Header */}
                  <tr className="bg-surface-container/70">
                    <td
                      colSpan={displayProducts.length + 1 + (displayProducts.length < maxItems ? 1 : 0)}
                      className="p-3.5 text-xs font-bold text-primary uppercase tracking-wider"
                    >
                      {group.title}
                    </td>
                  </tr>

                  {/* Group Definition Rows */}
                  {group.definitions.map((def) => {
                    const diff = hasDifference(def.code)
                    return (
                      <tr key={def.code} className={diff ? 'bg-primary/[0.04]' : ''}>
                        <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30 flex items-center justify-between gap-2">
                          <span>{def.name}</span>
                          {diff && (
                            <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-tighter shrink-0">
                              {t.differenceBadge}
                            </span>
                          )}
                        </td>
                        {displayProducts.map((p) => {
                          const specResult = getProductSpec(p, def.code)
                          return (
                            <td
                              key={p.id}
                              className={`p-4 text-center border-l border-outline-variant/10 font-medium ${
                                diff ? 'text-primary font-bold' : 'text-on-surface'
                              }`}
                            >
                              {specResult.isMissing ? (
                                <span className="inline-flex items-center gap-1.5 text-xs text-on-surface-variant/40 italic">
                                  <span className="w-1.5 h-1.5 rounded-full bg-outline-variant/60" />
                                  {t.notSpecified}
                                </span>
                              ) : (
                                <span>{specResult.display}</span>
                              )}
                            </td>
                          )
                        })}
                        {displayProducts.length < maxItems && (
                          <td className="border-l border-outline-variant/10 print:hidden" />
                        )}
                      </tr>
                    )
                  })}
                </tbody>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Modal: Add More Product to Compare */}
      {showSearchModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className="bg-surface-container rounded-3xl border border-outline-variant/30 max-w-lg w-full p-6 shadow-2xl space-y-4 animate-scale-up">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-bold text-on-surface flex items-center gap-2">
                <span className="material-symbols-outlined text-primary text-xl">add_circle</span>
                {t.searchModalTitle}
              </h3>
              <button
                type="button"
                onClick={() => setShowSearchModal(false)}
                className="w-8 h-8 rounded-full flex items-center justify-center text-on-surface-variant hover:text-on-surface hover:bg-surface-container-highest transition-colors"
              >
                <span className="material-symbols-outlined text-base">close</span>
              </button>
            </div>

            {/* Search Input */}
            <div className="relative">
              <span className="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline">
                search
              </span>
              <input
                type="text"
                autoFocus
                placeholder={t.searchPlaceholder}
                value={searchKeyword}
                onChange={(e) => setSearchKeyword(e.target.value)}
                className="w-full pl-10 pr-10 py-2.5 rounded-2xl border border-outline-variant/30 bg-surface-container-high text-on-surface placeholder:text-outline focus:outline-none focus:border-primary text-sm shadow-xs"
              />
              {searchKeyword && (
                <button
                  type="button"
                  onClick={() => setSearchKeyword('')}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface"
                >
                  <span className="material-symbols-outlined text-sm">cancel</span>
                </button>
              )}
            </div>

            {!searchKeyword && (
              <p className="text-[11px] font-semibold text-on-surface-variant px-1">
                {t.recommendedPhones}
              </p>
            )}

            {/* Results / Suggestions List */}
            <div className="max-h-72 overflow-y-auto space-y-2 pr-1">
              {searching ? (
                <div className="py-8 text-center text-xs text-on-surface-variant flex items-center justify-center gap-2">
                  <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                  <span>{t.searching}</span>
                </div>
              ) : searchResults.length === 0 ? (
                <div className="py-8 text-center text-xs text-on-surface-variant">
                  {searchKeyword ? t.noProductsFound : t.typeKeyword}
                </div>
              ) : (
                searchResults.map((p) => {
                  const alreadyIn = compareItems.some((i) => Number(i.id) === Number(p.id))
                  const primaryImg = getProductThumbnail(p)
                  const price = p.base_price || p.min_price || p.price || p.variants?.[0]?.price || 0

                  return (
                    <div
                      key={p.id}
                      className="flex items-center justify-between p-2.5 rounded-2xl bg-surface-container-high/60 border border-outline-variant/15 hover:border-primary/40 transition-all gap-3"
                    >
                      <div className="flex items-center gap-3 min-w-0">
                        <div className="w-12 h-12 rounded-xl bg-surface-container p-1 shrink-0 flex items-center justify-center overflow-hidden border border-outline-variant/20">
                          <CompareImage src={primaryImg} alt={p.name} className="w-full h-full rounded-lg" />
                        </div>
                        <div className="min-w-0">
                          <p className="text-xs md:text-sm font-bold text-on-surface truncate">{p.name}</p>
                          <p className="text-xs text-primary font-extrabold">{formatCurrencyVND(price)}</p>
                        </div>
                      </div>

                      <Button
                        size="sm"
                        variant={alreadyIn ? 'ghost' : 'outline'}
                        disabled={alreadyIn}
                        onClick={() => {
                          const added = addToCompare(p)
                          if (added) {
                            setShowSearchModal(false)
                          }
                        }}
                        className="shrink-0 rounded-xl text-xs font-semibold"
                      >
                        {alreadyIn ? t.alreadyAdded : t.selectToCompare}
                      </Button>
                    </div>
                  )
                })
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
