// src/components/admin/VariantImageManager.jsx
// Manages variant-specific and general product image galleries in Admin modal.

import { useState } from 'react'
import ProductImageGallery from './ProductImageGallery'

/**
 * @param {Object} props
 * @param {number} props.productId - Required product ID
 * @param {Array}  [props.variants] - Array of variant objects { id, sku, option_signature, price }
 */
export default function VariantImageManager({ productId, variants = [] }) {
  // 'general' or variant ID
  const [selectedScope, setSelectedScope] = useState('general')

  const currentVariant = variants.find((v) => String(v.id) === String(selectedScope))

  return (
    <div className="space-y-4">
      {/* Scope Selector: General vs Specific Variants */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <label className="text-xs font-semibold text-on-surface uppercase tracking-wider">
            Phân loại ảnh thư viện:
          </label>
          <span className="text-[11px] text-on-surface-variant">
            {variants.length} phiên bản cấu hình
          </span>
        </div>

        <div className="flex flex-wrap gap-2 p-1.5 bg-surface-container-low rounded-xl border border-outline-variant/30">
          {/* General Images Button */}
          <button
            type="button"
            onClick={() => setSelectedScope('general')}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
              selectedScope === 'general'
                ? 'bg-primary text-on-primary shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container'
            }`}
          >
            <span className="material-symbols-outlined text-sm">auto_awesome_motion</span>
            Ảnh chung sản phẩm
          </button>

          {/* Variants Buttons */}
          {variants.map((v) => {
            const isSelected = String(selectedScope) === String(v.id)
            const label = v.option_signature || v.sku || `Phiên bản #${v.id}`
            return (
              <button
                key={v.id}
                type="button"
                onClick={() => setSelectedScope(String(v.id))}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 ${
                  isSelected
                    ? 'bg-primary text-on-primary shadow-sm'
                    : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container'
                }`}
              >
                <span className="material-symbols-outlined text-sm">palette</span>
                <span className="max-w-[140px] truncate">{label}</span>
              </button>
            )
          })}
        </div>
      </div>

      {/* Gallery Section */}
      <div className="pt-2">
        {selectedScope === 'general' ? (
          <ProductImageGallery
            key="gallery-general"
            productId={productId}
            variantId={null}
            title="Thư viện ảnh chung của sản phẩm"
          />
        ) : (
          <ProductImageGallery
            key={`gallery-var-${currentVariant?.id}`}
            productId={productId}
            variantId={currentVariant?.id}
            title={`Ảnh riêng phiên bản: ${currentVariant?.option_signature || currentVariant?.sku || currentVariant?.id}`}
          />
        )}
      </div>
    </div>
  )
}
