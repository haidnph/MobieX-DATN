// src/utils/paymentTabSync.js
// Separate-tab payment orchestration, popup-blocker detection, cross-tab status sync,
// and active checkout session persistence across VNPay, ZaloPay, and MoMo.

import EP from '../api/endpoints'

export const PAYMENT_TAB_TARGET = 'mobiex_online_payment_tab'
const PAYMENT_CHANNEL_NAME = 'mobiex_payment_sync'
const PAYMENT_STORAGE_KEY_PREFIX = 'mobiex_payment_event_'
const ACTIVE_SESSION_KEY = 'mobiex_active_payment_session'
const SESSION_MAX_AGE_MS = 2 * 60 * 60 * 1000 // 2 hours

export const GATEWAY_CONFIGS = {
  vnpay: {
    key: 'vnpay',
    label: 'VNPay',
    fullName: 'Cổng thanh toán VNPay',
    createEndpoint: EP.PAYMENT_VNPAY_CREATE,
    icon: 'payments',
    accentText: 'text-blue-600 dark:text-blue-400',
    accentBg: 'bg-blue-500/10',
    accentBorder: 'border-blue-500/25',
    badgeGradient: 'linear-gradient(135deg, #2563eb, #4f46e5)',
    brandColor: '#2563eb',
    securityLabel: 'Chuẩn bảo mật PCI-DSS · Xác thực HMAC-SHA512',
    steps: [
      'Chuyển sang tab VNPay vừa mở và chọn phương thức thanh toán phù hợp (VNPAY-QR, Thẻ ATM nội địa hoặc Visa/Mastercard).',
      'Nhập thông tin thanh toán bảo mật trực tiếp trên trang của VNPay và xác nhận giao dịch.',
      'Hoàn tất xác thực OTP theo hướng dẫn của ngân hàng.',
      'Quay lại tab MobieX này — trạng thái đơn hàng sẽ tự động cập nhật sau khi cổng thanh toán xác nhận.',
    ],
  },
  zalopay: {
    key: 'zalopay',
    label: 'ZaloPay',
    fullName: 'Cổng thanh toán ZaloPay',
    createEndpoint: EP.PAYMENT_ZALOPAY_CREATE,
    icon: 'account_balance_wallet',
    accentText: 'text-emerald-600 dark:text-emerald-400',
    accentBg: 'bg-emerald-500/10',
    accentBorder: 'border-emerald-500/25',
    badgeGradient: 'linear-gradient(135deg, #009f73, #00b894)',
    brandColor: '#009f73',
    securityLabel: 'Bảo mật SSL · Xác thực HMAC-SHA256',
    steps: [
      'Chuyển sang tab ZaloPay vừa mở và chọn hình thức thanh toán (Ví ZaloPay, QR, Thẻ ATM hoặc Visa/Mastercard).',
      'Thực hiện thanh toán trực tiếp trên cổng bảo mật của ZaloPay.',
      'Hoàn tất bước xác thực giao dịch theo hướng dẫn trên màn hình.',
      'Quay lại tab MobieX này — đơn hàng sẽ tự động cập nhật ngay khi giao dịch được xác nhận.',
    ],
  },
  momo: {
    key: 'momo',
    label: 'Ví MoMo',
    fullName: 'Cổng thanh toán Ví MoMo',
    createEndpoint: EP.PAYMENT_MOMO_CREATE,
    icon: 'qr_code_2',
    accentText: 'text-pink-600 dark:text-pink-400',
    accentBg: 'bg-pink-500/10',
    accentBorder: 'border-pink-500/25',
    badgeGradient: 'linear-gradient(135deg, #a50064, #d82d8b)',
    brandColor: '#a50064',
    securityLabel: 'Bảo mật Ví MoMo · Xác thực chữ ký điện tử',
    steps: [
      'Chuyển sang tab MoMo vừa mở (hoặc quét mã QR hiển thị nếu có) để tiến hành thanh toán.',
      'Xác nhận thanh toán bằng ứng dụng Ví MoMo hoặc thẻ liên kết trên cổng thanh toán.',
      'Hoàn tất bước xác thực bảo mật trên giao diện của MoMo.',
      'Quay lại tab MobieX này — hệ thống sẽ tự động đối soát và cập nhật kết quả đơn hàng.',
    ],
  },
}

export function isOnlineGateway(method) {
  return Boolean(method && Object.prototype.hasOwnProperty.call(GATEWAY_CONFIGS, method))
}

export function getGatewayConfig(method) {
  return GATEWAY_CONFIGS[method] || GATEWAY_CONFIGS.vnpay
}

export function isValidPaymentUrl(url) {
  if (!url || typeof url !== 'string') return false
  try {
    const parsed = new URL(url)
    return parsed.protocol === 'https:' || parsed.protocol === 'http:'
  } catch {
    return false
  }
}

export function isTabOpen(win) {
  try {
    return Boolean(win && !win.closed)
  } catch {
    return false
  }
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function renderSplashMarkup(gatewayLabel) {
  const safeName = escapeHtml(gatewayLabel || 'Cổng thanh toán')
  return `<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Đang kết nối ${safeName} — MobieX</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #0f172a;
      color: #f8fafc;
      padding: 24px;
    }
    .card {
      max-width: 420px;
      width: 100%;
      background: #1e293b;
      border: 1px solid rgba(148,163,184,0.2);
      border-radius: 20px;
      padding: 32px 24px;
      text-align: center;
      box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    }
    .spinner {
      width: 44px;
      height: 44px;
      margin: 0 auto 20px;
      border: 3px solid rgba(99, 102, 241, 0.25);
      border-top-color: #6366f1;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    h1 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
    p { font-size: 13px; color: #94a3b8; line-height: 1.6; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="card">
    <div class="spinner"></div>
    <h1>Đang kết nối tới ${safeName}...</h1>
    <p>MobieX đang khởi tạo phiên giao dịch bảo mật. Vui lòng không đóng cửa sổ này trong giây lát.</p>
  </div>
</body>
</html>`
}

export function getOrderTabName(orderId) {
  return orderId ? `mobiex_order_${orderId}` : PAYMENT_TAB_TARGET
}

/**
 * Synchronously reserve a separate browser tab inside a user click handler so modern
 * popup blockers permit navigation after async order & gateway URL creation.
 */
export function reserveGatewayTab(existingTab, gatewayLabel, targetName = PAYMENT_TAB_TARGET) {
  if (isTabOpen(existingTab)) {
    try {
      existingTab.focus()
    } catch {
      // Ignore focus failures on cross-origin tabs
    }
    return { tab: existingTab, reused: true, blocked: false }
  }

  let win = null
  try {
    win = window.open('', targetName || PAYMENT_TAB_TARGET)
  } catch {
    win = null
  }

  if (!win || win.closed || typeof win.closed === 'undefined') {
    return { tab: null, reused: false, blocked: true }
  }

  try {
    win.document.open()
    win.document.write(renderSplashMarkup(gatewayLabel))
    win.document.close()
  } catch {
    // Ignore cross-origin document write restriction if target became cross-origin
  }

  return { tab: win, reused: false, blocked: false }
}

/**
 * Navigate an opened reserved tab to the verified payment URL, then sever window.opener
 * right after scheduling navigation to avoid Blink premature opener disconnection errors.
 */
export function navigateReservedTab(tab, paymentUrl) {
  if (!isValidPaymentUrl(paymentUrl) || !isTabOpen(tab)) {
    return false
  }
  try {
    tab.location.replace(paymentUrl)
    try {
      tab.opener = null
    } catch {
      // Ignore cross-origin opener assignment restriction
    }
    try {
      tab.focus()
    } catch {
      // Ignore focus restriction
    }
    return true
  } catch {
    return false
  }
}

/**
 * Open a payment URL directly inside a click handler (used by retry / manual reopen buttons).
 */
export function openPaymentTabOnUserAction(existingTab, paymentUrl, targetName = PAYMENT_TAB_TARGET) {
  if (!isValidPaymentUrl(paymentUrl)) {
    return { tab: null, blocked: false, invalidUrl: true }
  }

  if (isTabOpen(existingTab)) {
    try {
      existingTab.focus()
      return { tab: existingTab, blocked: false, reused: true }
    } catch {
      // Fall through to open cleanly if existing ref became inaccessible
    }
  }

  let win = null
  try {
    win = window.open(paymentUrl, targetName || PAYMENT_TAB_TARGET)
  } catch {
    win = null
  }

  if (!win || win.closed || typeof win.closed === 'undefined') {
    return { tab: null, blocked: true, reused: false }
  }

  try {
    win.opener = null
    win.focus()
  } catch {
    // Ignore cross-origin restrictions after opening
  }

  return { tab: win, blocked: false, reused: false }
}

export function closeReservedTab(tab) {
  try {
    if (tab && !tab.closed) {
      tab.close()
    }
  } catch {
    // Ignore close restriction errors
  }
}

/**
 * Broadcast payment completion/failure/pending update from a Return URL tab to the
 * original MobieX checkout/order waiting tab.
 */
export function broadcastPaymentResult(eventPayload) {
  if (!eventPayload?.orderId) return
  const normalized = {
    ...eventPayload,
    orderId: Number(eventPayload.orderId),
    timestamp: Date.now(),
  }

  if (typeof BroadcastChannel !== 'undefined') {
    try {
      const ch = new BroadcastChannel(PAYMENT_CHANNEL_NAME)
      ch.postMessage(normalized)
      ch.close()
    } catch {
      // Ignore BroadcastChannel unavailability
    }
  }

  if (typeof localStorage !== 'undefined') {
    try {
      const key = `${PAYMENT_STORAGE_KEY_PREFIX}${normalized.orderId}`
      localStorage.setItem(key, JSON.stringify(normalized))
    } catch {
      // Ignore storage quota / private mode issues
    }
  }
}

/**
 * Subscribe to cross-tab payment result notifications for a specific order ID.
 */
export function subscribePaymentResult(orderId, onEvent) {
  if (!orderId || typeof onEvent !== 'function') return () => {}
  const targetOrderId = Number(orderId)

  let channel = null
  if (typeof BroadcastChannel !== 'undefined') {
    try {
      channel = new BroadcastChannel(PAYMENT_CHANNEL_NAME)
      channel.onmessage = (event) => {
        const data = event?.data
        if (data && Number(data.orderId) === targetOrderId) {
          onEvent(data)
        }
      }
    } catch {
      channel = null
    }
  }

  const storageKey = `${PAYMENT_STORAGE_KEY_PREFIX}${targetOrderId}`
  const handleStorage = (event) => {
    if (event.key !== storageKey || !event.newValue) return
    try {
      const parsed = JSON.parse(event.newValue)
      if (parsed && Number(parsed.orderId) === targetOrderId) {
        onEvent(parsed)
      }
    } catch {
      // Ignore invalid JSON payloads
    }
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('storage', handleStorage)
  }

  return () => {
    if (channel) {
      try {
        channel.close()
      } catch {
        // Ignore close error
      }
    }
    if (typeof window !== 'undefined') {
      window.removeEventListener('storage', handleStorage)
    }
  }
}

export function saveActivePaymentSession(session) {
  if (typeof sessionStorage === 'undefined' || !session?.orderId) return
  try {
    sessionStorage.setItem(
      ACTIVE_SESSION_KEY,
      JSON.stringify({ ...session, savedAt: Date.now() }),
    )
  } catch {
    // Ignore storage restriction errors
  }
}

export function loadActivePaymentSession() {
  if (typeof sessionStorage === 'undefined') return null
  try {
    const raw = sessionStorage.getItem(ACTIVE_SESSION_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed?.orderId || !parsed?.gateway) return null
    if (parsed.savedAt && Date.now() - parsed.savedAt > SESSION_MAX_AGE_MS) {
      sessionStorage.removeItem(ACTIVE_SESSION_KEY)
      return null
    }
    return parsed
  } catch {
    return null
  }
}

export function clearActivePaymentSession() {
  if (typeof sessionStorage === 'undefined') return
  try {
    sessionStorage.removeItem(ACTIVE_SESSION_KEY)
  } catch {
    // Ignore storage errors
  }
}
