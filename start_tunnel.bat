@echo off
title MobieX Cloudflare Tunnel
echo ===================================================
echo     KHOI DONG CLOUDFLARE TUNNEL CHO MOBIEX API
echo ===================================================
echo.
echo Dang mo duong ham HTTPS cong khai cho http://localhost:8000...
echo Hay tim dong co dang: https://xxxx-xxxx.trycloudflare.com
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:8000
pause
