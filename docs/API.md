# RemitIQ Household Service API Documentation

## Overview

The Household Service exposes REST APIs responsible for:

- Authentication
- User management
- Household management
- Budget management
- Remittance tracking
- Analytics

Base URL:

```
/api/v1
```

Communication format:

```
HTTPS
JSON
REST
```

Authentication:

```
Authorization: Bearer <JWT_TOKEN>
```

---

# Response Format

All APIs use a consistent response structure.

## Success Response

```json
{
    "success": true,
    "message": "Operation successful",
    "data": {}
}
```

---

## Error Response

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {}
}
```

---

# Authentication API

## Register User

Creates a new user account.

```
POST /auth/register
```

Request:

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

Response:

```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {},
        "token": "jwt-token"
    }
}
```

---

## Login

Authenticate user.

```
POST /auth/login
```

Request:

```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

Response:

```json
{
    "success": true,
    "data": {
        "token": "jwt-token"
    }
}
```

---

## Logout

Invalidate current token.

```
POST /auth/logout
```

Authentication required.

---

## Current User

Returns authenticated user information.

```
GET /auth/me
```

Authentication required.

---

# User Profile API

## Get Profile

```
GET /profile
```

---

## Update Profile

```
PUT /profile
```

Request:

```json
{
    "name": "Updated Name"
}
```

---

# Household API

## Create Household

Creates a family household.

```
POST /households
```

Request:

```json
{
    "name": "Family Home",
    "country": "Cameroon"
}
```

---

## List Households

```
GET /households
```

---

## Get Household

```
GET /households/{id}
```

---

## Update Household

```
PUT /households/{id}
```

---

## Delete Household

```
DELETE /households/{id}
```

---

# Household Members API

## Add Member

```
POST /households/{id}/members
```

Request:

```json
{
    "name": "Jane Doe",
    "relationship": "Mother"
}
```

---

## List Members

```
GET /households/{id}/members
```

---

## Remove Member

```
DELETE /members/{id}
```

---

# Budget API

## Create Budget

Creates a monthly household budget.

```
POST /budgets
```

Request:

```json
{
    "household_id": "uuid",
    "category": "Food",
    "amount": 200000,
    "month": "2026-01"
}
```

---

## List Budgets

```
GET /budgets
```

Query:

```
?month=2026-01
```

---

## Update Budget

```
PUT /budgets/{id}
```

---

## Delete Budget

```
DELETE /budgets/{id}
```

---

# Remittance API

## Create Remittance Record

Stores a money transfer record.

```
POST /remittances
```

Request:

```json
{
    "household_id": "uuid",
    "amount_sent": 500,
    "currency": "EUR",
    "amount_received": 330000,
    "exchange_rate": 660,
    "provider": "Provider Name",
    "date": "2026-01-10"
}
```

---

## List Remittances

```
GET /remittances
```

Filters:

```
?year=2026
?month=01
```

---

## Get Remittance

```
GET /remittances/{id}
```

---

# Analytics API

## Monthly Summary

Returns household financial summary.

```
GET /analytics/monthly
```

Query:

```
?month=2026-01
```

Response:

```json
{
    "total_sent": 1000,
    "average_exchange_rate": 655,
    "budget_usage": 75,
    "expenses": []
}
```

---

## Remittance Trends

```
GET /analytics/trends
```

Returns historical insights.

---

# Market Service Integration API

These endpoints consume Market Service data.

---

## Latest Exchange Rate

```
GET /market/rates/latest
```

Example:

```
EUR/XAF
```

---

## Historical Rates

```
GET /market/rates/history
```

Query:

```
?base=EUR
&quote=XAF
&days=30
```

---

## Exchange Rate Alerts

Retrieve triggered alerts.

```
GET /market/alerts
```

---

# Health API

## Health Check

```
GET /health
```

Response:

```json
{
    "status": "ok",
    "service": "household-service"
}
```

---

# API Versioning

Current version:

```
/api/v1
```

Future versions:

```
/api/v2
```

---

# HTTP Status Codes

| Code | Meaning |
|---|---|
|200|Success|
|201|Created|
|400|Bad Request|
|401|Unauthorized|
|403|Forbidden|
|404|Not Found|
|422|Validation Error|
|500|Server Error|

---

# Future API Additions

Planned:

- Family collaboration
- Push notifications
- Financial goals
- AI recommendations
- Advanced analytics
- Multi-currency support
