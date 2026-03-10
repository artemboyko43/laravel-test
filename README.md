# Laravel Backend Tasks

Laravel application implementing three backend tasks with testing, Docker support, and CI/CD pipeline.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [API Endpoints](#api-endpoints)
- [Console Commands](#console-commands)
- [Testing](#testing)
- [Docker Setup](#docker-setup)
- [Project Structure](#project-structure)
- [Code Quality](#code-quality)
- [CI/CD](#cicd)

## Features

### Task 1: User Revenue Report API
- Generate revenue summary per user based on completed orders
- Optional date range filtering
- Pagination support
- N+1 query prevention using withCount and withSum
- Request validation with Form Requests

### Task 2: Order Reconciliation Command
- Verify order totals match calculated values from order items
- Detect and log mismatches to console and log files
- Floating point precision handling with tolerance
- Memory-efficient processing using cursor for large datasets
- Summary output with statistics

### Task 3: Fixed User Controller
- Fixed N+1 query problems using withCount
- Implemented pagination
- Removed unnecessary relationship loading
- API Resource for consistent response formatting
- Request validation

## Requirements

- PHP 8.2 or higher (8.4 recommended)
- Composer
- MySQL 8.0 or higher (SQLite for testing)
- Redis (optional, for caching)

## Installation

### Local Installation

1. Clone the repository
   ```bash
   git clone <repository-url>
   cd upwork-test
   ```

2. Install dependencies
   ```bash
   composer install
   ```

3. Environment setup
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database
   Update `.env` with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Run migrations
   ```bash
   php artisan migrate
   ```

6. Start the server
   ```bash
   php artisan serve
   ```

The application will be available at `http://localhost:8000`

### Docker Installation

1. Start containers
   ```bash
   docker-compose -f docker-compose.dev.yml up -d --build
   ```

2. Access the application
   - API: http://localhost:8000
   - MySQL: localhost:3308
   - Redis: localhost:6380

3. Run commands inside container
   ```bash
   docker-compose -f docker-compose.dev.yml exec app php artisan <command>
   ```

## API Endpoints

### Base URL
```
http://localhost:8000/api
```

### 1. Get Users with Order Counts
**Endpoint:** `GET /api/users`

Returns paginated list of users with their order counts.

**Query Parameters:**
- `page` (optional, integer, min: 1) - Page number
- `per_page` (optional, integer, min: 1, max: 100, default: 15) - Items per page

**Request Example:**
```bash
curl "http://localhost:8000/api/users?page=1&per_page=15"
```

**Response Example:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "orders_count": 5
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

### 2. User Revenue Report
**Endpoint:** `GET /api/reports/user-revenue`

Returns revenue summary per user based on completed orders only.

**Query Parameters:**
- `start_date` (optional, date, format: Y-m-d) - Start date for filtering
- `end_date` (optional, date, format: Y-m-d) - End date for filtering
- `page` (optional, integer, min: 1) - Page number
- `per_page` (optional, integer, min: 1, max: 100, default: 15) - Items per page

**Request Example:**
```bash
curl "http://localhost:8000/api/reports/user-revenue?start_date=2024-01-01&end_date=2024-12-31&per_page=20"
```

**Response Example:**
```json
{
  "data": [
    {
      "user_id": 1,
      "email": "john@example.com",
      "orders_count": 10,
      "total_revenue": 1500.50
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "last_page": 3
  }
}
```

## Console Commands

### Order Reconciliation
**Command:** `php artisan reconcile:orders`

Checks if each order's stored total matches the calculated sum of its order items.

**Usage:**
```bash
php artisan reconcile:orders
```

**Output:**
- Lists all mismatched orders with stored total, calculated total, and difference
- Displays summary with total orders checked, mismatched count, and percentage
- Returns exit code 0 (success) if no mismatches, 1 (failure) if mismatches found

**Example Output:**
```
Starting order reconciliation...
Order #123: Stored=100.00, Calculated=99.50, Difference=0.50

=== Reconciliation Summary ===
Total Orders Checked: 1,000
Mismatched Orders: 5
Mismatch Percentage: 0.50%
```

## Testing

### Run All Tests
```bash
php vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# User Revenue Report Tests
php vendor/bin/phpunit tests/Feature/UserRevenueReportTest.php

# Order Reconciliation Tests
php vendor/bin/phpunit tests/Feature/ReconcileOrdersCommandTest.php

# User Controller Tests
php vendor/bin/phpunit tests/Feature/UserControllerTest.php
```

### Run Tests with Coverage
```bash
php vendor/bin/phpunit --coverage-html coverage
```

### Test Statistics
- Total Tests: 26
- Total Assertions: 82
- Coverage: All implemented features

### Docker Testing
```bash
docker-compose -f docker-compose.dev.yml exec app php vendor/bin/phpunit
```

## Docker Setup

### Development Environment

**Start services:**
```bash
docker-compose -f docker-compose.dev.yml up -d --build
```

**Stop services:**
```bash
docker-compose -f docker-compose.dev.yml down
```

**View logs:**
```bash
docker-compose -f docker-compose.dev.yml logs -f app
```

**Execute commands:**
```bash
docker-compose -f docker-compose.dev.yml exec app php artisan <command>
```

### Production Environment

**Build and start:**
```bash
docker-compose up -d --build
```

### Services
- app: PHP 8.4-FPM application
- nginx: Web server (port 8000)
- db: MySQL 8.0 (port 3308)
- redis: Redis cache (port 6380)

## Project Structure

```
upwork-test/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── ReconcileOrders.php      # Task 2: Reconciliation command
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── UserController.php      # Task 3: Fixed user controller
│   │   │   └── ReportController.php    # Task 1: Revenue report
│   │   ├── Requests/
│   │   │   ├── UserIndexRequest.php
│   │   │   └── UserRevenueReportRequest.php
│   │   └── Resources/
│   │       ├── UserResource.php
│   │       └── UserRevenueResource.php
│   └── Models/
│       ├── User.php
│       ├── Order.php
│       └── OrderItem.php
├── database/
│   ├── factories/                       # Model factories for testing
│   └── migrations/                      # Database migrations
├── docker/
│   ├── entrypoint.sh                    # Docker entrypoint script
│   ├── nginx/
│   │   └── default.conf                 # Nginx configuration
│   └── php/
│       └── local.ini                     # PHP configuration
├── routes/
│   ├── api.php                          # API routes
│   └── web.php                          # Web routes
├── tests/
│   └── Feature/                         # Feature tests
├── .github/
│   └── workflows/
│       └── ci.yml                        # CI/CD pipeline
├── docker-compose.dev.yml                # Development Docker Compose
├── docker-compose.yml                   # Production Docker Compose
├── Dockerfile                           # Production Dockerfile
├── Dockerfile.dev                       # Development Dockerfile
└── phpunit.xml                          # PHPUnit configuration
```

## Code Quality

### Laravel Pint
Code style is enforced using Laravel Pint (PSR-12 standard).

**Check code style:**
```bash
vendor/bin/pint --test
```

**Fix code style:**
```bash
vendor/bin/pint
```

### PHP Lint
Check for syntax errors:
```bash
find app -type f -name "*.php" -exec php -l {} \;
find routes -type f -name "*.php" -exec php -l {} \;
find database -type f -name "*.php" -exec php -l {} \;
```

## CI/CD

### GitHub Actions

The project includes a CI/CD pipeline that runs on:
- Push to main, develop, or feature branches
- Pull requests to main or develop

**Pipeline Jobs:**
1. Tests: Runs PHPUnit tests with SQLite in-memory database
2. Lint: Runs PHP syntax check and Laravel Pint

**Pipeline configuration:** `.github/workflows/ci.yml`

## Database Schema

### Tables

**users**
- id (primary key)
- name
- email (unique)
- password
- email_verified_at
- remember_token
- timestamps

**orders**
- id (primary key)
- user_id (foreign key to users.id)
- status (enum: pending, processing, completed, cancelled)
- total_amount (decimal 10,2)
- timestamps
- Indexes: user_id, status, created_at

**order_items**
- id (primary key)
- order_id (foreign key to orders.id)
- price (decimal 10,2)
- quantity (integer)
- timestamps
- Index: order_id

## Security

- Input validation on all endpoints using Form Requests
- SQL injection prevention through Eloquent ORM
- Password hashing using bcrypt
- API Resources for controlled data exposure
- Request validation with proper error messages

## Performance Optimizations

- N+1 Prevention: Uses withCount, withSum, and eager loading
- Memory Efficiency: Uses cursor for large dataset processing
- Database Indexes: Proper indexes on foreign keys and query fields
- Pagination: All list endpoints support pagination

## Development

### Code Style
- Follows PSR-12 coding standards
- Enforced with Laravel Pint
- Type hints throughout
- Clean, self-documenting code

### Best Practices
- Single Responsibility Principle
- DRY (Don't Repeat Yourself)
- Proper error handling
- Comprehensive testing
- Docker-first development

## License

Proprietary

## Author

Developer

---

Last Updated: 2026-03-09
