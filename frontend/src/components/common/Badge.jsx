// src/components/common/Badge.jsx
// Inline status badge with semantic color variants.

const variants = {
  success:  'bg-secondary-container/20 text-secondary border-secondary/20',
  error:    'bg-error-container/20 text-error border-error/20',
  warning:  'bg-tertiary-container/20 text-tertiary border-tertiary/20',
  info:     'bg-primary-container/20 text-primary border-primary/20',
  neutral:  'bg-surface-container-high text-on-surface-variant border-outline-variant/30',
  pending:  'bg-tertiary-container/20 text-tertiary-container border-tertiary-container/20',
}

/**
 * @param {{
 *   variant?: keyof typeof variants,
 *   children: React.ReactNode,
 *   className?: string,
 * }} props
 */
export default function Badge({ variant = 'neutral', children, className = '' }) {
  return (
    <span
      className={[
        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border',
        variants[variant] ?? variants.neutral,
        className,
      ].join(' ')}
    >
      {children}
    </span>
  )
}
