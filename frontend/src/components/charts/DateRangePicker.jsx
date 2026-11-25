
import { useState, useRef, useEffect } from 'react'

const iso = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const display = (s) =>
  s ? s.split('-').reverse().join('/') : 'Chưa chọn'

const monthOf = (s) =>
  s
    ? new Date(Number(s.slice(0, 4)), Number(s.slice(5, 7)) - 1, 1)
    : new Date(new Date().getFullYear(), new Date().getMonth(), 1)

export default function DateRangePicker({ value, onApply }) {
  const [open, setOpen] = useState(false)
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [month, setMonth] = useState(monthOf())
  const root = useRef(null)

  useEffect(() => {
    if (!open) return

    const close = (e) => {
      if (!root.current?.contains(e.target)) {
        setOpen(false)
      }
    }

    const escape = (e) => {
      if (e.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', close)
    document.addEventListener('keydown', escape)

    return () => {
      document.removeEventListener('pointerdown', close)
      document.removeEventListener('keydown', escape)
    }
  }, [open])

  const toggle = () => {
    if (!open) {
      setStart(value?.start_date || '')
      setEnd(value?.end_date || '')
      setMonth(monthOf(value?.start_date))
    }

    setOpen(!open)
  }

  const choose = (day) => {
    if (!start || end) {
      setStart(day)
      setEnd('')
    } else if (day < start) {
      setEnd(start)
      setStart(day)
    } else {
      setEnd(day)
    }
  }

  const year = month.getFullYear()
  const m = month.getMonth()
  const offset = (new Date(year, m, 1).getDay() + 6) % 7
  const count = new Date(year, m + 1, 0).getDate()
  const today = iso(new Date())

  return (
    <div ref={root} className="relative">
      <button
        type="button"
        onClick={toggle}
        aria-expanded={open}
        className={`flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold ${
          value
            ? 'border-primary text-primary bg-primary/10'
            : 'border-outline-variant/30 bg-surface-container-high text-on-surface'
        }`}
      >
        <span className="material-symbols-outlined text-base">
          calendar_month
        </span>

        {value
          ? `${display(value.start_date)} – ${display(value.end_date)}`
          : 'Chọn khoảng ngày'}

        <span className="material-symbols-outlined text-sm">
          expand_more
        </span>
      </button>

      {open && (
        <div
          role="dialog"
          aria-label="Chọn khoảng ngày thống kê"
          className="absolute z-50 top-full mt-2 right-0 w-[300px] max-w-[calc(100vw-48px)] rounded-2xl border border-outline-variant/40 bg-surface-container-high p-4 shadow-2xl text-on-surface"
        >
          <div className="font-bold text-sm">
            Chọn khoảng thời gian
          </div>

          <p className="text-xs text-on-surface-variant mt-1">
            {!start || end
              ? 'Chọn ngày bắt đầu'
              : 'Chọn ngày kết thúc'}
          </p>

          <div className="grid grid-cols-2 gap-2 my-3 text-xs">
            <div className="rounded-lg border border-outline-variant/30 p-2">
              Từ ngày
              <br />
              <strong>{display(start)}</strong>
            </div>

            <div className="rounded-lg border border-outline-variant/30 p-2">
              Đến ngày
              <br />
              <strong>{display(end)}</strong>
            </div>
          </div>

          <div className="flex items-center justify-between mb-3">
            <button
              type="button"
              aria-label="Tháng trước"
              onClick={() =>
                setMonth(new Date(year, m - 1, 1))
              }
              className="p-1 rounded hover:bg-surface-bright"
            >
              <span className="material-symbols-outlined">
                chevron_left
              </span>
            </button>

            <strong className="text-sm">
              Tháng {m + 1}/{year}
            </strong>

            <button
              type="button"
              aria-label="Tháng sau"
              disabled={
                iso(new Date(year, m + 1, 1)) > today
              }
              onClick={() =>
                setMonth(new Date(year, m + 1, 1))
              }
              className="p-1 rounded hover:bg-surface-bright disabled:opacity-30"
            >
              <span className="material-symbols-outlined">
                chevron_right
              </span>
            </button>
          </div>

          <div className="grid grid-cols-7 text-center text-xs gap-y-1">
            {[
              'T2',
              'T3',
              'T4',
              'T5',
              'T6',
              'T7',
              'CN',
            ].map((d) => (
              <span
                key={d}
                className="text-on-surface-variant py-1"
              >
                {d}
              </span>
            ))}

            {Array.from(
              { length: offset },
              (_, i) => (
                <span key={`blank${i}`} />
              )
            )}

            {Array.from(
              { length: count },
              (_, i) => {
                const day = iso(
                  new Date(year, m, i + 1)
                )

                const edge =
                  day === start || day === end

                const inside =
                  start &&
                  end &&
                  day > start &&
                  day < end

                return (
                  <button
                    key={day}
                    type="button"
                    aria-label={display(day)}
                    aria-pressed={Boolean(
                      edge || inside
                    )}
                    disabled={day > today}
                    onClick={() => choose(day)}
                    className={`h-8 text-xs ${
                      edge
                        ? 'rounded-lg bg-primary text-on-primary font-bold'
                        : inside
                          ? 'bg-primary/15 text-primary'
                          : 'rounded-lg hover:bg-surface-bright'
                    } disabled:opacity-30`}
                  >
                    {i + 1}
                  </button>
                )
              }
            )}
          </div>

          <div className="flex justify-end gap-2 mt-4 pt-3 border-t border-outline-variant/20">
            <button
              type="button"
              onClick={() => {
                setStart('')
                setEnd('')
              }}
              className="text-xs px-3 py-2 text-on-surface-variant"
            >
              Xóa chọn
            </button>

            <button
              type="button"
              disabled={!start || !end}
              onClick={() => {
                onApply(start, end)
                setOpen(false)
              }}
              className="rounded-lg bg-primary text-on-primary px-4 py-2 text-xs font-bold disabled:opacity-40"
            >
              Áp dụng
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
