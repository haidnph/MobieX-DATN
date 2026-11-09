// src/components/common/ImageUploader.jsx
// Generic multi-file dropzone with per-file preview, progress and error reporting.
// Used across ProductImageGallery and other admin media components.

import { useCallback, useRef, useState } from 'react'

const ACCEPT_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_SIZE_MB = 5
const MAX_FILES = 10

/**
 * @param {Object} props
 * @param {function} props.onUpload  - Called with (files: File[]) after user selects valid files
 * @param {boolean}  props.disabled  - Disable the dropzone
 * @param {number}   props.maxFiles  - Override max files (default: 10)
 * @param {string}   props.className - Extra className for outer wrapper
 */
export default function ImageUploader({ onUpload, disabled = false, maxFiles = MAX_FILES, className = '' }) {
  const inputRef = useRef(null)
  const [dragOver, setDragOver] = useState(false)
  const [fileErrors, setFileErrors] = useState([])

  const validateAndDispatch = useCallback((rawFiles) => {
    const errors = []
    const valid = []

    Array.from(rawFiles).slice(0, maxFiles).forEach((file) => {
      if (!ACCEPT_TYPES.includes(file.type)) {
        errors.push(`"${file.name}": Định dạng không hỗ trợ (chỉ JPG, PNG, WEBP)`)
        return
      }
      if (file.size > MAX_SIZE_MB * 1024 * 1024) {
        errors.push(`"${file.name}": Vượt quá ${MAX_SIZE_MB}MB`)
        return
      }
      valid.push(file)
    })

    setFileErrors(errors)
    if (valid.length > 0) onUpload?.(valid)
  }, [maxFiles, onUpload])

  const handleDrop = useCallback((e) => {
    e.preventDefault()
    setDragOver(false)
    if (disabled) return
    validateAndDispatch(e.dataTransfer.files)
  }, [disabled, validateAndDispatch])

  const handleChange = useCallback((e) => {
    validateAndDispatch(e.target.files)
    e.target.value = ''
  }, [validateAndDispatch])

  return (
    <div className={`space-y-2 ${className}`}>
      <div
        onDragOver={(e) => { e.preventDefault(); if (!disabled) setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        onClick={() => !disabled && inputRef.current?.click()}
        className={[
          'relative flex flex-col items-center justify-center gap-2',
          'border-2 border-dashed rounded-xl p-6 cursor-pointer transition-all',
          dragOver
            ? 'border-primary bg-primary/5 scale-[1.01]'
            : 'border-outline-variant hover:border-primary/60 hover:bg-surface-container-low',
          disabled ? 'opacity-50 cursor-not-allowed' : '',
        ].join(' ')}
      >
        <span className="material-symbols-outlined text-4xl text-outline">cloud_upload</span>
        <p className="text-sm text-on-surface-variant text-center">
          <span className="font-semibold text-primary">Kéo thả ảnh vào đây</span>
          {' '}hoặc{' '}
          <span className="font-semibold text-primary">nhấn để chọn</span>
        </p>
        <p className="text-xs text-outline">
          Tối đa {maxFiles} ảnh · JPG, PNG, WEBP · Mỗi ảnh tối đa {MAX_SIZE_MB}MB
        </p>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT_TYPES.join(',')}
          multiple
          className="hidden"
          onChange={handleChange}
          disabled={disabled}
        />
      </div>

      {fileErrors.length > 0 && (
        <ul className="space-y-1">
          {fileErrors.map((err, i) => (
            <li key={i} className="flex items-start gap-1 text-xs text-error">
              <span className="material-symbols-outlined text-sm mt-0.5 shrink-0">error</span>
              {err}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
