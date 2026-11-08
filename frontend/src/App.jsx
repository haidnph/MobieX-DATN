// src/App.jsx
// Root application component wrapping all context providers and router.

import { BrowserRouter } from 'react-router-dom'
import { AuthProvider } from './contexts/AuthContext'
import { CartProvider } from './contexts/CartContext'
import { ToastProvider } from './contexts/ToastContext'
import ErrorBoundary from './components/feedback/ErrorBoundary'
import AppRoutes from './routes/AppRoutes'

export default function App() {
  return (
    <ErrorBoundary>
      <BrowserRouter>
        <AuthProvider>
          <CartProvider>
            <ToastProvider>
              <AppRoutes />
            </ToastProvider>
          </CartProvider>
        </AuthProvider>
      </BrowserRouter>
    </ErrorBoundary>
  )
}
