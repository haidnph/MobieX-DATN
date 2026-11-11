// src/contexts/LanguageContext.jsx
// Multilingual support context providing Vietnamese and English translations.

import { createContext, useContext, useEffect, useState } from 'react'

const STORAGE_KEY = 'mobiex_lang'

const DICTIONARY = {
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
    searchPlaceholder: 'Tìm kiếm điện thoại, phụ kiện...',
    flashSale: 'Giờ vàng Flash Sale',
    featured: 'Sản phẩm nổi bật',
    viewAll: 'Xem tất cả',
    addToCart: 'Thêm vào giỏ hàng',
    buyNow: 'Mua ngay',
    support: 'Hỗ trợ 24/7',
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
    searchPlaceholder: 'Search smartphones, accessories...',
    flashSale: 'Hot Flash Sale',
    featured: 'Featured Products',
    viewAll: 'View All',
    addToCart: 'Add to Cart',
    buyNow: 'Buy Now',
    support: '24/7 Support',
  },
}

const LanguageContext = createContext(null)

export function LanguageProvider({ children }) {
  const [lang, setLang] = useState(() => {
    try {
      return localStorage.getItem(STORAGE_KEY) || 'vi'
    } catch {
      return 'vi'
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, lang)
      document.documentElement.lang = lang
    } catch {
      // ignore storage errors
    }
  }, [lang])

  const toggleLang = () => {
    setLang((prev) => (prev === 'vi' ? 'en' : 'vi'))
  }

  const t = (key) => {
    return DICTIONARY[lang]?.[key] ?? DICTIONARY.vi[key] ?? key
  }

  return (
    <LanguageContext.Provider value={{ lang, setLang, toggleLang, t }}>
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
