@echo off
setlocal enabledelayedexpansion
title MobieX Public HTTPS Tunnel (IPN / Webhook)
echo ===============================================================
echo   KHOI DONG PUBLIC HTTPS TUNNEL CHO MOBIEX PAYMENT WEBHOOKS
echo ===============================================================
echo.
echo Muc tieu: Cung cap dia chi HTTPS cong khai tro ve http://127.0.0.1:8000
echo de VNPay IPN, ZaloPay Callback va MoMo IPN co the goi toi may local.
echo.
echo Sau khi thay URL cong khai dang https://xxxx.trycloudflare.com (hoac ngrok):
echo   - VNPay IPN URL (cau hinh tren Portal VNPay):
echo       https://^<tunnel-host^>/api/payments/vnpay/ipn
echo   - ZaloPay Callback (cap nhat ZALOPAY_CALLBACK_URL trong backend/.env):
echo       https://^<tunnel-host^>/api/payments/zalopay/callback
echo   - MoMo IPN (cap nhat MOMO_CALLBACK_URL trong backend/.env):
echo       https://^<tunnel-host^>/api/payments/momo/callback
echo.

set "CLOUDFLARED_BIN="
where cloudflared >nul 2>nul
if %errorlevel%==0 (
    set "CLOUDFLARED_BIN=cloudflared"
) else if exist "C:\Program Files (x86)\cloudflared\cloudflared.exe" (
    set "CLOUDFLARED_BIN=C:\Program Files (x86)\cloudflared\cloudflared.exe"
) else if exist "C:\Program Files\cloudflared\cloudflared.exe" (
    set "CLOUDFLARED_BIN=C:\Program Files\cloudflared\cloudflared.exe"
)

if defined CLOUDFLARED_BIN (
    echo [OK] Dang chay Cloudflare Tunnel toi http://127.0.0.1:8000 ...
    "%CLOUDFLARED_BIN%" tunnel --url http://127.0.0.1:8000
    goto :end
)

where ngrok >nul 2>nul
if %errorlevel%==0 (
    echo [OK] Dang chay ngrok HTTPS Tunnel toi http://127.0.0.1:8000 ...
    ngrok http 8000
    goto :end
)

echo [CHUA CAI DAT] Khong tim thay cloudflared hoac ngrok tren may nay.
echo.
echo Vui long cai dat mot trong hai cong cu:
echo   1. Cloudflare Tunnel (mien phi, khong can tai khoan):
echo      winget install --id Cloudflare.cloudflared -e
echo   2. Hoac ngrok:
echo      winget install --id Ngrok.Ngrok -e
echo.
echo Sau khi cai xong, mo terminal moi va chay lai start_tunnel.bat.
:end
pause
