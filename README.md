# Instructor Revenue Ledger

> **The Financial Core of an Online Course Platform (LMS)**  
> Built with Laravel 12, Filament v5, and MySQL/SQLite. Engineered for absolute financial correctness, mathematical conservation of money, idempotency under high concurrency, and resilience against remote payment gateway failures at scale (500,000+ active subscriptions).

---

## 🌟 Quick Links

* 📖 **[Architecture & Design Document](docs/ARCHITECTURE.md)**: Deep dive into the immutable double-entry ledger, Hare-Niemeyer penny rounding, two-tier concurrency locks, Byzantine gateway timeouts, and 500k scaling strategies.
* 🤖 **[AI Usage & Architectural Ownership](docs/AI_USAGE.md)**: Human-AI collaboration breakdown, architectural decisions made, and the **Senior Bonus** (handling mid-term plan upgrades).

---

## 🚀 Setup Instructions

### Prerequisites
* **PHP**: `>= 8.3` (with `pdo`, `mbstring`, `bcmath`, `curl`)
* **Composer**: `>= 2.0`
* **Database**: MySQL 8.0+ or SQLite
* **Node.js**: `>= 18.x` (for building frontend assets if modifying Filament assets)

### Installation Steps

1. **Clone the repository**:
   ```bash
   git clone https://github.com/soheib5566/Code_quest.git
   cd Code_quest
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Edit `.env` to configure your database connection (MySQL or SQLite).*

4. **Run Database Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```
   *Seeds plans, realistic instructors, courses, students, accounting periods, and financial ledger data.*  
   *Pre-configured Admin Login:* `admin@lms.test` / `password`

5. **Start the Local Development Server**:
   ```bash
   php artisan serve
   ```
   *The Filament administrative dashboard is available at: `http://localhost:8000/admin`*

---

## 🧪 How to Run Tests

The test suite contains **20 comprehensive unit and feature tests with 72 assertions** verifying financial precision, concurrency defense, retries, and timeout safety.

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites

| Test Suite | File | What It Verifies |
| :--- | :--- | :--- |
| **Penny Rounding & Split Math** | `tests/Unit/RevenueSplitCalculatorTest.php` | Hare-Niemeyer allocation, largest remainder distribution, deterministic tie-breaking, zero-consumption guards. |
| **Mock Gateway Scenarios** | `tests/Unit/MockPaymentGatewayTest.php` | Gateway outcomes: Success, Hard Failure (400), and Timeout After Success (`IN_DOUBT`), plus status queries. |
| **Revenue Allocation Service** | `tests/Feature/RevenueAllocationServiceTest.php` | 30% platform cut, immutable credit ledger entries, period finalization, breakage handling. |
| **Payout Processing & Concurrency** | `tests/Feature/ProcessInstructorPayoutJobTest.php` | Pessimistic DB locking, idempotency keys, zero double-payouts on overlapping runs, queue retries. |
| **Reconciliation Engine** | `tests/Feature/PayoutReconciliationTest.php` | Self-healing resolution of `IN_DOUBT` payouts via `payouts:reconcile`. |
| **Subscription Refunds & Clawbacks** | `tests/Feature/SubscriptionRefundTest.php` | Mid-term refund handling, cancelling unearned periods without clawback, and debit ledger clawbacks. |

```bash
# Example: Run only payout job and concurrency tests
php artisan test tests/Feature/ProcessInstructorPayoutJobTest.php

# Example: Run only the penny split calculator tests
php artisan test tests/Unit/RevenueSplitCalculatorTest.php
```

---

## ⚙️ Core Artisan Commands

### 1. Process Payouts (`payouts:process`)
Scans all instructors with payable balances, groups them into batches of 250, and dispatches background payout jobs:
```bash
php artisan payouts:process --period=2026-03
```

### 2. Reconcile In-Doubt Payouts (`payouts:reconcile`)
Scans timed-out or in-doubt transactions and queries the payment gateway to settle or fail them:
```bash
php artisan payouts:reconcile
```

---

## 🧠 Assumptions Made

1. **Integer Cents Representation (`BIGINT`)**:
   * All monetary figures are stored as integer cents to prevent IEEE 754 floating-point rounding errors (`$100.00` is stored as `10000`).
2. **Accrual Revenue Recognition (`subscription_periods`)**:
   * Upfront multi-month subscriptions (Monthly = 1 month, Quarterly = 3 months, Annual = 12 months) are partitioned into monthly accounting periods. Instructors earn their share month-by-month as the service is delivered.
3. **Platform Cut & Instructor Pool**:
   * The platform takes a default 30% cut; the remaining 70% constitutes the net instructor pool for that accounting period.
4. **Proportional Consumption Split**:
   * A student's monthly pool is distributed to instructors strictly proportionally based on `seconds_watched` during that accounting period.
5. **The Penny Rounding Method (Hare-Niemeyer)**:
   * Uneven fractional cents are distributed deterministically using the **Largest Remainder Method** (tie-broken by `instructor_id` ascending). $\sum \text{allocated} \equiv \text{pool}$ to the exact penny. No money is ever lost or created.
6. **Zero-Consumption Breakage**:
   * If a student subscribes but watches 0 seconds during a month, the pool is retained by the platform as unconsumed breakage/deferred revenue, and zero artificial instructor earnings are created.
7. **Gateway Timeout State (`IN_DOUBT`)**:
   * If the payment gateway times out after moving money, the system locks funds in escrow (`status = IN_DOUBT`, ledger `status = LOCKED`) and refuses to retry until `payouts:reconcile` queries the gateway status.
8. **Single Currency Model**:
   * All calculations operate in a single currency (USD cents). Multi-currency support can be layered on via foreign exchange snapshot tables.

---

## 📦 Seeders & Model Factories

To support immediate local evaluation and rigorous test fixture generation, comprehensive factories and seeders are provided:

### Model Factories (`database/factories`)
* `UserFactory`: States for `instructor()` (with generated IBAN) and `student()`.
* `PlanFactory`: States for `monthly()`, `quarterly()`, `annual()`, and `inactive()`.
* `CourseFactory`: Generates realistic course titles associated with instructors.
* `CourseEngagementFactory`: Simulates student watch time in seconds.
* `SubscriptionFactory`: States for `active()`, `cancelled()`, `refunded()`, and `expired()`.
* `SubscriptionPeriodFactory`: States for `pending()`, `open()`, `allocated()`, and `cancelled()`.
* `LedgerEntryFactory`: States for `payable()`, `locked()`, `settled()`, `credit()`, `debit()`, and `clawback()`.
* `PayoutFactory`: States for `pending()`, `processing()`, `paid()`, `inDoubt()`, and `failed()`.

### Seeders (`database/seeders`)
* `PlanSeeder`: Seeds canonical Monthly ($29), Quarterly ($79), and Annual ($249) subscription tiers.
* `InstructorSeeder`: Seeds 4 industry instructors with courses and valid banking IBANs.
* `DatabaseSeeder`: Orchestrates the complete LMS ecosystem with admin user (`admin@lms.test` / `password`), active students, multi-month accounting periods, immutable credit/debit ledger entries, and historical payouts illustrating `PAID`, `IN_DOUBT`, and `FAILED` states.

