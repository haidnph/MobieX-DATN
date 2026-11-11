// src/components/common/Spinner.jsx
// Loading spinner used throughout the app.

/**
 * @param {{ size?: 'sm'|'md'|'lg', className?: string }} props
 */
export default function Spinner({ size = 'md', className = '' }) {
  const sizes = { sm: 'h-4 w-4 border-2', md: 'h-8 w-8 border-2', lg: 'h-12 w-12 border-4' }
  return (
    <div
      className={[
        'rounded-full border-primary-container border-t-primary animate-spin',
        sizes[size] ?? sizes.md,
        className,
      ].join(' ')}
      role="status"
      aria-label="Đang tải..."
    />
  )
}
