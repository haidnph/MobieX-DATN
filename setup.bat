@echo off
chcp 65001 >nul
title MobieX — Project Setup

echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║          MobieX E-Commerce Platform Setup            ║
echo  ║          FPT Polytechnic — Do an Tot nghiep          ║
echo  ╚══════════════════════════════════════════════════════╝
echo.

:: ── Check PHP ──────────────────────────────────────────────────────────────
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo  [ERROR] PHP not found in PATH.
    echo  Please install Laragon or add PHP to your PATH.
    pause
    exit /b 1
)
for /f "tokens=*" %%i in ('php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"') do set PHP_VER=%%i
echo  [OK] PHP %PHP_VER% found.

:: ── Check Composer ─────────────────────────────────────────────────────────
where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo  [ERROR] Composer not found in PATH.
    echo  Install from: https://getcomposer.org
    pause
    exit /b 1
)
echo  [OK] Composer found.

:: ── Check Node.js ──────────────────────────────────────────────────────────
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo  [WARN] Node.js not found. Frontend setup will be skipped.
    set HAS_NODE=0
) else (
    for /f "tokens=*" %%i in ('node -v') do set NODE_VER=%%i
    echo  [OK] Node.js %NODE_VER% found.
    set HAS_NODE=1
)

echo.
echo  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo  STEP 1: Backend Setup
echo  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

cd backend

:: Install PHP dependencies
echo  [1/5] Installing PHP dependencies...
call composer install --no-interaction --optimize-autoloader
if %errorlevel% neq 0 (
    echo  [ERROR] composer install failed.
    pause
    exit /b 1
)
echo  [OK] PHP dependencies installed.

:: Copy .env if missing
if not exist .env (
    echo  [2/5] Creating .env from .env.example...
    copy .env.example .env
    echo  [OK] .env created.
) else (
    echo  [2/5] .env already exists. Skipping.
)

:: Generate key
echo  [3/5] Generating application key...
php artisan key:generate
echo  [OK] APP_KEY generated.

:: Run mobiex:setup (migrate + seed + storage:link)
echo  [4/5] Running database setup (migrate + seed)...
echo.
echo  NOTE: Make sure MySQL/MariaDB is running and DB settings in .env are correct.
echo  Default: DB_HOST=127.0.0.1, DB_PORT=3306, DB_DATABASE=mobiex_unified, DB_USERNAME=root, DB_PASSWORD=
echo.
php artisan mobiex:setup
if %errorlevel% neq 0 (
    echo  [ERROR] Database setup failed. Check .env DB_* settings.
    pause
    exit /b 1
)

:: Storage link (artisan mobiex:setup already does this, but just in case)
echo  [5/5] Verifying storage link...
php artisan storage:link 2>nul
echo  [OK] Storage configured.

cd ..

:: ── Frontend ───────────────────────────────────────────────────────────────
if "%HAS_NODE%"=="1" (
    echo.
    echo  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    echo  STEP 2: Frontend Setup
    echo  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    cd frontend
    echo  [1/1] Installing Node.js dependencies...
    call npm install
    if %errorlevel% neq 0 (
        echo  [ERROR] npm install failed.
    ) else (
        echo  [OK] Frontend dependencies installed.
    )
    cd ..
)

:: ── Summary ────────────────────────────────────────────────────────────────
echo.
echo  ╔══════════════════════════════════════════════════════╗
echo  ║           Setup Complete! Ready to launch.           ║
echo  ╠══════════════════════════════════════════════════════╣
echo  ║  Configure RBAC_ADMIN_* in backend/.env to create   ║
echo  ║  a local admin. No default privileged credentials.  ║
echo  ╠══════════════════════════════════════════════════════╣
echo  ║  To start:                                           ║
echo  ║  Backend:  cd backend ^&^& php artisan serve          ║
echo  ║  Frontend: cd frontend ^&^& npm run dev               ║
echo  ║  API:      http://127.0.0.1:8000                     ║
echo  ║  SPA:      http://localhost:5173                     ║
echo  ╚══════════════════════════════════════════════════════╝
echo.
pause
