# MobieX Laravel API — Backend Setup

## Requirements

- PHP version supported by the Laravel version installed in `backend/`
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

Update the local database credentials in `.env`.

Do not commit `.env` or any secrets.

## 2. Prepare the development database

Create a separate database named `mobilex_api_dev`.

Do not configure the Laravel backend to use the legacy PHP
application database `mobilex_store`.

## 3. Install and initialize

```bash
composer install
php artisan key:generate
```

## 4. Inspect routes and run tests

```bash
php artisan route:list
php artisan test
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

## Scope of this foundation

- The legacy PHP MVC application remains unchanged.
- The Laravel API runs as an independent application.
- The API uses a separate development database.
- No business migrations for users, roles, permissions,
  categories, brands, products, stock or attributes are included.
- The authentication mode for the SPA/API must be agreed
  before implementing the authentication feature.