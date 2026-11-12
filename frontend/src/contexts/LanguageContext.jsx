// src/contexts/LanguageContext.jsx
// Multilingual support context integrated with official GTranslate Website Translator Widget.
// Supported languages: Tiếng Việt (vi - default), English (en), 中文（简体） (zh-CN).

import { createContext, useContext, useEffect, useState, useCallback } from 'react'

const STORAGE_KEY = 'mobiex_lang'
const GT_STORAGE_KEY = '__GT_TRANSLATE_LANGS'

export const SUPPORTED_LANGUAGES = [
  { code: 'vi', label: 'Tiếng Việt', short: 'VI' },
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'zh-CN', label: '中文（简体）', short: '中文' },
]

export const DICTIONARY = {
  vi: {
    home: 'Trang chủ',
    phones: 'Điện thoại',
    accessories: 'Phụ kiện',
    cart: 'Giỏ hàng',
    compare: 'So sánh',
    wishlist: 'Yêu thích',
    login: 'Đăng nhập',
    register: 'Đăng ký',
    orders: 'Đơn hàng của tôi',
    profile: 'Tài khoản của tôi',
    addresses: 'Sổ địa chỉ nhận hàng',
    logout: 'Đăng xuất',
    admin: 'Quản trị',
    searchPlaceholder: 'Tìm kiếm sản phẩm...',
    flashSale: 'Giờ vàng Flash Sale',
    featured: 'Sản phẩm nổi bật',
    viewAll: 'Xem tất cả',
    addToCart: 'Thêm vào giỏ hàng',
    buyNow: 'Mua ngay',
    support: 'Hỗ trợ 24/7',
    orderTracking: 'Tra cứu đơn hàng',
    categories: 'Danh mục',
    products: 'Sản phẩm',
  },
  en: {
    home: 'Home',
    phones: 'Smartphones',
    accessories: 'Accessories',
    cart: 'Cart',
    compare: 'Compare',
    wishlist: 'Wishlist',
    login: 'Sign In',
    register: 'Register',
    orders: 'My Orders',
    profile: 'My Profile',
    addresses: 'Address Book',
    logout: 'Sign Out',
    admin: 'Admin Portal',
    searchPlaceholder: 'Search products...',
    flashSale: 'Hot Flash Sale',
    featured: 'Featured Products',
    viewAll: 'View All',
    addToCart: 'Add to Cart',
    buyNow: 'Buy Now',
    support: '24/7 Support',
    orderTracking: 'Order Tracking',
    categories: 'Categories',
    products: 'Products',
  },
  'zh-CN': {
    home: '首页',
    phones: '智能手机',
    accessories: '配件',
    cart: '购物车',
    compare: '对比',
    wishlist: '收藏夹',
    login: '登录',
    register: '注册',
    orders: '我的订单',
    profile: '个人中心',
    addresses: '收货地址',
    logout: '退出登录',
    admin: '管理后台',
    searchPlaceholder: '搜索产品...',
    flashSale: '限时秒杀',
    featured: '精选产品',
    viewAll: '查看全部',
    addToCart: '加入购物车',
    buyNow: '立即购买',
    support: '24/7客服支持',
    orderTracking: '订单查询',
    categories: '商品分类',
    products: '所有产品',
  },
}

const LanguageContext = createContext(null)

export function LanguageProvider({ children }) {
  const [lang, setLangState] = useState(() => {
    try {
      // Check GTranslate storage first
      const gtStored = localStorage.getItem(GT_STORAGE_KEY)
      if (gtStored) {
        const parsed = JSON.parse(gtStored)
        if (parsed?.tgtLang && ['vi', 'en', 'zh-CN'].includes(parsed.tgtLang)) {
          return parsed.tgtLang
        }
      }
      const local = localStorage.getItem(STORAGE_KEY)
      if (local && ['vi', 'en', 'zh-CN'].includes(local)) {
        return local
      }
      return 'vi'
    } catch {
      return 'vi'
    }
  })

  // Initialize official GTranslate settings and script once
  useEffect(() => {
    if (typeof window === 'undefined') return

    window.gtranslateSettings = {
      default_language: 'vi',
      languages: ['vi', 'en', 'zh-CN'],
      wrapper_selector: '.gtranslate_wrapper',
      native_language_names: 1,
      select_language_label: 'Ngôn ngữ',
    }

    const SCRIPT_ID = 'gt-widget-script'
    let script = document.getElementById(SCRIPT_ID)
    if (!script) {
      script = document.createElement('script')
      script.id = SCRIPT_ID
      script.src = 'https://cdn.gtranslate.net/widgets/latest/dropdown.js'
      script.defer = true
      document.body.appendChild(script)
    }
  }, [])

  // Sync document lang and local storage
  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, lang)
      document.documentElement.lang = lang
    } catch {
      // ignore storage errors
    }
  }, [lang])

  // Change language and invoke GTranslate official translation
  const changeLanguage = useCallback((newLang) => {
    if (!['vi', 'en', 'zh-CN'].includes(newLang)) return
    setLangState(newLang)

    try {
      localStorage.setItem(STORAGE_KEY, newLang)
      document.documentElement.lang = newLang

      // Trigger GTranslate widget dropdown if present in DOM
      const selectors = document.querySelectorAll('.gt_selector')
      selectors.forEach((sel) => {
        sel.value = `vi|${newLang}`
        sel.dispatchEvent(new Event('change', { bubbles: true }))
      })

      // Also trigger doGTranslate directly if available
      if (typeof window.doGTranslate === 'function') {
        window.doGTranslate(`vi|${newLang}`)
      }
    } catch (e) {
      console.warn('GTranslate switch notice:', e)
    }
  }, [])

  const setLang = (next) => {
    if (typeof next === 'function') {
      setLangState((prev) => {
        const resolved = next(prev)
        changeLanguage(resolved)
        return resolved
      })
    } else {
      changeLanguage(next)
    }
  }

  const toggleLang = () => {
    setLangState((prev) => {
      const order = ['vi', 'en', 'zh-CN']
      const currentIndex = order.indexOf(prev)
      const nextLang = order[(currentIndex + 1) % order.length]
      changeLanguage(nextLang)
      return nextLang
    })
  }

  const t = (key) => {
    return DICTIONARY[lang]?.[key] ?? DICTIONARY.vi[key] ?? key
  }

  return (
    <LanguageContext.Provider
      value={{
        lang,
        setLang,
        changeLanguage,
        toggleLang,
        t,
        languages: SUPPORTED_LANGUAGES,
      }}
    >
      {children}
    </LanguageContext.Provider>
  )
}

export function useLanguage() {
  const ctx = useContext(LanguageContext)
  if (!ctx) {
    throw new Error('useLanguage must be used within a LanguageProvider')
  }
  return ctx
}
