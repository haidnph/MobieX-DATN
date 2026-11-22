@echo off
chcp 65001 >nul
title MobieX — Launcher

echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║       MobieX E-Commerce Platform — Khởi động         ║
echo  ║       FPT Polytechnic — Đồ án Tốt nghiệp             ║
echo  ╚══════════════════════════════════════════════════════╝
echo.

echo  [1/2] Đang khởi động Backend API (Laravel :8000)...
start "MobieX - Backend API" cmd /k "cd /d "%~dp0backend" && php artisan serve"

echo  [2/2] Đang khởi động Frontend (React Vite :5173)...
start "MobieX - Frontend" cmd /k "cd /d "%~dp0frontend" && npm run dev"

timeout /t 3 /nobreak >nul

echo.
echo  ✓ Đã khởi động xong cả Backend và Frontend!
echo.
echo  🌐 Frontend SPA: http://localhost:5173
echo  🔌 Backend API:  http://127.0.0.1:8000
echo  📚 API Docs:     http://127.0.0.1:8000/docs/api
echo.
echo  👤 Demo Accounts:
echo    - Super Admin: superadmin@gmail.com / superadmin1234567890
echo    - Admin:       admin@mobilex.tech / AdminPass123456!
echo    - Customer:    customer@mobilex.tech / Password123456!
echo.
echo  Đang mở trình duyệt...
start http://localhost:5173
