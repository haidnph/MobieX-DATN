import { describe, it, expect } from 'vitest'

const DICTIONARY = {
  vi: {
    home: 'Trang chủ',
    phones: 'Điện thoại',
    accessories: 'Phụ kiện',
    cart: 'Giỏ hàng',
    compare: 'So sánh',
    wishlist: 'Yêu thích',
  },
  en: {
    home: 'Home',
    phones: 'Smartphones',
    accessories: 'Accessories',
    cart: 'Cart',
    compare: 'Compare',
    wishlist: 'Wishlist',
  },
  'zh-CN': {
    home: '首页',
    phones: '智能手机',
    accessories: '配件',
    cart: '购物车',
    compare: '对比',
    wishlist: '收藏夹',
  },
}

function translate(dict, lang, key) {
  return dict[lang]?.[key] ?? dict.vi?.[key] ?? key
}

function nextLang(current) {
  return current === 'vi' ? 'en' : 'vi'
}

describe('Language Logic & Translations', () => {
  it('translates navigation keys to Vietnamese by default', () => {
    expect(translate(DICTIONARY, 'vi', 'home')).toBe('Trang chủ')
    expect(translate(DICTIONARY, 'vi', 'phones')).toBe('Điện thoại')
    expect(translate(DICTIONARY, 'vi', 'cart')).toBe('Giỏ hàng')
  })

  it('translates navigation keys to English when lang is en', () => {
    expect(translate(DICTIONARY, 'en', 'home')).toBe('Home')
    expect(translate(DICTIONARY, 'en', 'phones')).toBe('Smartphones')
    expect(translate(DICTIONARY, 'en', 'cart')).toBe('Cart')
  })

  it('translates navigation keys to Simplified Chinese when lang is zh-CN', () => {
    expect(translate(DICTIONARY, 'zh-CN', 'home')).toBe('首页')
    expect(translate(DICTIONARY, 'zh-CN', 'phones')).toBe('智能手机')
    expect(translate(DICTIONARY, 'zh-CN', 'cart')).toBe('购物车')
  })

  it('toggles language between vi and en', () => {
    expect(nextLang('vi')).toBe('en')
    expect(nextLang('en')).toBe('vi')
  })

  it('falls back gracefully to Vietnamese or key if translation is missing', () => {
    expect(translate(DICTIONARY, 'en', 'missing_key')).toBe('missing_key')
  })
})
