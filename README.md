# RemitIQ Household Service

> Authentication, Household Management, Budgeting, Remittances, and Analytics API for the RemitIQ Ecosystem.

![Laravel](https://img.shields.io/badge/Laravel-12-red)
![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED)
![License](https://img.shields.io/badge/License-MIT-green)

---

# Overview

The **RemitIQ Household Service** is responsible for all user and household-related functionality within the RemitIQ ecosystem.

Unlike the Market Service—which owns exchange rates and market intelligence—the Household Service owns the application's users, authentication, household budgets, remittances, and financial analytics.

This service exposes a REST API consumed by:

- RemitIQ Web
- RemitIQ Mobile
- (Future) Third-party integrations

It is the **identity provider** for the ecosystem and issues JWT access tokens used by other backend services.

---

# Responsibilities

The Household Service owns:

- User Accounts
- Authentication
- Authorization
- Households
- Household Members
- Monthly Budgets
- Budget Categories
- Remittances
- Financial Analytics
- User Profiles
- Email Verification
- Password Reset
- Refresh Tokens

It **does not** manage:

- Exchange Rates
- Exchange Rate Alerts
- Market Data

Those belong to the Market Service.

---

# Architecture

```
                Web App
                   │
                   │
              REST API
                   │
          Household Service
                   │
        ┌──────────┴──────────┐
        │                     │
 Authentication         Business Logic
        │                     │
        └──────────┬──────────┘
                   │
              PostgreSQL
```

Every responsibility lives inside this service.

No other service accesses its database directly.

---

# Technology Stack

| Layer | Technology |
|--------|------------|
| Framework | Laravel 12 |
| Language | PHP 8.4+ |
| Database | PostgreSQL |
| ORM | Eloquent |
| Authentication | JWT |
| Validation | Laravel Validation |
| Testing | PHPUnit + Pest |
| API | REST |
| Queue | Laravel Queue |
| Scheduler | Laravel Scheduler |
| Logging | Monolog |
| Containerization | Docker |
| Reverse Proxy | Nginx |
| CI/CD | GitHub Actions |

---

# Core Features

## Authentication

- User Registration
- Login
- Logout
- JWT Authentication
- Refresh Tokens
- Password Reset
- Email Verification

---

## Household Management

- Create Household
- Update Household
- Invite Members
- Manage Members
- Remove Members

---

## Budget Management

- Monthly Budgets
- Categories
- Budget Tracking
- Budget vs Actual Spending

---

## Remittance Tracking

Store every remittance including:

- Sender
- Household
- Amount Sent
- Amount Received
- Exchange Rate
- Transfer Provider
- Notes
- Date

---

## Analytics

Generate reports including:

- Total Sent
- Monthly Spending
- Annual Spending
- Average Exchange Rate
- Budget Utilization
- Spending Trends

---

# Repository Structure

```
app/
bootstrap/
config/
database/
docs/
public/
resources/
routes/
storage/
tests/

Dockerfile
docker-compose.yml
.env.example
README.md
LICENSE
development-roadmap.txt
```

---

# Development Roadmap

The implementation roadmap is maintained in

```
development-roadmap.txt
```

Progress is tracked phase-by-phase.

---

# API Versioning

Current API version

```
/api/v1
```

Future versions

```
/api/v2
```

---

# Authentication

Authentication uses JWT.

Typical workflow:

```
POST /login

↓

JWT Access Token

↓

Authorization: Bearer <token>

↓

Protected API
```

The Household Service is the only service that issues JWTs.

The Market Service validates them but never creates users.

---

# Database Ownership

The Household Service exclusively owns:

- Users
- Households
- Members
- Budgets
- Budget Categories
- Remittances
- Analytics

No external application accesses this database directly.

---

# Local Development

Clone the repository

```bash
git clone https://github.com/<your-org>/remitIQ-household-service.git

cd remitIQ-household-service
```

Copy the environment file

```bash
cp .env.example .env
```

Install dependencies

```bash
composer install
```

Generate the application key

```bash
php artisan key:generate
```

Start Docker

```bash
docker compose up -d
```

Run migrations

```bash
php artisan migrate
```

Seed the database

```bash
php artisan db:seed
```

Start the development server

```bash
php artisan serve
```

---

# Testing

Run all tests

```bash
php artisan test
```

or

```bash
vendor/bin/phpunit
```

---

# Documentation

Additional documentation is available in:

```
docs/

├── API.md
├── ARCHITECTURE.md
├── DEPLOYMENT.md
└── DEVELOPMENT.md
```

---

# Development Principles

This project follows the RemitIQ engineering standards:

- API First
- Database per Service
- Docker First
- Documentation First
- Production Ready
- Incremental Development
- Testable Code
- SOLID Principles
- Clean Architecture
- Maintainable Code

---

# Deployment

Supported deployment targets include:

- VPS
- AWS
- Azure
- DigitalOcean
- Railway
- Render

Production deployments use Docker containers behind Nginx.

---

# License

This project is licensed under the MIT License.

See the `LICENSE` file for details.

---

# Related Repositories

| Repository | Purpose |
|------------|---------|
| remitIQ-market-service | Exchange Rates & Market Intelligence |
| remitIQ-web | Next.js Web Application |
| remitIQ-mobile | React Native Mobile Application |

---

# Status

Current Phase

```
Phase 1 — Infrastructure
```

Project Status

```
🚧 Under Active Development
```
