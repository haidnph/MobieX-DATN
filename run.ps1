#Requires -Version 5.1
<#
.SYNOPSIS
    MobieX E-Commerce Platform — 1-Click Run Script (PowerShell)
#>
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║       MobieX E-Commerce Platform — Khởi động         ║" -ForegroundColor Cyan
Write-Host "  ║       FPT Polytechnic — Đồ án Tốt nghiệp             ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Write-Host "  [1/2] Đang khởi động Backend API (Laravel :8000)..." -ForegroundColor Yellow
Start-Process -FilePath "powershell.exe" -ArgumentList "-NoExit", "-Command", "Set-Location '$PSScriptRoot\backend'; php artisan serve"

Write-Host "  [2/2] Đang khởi động Frontend (React Vite :5173)..." -ForegroundColor Yellow
Start-Process -FilePath "powershell.exe" -ArgumentList "-NoExit", "-Command", "Set-Location '$PSScriptRoot\frontend'; npm run dev"

Start-Sleep -Seconds 3

Write-Host ""
Write-Host "  [✓] Đã khởi động xong cả Backend và Frontend!" -ForegroundColor Green
Write-Host ""
Write-Host "  🌐 Frontend SPA: http://localhost:5173" -ForegroundColor White
Write-Host "  🔌 Backend API:  http://127.0.0.1:8000" -ForegroundColor White
Write-Host "  📚 API Docs:     http://127.0.0.1:8000/docs/api" -ForegroundColor White
Write-Host ""
Write-Host "  👤 Demo Accounts:" -ForegroundColor Green
Write-Host "    - Super Admin: superadmin@gmail.com / superadmin1234567890"
Write-Host "    - Admin:       admin@mobilex.tech / AdminPass123456!"
Write-Host "    - Customer:    customer@mobilex.tech / Password123456!"
Write-Host ""

Start-Process "http://localhost:5173"
