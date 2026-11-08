// src/components/common/Button.jsx
// Reusable button component with MobieX design variants.

/**
 * @param {{
 *   variant?: 'primary'|'secondary'|'ghost'|'danger',
 *   size?: 'sm'|'md'|'lg',
 *   loading?: boolean,
 *   disabled?: boolean,
 *   className?: string,
 *   children: React.ReactNode,
 *   [key: string]: any
 * }} props
 */
export default function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  className = '',
  children,
  ...rest
}) {
  const base =
    'inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:opacity-50 disabled:cursor-not-allowed'

  const variants = {
    primary:   'bg-primary-container text-on-primary-container hover:bg-primary hover:text-on-primary shadow-lg',
    secondary: 'bg-secondary-container/20 text-secondary border border-secondary/20 hover:bg-secondary-container/40',
    ghost:     'bg-transparent text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface',
    danger:    'bg-error-container text-on-error-container hover:bg-error hover:text-on-error',
  }

  const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-7 py-3.5 text-base',
  }

  return (
    <button
      className={[base, variants[variant] ?? variants.primary, sizes[size] ?? sizes.md, className].join(' ')}
      disabled={disabled || loading}
      {...rest}
    >
      {loading && (
        <span className="h-4 w-4 rounded-full border-2 border-current border-t-transparent animate-spin" />
      )}
      {children}
    </button>
  )
}
