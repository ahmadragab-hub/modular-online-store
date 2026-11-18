# Payment expiry concurrency fix report

**Date:** 2026-11-18  
**Repository:** `modular-online-store`

## Summary

The MySQL integration failure in `PaymentExpiryConcurrencyTest` was caused by an **incorrect test expectation**, not a production defect. Production already enforces **ADMIN-006** / **CHECKOUT-009**: an expired hold must not be committed on late payment success, and cancelled orders must not be resurrected.

**Fix:** Correct test ordering and assertions; add explicit coverage for expiry-wins and TTL-guard paths. **No production code changes.**

---

## Root cause

| Item | Detail |
| --- | --- |
| Failing test | `test_payment_success_then_expiry_on_same_order_does_not_leave_inconsistent_state` |
| Setup error | Backdated `expires_at` **before** calling `CompleteVerifiedPaymentSuccessAction` while the reservation remained `active`. |
| Expected (wrong) | Order → `processing`, payment → `captured`, reservation → `committed`. |
| Actual (correct) | `CommitReservationAction` throws `ExpiredInventoryReservationException` for `active` rows with `expires_at <= now()` (**INV-002** / Phase 2c). Transaction rolls back; order stays `pending_payment`, payment stays `pending`. |

The test name implied **payment success first, then expiry**, but the setup implemented **expired hold, then payment success** — which is explicitly forbidden by approved decisions.

Reference unit coverage already documents rollback: `MarkOrderPaidActionTest::test_commit_failure_rolls_back_order_and_payment_transition`.

---

## Business rules (unchanged)

- **ADMIN-006 §E:** Success path requires reservations **active** and **unexpired** before commit; expiry and success must not both win.
- **CHECKOUT-009:** Late success after cancellation/expiry → **reconciliation only**; no `processing`, no stock commit.
- **ExpireReservationsAction:** Skips order-linked holds when order ∉ (`pending_payment`, `payment_failed`) — paid orders are not undone by the expiry job.

---

## Files changed

| File | Change |
| --- | --- |
| `tests/Integration/MySQL/PaymentExpiryConcurrencyTest.php` | Aligned scenarios with ADMIN-006; replaced skip-based second test; added TTL rejection under MySQL locks |
| `docs/PAYMENT_EXPIRY_CONCURRENCY_FIX_REPORT.md` | This report |

---

## Test scenarios (after fix)

1. **Payment success then expiry** — Pay while hold is valid; run expiry after backdating TTL on a **committed** hold; expiry must **skip** (`expiredCount = 0`); order remains `processing`.
2. **Expiry then late payment** — Expire hold → order `cancelled`, pending payment → `cancelled` (**ADMIN-006**); `CompleteVerifiedPaymentSuccessAction` rejects with `PaymentOperationException` (not payable); no capture, no stock commit.
3. **TTL passed, job not run** — Past `expires_at` but still `active`; payment success must throw `ExpiredInventoryReservationException`; no partial paid state.

---

## Verification

### MySQL — `php artisan test --configuration=phpunit.mysql.xml`

| Suite | Result |
| --- | --- |
| `PaymentExpiryConcurrencyTest` | **3 passed** |
| `CheckoutConcurrencyTest` | **4 passed** |
| `CustomerCartConcurrencyTest` | **1 passed** |
| `ActiveCustomerCartConstraintTest` | **4 passed** |
| **MySQL integration total** | **12 passed** |

### SQLite — `php artisan test` (default `phpunit.xml`)

| Result | Detail |
| --- | --- |
| **397 passed** | |
| **12 skipped** | `tests/Integration/MySQL/*` (expected) |

### Tooling

| Tool | Result |
| --- | --- |
| `vendor/bin/pint --test` | **Passed** (after EOF fix on test file) |
| `npm run build` | **Passed** |

---

## Business-rule impact

**None.** Production Actions (`CompleteVerifiedPaymentSuccessAction`, `MarkOrderPaidAction`, `CommitReservationAction`, `ExpireReservationsAction`) unchanged.

---

## Remaining limitations

- **Reconciliation recording** for provider-captured funds after expiry (CHECKOUT-009 ops runbook) is not asserted in this integration suite — covered at unit/feature level elsewhere.
- **True parallel threads** (two PHP processes) are not simulated; tests use sequential transactions with MySQL `lockForUpdate` as in other integration tests.

---

## Out of scope

Demo seeding and UI redesign were not started.