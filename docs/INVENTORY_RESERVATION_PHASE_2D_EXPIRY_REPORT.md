# Inventory reservation Phase 2d — expiry report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows`
**Scope:** `ExpireReservationsAction`, Artisan command, scheduler registration (no order cancel)

---

## 1. Files created / modified

| Path | Role |
| --- | --- |
| `app/Domains/Inventory/Actions/ExpireReservationsAction.php` | Batch expiry |
| `app/Domains/Inventory/DTOs/ExpireReservationsInput.php` | `batchLimit`, optional `asOf` |
| `app/Domains/Inventory/DTOs/ExpireReservationsResult.php` | Counts and id lists |
| `app/Console/Commands/ExpireInventoryReservationsCommand.php` | `inventory:expire-reservations` |
| `bootstrap/app.php` | `withSchedule()` — every minute, `withoutOverlapping()` |
| `tests/Unit/Domains/Inventory/ExpireReservationsActionTest.php` | Action tests |
| `tests/Feature/Console/ExpireInventoryReservationsCommandTest.php` | Command test |

Reserve / commit / release Actions unchanged.

---

## 2. Expiry Action API

**`ExpireReservationsAction::execute(ExpireReservationsInput): ExpireReservationsResult`**

| Input | Default | Meaning |
| --- | --- | --- |
| `batchLimit` | 100 | Max candidates per run (must be ≥ 1) |
| `asOf` | `now()` | Expire when `expires_at <= asOf` |

Candidate selection (unlocked read): `status = active` AND `expires_at <= asOf`, `orderBy id`, `limit batchLimit`.

Per candidate: process in **own transaction** when no caller transaction is active; otherwise participate in caller transaction (no nested commit).

---

## 3. Lock order (per reservation)

1. Cart `lockForUpdate()` if `cart_id` set
2. Reservation `lockForUpdate()`
3. Re-validate: still `active`, TTL passed, `quantity >= 1`
4. Inventory level `lockForUpdate()`
5. Counter checks → mutate

Compatible with reserve / commit / release.

**`skipLocked`:** Not used on the candidate query. Duplicate processing is prevented by re-reading reservation status under row lock (second worker skips). Documented for Phase 2e MySQL verification.

---

## 4. State transition

| Before | After |
| --- | --- |
| `active`, `expires_at <= asOf` | `expired` (never `released`) |
| `expired` | Skip (idempotent) |
| `released` / `committed` | Skip |
| `active`, future `expires_at` | Not selected |

`released_at` is **not** set on expiry (distinguishes cancel release from TTL).

---

## 5. Counter and movement accounting

- `quantity_reserved -= Q`
- `quantity_on_hand` unchanged
- Movement: `reservation_release`, `quantity_delta = 0`, `quantity_on_hand_after` = current on-hand, `reference_type = reservation`, `reference_id` = reservation id, `notes = expired`

Failures (missing level, counter inconsistency) throw inside per-reservation transaction → rollback that reservation only; batch continues and increments `failedCount` (logged warning).

---

## 6. Scheduling

**Command:** `php artisan inventory:expire-reservations {--limit=100}`

**Scheduler** (`bootstrap/app.php`):

```php
$schedule->command('inventory:expire-reservations')
    ->everyMinute()
    ->withoutOverlapping();
```

**Production requirement:** OS cron must invoke Laravel scheduler every minute, e.g.:

```bash
* * * * * cd /path/to/modular-online-store && php artisan schedule:run >> /dev/null 2>&1
```

Without this, expiry runs only when the command is executed manually.

---

## 7. Order and payment coordination (out of scope)

- Inventory does **not** change order status.
- Late payment after expiry remains **reconciliation-only** (no auto commit).
- **Checkout (later):** `CancelOrderAction` or payment webhooks should react to expired reservations using returned `expiredReservationIds` / order linkage — not implemented in 2d.

After 2d, `ReserveStockForCartAction` stale-active guard can clear once TTL rows become `expired` routinely.

---

## 8. Tests and results

| Area | Coverage |
| --- | --- |
| Expire TTL-active | Status, counters, movement |
| Idempotent second run | No duplicate movement |
| Future TTL | Not selected |
| Released / committed | Ignored |
| Batch limit | Honored |
| Counter failure | Failed count, no partial expire |
| Outer transaction | Participates without nested commit |
| Command | Feature test |

**Inventory suite:** 68 passed
**Full suite:** **141 passed** (426 assertions)
**Pint / `git diff --check`:** pass

SQLite does not prove MySQL worker concurrency (Phase 2e).

---

## 9. Known limitations

| Limitation | Note |
| --- | --- |
| Unlocked candidate query | Two workers may attempt same id; lock + re-read prevents double decrement |
| No order cancellation | Orders domain must handle stale `pending_payment` separately |
| Logging only on failure | No alerting integration |
| `skipLocked` | Not implemented on batch select |

---

## 10. Readiness for PlaceOrderAction

Full reservation lifecycle (reserve → commit / release / expire) is available. PlaceOrder should:

1. Reserve inside transaction with cart lock (2b).
2. Link `order_id` on reservations after order create.
3. Rely on expiry job for TTL cleanup.
4. Call `CommitReservationAction` only on verified payment success and **non-expired** holds.

---

## Final verdict

**RESERVATION EXPIRY IMPLEMENTED AND VERIFIED**
