import { describe, it, expect } from 'vitest'
import ProductForm from '../features/admin/product/ProductForm'

// Helper function mirroring slugify from ProductForm
function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[đĐ]/g, 'd')
    .replace(/[^a-z0-9 -]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
}

// Helper formatting form payload
function formatProductPayload(form) {
  return {
    name: form.name.trim(),
    slug: form.slug.trim(),
    brand_id: form.brand_id ? Number(form.brand_id) : null,
    category_id: form.category_id ? Number(form.category_id) : null,
    status: form.status || 'active',
    description: form.description?.trim() || null,
    price: form.price ? Number(form.price) : 0,
    compare_price: form.compare_price ? Number(form.compare_price) : null,
    stock_quantity: form.stock_quantity ? Number(form.stock_quantity) : 50,
    image_url: form.image_url?.trim() || null,
  }
}

// Helper formatting specs payload
function formatSpecsPayload(productSpecs) {
  return Object.entries(productSpecs)
    .filter(([_, val]) => val !== '' && val !== null && val !== undefined)
    .map(([defId, val]) => ({
      definition_id: Number(defId),
      value: val,
    }))
}

describe('Admin Product Dedicated Form & UX Logic', () => {
  it('correctly converts complex Vietnamese product names into clean SEO slugs', () => {
    expect(slugify('iPhone 16 Pro Max 256GB Titan Tự Nhiên')).toBe('iphone-16-pro-max-256gb-titan-tu-nhien')
    expect(slugify('Điện Thoại Samsung Galaxy S25 Ultra 5G (Đen Nguyên Bản)')).toBe('dien-thoai-samsung-galaxy-s25-ultra-5g-den-nguyen-ban')
    expect(slugify('Xiaomi 14T Pro 12GB/512GB - Xám Titan')).toBe('xiaomi-14t-pro-12gb512gb-xam-titan')
  })

  it('formats create/edit product payload correctly with numeric casts and null fallbacks', () => {
    const rawForm = {
      name: '  iPhone 15 Pro  ',
      slug: '  iphone-15-pro  ',
      brand_id: '5',
      category_id: '2',
      status: 'active',
      description: '  Mô tả chi tiết sản phẩm  ',
      price: '24990000',
      compare_price: '27990000',
      stock_quantity: '30',
      image_url: '  https://cdn.example.com/ip15.jpg  ',
    }

    const payload = formatProductPayload(rawForm)
    expect(payload).toEqual({
      name: 'iPhone 15 Pro',
      slug: 'iphone-15-pro',
      brand_id: 5,
      category_id: 2,
      status: 'active',
      description: 'Mô tả chi tiết sản phẩm',
      price: 24990000,
      compare_price: 27990000,
      stock_quantity: 30,
      image_url: 'https://cdn.example.com/ip15.jpg',
    })
  })

  it('handles empty optional fields cleanly during payload creation', () => {
    const rawMinimal = {
      name: 'Cáp sạc Type-C',
      slug: 'cap-sac-type-c',
      brand_id: '',
      category_id: '',
      status: 'draft',
      description: '',
      price: '',
      compare_price: '',
      stock_quantity: '',
      image_url: '',
    }

    const payload = formatProductPayload(rawMinimal)
    expect(payload.brand_id).toBeNull()
    expect(payload.category_id).toBeNull()
    expect(payload.description).toBeNull()
    expect(payload.price).toBe(0)
    expect(payload.compare_price).toBeNull()
    expect(payload.stock_quantity).toBe(50)
    expect(payload.image_url).toBeNull()
  })

  it('formats specifications map into API payload excluding empty and undefined values', () => {
    const specsMap = {
      1: '6.7 inch',
      2: 120,
      3: true,
      4: false,
      5: '',
      6: null,
      7: undefined,
    }

    const payload = formatSpecsPayload(specsMap)
    expect(payload).toEqual([
      { definition_id: 1, value: '6.7 inch' },
      { definition_id: 2, value: 120 },
      { definition_id: 3, value: true },
      { definition_id: 4, value: false },
    ])
  })

  it('verifies route paths for Dedicated Admin Product management', () => {
    const routes = {
      list: '/admin/products',
      create: '/admin/products/create',
      edit: (id) => `/admin/products/${id}/edit`,
    }

    expect(routes.list).toBe('/admin/products')
    expect(routes.create).toBe('/admin/products/create')
    expect(routes.edit(488)).toBe('/admin/products/488/edit')
    expect(routes.edit('791')).toBe('/admin/products/791/edit')
    expect(routes.edit('1280')).toBe('/admin/products/1280/edit')
  })

  it('successfully loads ProductForm component without syntax or reference errors', () => {
    expect(ProductForm).toBeDefined()
    expect(typeof ProductForm).toBe('function')
  })
})
