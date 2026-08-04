# RemitIQ Household Service Architecture

## 1. Overview

The RemitIQ Household Service is a backend microservice responsible for:

- User authentication
- User management
- Household management
- Family member management
- Budget management
- Remittance tracking
- Financial analytics

The service is part of the RemitIQ ecosystem.

It does not handle:

- Money transfers
- Exchange rate calculation
- Payment processing
- Wallet management

Those responsibilities belong to external providers or the Market Service.

---

# 2. Architecture Principles

The service follows these principles:

## Independent Service

Household Service is a standalone application.

It owns:

- Its source code
- Its database
- Its deployment
- Its CI/CD pipeline


## Database Ownership

The Household Service owns:

- Users
- Authentication data
- Households
- Family members
- Budgets
- Remittance records
- Analytics data


No other service directly accesses this database.


## API Communication

Services communicate only through:

- HTTPS
- REST APIs
- JSON payloads
- JWT authentication


Example:

```
Mobile/Web

     |
     |
 HTTPS API

     |
     |

Household Service
```

---

# 3. System Context

```
                         Users

                           |
                           |

              +------------+------------+
              |                         |

            Web                      Mobile

              |                         |

              +------------+------------+

                           |

                    HTTPS REST API


              +----------+-----------+
              |                      |

   Household Service          Market Service


              |                      |

       Household DB          Market DB
```

---

# 4. Technology Stack

## Backend

Laravel

PHP

## Database

PostgreSQL

## ORM

Eloquent ORM

## Authentication

JWT

## Cache / Queue

Redis

## Containerization

Docker

## API Style

REST API

JSON

---

# 5. Application Layers

The application follows a layered architecture.

```
Request

  |

Routes

  |

Controllers

  |

Services

  |

Repositories

  |

Models

  |

Database
```

---

# 6. Layer Responsibilities


## Routes

Responsible for:

- Endpoint definitions
- Middleware assignment
- API versioning


Example:

```
/api/v1/auth/login
```


---

## Controllers

Responsible for:

- HTTP request handling
- Input extraction
- Calling services
- Returning responses


Controllers should not contain business logic.


---

## Services

Responsible for:

- Business rules
- Complex operations
- Multiple repository coordination


Example:

```
Create household
|
Validate user
|
Create household record
|
Create members
|
Return result
```


---

## Repositories

Responsible for:

- Database access
- Query logic
- Eloquent operations


Repositories should not contain business decisions.


---

## Models

Responsible for:

- Database representation
- Relationships
- Model behavior


---

# 7. Authentication Architecture

Authentication belongs exclusively to Household Service.


Flow:

```
User

 |

Login

 |

Household Service

 |

Validate credentials

 |

Generate JWT

 |

Return token

 |

Client stores token

 |

API requests include:

Authorization:
Bearer <token>
```

---

# 8. Market Service Integration

Household Service communicates with Market Service through APIs.

Example:

```
Household Service

        |

        |

GET /api/v1/rates/latest

        |

        |

Market Service
```

The Household Service may consume:

- Current exchange rates
- Historical rates
- Alert events


The Household Service never accesses the Market database.

---

# 9. Database Design Strategy

Database engine:

```
PostgreSQL
```

Main domains:

```
Users

 |

Households

 |

Members

 |

Budgets

 |

Remittances

 |

Analytics
```

---

# 10. Security Strategy


## Authentication

JWT tokens.


## Authorization

Role and permission checks.


## API Protection

- Rate limiting
- Input validation
- Security headers
- HTTPS only in production


## Data Protection

Sensitive information must never be exposed through APIs.

---

# 11. Queue Architecture

Background tasks use Laravel queues.

Examples:

- Email delivery
- Notification sending
- Analytics generation
- External API synchronization


Architecture:

```
Application

     |

 Queue Job

     |

 Redis

     |

 Worker
```

---

# 12. Testing Strategy


Testing layers:

## Unit Tests

Business logic testing.


## Feature Tests

HTTP endpoint testing.


## Repository Tests

Database operation testing.


## Integration Tests

Communication with external services.


---

# 13. Deployment Architecture


Production:

```
             Nginx

               |

               |

          PHP-FPM Laravel

               |

       -----------------

       |               |

 PostgreSQL        Redis
```

---

# 14. Important Decisions

## Decision: Authentication ownership

Chosen:

Household Service owns authentication.

Reason:

Central identity management.

---

## Decision: Database separation

Chosen:

One database per service.

Reason:

Independent deployment and scalability.

---

## Decision: REST communication

Chosen:

REST APIs.

Reason:

Simple, reliable, language independent communication.

---

# 15. Future Improvements

Possible future additions:

- OAuth providers
- Multi-factor authentication
- Event-driven communication
- Advanced analytics
- Machine learning insights
- Financial recommendations
