# Inventory reservation audit report

**Date:** 2026-11-13
**Branch:** `feature/checkout-workflows` (analysis only; no code changes)
**Related:** [CHECKOUT_WORKFLOW_DESIGN.md](CHECKOUT_WORKFLOW_DESIGN.md), [DOMAIN_DATA_OWNERSHIP.md](DOMAIN_DATA_OWNERSHIP.md)

---

## 1. Files inspected

### Migrations (authoritative schema)

| File | Tables |
| --- | --- |
| `database/migrations/2026_03_20_100005_create_inventory_levels_and_movements_tables.php` | `inventory_levels`, `inventory_movements` |
| `database/migrations/2026_03_20_100008_create_inventory_reservations_table.php` | `inventory_reservations` |
| `database/migrations/2026_03_20_100006_create_carts_tables.php` | FK `inventory_reservations.cart_id` |
| `database/migrations/2026_03_20_100007_create_orders_tables.php` | FK `inventory_reservations.order_id` |

### Models

| Model | Path |
| --- | --- |
| `InventoryLevel` | `app/Domains/Inventory/Models/InventoryLevel.php` |
| `InventoryMovement` | `app/Domains/Inventory/Models/InventoryMovement.php` |
| `InventoryReservation` | `app/Domains/Inventory/Models/InventoryReservation.php` |

### Related models (FK targets)

- `ProductVariant` (restrict on delete for levels/reservations)
- `Cart`, `Order` (`belongsTo` on `InventoryReservation`; inverse `hasMany` on Cart/Order)
- `User` (`inventory_movements.created_by_user_id`, nullable)

### Tests

| File | Coverage today |
| --- | --- |
| `tests/Unit/Domains/Inventory/InventoryModelsTest.php` | Model persistence, unique level per variant, movement immutability, reservation scopes |
| `tests/Unit/Domains/Checkout/PrepareCheckoutActionTest.php` | Read-only stock check (`on_hand - reserved`); **no reservation Actions** |
| `tests/Unit/Domains/Cart/CartModelsTest.php` | `Cart::reservations()` relationship |

### Documentation

`AGENTS.md`, `ARCHITECTURE.md`, `DATABASE_DESIGN.md`, `DOMAIN_DATA_OWNERSHIP.md`, `BUSINESS_WORKFLOWS.md`, checkout design/audit/preparation reports.

---

## 2. Actual schema and model capabilities

### `inventory_levels`

| Column | Type / behavior |
| --- | --- |
| `product_variant_id` | UNIQUE FK → `product_variants`, **RESTRICT** on delete |
| `quantity_on_hand` | `INT`, default 0 |
| `quantity_reserved` | `INT`, default 0 |
| `version` | `INT UNSIGNED`, default 0 (optimistic concurrency **optional**, not required for MVP) |
| `low_stock_threshold` | nullable |
| Timestamps | `updated_at` only (`CREATED_AT = null` on model) |

**Indexes:** implicit unique on `product_variant_id` only.

**No DB constraint** enforcing `quantity_reserved <= quantity_on_hand` or non-negative values (application invariants per `DATABASE_DESIGN.md`).

### `inventory_reservations`

| Column | Type / behavior |
| --- | --- |
| `product_variant_id` | FK, **RESTRICT** on delete |
| `quantity` | `INT UNSIGNED` |
| `status` | `VARCHAR(32)`, default `active` |
| `cart_id` | nullable FK → `carts`, **nullOnDelete** |
| `order_id` | nullable FK → `orders`, **nullOnDelete** |
| `expires_at` | required timestamp |
| `committed_at`, `released_at` | nullable |
| `created_at`, `updated_at` | standard |

**Indexes:** `(product_variant_id, status)`, `order_id`, `(expires_at, status)`, `cart_id`.

**No unique** on `(order_id, product_variant_id)` — application should create **one reservation row per variant per order** (aggregate line qty).

**Model statuses:** `active`, `committed`, `released`, `expired` (constants on `InventoryReservation`).

**Scopes:** `active()`, `notExpired()` (`expires_at > now()`).

### `inventory_movements`

| Column | Notes |
| --- | --- |
| `type` | Constants include `reservation_hold`, `reservation_release`, `sale_commit`, adjustments, `return_restock` |
| `quantity_delta` | Signed; doc: negative reduces **on-hand** |
| `quantity_on_hand_after` | Snapshot after movement affecting on-hand |
| `reference_type`, `reference_id` | Polymorphic (`reservation`, `order`, `adjustment`) |
| `created_by_user_id` | nullable (admin adjustments; checkout/system null) |
| Immutable | no `updated_at` |

**Indexes:** `(product_variant_id, created_at)`, `(reference_type, reference_id)`.

### Relationships implemented

| From | To |
| --- | --- |
| `InventoryLevel` | `productVariant()` |
| `InventoryMovement` | `productVariant()`, `createdByUser()` |
| `InventoryReservation` | `productVariant()`, `cart()`, `order()` |
| `Cart` | `reservations()` |
| `Order` | `reservations()` |

`ProductVariant` has **no** `inventoryLevel()` / `reservations()` helpers (not blocking; Actions can query by `product_variant_id`).

---

## 3. Current lifecycle gaps

| Gap | Impact |
| --- | --- |
| **No `ReserveStockAction`** | Place order cannot hold stock |
| **No `CommitReservationAction`** | Payment success cannot deduct on-hand |
| **No `ReleaseReservationAction`** | Cancel/expiry cannot free reserved qty |
| **No `ExpireReservationsJob`** | TTL not enforced in application |
| **No `GetAvailableQuantityQuery`** | Duplicated ad-hoc reads in `PrepareCheckoutAction` |
| **No movement writers** for checkout lifecycle | Audit trail incomplete |
| **No integration tests** for counters, concurrency, or rollback | Oversell risk untested at Action layer |
| **No admin `AdjustStockAction`** | Out of checkout scope but affects available qty |

Checkout Phase 1 validates availability read-only; **schema and models are sufficient** to implement Actions without migration changes.

---

## 4. Quantity accounting assessment

### Documented semantics (`DATABASE_DESIGN.md`)

| Term | Definition |
| --- | --- |
| **On hand** | `inventory_levels.quantity_on_hand` — physically available units (before sale commit). |
| **Reserved (counter)** | `inventory_levels.quantity_reserved` — denormalized sum mirror for fast checks. |
| **Available to sell** | `quantity_on_hand - quantity_reserved` (MVP authoritative check at reserve time). |
| **Cross-check** | Sum of `inventory_reservations.quantity` where `status = active` should equal counter per variant when Actions are correct. |

### Single source of truth (MVP recommendation)

- **Operational authority:** `inventory_levels.quantity_on_hand` and `quantity_reserved`, updated **only** inside Inventory Actions under row lock.
- **Reservation rows:** authoritative **per-hold** history and status (`active` → `committed` / `released` / `expired`).
- **Do not** use “recompute reserved from rows” as the primary path during checkout (too slow and racy); use counter after `FOR UPDATE` lock.
- **Optional reconciliation job (deferred):** compare counter vs sum(`active`) for ops alerts.

### Invariants (application-enforced)

- `quantity_on_hand >= 0`, `quantity_reserved >= 0`, `quantity_reserved <= quantity_on_hand`.
- Reserve only if `available >= requested_qty` inside locked transaction.
- Commit reduces **both** on-hand and reserved by reservation qty.
- Release/expiry reduces **reserved only** (on-hand unchanged until commit).

---

## 5. Concurrency risks

| Risk | Mitigation (designed, not yet implemented) |
| --- | --- |
| Two checkouts, one unit | `SELECT inventory_levels ... FOR UPDATE` + available check |
| Multi-variant cart deadlock | Lock variant rows in **ascending `product_variant_id` order** |
| Partial multi-line reserve | Single outer transaction; failure rolls back all lines |
| Lost update on `quantity_reserved` | Row lock on level row; no read-modify-write without lock |
| SQLite tests vs production MySQL | `lockForUpdate()` semantics differ; **true concurrency tests need MySQL** |
| No reservation idempotency key | Place-order **order idempotency** prevents duplicate reserves for same checkout |

**Schema note:** `version` column exists for optimistic locking but MVP design prefers **pessimistic `FOR UPDATE`** per `DOMAIN_DATA_OWNERSHIP.md`.

---

## 6. Required schema/model changes

**None for MVP reservation lifecycle.** Existing tables, FKs, statuses, and movement types support Strategy C.

**Non-blocking improvements (deferred):**

- `ProductVariant::inventoryLevel()` for ergonomics.
- DB CHECK constraints for non-negative stock (MySQL 8.0.16+ CHECK optional; not in current migrations).
- Unique `(order_id, product_variant_id)` on reservations — not required if Actions aggregate lines.

---

## 7. Open decisions (not blocking design)

| # | Topic | Notes |
| --- | --- | --- |
| 1 | **Movement `quantity_delta` on hold/release** | Schema ties delta to on-hand changes; hold/release do not change on-hand — use `quantity_delta = 0` with `reference_type = reservation` (see lifecycle design). |
| 2 | **Explicit “abandon retry” release** | `BUSINESS_WORKFLOWS.md`: release only when abandoning retry; needs HTTP/Action entry later. |
| 3 | **Expire job batch size / order cancel coupling** | Job releases inventory; Orders domain cancels order — orchestration in job or small Checkout coordinator. |
| 4 | **Missing `inventory_levels` row** | Reserve fails if level missing (treat available = 0); admin must initialize stock. |

---

## 8. Implementation readiness verdict

**INVENTORY RESERVATION DESIGN READY FOR IMPLEMENTATION**

Schema, models, and approved business rules align. Implementation work is **Inventory Actions + tests + expiry job**, callable from a future `PlaceOrderAction` outer transaction without migration changes.

**Detailed design:** [INVENTORY_RESERVATION_LIFECYCLE_DESIGN.md](INVENTORY_RESERVATION_LIFECYCLE_DESIGN.md)
