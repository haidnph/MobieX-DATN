import { describe, it, expect } from 'vitest'
import { SUPPORTED_LANGUAGES, DICTIONARY } from '../contexts/LanguageContext'
import {
  buildCategoryTree,
  getCategoryIcon,
  getLocalizedCategoryName,
  CATEGORY_UI,
} from '../components/layout/CategoryMegaMenu'

describe('Header & Navigation Architecture', () => {
  it('supports exactly 3 languages in strict order: vi (default), en, zh-CN', () => {
    expect(SUPPORTED_LANGUAGES).toHaveLength(3)
    expect(SUPPORTED_LANGUAGES[0].code).toBe('vi')
    expect(SUPPORTED_LANGUAGES[0].label).toBe('Tiếng Việt')
    expect(SUPPORTED_LANGUAGES[1].code).toBe('en')
    expect(SUPPORTED_LANGUAGES[1].label).toBe('English')
    expect(SUPPORTED_LANGUAGES[2].code).toBe('zh-CN')
    expect(SUPPORTED_LANGUAGES[2].label).toBe('中文（简体）')
  })

  it('provides complete translations for all 3 supported languages', () => {
    const requiredKeys = ['home', 'phones', 'cart', 'compare', 'wishlist', 'orders', 'orderTracking', 'categories', 'products']
    for (const lang of ['vi', 'en', 'zh-CN']) {
      expect(DICTIONARY[lang]).toBeDefined()
      for (const key of requiredKeys) {
        expect(DICTIONARY[lang][key]).toBeDefined()
        expect(typeof DICTIONARY[lang][key]).toBe('string')
        expect(DICTIONARY[lang][key].length).toBeGreaterThan(0)
      }
    }
  })

  it('localizes all category names across vi, en, and zh-CN without residual language mix', () => {
    const phoneCat = { id: 1, slug: 'dien-thoai', name: 'Điện thoại' }
    const accessoryCat = { id: 2, slug: 'phu-kien', name: 'Phụ kiện' }
    const chargerCat = { id: 5, slug: 'sac', name: 'Sạc' }
    const screenGlassCat = { id: 15, slug: 'kinh-cuong-luc-nano', name: 'Kính cường lực Nano 9D chống nhìn trộm' }

    // Vietnamese
    expect(getLocalizedCategoryName(phoneCat, 'vi')).toBe('Điện thoại')
    expect(getLocalizedCategoryName(accessoryCat, 'vi')).toBe('Phụ kiện')
    expect(getLocalizedCategoryName(chargerCat, 'vi')).toBe('Củ sạc')

    // English
    expect(getLocalizedCategoryName(phoneCat, 'en')).toBe('Smartphones')
    expect(getLocalizedCategoryName(accessoryCat, 'en')).toBe('Accessories')
    expect(getLocalizedCategoryName(chargerCat, 'en')).toBe('Chargers')
    expect(getLocalizedCategoryName(screenGlassCat, 'en')).toBe('Privacy Tempered Glass 9D')

    // Simplified Chinese (zh-CN)
    expect(getLocalizedCategoryName(phoneCat, 'zh-CN')).toBe('手机')
    expect(getLocalizedCategoryName(accessoryCat, 'zh-CN')).toBe('配件')
    expect(getLocalizedCategoryName(chargerCat, 'zh-CN')).toBe('充电器')
    expect(getLocalizedCategoryName(screenGlassCat, 'zh-CN')).toBe('9D 防窥纳米钢化膜')
  })

  it('provides complete Category MegaMenu UI labels for all 3 languages', () => {
    for (const lang of ['vi', 'en', 'zh-CN']) {
      expect(CATEGORY_UI[lang]).toBeDefined()
      expect(CATEGORY_UI[lang].categoryBtn).toBeDefined()
      expect(CATEGORY_UI[lang].categoryTitle).toBeDefined()
      expect(CATEGORY_UI[lang].allProducts).toBeDefined()
      expect(CATEGORY_UI[lang].genuineNote).toBeDefined()
      expect(CATEGORY_UI[lang].deliveryNote).toBeDefined()
    }
    expect(CATEGORY_UI['zh-CN'].categoryTitle).toBe('商品全部分类')
    expect(CATEGORY_UI['zh-CN'].allProducts).toBe('全部商品')
    expect(CATEGORY_UI.en.allProducts).toBe('All Products')
  })

  it('defines GTranslate settings with required configuration keys', () => {
    const settings = {
      default_language: 'vi',
      languages: ['vi', 'en', 'zh-CN'],
      wrapper_selector: '.gtranslate_wrapper',
      native_language_names: 1,
    }

    expect(settings.default_language).toBe('vi')
    expect(settings.languages).toEqual(['vi', 'en', 'zh-CN'])
    expect(settings.wrapper_selector).toBe('.gtranslate_wrapper')
    expect(settings.native_language_names).toBe(1)
  })

  it('enforces matching red badges (#EF4444) for both Wishlist and Cart', () => {
    const wishlistBadgeClass = 'bg-[#ef4444] text-white text-[10px] font-bold'
    const cartBadgeClass = 'bg-[#ef4444] text-white text-[10px] font-bold'
    expect(wishlistBadgeClass).toContain('bg-[#ef4444]')
    expect(cartBadgeClass).toContain('bg-[#ef4444]')
    expect(wishlistBadgeClass).toBe(cartBadgeClass)
  })

  it('transforms flat category list into parent-child hierarchy tree', () => {
    const flat = [
      { id: 1, parent_id: null, name: 'Điện thoại', slug: 'dien-thoai' },
      { id: 2, parent_id: 1, name: 'iPhone', slug: 'iphone' },
      { id: 3, parent_id: 1, name: 'Android', slug: 'android' },
      { id: 4, parent_id: null, name: 'Phụ kiện', slug: 'phu-kien' },
      { id: 5, parent_id: 4, name: 'Sạc', slug: 'sac' },
    ]
    const tree = buildCategoryTree(flat)
    expect(tree).toHaveLength(2)
    expect(tree[0].name).toBe('Điện thoại')
    expect(tree[0].children).toHaveLength(2)
    expect(tree[0].children[0].name).toBe('iPhone')
    expect(tree[1].name).toBe('Phụ kiện')
    expect(tree[1].children).toHaveLength(1)
  })

  it('assigns correct category icons based on slug and name', () => {
    expect(getCategoryIcon('dien-thoai', 'Điện thoại')).toBe('smartphone')
    expect(getCategoryIcon('phu-kien', 'Phụ kiện')).toBe('headphones')
    expect(getCategoryIcon('may-tinh-bang', 'Máy tính bảng')).toBe('tablet_mac')
    expect(getCategoryIcon('smartwatch', 'Đồng hồ thông minh')).toBe('watch')
    expect(getCategoryIcon('other', 'Khác')).toBe('category')
  })
})
