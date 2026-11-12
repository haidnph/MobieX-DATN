// src/components/layout/LanguageSelector.jsx
// Compact, accessible multilingual selector integrated with official GTranslate widget.
// Supports: Tiếng Việt (vi - default), English (en), 中文（简体） (zh-CN).

import { useEffect, useRef, useState } from 'react'
import { useLanguage, SUPPORTED_LANGUAGES } from '../../contexts/LanguageContext'

export default function LanguageSelector({ className = '' }) {
  const { lang, changeLanguage } = useLanguage()
  const [isOpen, setIsOpen] = useState(false)
  const menuRef = useRef(null)

  const currentLangObj =
    SUPPORTED_LANGUAGES.find((item) => item.code === lang) || SUPPORTED_LANGUAGES[0]

  // Close dropdown on outside click and ESC key
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setIsOpen(false)
      }
    }

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleKeyDown)
    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [])

  const handleSelect = (code) => {
    changeLanguage(code)
    setIsOpen(false)
  }

  return (
    <div ref={menuRef} className={`relative shrink-0 ${className}`}>
      {/* Official GTranslate Widget container matching wrapper_selector */}
      <div className="gtranslate_wrapper sr-only" aria-hidden="true" />

      {/* Language Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        aria-haspopup="true"
        aria-expanded={isOpen}
        aria-label={`Chọn ngôn ngữ: ${currentLangObj.label}`}
        title={`Ngôn ngữ: ${currentLangObj.label}`}
        className={`h-9 px-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all duration-200 border ${
          isOpen
            ? 'bg-surface-container text-primary border-primary/40 ring-2 ring-primary/20 shadow-sm'
            : 'bg-surface-container-low text-on-surface hover:bg-surface-container hover:text-primary border-outline-variant/30 hover:border-outline-variant/60'
        }`}
      >
        <span className="material-symbols-outlined text-base select-none text-on-surface-variant">
          language
        </span>
        <span translate="no" className="uppercase tracking-wide font-bold text-[11px] notranslate">
          {currentLangObj.short}
        </span>
        <span
          className={`material-symbols-outlined text-xs text-outline transition-transform duration-200 ${
            isOpen ? 'rotate-180 text-primary' : ''
          }`}
        >
          expand_more
        </span>
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div
          role="menu"
          aria-label="Danh sách ngôn ngữ"
          className="absolute right-0 top-full mt-2 w-44 bg-surface-container-high/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-outline-variant/30 p-1.5 z-50 animate-in fade-in zoom-in-95 duration-150"
        >
          <div className="px-2.5 py-1.5 border-b border-outline-variant/15 mb-1 flex items-center justify-between text-[10px] font-bold text-outline uppercase tracking-wider select-none">
            <span>Ngôn ngữ</span>
            <span className="text-[9px] font-normal lowercase text-outline-variant">
              GTranslate
            </span>
          </div>

          <div className="space-y-0.5">
            {SUPPORTED_LANGUAGES.map((item) => {
              const isSelected = item.code === lang
              return (
                <button
                  key={item.code}
                  type="button"
                  role="menuitem"
                  onClick={() => handleSelect(item.code)}
                  className={`w-full flex items-center justify-between px-2.5 py-2 rounded-xl text-xs transition-colors ${
                    isSelected
                      ? 'bg-primary/10 text-primary font-bold'
                      : 'text-on-surface hover:bg-surface-bright hover:text-on-surface'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    <span
                      className={`w-1.5 h-1.5 rounded-full ${
                        isSelected ? 'bg-primary' : 'bg-transparent'
                      }`}
                    />
                    <span translate="no" className="notranslate">{item.label}</span>
                  </span>
                  {isSelected && (
                    <span className="material-symbols-outlined text-sm text-primary">
                      check
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
