// src/components/common/Input.jsx
// Reusable controlled input with label, error display, and icon support.

/**
 * @param {{
 *   label?: string,
 *   error?: string,
 *   icon?: string,
 *   className?: string,
 *   [key: string]: any
 * }} props
 */
export default function Input({ label, error, icon, className = '', ...rest }) {
  return (
    <div className={['flex flex-col gap-1', className].join(' ')}>
      {label && (
        <label className="text-sm font-medium text-on-surface-variant" htmlFor={rest.id}>
          {label}
        </label>
      )}
      <div className="relative">
        {icon && (
          <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg pointer-events-none select-none">
            {icon}
          </span>
        )}
        <input
          className={[
            'w-full bg-surface-container-low text-on-surface placeholder:text-outline',
            'rounded-xl px-4 py-2.5 text-sm border border-outline-variant/50',
            'focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent',
            'disabled:opacity-50 transition-all',
            icon ? 'pl-10' : '',
            error ? 'border-error focus:ring-error' : '',
          ].join(' ')}
          {...rest}
        />
      </div>
      {error && <p className="text-xs text-error mt-0.5">{error}</p>}
    </div>
  )
}
