#Requires -Version 5.1
<#
.SYNOPSIS
    MobieX E-Commerce Platform — One-Click Setup Script (PowerShell)

.DESCRIPTION
    Sets up the full MobieX development environment:
    - Installs PHP (Composer) and Node.js (npm) dependencies
    - Creates .env from .env.example
    - Generates APP_KEY
    - Runs database migrations and seeds
    - Creates storage symlink
    - Installs frontend Node modules

.PARAMETER Fresh
    Drop and recreate database tables (WARNING: destroys all existing data)

.PARAMETER SkipFrontend
    Skip the npm install step

.EXAMPLE
    .\setup.ps1
    .\setup.ps1 -Fresh
    .\setup.ps1 -SkipFrontend
#>

[CmdletBinding()]
param(
    [switch]$Fresh,
    [switch]$SkipFrontend
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Write-Header([string]$title) {
    Write-Host ""
    Write-Host "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
    Write-Host "  $title" -ForegroundColor White
    Write-Host "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
}

function Write-Step([string]$msg) {
    Write-Host "  [»] $msg" -ForegroundColor DarkCyan
}

function Write-OK([string]$msg) {
    Write-Host "  [✓] $msg" -ForegroundColor Green
}

function Write-Warn([string]$msg) {
    Write-Host "  [!] $msg" -ForegroundColor Yellow
}

function Write-Fail([string]$msg) {
    Write-Host "  [✗] $msg" -ForegroundColor Red
}

# ── Banner ──────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "  ║       MobieX E-Commerce Platform — Setup Wizard      ║" -ForegroundColor Cyan
Write-Host "  ║       FPT Polytechnic — Đồ án Tốt nghiệp             ║" -ForegroundColor Cyan
Write-Host "  ╚══════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# ── Prerequisites ────────────────────────────────────────────────────────────
Write-Header "Checking Prerequisites"

$phpPath = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpPath) {
    Write-Fail "PHP not found. Install Laragon from https://laragon.org or add PHP to PATH."
    exit 1
}
$phpVer = & php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;"
Write-OK "PHP $phpVer"

$composerPath = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composerPath) {
    Write-Fail "Composer not found. Install from https://getcomposer.org"
    exit 1
}
Write-OK "Composer found"

$nodeAvailable = $false
$nodePath = Get-Command node -ErrorAction SilentlyContinue
if ($nodePath) {
    $nodeVer = & node -v
    Write-OK "Node.js $nodeVer"
    $nodeAvailable = $true
} else {
    Write-Warn "Node.js not found. Frontend setup will be skipped."
}

# ── Backend ───────────────────────────────────────────────────────────────────
Write-Header "Step 1: Backend Setup (Laravel)"

Set-Location backend

Write-Step "Installing PHP dependencies..."
& composer install --no-interaction --optimize-autoloader
if ($LASTEXITCODE -ne 0) { Write-Fail "composer install failed"; exit 1 }
Write-OK "PHP dependencies installed"

if (-not (Test-Path .env)) {
    Write-Step "Creating .env from .env.example..."
    Copy-Item .env.example .env
    Write-OK ".env created"
} else {
    Write-Warn ".env already exists — skipping copy"
}

Write-Step "Generating application key..."
& php artisan key:generate
Write-OK "APP_KEY generated"

Write-Step "Running database setup..."
Write-Host ""
Write-Host "  NOTE: Ensure MySQL/MariaDB is running." -ForegroundColor Gray
Write-Host "  Default DB config: host=127.0.0.1, db=mobiex_unified, user=root, password=(empty)" -ForegroundColor Gray
Write-Host ""

if ($Fresh) {
    & php artisan mobiex:setup --fresh
} else {
    & php artisan mobiex:setup
}
if ($LASTEXITCODE -ne 0) { Write-Fail "Database setup failed. Check .env DB_* settings."; exit 1 }

Set-Location ..

# ── Frontend ──────────────────────────────────────────────────────────────────
if ($nodeAvailable -and -not $SkipFrontend) {
    Write-Header "Step 2: Frontend Setup (React + Vite)"
    Set-Location frontend

    Write-Step "Installing Node.js dependencies..."
    & npm install
    if ($LASTEXITCODE -ne 0) {
        Write-Warn "npm install failed. Run 'npm install' manually in the frontend directory."
    } else {
        Write-OK "Frontend dependencies installed"
    }

    Set-Location ..
} elseif ($SkipFrontend) {
    Write-Warn "Frontend setup skipped (-SkipFrontend flag)."
}

# ── Summary ───────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "  ║            Setup Complete! Ready to launch.          ║" -ForegroundColor Green
Write-Host "  ╠══════════════════════════════════════════════════════╣" -ForegroundColor Green
Write-Host "  ║  Demo Accounts:                                      ║" -ForegroundColor Green
Write-Host "  ║  Admin:      admin@mobilex.tech / AdminPass123456!   ║" -ForegroundColor Green
Write-Host "  ║  SuperAdmin: superadmin@gmail.com                    ║" -ForegroundColor Green
Write-Host "  ║              superadmin1234567890                    ║" -ForegroundColor Green
Write-Host "  ║  Customer:   customer@mobilex.tech                   ║" -ForegroundColor Green
Write-Host "  ║              Password123456!                         ║" -ForegroundColor Green
Write-Host "  ╠══════════════════════════════════════════════════════╣" -ForegroundColor Green
Write-Host "  ║  Start the servers:                                  ║" -ForegroundColor Green
Write-Host "  ║  cd backend ; php artisan serve                      ║" -ForegroundColor Green
Write-Host "  ║  cd frontend ; npm run dev                           ║" -ForegroundColor Green
Write-Host "  ║  API → http://127.0.0.1:8000                         ║" -ForegroundColor Green
Write-Host "  ║  SPA → http://localhost:5173                         ║" -ForegroundColor Green
Write-Host "  ╚══════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""
