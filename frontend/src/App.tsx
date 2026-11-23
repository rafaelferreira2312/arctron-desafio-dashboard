import { NavLink, Navigate, Route, Routes } from 'react-router-dom'
import { DashboardPage } from './pages/DashboardPage'
import { PurchasesPage } from './pages/PurchasesPage'

export function App() {
  return (
    <>
      <header className="topbar">
        <div className="topbar__inner">
          <div className="brand">
            <img src="/favicon.svg" alt="" width={26} height={26} />
            <span>Painel de compras</span>
          </div>
          <nav className="nav" aria-label="Principal">
            <NavLink to="/" end>
              Visão geral
            </NavLink>
            <NavLink to="/compras">Compras</NavLink>
          </nav>
        </div>
      </header>

      <main className="page">
        <Routes>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/compras" element={<PurchasesPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </main>
    </>
  )
}
