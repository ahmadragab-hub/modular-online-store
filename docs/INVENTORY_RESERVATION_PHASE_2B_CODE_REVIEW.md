# Inventory reservation Phase 2b — code review

**Date:** 2026-11-13
**Reviewer scope:** `ReserveStockForCartAction` and supporting Inventory code (no code changes in this task)
**References:** `AGENTS.md`, `docs/INVENTORY_RESERVATION_LIFECYCLE_DESIGN.md`, `docs/INVENTORY_RESERVATION_PHASE_2B_REPORT.md`

---

## Executive summary

Single-threaded / serialized flows behave as designed: two-phase availability check before writes, counter and movement semantics match the lifecycle doc, transaction participation is correct, and cart-scoped idempotent reuse works when a prior commit is visible before the idempotency read.

**The implementation does not lock the `carts` row**, and **`SELECT … FOR UPDATE` on zero reservation rows does not serialize same-cart concurrent place attempts.** Combined with **no re-read of active reservations after inventory level locks**, two transactions for the same cart can both create holds and double-increment `quantity_reserved`.

Cross-cart contention for the last unit is **materially better**: variant `inventory_levels` rows are locked in ascending ID order for the duration of the outer transaction, which is the correct MySQL pattern for oversell prevention **across carts**.

---

## Findings

### Critical

#### C1 — Same-cart duplicate reserves under concurrent transactions

**Location:** `ReserveStockForCartAction::execute()` — lines 55–60 (reservation read), 76–136 (level lock + create); no cart row lock at lines 34–39.

**Behavior today:**

1. Stale-active `exists()` (lines 44–53) — no row lock.
2. `inventory_reservations` filtered by `cart_id`, `status = active`, `expires_at > now()` with `lockForUpdate()` (lines 55–60).
3. If the result set is empty, proceed to lock levels and insert new rows.

**Problem:** On InnoDB, `SELECT … FOR UPDATE` that matches **no rows** typically acquires **no lock** on future inserts for that `cart_id` (non-unique index on `cart_id` only). Two transactions can therefore both observe “no active reservations” before either inserts.

**Race timeline (same cart, one variant, qty 1):**

| Time | Transaction A | Transaction B |
| --- | --- | --- |
| T1 | `transactionLevel` OK; stale check pass | Same |
| T2 | `SELECT` active reservations `FOR UPDATE` → **0 rows** | Same → **0 rows** |
| T3 | `lockForUpdate` on `inventory_levels` for variant V | Blocked on V |
| T4 | Insert reservation R1; `quantity_reserved += 1` | (waiting) |
| T5 | **COMMIT** | Acquires lock on V |
| T6 | — | Availability still OK (sees incremented reserved) |
| T7 | — | Insert reservation R2; **`quantity_reserved += 1` again** |
| T8 | — | COMMIT → **two active rows, counter +2 for one logical checkout** |

B does **not** re-run the reservation query after waiting on the level lock (review question: **no**). B still believes the cart has zero active holds from T2.

**Minimal fixes (pick at least one for Phase 3 readiness):**

| Fix | Change |
| --- | --- |
| **F1 (recommended)** | After `Cart::query()->whereKey($cartId)->lockForUpdate()->firstOrFail()` at the start of `execute()`, all same-cart reserve paths serialize. |
| **F2** | After `FindInventoryLevelsForVariantIdsQuery::find(..., lockForUpdate: true)`, **re-query** active cart reservations under `lockForUpdate()` and re-apply idempotency / mismatch rules before any insert. |
| **F3 (schema)** | Partial unique constraint on active holds per cart variant (not in current schema; report as longer-term). |

**Tests to add:**

- MySQL (Phase 2e): two connections, same `cart_id`, parallel `execute` inside separate transactions → exactly one reservation set / one counter increment.
- SQLite regression: after F1/F2, second sequential call still reuses (existing test).

---

### High

#### H1 — Cart row is never locked

**Location:** `ReserveStockForCartAction::execute()` line 34 — `Cart::query()->whereKey(...)->exists()` only.

**Impact:** No domain mutex for checkout on a cart. Contributes directly to **C1**; also allows interleaving with future cart mutations (items updated in another txn) while holds are being created.

**Minimal fix:** Replace `exists()` with `lockForUpdate()->firstOrFail()` (or equivalent) once per `execute()`, before reservation logic.

**Tests:** Same as C1; optional test that reserve fails fast if cart deleted after lock (FK on insert).

---

#### H2 — Stale-active gate is non-locking and precedes reservation locks

**Location:** Lines 44–53 vs 55–60.

**Impact:** Low probability but possible TOCTOU: between `exists()` for stale rows and `lockForUpdate()` on reservations, another session could leave `status = active` and `expires_at <= now()` if clock/job ordering is odd. More importantly, stale check does **not** participate in the same lock ordering as reserves → harder to reason about combined with **C1**.

**Minimal fix:** Run stale detection on rows already selected under `lockForUpdate()` in the same query as active holds (e.g. single locked query, then branch on expiry in PHP), or rely on **F1** cart lock + single reservation query.

**Tests:** Unit test with stale row present → `STALE_ACTIVE_RESERVATIONS`; integration test that stale row inserted in parallel fails after fix.

---

#### H3 — Cart-level idempotency not enforceable under concurrency without F1/F2 or schema

**Location:** Idempotency block lines 64–74; migration `2026_03_20_100008_create_inventory_reservations_table.php` — indexes only, no unique on `(cart_id, product_variant_id, status)`.

**Impact:** Documented in Phase 2b report but operationally **High** for PlaceOrder: idempotent reuse works only when the first commit is visible **before** the second txn’s first reservation read.

**Minimal fix:** **F1** + PlaceOrder `orders.idempotency_key` lock (Phase 3). Do not claim “full” idempotency until MySQL tests pass.

**Tests:** Phase 2e concurrent matrix; PlaceOrder integration replay test.

---

#### H4 — Duplicate active rows per variant can “match” idempotent sum

**Location:** `sumQuantitiesByVariant()` lines 227–238; reuse branch lines 64–66.

**Impact:** If **C1** (or manual DB edits) creates two `active` rows for the same variant (e.g. two × qty 1), a request for qty **2** matches the aggregated map and **reuses** without fixing counter duplication. Counter may already be double-counted.

**Minimal fix:** After **F1**, enforce at most one `active` row per `(cart_id, product_variant_id)` in application (reject if count > 1) or add schema constraint later.

**Tests:** Seed two active rows same cart/variant; expect mismatch or dedicated “corrupt holds” exception, not silent reuse.

---

### Medium

#### M1 — No upper bound on requested quantity

**Location:** `buildRequestedQuantitiesMap()` lines 179–186 — only `quantity < 1` rejected.

**Impact:** `inventory_reservations.quantity` is `unsignedInteger`; extremely large values could exceed business rules or stress `quantity_reserved += quantity` (PHP int is fine on 64-bit; DB still caps at 2³²−1). No check that `quantity_reserved + quantity <= quantity_on_hand` beyond `available >= quantity` (logically equivalent when counters are consistent).

**Minimal fix:** Cap per line (e.g. cart item max) or assert `quantity <= available` explicitly after lock with a clear exception.

**Tests:** Request qty > `on_hand`; request qty = `PHP_INT_MAX` if product allows.

---

#### M2 — Corrupt level state (`quantity_reserved > quantity_on_hand`) not rejected

**Location:** `assertLevelCountersValid()` lines 210–220 — only negative components.

**Impact:** Negative `available` with positive request throws `InsufficientStockException` with `max(0, available)` — OK. If `reserved > on_hand` due to corruption, reserve might still succeed if `available` is small but non-negative.

**Minimal fix:** Assert `quantity_reserved <= quantity_on_hand` after lock (or `available >= 0` and reserved <= on_hand).

**Tests:** Level with `on_hand = 5`, `reserved = 8` → fail with domain exception.

---

#### M3 — Expiry validation uses default connection timezone

**Location:** `assertValidExpiry()` lines 194–207; active filter line 58 `expires_at > now()`.

**Impact:** `isFuture()` and `now()` follow app timezone, not necessarily UTC stored in DB. Risk of borderline TTL mistakes if store and DB assumptions differ.

**Minimal fix:** Document that callers must pass UTC; optionally `Carbon::now('UTC')` for comparisons in Inventory checkout path.

**Tests:** Freeze time; expiry one second ahead in UTC.

---

#### M4 — `buildReuseResult` may return multiple lines per variant

**Location:** `buildReuseResult()` lines 244–257.

**Impact:** If multiple rows exist per variant (see **H4**), result lists all row IDs. PlaceOrder must link **all** reservation IDs to an order or consolidate — not defined in 2b.

**Minimal fix:** Document in Action docblock; PlaceOrder sets `order_id` on each returned line.

**Tests:** Covered by H4 test.

---

### Low

#### L1 — Cart existence check without lock

**Location:** Line 34.

**Impact:** Cart deleted between check and insert → insert fails on FK or `cart_id` set; acceptable with outer rollback.

---

#### L2 — Transaction guard is `transactionLevel() < 1` only

**Location:** Lines 30–32.

**Impact:** Correct for PlaceOrder outer tx. Standalone misuse is rejected; nested savepoints still level ≥ 1 — OK.

---

#### L3 — Design doc names `ReserveStockForOrderAction`; implementation is cart-first

**Location:** Action naming vs `CHECKOUT_WORKFLOW_DESIGN.md` §7 step 5.

**Impact:** PlaceOrder must call `ReserveStockForCartAction` then update `order_id` on reservations (or wrap a thin Checkout adapter). API (`ReserveStockForCartInput`) is **appropriate** for that sequence.

---

## Area-by-area assessment

| # | Area | Assessment |
| --- | --- | --- |
| 1 | Transaction enforcement / rollback | **Pass** — no nested commit; failures in create loop roll back with caller. |
| 2 | Same-cart concurrency / stale reads | **Fail** — **C1**, **H1**, **H2**. |
| 3 | Lock order / deadlock | **Pass (cross-variant)** — variant IDs sorted via query; reservation locks cart-scoped. **Fail (same-cart serialization)** without cart lock. |
| 4 | Cross-cart oversell | **Pass on MySQL** — level `lockForUpdate` through create; not proven in SQLite tests. |
| 5 | Duplicate / mismatch handling | **Pass sequential**; **Fail concurrent**; mismatch rules are sound. |
| 6 | Expired-but-still-active | **Pass** — `STALE_ACTIVE_RESERVATIONS` blocks new reserve; does not release counters (2d). |
| 7 | Quantity validation | **Partial** — positives only; **M1**, **M2**. |
| 8 | Counter / movement consistency | **Pass** on happy path; hold movements correct; duplicate reserves break consistency (**C1**). |
| 9 | Expiry validation | **Pass** future-only; **M3** timezone. |
| 10 | PlaceOrder API fit | **Pass** with documented `order_id` linking and **required** cart/lock hardening before production. |

---

## Cart lock and post-lock reservation re-query (explicit answers)

| Question | Answer |
| --- | --- |
| Does the Action lock the **cart** row before checking reservations? | **No** — only `exists()` on `carts`. |
| Is the reservation query executed again after any lock wait? | **No** — a single `lockForUpdate()->get()` on reservations at lines 55–60; no second pass after level locks. |

---

## Recommended fix priority (minimal, pre–Phase 3)

1. **F1** — `lockForUpdate()` on cart at beginning of `execute()` (after transaction check).
2. **F2** — Re-query active cart reservations after level locks if F1 is not used (F1 alone may suffice for same-cart).
3. **H4** — Detect multiple active rows per cart/variant and fail closed.
4. Phase **2e** MySQL tests for C1/H3.
5. **M1/M2/M3** as follow-ups; not blockers if caps enforced at cart line validation in PlaceOrder.

---

## Final verdict

**PHASE 2B APPROVED WITH REQUIRED FIXES**

Core accounting, movement audit, transactional participation, mismatch semantics, and cross-cart level locking align with the lifecycle design **when transactions are serialized**. **Do not integrate with `PlaceOrderAction` in production without addressing C1/H1** (cart lock and/or post-lock reservation re-read) and planning Phase 2e concurrency coverage.
