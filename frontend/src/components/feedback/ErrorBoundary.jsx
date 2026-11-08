// src/components/feedback/ErrorBoundary.jsx
// Class-based React Error Boundary — catches unhandled render errors.

import { Component } from 'react'

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false, error: null }
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error }
  }

  componentDidCatch(error, info) {
    // In production, send to error monitoring (e.g. Sentry)
    console.error('[ErrorBoundary]', error, info)
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4 text-center px-4">
          <span className="material-symbols-outlined text-6xl text-error">error</span>
          <h2 className="text-xl font-bold text-on-surface">Đã xảy ra lỗi không mong đợi</h2>
          <p className="text-on-surface-variant text-sm max-w-sm">
            {this.state.error?.message ?? 'Vui lòng tải lại trang hoặc liên hệ hỗ trợ.'}
          </p>
          <button
            onClick={() => window.location.reload()}
            className="px-5 py-2.5 bg-primary-container text-on-primary-container rounded-lg text-sm font-semibold hover:bg-primary hover:text-on-primary transition-colors"
          >
            Tải lại trang
          </button>
        </div>
      )
    }

    return this.props.children
  }
}
