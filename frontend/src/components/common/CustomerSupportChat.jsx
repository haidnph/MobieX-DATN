// src/components/common/CustomerSupportChat.jsx
// Floating customer support live chat widget with auto-consultant and quick FAQ responses.

import { useEffect, useRef, useState } from 'react'

const FAQ_CHIPS = [
  { label: '📱 Tư vấn chọn máy', prompt: 'Tư vấn giúp tôi điện thoại hot nhất hiện nay' },
  { label: '🚚 Thời gian giao hàng', prompt: 'Thời gian giao hàng mất bao lâu?' },
  { label: '🛡️ Chính sách bảo hành', prompt: 'Chính sách bảo hành tại MobieX như thế nào?' },
  { label: '💳 Phương thức thanh toán', prompt: 'Hệ thống hỗ trợ những cách thanh toán nào?' },
  { label: '📦 Kiểm tra đơn hàng', prompt: 'Làm sao để tôi kiểm tra đơn hàng đã đặt?' },
]

const BOT_KNOWLEDGE = [
  {
    keywords: ['tư vấn', 'iphone', 'samsung', 'xiaomi', 'chọn máy', 'mua điện thoại'],
    reply:
      'MobieX đang phân phối chính hãng các siêu phẩm: iPhone 16 Pro Max, Samsung Galaxy S24 Ultra và Xiaomi 14 Ultra. Anh/Chị có thể bấm vào mục "So sánh" trên thanh điều hướng để xem chi tiết bảng so sánh cấu hình và chọn chiếc máy ưng ý nhất nhé!',
  },
  {
    keywords: ['giao hàng', 'vận chuyển', 'ship', 'bao lâu', 'thời gian'],
    reply:
      'Đơn hàng tại MobieX được xử lý trong vòng 24h. Thời gian giao hàng: Nội thành 1-2 ngày làm việc, liên tỉnh từ 2-4 ngày. Đơn hàng từ 5.000.000đ được miễn phí giao hàng toàn quốc!',
  },
  {
    keywords: ['bảo hành', 'đổi trả', 'lỗi', '1 đổi 1'],
    reply:
      'Chính sách bảo hành vàng tại MobieX: Cam kết 100% hàng chính hãng, bảo hành 12 tháng tại các trung tâm ủy quyền toàn quốc và áp dụng chính sách 1 đổi 1 trong 30 ngày đầu tiên nếu có lỗi phần cứng từ nhà sản xuất.',
  },
  {
    keywords: ['thanh toán', 'vnpay', 'cod', 'tiền mặt', 'chuyển khoản', 'thẻ'],
    reply:
      'Hệ thống hỗ trợ 2 phương thức thanh toán linh hoạt: 1. Thanh toán tiền mặt khi nhận hàng (COD). 2. Thanh toán trực tuyến an toàn qua cổng VNPay (quét mã VNPAY-QR, thẻ ATM nội địa, thẻ Visa/MasterCard).',
  },
  {
    keywords: ['đơn hàng', 'tra cứu', 'kiểm tra đơn', 'tình trạng'],
    reply:
      'Để kiểm tra đơn hàng, Anh/Chị vui lòng đăng nhập và chọn mục "Đơn hàng của tôi" trên menu tài khoản (hoặc đường dẫn /orders). Hệ thống cập nhật tiến độ từ Chờ xác nhận, Đang xử lý, Đang giao đến Đã giao theo thời gian thực.',
  },
  {
    keywords: ['hotline', 'liên hệ', 'tổng đài', 'sđt', 'nhân viên', 'gặp'],
    reply:
      'Anh/Chị có thể liên hệ trực tiếp Tổng đài Chăm sóc Khách hàng MobieX qua Hotline miễn cước: 1900 6868 hoặc gửi email tới cskh@mobiex.vn (Hỗ trợ từ 8:00 đến 21:30 hàng ngày).',
  },
]

function getBotReply(userText) {
  const lower = userText.toLowerCase().trim()
  for (const item of BOT_KNOWLEDGE) {
    if (item.keywords.some((kw) => lower.includes(kw))) {
      return item.reply
    }
  }
  return 'Cảm ơn Anh/Chị đã gửi câu hỏi! Em đã ghi nhận yêu cầu và tư vấn viên sẽ hỗ trợ ngay. Anh/Chị cũng có thể gọi Hotline 1900 6868 (miễn cước) để được tư vấn trực tiếp 24/7 ạ!'
}

export default function CustomerSupportChat() {
  const [isOpen, setIsOpen] = useState(false)
  const [hasUnread, setHasUnread] = useState(true)
  const [inputValue, setInputValue] = useState('')
  const [isTyping, setIsTyping] = useState(false)
  const [messages, setMessages] = useState([
    {
      id: 1,
      sender: 'bot',
      text: 'Xin chào! Em là Trợ lý ảo MobieX 🤖. Em có thể hỗ trợ gì cho Anh/Chị về sản phẩm, khuyến mãi hay đơn hàng hôm nay ạ?',
      time: 'Vừa xong',
    },
  ])

  const messagesEndRef = useRef(null)

  useEffect(() => {
    if (isOpen) {
      setHasUnread(false)
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
    }
  }, [isOpen, messages, isTyping])

  const handleSendMessage = (textToSend) => {
    const text = (textToSend || inputValue).trim()
    if (!text) return

    const userMsg = {
      id: Date.now(),
      sender: 'user',
      text,
      time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
    }

    setMessages((prev) => [...prev, userMsg])
    setInputValue('')
    setIsTyping(true)

    // Simulate natural consultant typing delay
    setTimeout(() => {
      const replyText = getBotReply(text)
      const botMsg = {
        id: Date.now() + 1,
        sender: 'bot',
        text: replyText,
        time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      }
      setMessages((prev) => [...prev, botMsg])
      setIsTyping(false)
    }, 800)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    handleSendMessage()
  }

  return (
    <div className="fixed bottom-6 right-6 z-40">
      {/* Floating Toggle Button */}
      {!isOpen && (
        <button
          type="button"
          onClick={() => setIsOpen(true)}
          className="group relative flex items-center gap-2.5 px-4 py-3 bg-primary text-on-primary rounded-full shadow-[0_8px_30px_rgba(77,142,255,0.4)] hover:shadow-[0_12px_40px_rgba(77,142,255,0.6)] hover:scale-105 transition-all duration-300"
          aria-label="Mở cửa sổ hỗ trợ"
        >
          <div className="relative">
            <span className="material-symbols-outlined text-2xl">support_agent</span>
            {hasUnread && (
              <span className="absolute -top-1 -right-1 flex h-3 w-3">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75" />
                <span className="relative inline-flex rounded-full h-3 w-3 bg-error" />
              </span>
            )}
          </div>
          <span className="text-xs font-bold tracking-tight hidden sm:inline">
            Hỗ trợ 24/7
          </span>
        </button>
      )}

      {/* Expanded Chat Box */}
      {isOpen && (
        <div className="w-[90vw] sm:w-[380px] h-[520px] bg-surface-container-high rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-outline-variant/30 flex flex-col overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-200">
          {/* Header */}
          <div className="px-5 py-4 bg-primary text-on-primary flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="relative">
                <div className="w-10 h-10 rounded-full bg-surface-container-lowest flex items-center justify-center text-primary shadow-xs">
                  <span className="material-symbols-outlined text-2xl">smart_toy</span>
                </div>
                <span className="absolute bottom-0 right-0 w-3 h-3 bg-secondary rounded-full border-2 border-primary" />
              </div>
              <div>
                <h3 className="text-sm font-bold tracking-tight">Trợ lý MobieX</h3>
                <p className="text-[11px] opacity-90 flex items-center gap-1">
                  <span className="inline-block w-1.5 h-1.5 rounded-full bg-secondary" />
                  Đang trực tuyến • Sẵn sàng hỗ trợ
                </p>
              </div>
            </div>

            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-black/10 transition-colors"
              aria-label="Đóng cửa sổ chat"
            >
              <span className="material-symbols-outlined text-xl">close</span>
            </button>
          </div>

          {/* Messages Body */}
          <div className="flex-1 p-4 overflow-y-auto space-y-3 bg-surface-container-low/50">
            {messages.map((m) => (
              <div
                key={m.id}
                className={`flex flex-col ${m.sender === 'user' ? 'items-end' : 'items-start'}`}
              >
                <div
                  className={`max-w-[85%] px-4 py-2.5 rounded-2xl text-xs leading-relaxed ${
                    m.sender === 'user'
                      ? 'bg-primary text-on-primary rounded-br-xs'
                      : 'bg-surface-container text-on-surface border border-outline-variant/30 rounded-bl-xs shadow-xs'
                  }`}
                >
                  {m.text}
                </div>
                <span className="text-[10px] text-outline px-1 mt-1">{m.time}</span>
              </div>
            ))}

            {isTyping && (
              <div className="flex items-center gap-1.5 bg-surface-container px-3 py-2 rounded-2xl rounded-bl-xs text-xs text-on-surface-variant w-fit border border-outline-variant/20 shadow-xs">
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce" />
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce [animation-delay:0.2s]" />
                <span className="inline-block w-1.5 h-1.5 rounded-full bg-primary animate-bounce [animation-delay:0.4s]" />
                <span className="text-[11px] ml-1">Đang soạn phản hồi...</span>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Quick FAQ Chips */}
          <div className="px-3 py-2 bg-surface-container border-t border-outline-variant/20 overflow-x-auto flex gap-1.5 no-scrollbar">
            {FAQ_CHIPS.map((chip, idx) => (
              <button
                key={idx}
                type="button"
                onClick={() => handleSendMessage(chip.prompt)}
                className="whitespace-nowrap px-2.5 py-1 rounded-full bg-surface-container-high hover:bg-primary hover:text-on-primary text-[11px] font-medium text-on-surface-variant transition-colors border border-outline-variant/30 shrink-0"
              >
                {chip.label}
              </button>
            ))}
          </div>

          {/* Chat Input */}
          <form
            onSubmit={handleSubmit}
            className="p-3 bg-surface-container border-t border-outline-variant/20 flex items-center gap-2"
          >
            <input
              type="text"
              value={inputValue}
              onChange={(e) => setInputValue(e.target.value)}
              placeholder="Nhập câu hỏi cần hỗ trợ..."
              className="flex-1 bg-surface-container-low text-xs text-on-surface placeholder:text-outline rounded-xl px-3.5 py-2.5 border border-outline-variant/40 focus:outline-none focus:ring-2 focus:ring-primary"
            />
            <button
              type="submit"
              disabled={!inputValue.trim()}
              className="p-2.5 rounded-xl bg-primary text-on-primary disabled:opacity-40 disabled:cursor-not-allowed hover:bg-primary-fixed transition-colors flex items-center justify-center shrink-0"
              aria-label="Gửi tin nhắn"
            >
              <span className="material-symbols-outlined text-base">send</span>
            </button>
          </form>
        </div>
      )}
    </div>
  )
}
