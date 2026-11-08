// src/components/feedback/ConfirmDialog.jsx
// A simple confirm/cancel modal for destructive actions.

import Modal from '../common/Modal'
import Button from '../common/Button'

/**
 * @param {{
 *   open: boolean,
 *   onConfirm: () => void,
 *   onCancel: () => void,
 *   title?: string,
 *   message?: string,
 *   confirmLabel?: string,
 *   loading?: boolean,
 * }} props
 */
export default function ConfirmDialog({
  open,
  onConfirm,
  onCancel,
  title = 'Xác nhận',
  message = 'Bạn có chắc chắn muốn thực hiện hành động này không?',
  confirmLabel = 'Xác nhận',
  loading = false,
}) {
  return (
    <Modal open={open} onClose={onCancel} title={title} size="sm">
      <div className="flex flex-col gap-5">
        <p className="text-sm text-on-surface-variant leading-relaxed">{message}</p>
        <div className="flex justify-end gap-3">
          <Button variant="ghost" onClick={onCancel} disabled={loading}>
            Hủy
          </Button>
          <Button variant="danger" onClick={onConfirm} loading={loading}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
