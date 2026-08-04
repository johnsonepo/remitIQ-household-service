# RemitIQ Household Service Development Guide

## Overview

This document explains the local development workflow for the RemitIQ Household Service.

Technology stack:

- Laravel
- PHP 8.3
- PostgreSQL
- Redis
- Docker
- PHPUnit / Pest
- JWT Authentication

---

# 1. Requirements

Before starting, install:

- Git
- Docker
- Docker Compose

Optional for local development:

- PHP 8.3
- Composer
- PostgreSQL client

The recommended workflow uses Docker.

---

# 2. Clone Repository

Clone the repository:

```bash
git clone <repository-url>

cd remitIQ-household-service
```

---

# 3. Environment Setup

Copy the environment file:

```bash
cp .env.example .env
```

Generate Laravel application key:

```bash
docker compose exec household-service php artisan key:generate
```

Configure environment variables:

```
APP_ENV=local

DB_CONNECTION=pgsql

DB_HOST=household-db

DB_DATABASE=remitiq_household

DB_USERNAME=postgres

DB_PASSWORD=postgres
```

---

# 4. Start Docker Environment

Build containers:

```bash
docker compose build
```

Start services:

```bash
docker compose up -d
```

Check running containers:

```bash
docker compose ps
```

Expected services:

```
household-service
household-db
redis
mailpit
```

---

# 5. Install Dependencies

Install Composer packages:

```bash
docker compose exec household-service composer install
```

---

# 6. Database Setup

Run migrations:

```bash
docker compose exec household-service php artisan migrate
```

Seed development data:

```bash
docker compose exec household-service php artisan db:seed
```

Reset database:

```bash
docker compose exec household-service php artisan migrate:fresh --seed
```

---

# 7. Running Laravel Commands

Artisan commands:

```bash
docker compose exec household-service php artisan
```

Example:

```bash
php artisan make:model Household -m
```

Inside container:

```bash
docker compose exec household-service php artisan make:model Household -m
```

---

# 8. Application Structure

The project follows:

```
app/

├── Http/
│
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
│
├── Models/
│
├── Services/
│
├── Repositories/
│
├── Exceptions/
│
└── Jobs/
```

Responsibilities:

## Controllers

HTTP handling only.

No business logic.

---

## Services

Business rules.

Example:

```
Create household

Validate user

Create household

Add members
```

---

## Repositories

Database operations.

Example:

```
Find household by user

Get monthly budgets
```

---

## Models

Database entities and relationships.

---

# 9. Testing

Run all tests:

```bash
docker compose exec household-service php artisan test
```

Run specific test:

```bash
php artisan test tests/Feature/AuthTest.php
```

---

# 10. Code Quality

Before committing:

Run formatting:

```bash
composer pint
```

Run tests:

```bash
php artisan test
```

---

# 11. Queue Workers

Start queue worker:

```bash
docker compose exec household-service php artisan queue:work
```

Used for:

- emails
- notifications
- analytics generation

---

# 12. Scheduler

Run scheduler locally:

```bash
docker compose exec household-service php artisan schedule:work
```

Used for:

- reports
- synchronization
- cleanup tasks

---

# 13. Redis

Check Redis:

```bash
docker compose exec redis redis-cli ping
```

Expected:

```
PONG
```

---

# 14. Mail Testing

Mailpit interface:

```
http://localhost:8025
```

Used to test:

- verification emails
- password reset
- notifications

---

# 15. Common Problems


## Database Connection Error

Check containers:

```bash
docker compose ps
```

Check PostgreSQL:

```bash
docker compose logs household-db
```


---

## Permission Problems

Fix Laravel permissions:

```bash
docker compose exec household-service chmod -R 775 storage bootstrap/cache
```

---

## Container Rebuild

After Dockerfile changes:

```bash
docker compose build --no-cache

docker compose up -d
```

---

# 16. Git Workflow

Create feature branch:

```bash
git checkout -b feature/name
```

Commit:

```bash
git add .

git commit -m "feat: add feature"
```

Push:

```bash
git push origin feature/name
```

---

# 17. Development Principles

Follow these rules:

1. Keep controllers thin.
2. Put business logic in services.
3. Keep database queries in repositories.
4. Validate every request.
5. Write tests with features.
6. Never access another service database.
7. Communicate through APIs only.
8. Document architectural decisions.
9. Keep commits small and meaningful.
10. Build incrementally.
