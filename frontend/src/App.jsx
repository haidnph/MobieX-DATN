// src/App.jsx
// Root application component wrapping all context providers and router.

import { BrowserRouter } from 'react-router-dom'
import { ThemeProvider } from './contexts/ThemeContext'
import { AuthProvider } from './contexts/AuthContext'
import { CartProvider } from './contexts/CartContext'
import { CompareProvider } from './contexts/CompareContext'
import { WishlistProvider } from './contexts/WishlistContext'
import { ToastProvider } from './contexts/ToastContext'
import ErrorBoundary from './components/feedback/ErrorBoundary'
import AppRoutes from './routes/AppRoutes'

export default function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider>
        <BrowserRouter>
          <AuthProvider>
            <CartProvider>
              <ToastProvider>
                <CompareProvider>
                  <WishlistProvider>
                    <AppRoutes />
                  </WishlistProvider>
                </CompareProvider>
              </ToastProvider>
            </CartProvider>
          </AuthProvider>
        </BrowserRouter>
      </ThemeProvider>
    </ErrorBoundary>
  )
}
