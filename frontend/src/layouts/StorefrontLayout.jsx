// src/layouts/StorefrontLayout.jsx
// Wraps public-facing pages: Header + main content + Footer.

import { Outlet } from 'react-router-dom'
import Header from '../components/layout/Header'
import Footer from '../components/layout/Footer'
import CustomerSupportChat from '../components/common/CustomerSupportChat'
import CompareDock from '../components/common/CompareDock'

export default function StorefrontLayout() {
  return (
    <div className="min-h-screen flex flex-col bg-background">
      <Header />
      <main className="flex-1 pt-[104px] lg:pt-20">
        <Outlet />
      </main>
      <Footer />
      <CompareDock />
      <CustomerSupportChat />
    </div>
  )
}
