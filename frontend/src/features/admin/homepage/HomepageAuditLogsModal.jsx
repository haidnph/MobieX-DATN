// src/features/admin/homepage/HomepageAuditLogsModal.jsx
// Modal displaying historical audit trail of homepage content and section changes.

import { useEffect, useState } from 'react'
import axiosClient from '../../../api/axiosClient'
import EP from '../../../api/endpoints'
import Modal from '../../../components/common/Modal'
import Spinner from '../../../components/common/Spinner'
import { formatDate } from '../../../utils/formatDate'

export default function HomepageAuditLogsModal({ open = false, onClose }) {
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (open) {
      setLoading(true)
      axiosClient
        .get(EP.ADMIN_HOMEPAGE_AUDIT_LOGS)
        .then(({ data }) => {
          setLogs(data?.data || [])
        })
        .catch(() => {})
        .finally(() => setLoading(false))
    }
  }, [open])

  const renderActionBadge = (action) => {
    if (action.includes('publish')) {
      return (
        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-success/15 text-success">
          Xuất bản
        </span>
      )
    }
    if (action.includes('reorder')) {
      return (
        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/15 text-primary">
          Sắp xếp
        </span>
      )
    }
    if (action.includes('created')) {
      return (
        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/15 text-primary">
          Tạo mới
        </span>
      )
    }
    if (action.includes('deleted')) {
      return (
        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-error/15 text-error">
          Xóa
        </span>
      )
    }
    return (
      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface-variant">
        Cập nhật
      </span>
    )
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Lịch sử thay đổi Trang chủ (Audit Logs)"
      size="lg"
    >
      <div className="space-y-4 max-h-[500px] overflow-y-auto pr-1">
        {loading ? (
          <div className="flex flex-col items-center justify-center py-16 gap-2">
            <Spinner size="md" />
            <span className="text-xs text-on-surface-variant">Đang tải lịch sử...</span>
          </div>
        ) : logs.length === 0 ? (
          <div className="text-center py-12 text-on-surface-variant text-xs">
            Chưa có ghi nhận thay đổi nào
          </div>
        ) : (
          <div className="space-y-3">
            {logs.map((log) => (
              <div
                key={log.id}
                className="p-3.5 rounded-xl bg-surface-container-high/40 border border-outline-variant/20 space-y-1.5 text-xs"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {renderActionBadge(log.action)}
                    <span className="font-semibold text-on-surface">{log.description}</span>
                  </div>
                  <span className="text-outline text-[11px]">{formatDate(log.created_at)}</span>
                </div>

                <div className="flex items-center gap-4 text-on-surface-variant text-[11px]">
                  <span>Người thực hiện: <strong className="text-on-surface">{log.user?.name || 'Hệ thống'}</strong></span>
                  {log.ip_address && <span>IP: {log.ip_address}</span>}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </Modal>
  )
}
