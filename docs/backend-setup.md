# MobieX Laravel API — Backend Setup

## Requirements

- PHP version supported by the Laravel version installed in `backend/` (PHP 8.3+)
- Composer 2.x
- MySQL
- Required PHP extensions for Laravel and PDO MySQL

## 1. Configure environment

From the repository root:

```bash
cd backend
```

If `.env` does not exist, copy the example:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Update the local database credentials and optional initial admin seed configuration in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mobilex_api_dev
DB_USERNAME=root
DB_PASSWORD=

# Optional: seed local admin user
RBAC_ADMIN_NAME="System Administrator"
RBAC_ADMIN_EMAIL="admin@mobiex.local"
RBAC_ADMIN_PASSWORD="AdminSecurePassword123!"
```

Do not commit `.env` or any secrets.

## 2. Prepare the development database

Create a separate database named `mobilex_api_dev` (and test database `mobilex_api_test` for MySQL testing).

Do not configure the Laravel backend to use the legacy PHP application database `mobilex_store`.

## 3. Install and initialize

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
```

`db:seed` idempotently seeds canonical system roles (`admin`, `staff`, `customer`), canonical permissions, and the local administrator user (when configured in `.env`).

## 4. Inspect routes, run tests, and code style

```bash
php artisan route:list
php artisan test
./vendor/bin/pint --test
```

The API health endpoint should be registered as:

```text
GET /api/health
```

## 5. Run the backend

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Check the endpoint:

```text
http://127.0.0.1:8001/api/health
```

Expected response:

```json
{
    "status": "ok",
    "service": "MobieX API"
}
```

## Architecture and boundary

- The legacy PHP MVC application remains unchanged.
- The Laravel API runs as an independent application inside `backend/`.
- The API uses a separate development database.
- Authentication uses Laravel Sanctum Bearer Tokens.
- Access control uses custom RBAC middleware (`permission:<slug>`) and active-account enforcement (`active`).
