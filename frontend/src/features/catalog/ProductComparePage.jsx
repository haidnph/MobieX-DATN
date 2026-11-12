import { useState, useEffect, useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useCompare } from '../../contexts/CompareContext'
import { useCart } from '../../contexts/CartContext'
import { formatCurrencyVND } from '../../utils/formatCurrencyVND'
import axiosClient from '../../api/axiosClient'
import EP from '../../api/endpoints'
import Button from '../../components/common/Button'
import { useToast } from '../../contexts/ToastContext'

export default function ProductComparePage() {
  const { compareItems, removeFromCompare, clearCompare, addToCompare } = useCompare()
  const { addToCart } = useCart()
  const { showToast } = useToast()
  const navigate = useNavigate()

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

  // Fetch full details of items in compare list
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

  // Search products to add into comparison
  useEffect(() => {
    if (!showSearchModal || !searchKeyword.trim()) {
      setSearchResults([])
      return
    }

    const timer = setTimeout(() => {
      setSearching(true)
      axiosClient
        .get(EP.PRODUCTS, { params: { keyword: searchKeyword.trim(), per_page: 5 } })
        .then((res) => {
          const list = res.data?.data?.data || []
          setSearchResults(list)
        })
        .catch((err) => console.error(err))
        .finally(() => setSearching(false))
    }, 300)

    return () => clearTimeout(timer)
  }, [searchKeyword, showSearchModal])

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

  // Helper to extract a spec value by definition code
  const getProductSpec = (product, defCode) => {
    const spec = product.specifications?.find((s) => s.definition?.code === defCode)
    if (!spec) return '—'
    if (spec.numeric_value !== null && spec.numeric_value !== undefined) {
      const unit = spec.definition?.unit ? ` ${spec.definition.unit}` : ''
      return `${Number(spec.numeric_value)}${unit}`
    }
    if (spec.value_boolean !== null && spec.value_boolean !== undefined) {
      return spec.value_boolean ? 'Có' : 'Không'
    }
    return spec.value_text || '—'
  }

  // Determine if a spec has differences among products
  const hasDifference = (defCode) => {
    if (productsData.length < 2) return false
    const values = productsData.map((p) => getProductSpec(p, defCode))
    return values.some((val) => val !== values[0])
  }

  // Filter definitions based on differences toggle
  const visibleDefinitions = useMemo(() => {
    if (!onlyDifferences) return definitions
    return definitions.filter((def) => hasDifference(def.code))
  }, [definitions, onlyDifferences, productsData])

  if (compareItems.length === 0) {
    return (
      <div className="max-w-4xl mx-auto py-16 px-4 text-center">
        <div className="w-20 h-20 mx-auto rounded-3xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary mb-6">
          <span className="material-symbols-outlined text-4xl">compare_arrows</span>
        </div>
        <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface mb-3">
          Chưa có sản phẩm nào để so sánh
        </h1>
        <p className="text-sm md:text-base text-on-surface-variant max-w-md mx-auto mb-8">
          Chọn tối đa 4 dòng điện thoại đặt cạnh nhau để so sánh chi tiết về thông số kỹ thuật, giá bán và cấu hình.
        </p>
        <Button variant="primary" size="lg" onClick={() => navigate('/products')}>
          <span className="material-symbols-outlined text-xl">storefront</span>
          Khám phá danh sách sản phẩm
        </Button>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-outline-variant/20">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-primary uppercase tracking-wider mb-1">
            <span className="material-symbols-outlined text-base">balance</span>
            Công cụ đối chiếu cấu hình
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-on-surface">
            So sánh sản phẩm ({compareItems.length}/4)
          </h1>
          <p className="text-xs md:text-sm text-on-surface-variant mt-1">
            Đặt các dòng máy cạnh nhau để dễ dàng đánh giá cấu hình, màn hình, hiệu năng và camera.
          </p>
        </div>

        <div className="flex items-center flex-wrap gap-3">
          <label className="flex items-center gap-2 text-xs font-medium text-on-surface cursor-pointer select-none bg-surface-container px-3 py-2 rounded-xl border border-outline-variant/20">
            <input
              type="checkbox"
              checked={onlyDifferences}
              onChange={(e) => setOnlyDifferences(e.target.checked)}
              className="rounded text-primary focus:ring-primary h-4 w-4"
            />
            <span>Chỉ xem điểm khác biệt</span>
          </label>

          {compareItems.length < 4 && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowSearchModal(true)}
              className="rounded-xl"
            >
              <span className="material-symbols-outlined text-base">add</span>
              Thêm máy khác
            </Button>
          )}

          <Button
            variant="ghost"
            size="sm"
            onClick={clearCompare}
            className="text-error hover:bg-error/10 rounded-xl"
          >
            <span className="material-symbols-outlined text-base">delete_sweep</span>
            Xóa tất cả
          </Button>
        </div>
      </div>

      {loading ? (
        <div className="py-24 text-center">
          <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-4" />
          <p className="text-sm text-on-surface-variant font-medium">Đang tải bảng đối chiếu thông số...</p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-outline-variant/20 bg-surface-container-low shadow-sm">
          <table className="w-full text-left border-collapse min-w-[700px]">
            {/* Table Header: Sticky Product Cards */}
            <thead>
              <tr className="border-b border-outline-variant/20 bg-surface-container">
                <th className="p-4 w-52 md:w-64 text-xs font-bold uppercase tracking-wider text-on-surface-variant align-top">
                  Thông tin so sánh
                </th>
                {productsData.map((p) => {
                  const primaryImg =
                    p.primary_image_url ||
                    p.images?.find((img) => img.is_primary)?.image_url ||
                    p.images?.[0]?.image_url ||
                    '/placeholder-phone.png'
                  const minPrice = p.base_price || p.variants?.[0]?.price || 0

                  return (
                    <th
                      key={p.id}
                      className="p-4 w-60 md:w-72 align-top border-l border-outline-variant/15 text-center relative"
                    >
                      <button
                        onClick={() => removeFromCompare(p.id)}
                        title="Bỏ khỏi so sánh"
                        className="absolute top-2 right-2 w-7 h-7 rounded-full bg-surface-container-high/80 text-on-surface-variant hover:text-error hover:bg-error/15 flex items-center justify-center transition-colors"
                      >
                        <span className="material-symbols-outlined text-base">close</span>
                      </button>

                      <div className="flex flex-col items-center">
                        <Link to={`/products/${p.slug || p.id}`} className="group block mb-3">
                          <div className="w-28 h-28 mx-auto rounded-xl overflow-hidden bg-surface-container-high p-2 flex items-center justify-center group-hover:scale-105 transition-transform duration-200">
                            <img
                              src={primaryImg}
                              alt={p.name}
                              className="max-h-full max-w-full object-contain"
                              onError={(e) => {
                                e.currentTarget.src = '/placeholder-phone.png'
                              }}
                            />
                          </div>
                          <span className="text-xs font-semibold text-primary block mt-1">
                            {p.brand?.name || 'MobieX'}
                          </span>
                          <h3 className="text-sm font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-2">
                            {p.name}
                          </h3>
                        </Link>

                        <div className="text-base font-extrabold text-primary mb-3">
                          {formatCurrencyVND(minPrice)}
                        </div>

                        <Button
                          variant="primary"
                          size="sm"
                          className="w-full text-xs"
                          loading={addingToCartId === p.id}
                          disabled={addingToCartId === p.id}
                          onClick={() => handleQuickAddToCart(p)}
                        >
                          <span className="material-symbols-outlined text-base">add_shopping_cart</span>
                          Thêm vào giỏ
                        </Button>
                      </div>
                    </th>
                  )
                })}

                {/* Add slot placeholder if < 4 */}
                {productsData.length < 4 && (
                  <th className="p-4 w-48 align-middle border-l border-outline-variant/15 text-center bg-surface-container/40">
                    <button
                      onClick={() => setShowSearchModal(true)}
                      className="w-full py-12 border-2 border-dashed border-outline-variant/50 hover:border-primary rounded-2xl flex flex-col items-center justify-center gap-2 text-on-surface-variant hover:text-primary transition-colors group"
                    >
                      <div className="w-10 h-10 rounded-full bg-primary/10 group-hover:bg-primary/20 flex items-center justify-center text-primary transition-colors">
                        <span className="material-symbols-outlined text-xl">add</span>
                      </div>
                      <span className="text-xs font-medium">Thêm máy khác ({productsData.length}/4)</span>
                    </button>
                  </th>
                )}
              </tr>
            </thead>

            {/* Table Body: Sections */}
            <tbody className="divide-y divide-outline-variant/10 text-xs md:text-sm">
              {/* Section 1: Overview */}
              <tr className="bg-surface-container/60">
                <td
                  colSpan={productsData.length + 1 + (productsData.length < 4 ? 1 : 0)}
                  className="p-3 text-xs font-bold text-primary uppercase tracking-wider"
                >
                  1. Thông tin chung & Phiên bản
                </td>
              </tr>
              <tr>
                <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30">
                  Thương hiệu
                </td>
                {productsData.map((p) => (
                  <td key={p.id} className="p-4 text-center border-l border-outline-variant/10 font-medium">
                    {p.brand?.name || '—'}
                  </td>
                ))}
                {productsData.length < 4 && <td className="border-l border-outline-variant/10" />}
              </tr>
              <tr>
                <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30">
                  Các phiên bản có sẵn
                </td>
                {productsData.map((p) => (
                  <td key={p.id} className="p-4 text-center border-l border-outline-variant/10">
                    {p.variants && p.variants.length > 0 ? (
                      <div className="flex flex-col gap-1 items-center">
                        {p.variants.map((v) => (
                          <span
                            key={v.id}
                            className="inline-block px-2 py-0.5 rounded bg-surface-container-high text-[11px] font-mono text-on-surface"
                          >
                            {v.name || v.option_signature || v.sku} • {formatCurrencyVND(v.price)}
                          </span>
                        ))}
                      </div>
                    ) : (
                      'Phiên bản tiêu chuẩn'
                    )}
                  </td>
                ))}
                {productsData.length < 4 && <td className="border-l border-outline-variant/10" />}
              </tr>
              <tr>
                <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30">
                  Tình trạng kho hàng
                </td>
                {productsData.map((p) => {
                  const totalStock = p.variants?.reduce(
                    (sum, v) => sum + (v.inventory?.available_quantity || v.available_quantity || 0),
                    0
                  )
                  return (
                    <td key={p.id} className="p-4 text-center border-l border-outline-variant/10">
                      {totalStock > 0 ? (
                        <span className="text-secondary font-semibold">Còn {totalStock} máy</span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs font-bold text-white bg-error px-2.5 py-0.5 rounded-full shadow-xs">
                          Tạm hết hàng
                        </span>
                      )}
                    </td>
                  )
                })}
                {productsData.length < 4 && <td className="border-l border-outline-variant/10" />}
              </tr>

              {/* Section 2: Technical Specifications */}
              <tr className="bg-surface-container/60">
                <td
                  colSpan={productsData.length + 1 + (productsData.length < 4 ? 1 : 0)}
                  className="p-3 text-xs font-bold text-primary uppercase tracking-wider"
                >
                  2. Thông số kỹ thuật chi tiết
                </td>
              </tr>

              {visibleDefinitions.length === 0 ? (
                <tr>
                  <td
                    colSpan={productsData.length + 1 + (productsData.length < 4 ? 1 : 0)}
                    className="p-6 text-center text-on-surface-variant italic"
                  >
                    {onlyDifferences
                      ? 'Không có điểm khác biệt nào giữa các sản phẩm được chọn.'
                      : 'Đang cập nhật danh mục thông số kỹ thuật.'}
                  </td>
                </tr>
              ) : (
                visibleDefinitions.map((def) => {
                  const diff = hasDifference(def.code)
                  return (
                    <tr
                      key={def.code}
                      className={diff ? 'bg-primary/[0.03]' : ''}
                    >
                      <td className="p-4 font-semibold text-on-surface-variant bg-surface-container/30 flex items-center justify-between">
                        <span>{def.name}</span>
                        {diff && (
                          <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-tighter">
                            Khác biệt
                          </span>
                        )}
                      </td>
                      {productsData.map((p) => {
                        const val = getProductSpec(p, def.code)
                        return (
                          <td
                            key={p.id}
                            className={`p-4 text-center border-l border-outline-variant/10 font-medium ${
                              diff ? 'text-primary font-semibold' : 'text-on-surface'
                            }`}
                          >
                            {val}
                          </td>
                        )
                      })}
                      {productsData.length < 4 && <td className="border-l border-outline-variant/10" />}
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal: Add More Product to Compare */}
      {showSearchModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
          <div className="bg-surface-container rounded-2xl border border-outline-variant/30 max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-bold text-on-surface flex items-center gap-2">
                <span className="material-symbols-outlined text-primary">add_circle</span>
                Chọn máy thêm vào so sánh
              </h3>
              <button
                onClick={() => setShowSearchModal(false)}
                className="text-on-surface-variant hover:text-on-surface"
              >
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>

            <div className="relative">
              <span className="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-outline">
                search
              </span>
              <input
                type="text"
                autoFocus
                placeholder="Nhập tên điện thoại (iPhone 16, Galaxy S24, Xiaomi...)"
                value={searchKeyword}
                onChange={(e) => setSearchKeyword(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-outline-variant/30 bg-surface-container-high text-on-surface placeholder:text-outline focus:outline-none focus:border-primary text-sm"
              />
            </div>

            <div className="max-h-64 overflow-y-auto space-y-2">
              {searching ? (
                <div className="py-6 text-center text-xs text-on-surface-variant">Đang tìm kiếm...</div>
              ) : searchResults.length === 0 ? (
                <div className="py-6 text-center text-xs text-on-surface-variant">
                  {searchKeyword ? 'Không tìm thấy sản phẩm phù hợp' : 'Gõ từ khóa để tìm sản phẩm'}
                </div>
              ) : (
                searchResults.map((p) => {
                  const alreadyIn = compareItems.some((i) => Number(i.id) === Number(p.id))
                  const primaryImg =
                    p.primary_image_url ||
                    p.images?.find((img) => img.is_primary)?.image_url ||
                    p.images?.[0]?.image_url ||
                    '/placeholder-phone.png'

                  return (
                    <div
                      key={p.id}
                      className="flex items-center justify-between p-2.5 rounded-xl bg-surface-container-high/60 border border-outline-variant/15 hover:border-primary/40 transition-colors"
                    >
                      <div className="flex items-center gap-3">
                        <img
                          src={primaryImg}
                          alt={p.name}
                          className="w-12 h-12 object-contain rounded-lg bg-surface-container"
                          onError={(e) => {
                            e.currentTarget.src = '/placeholder-phone.png'
                          }}
                        />
                        <div>
                          <p className="text-sm font-semibold text-on-surface line-clamp-1">{p.name}</p>
                          <p className="text-xs text-primary font-bold">
                            {formatCurrencyVND(p.base_price || p.variants?.[0]?.price || 0)}
                          </p>
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
                      >
                        {alreadyIn ? 'Đã thêm' : 'Chọn so sánh'}
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
