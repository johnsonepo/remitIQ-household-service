# RemitIQ Household Service Deployment Guide

## Overview

This document describes the deployment strategy for the RemitIQ Household Service.

The service is designed to be:

- Independently deployable
- Containerized
- Environment driven
- Production ready
- Scalable

---

# 1. Production Architecture

Production deployment:

```
                    Internet

                       |

                    Nginx

                       |

                 Laravel Application

                    PHP-FPM

                       |

        --------------------------------

        |                              |

   PostgreSQL                       Redis


                       |

                 Queue Workers


                       |

                  Scheduler
```

---

# 2. Deployment Requirements

Recommended production environment:

- Linux server
- Docker Engine
- Docker Compose
- PostgreSQL
- Redis
- Nginx
- SSL Certificate


Possible providers:

- AWS
- DigitalOcean
- Azure
- Hetzner
- Railway
- Render

---

# 3. Environment Configuration

Production must use:

```
APP_ENV=production

APP_DEBUG=false
```

Required variables:

```
APP_KEY

DB_HOST

DB_DATABASE

DB_USERNAME

DB_PASSWORD

JWT_SECRET
```

Secrets must never be committed to Git.

---

# 4. Docker Production Build

Build image:

```bash
docker build \
-t remitiq-household-service .
```

Run container:

```bash
docker run \
-d \
-p 9000:9000 \
remitiq-household-service
```

---

# 5. Docker Compose Production

Production compose should include:

Services:

```
household-service

postgres

redis

nginx

queue-worker

scheduler
```

Each service runs independently.

---

# 6. Database Deployment

Before release:

Run migrations:

```bash
php artisan migrate --force
```

Never run migrations manually in production.

---

# 7. Cache Optimization

Production optimization:

```bash
php artisan optimize
```

or:

```bash
php artisan config:cache

php artisan route:cache

php artisan view:cache
```

---

# 8. Queue Workers

Background processing uses Laravel queues.

Examples:

- Email delivery
- Notifications
- Analytics generation


Production worker:

```bash
php artisan queue:work \
--tries=3
```

Recommended process manager:

- Supervisor
- Laravel Horizon (future)

---

# 9. Scheduler

Laravel scheduled tasks require:

```
php artisan schedule:run
```

Production cron:

```
* * * * * php /var/www/html/artisan schedule:run
```

---

# 10. Health Checks

Every deployment must verify:

Application:

```
GET /api/v1/health
```

Expected:

```json
{
    "status": "ok",
    "service": "household-service"
}
```

Database:

```bash
php artisan db:monitor
```

Redis:

```bash
redis-cli ping
```

---

# 11. Monitoring

Production monitoring should include:

## Application Logs

Laravel logs:

```
storage/logs/laravel.log
```


## Metrics

Track:

- Request count
- Response time
- Error rate
- Queue failures
- Database performance


Future integration:

- Prometheus
- Grafana
- Loki

---

# 12. Security Checklist

Before production:

## Application

```
APP_DEBUG=false
```

## Database

- Strong password
- Private network access
- Regular backups


## API

Enable:

- Rate limiting
- Security headers
- Request validation
- JWT expiration


## Server

Enable:

- Firewall
- HTTPS
- Automatic updates

---

# 13. CI/CD Pipeline

Recommended workflow:

```
Push Code

    |

GitHub Actions

    |

Run Tests

    |

Build Docker Image

    |

Security Scan

    |

Deploy

    |

Health Check

```

Pipeline stages:

1. Install dependencies
2. Run tests
3. Run static analysis
4. Build image
5. Deploy
6. Verify health

---

# 14. Backup Strategy

Database backups:

Daily:

```
PostgreSQL dump
```

Backup:

- Users
- Households
- Budgets
- Remittance history

Store backups separately from production server.

---

# 15. Rollback Strategy

If deployment fails:

1. Stop new version
2. Restore previous Docker image
3. Roll back database migration if required
4. Verify health endpoint


Example:

```bash
docker compose down

docker compose up -d previous-version
```

---

# 16. Release Process

Every release follows:

```
Feature Development

        |

Testing

        |

Pull Request

        |

Code Review

        |

Merge

        |

Build Image

        |

Deploy

        |

Monitor
```

---

# 17. Production Principles

1. Never deploy without tests.
2. Never expose database publicly.
3. Never commit secrets.
4. Always monitor errors.
5. Keep deployments reversible.
6. Keep services independent.
7. Document every production change.
