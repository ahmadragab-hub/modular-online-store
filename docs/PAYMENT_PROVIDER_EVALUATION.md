# Payment provider evaluation (Milestone 11)

**Date:** 2026-11-18  
**Status:** Planning — **no provider selected**  
**Authority:** Supports **OPEN-001** and **OPEN-005** in [PROJECT_DECISIONS.md](PROJECT_DECISIONS.md). This document does **not** approve a provider.

---

## 1. Jurisdiction and business inputs (unresolved)

No **Approved** decision records the merchant’s legal registration country, settlement country, or target markets.

The following **must be confirmed by the project owner** before a definitive provider recommendation:

| Input | Status | Why it matters |
| --- | --- | --- |
| Business registration country | **Unresolved** | Gateway eligibility and onboarding rules |
| Merchant settlement country / bank account jurisdiction | **Unresolved** | Payout currency and settlement rails |
| Preferred settlement currency | **Unresolved** | FX and reconciliation |
| Required customer payment methods (cards only vs local wallets) | **Unresolved** | Provider shortlist |
| International Visa/Mastercard required | **Unresolved** | Cross-border card support |
| Local payment methods required (e.g. Meeza, KNET, Benefit) | **Unresolved** | Regional gateways |
| Refunds / partial refunds at launch | **Deferred** (**PAY-004**) | MVP excludes refund Actions |

Do **not** assume the developer’s physical location is the store’s business jurisdiction.

Until **OPEN-001** and **OPEN-005** are resolved, treat provider selection as **conditional** using the matrix in §4.

---

## 2. Store constraints (approved)

From **CUR-001–010** and **OPEN-001** notes:

| Constraint | Source |
| --- | --- |
| Transaction currencies at checkout: **USD, EGP, KWD** | Approved |
| Product/catalog base: **USD** | Approved |
| Minor units: USD/EGP **2**, KWD **3** | Approved |
| Provider must support currencies **actually offered** at checkout | **OPEN-001** |
| No card data storage (**PAY-003**) | Approved |
| Hosted checkout preferred when suitable | Planning (security § in integration plan) |
| Refunds | **Deferred** — provider choice should not assume refund API in MVP |

---

## 3. Evaluation criteria

Each candidate is scored against:

1. Merchant account eligibility (by registration country — TBD).
2. Country availability for accepting payments from target customers.
3. Charge currencies: USD, EGP, KWD (presentment / processing).
4. Settlement currencies and payout timing.
5. Visa/Mastercard acceptance.
6. Local payment methods.
7. Hosted checkout / redirect session.
8. REST API + **signed webhooks** (or equivalent verifiable callbacks).
9. Idempotency keys on payment/session creation.
10. Sandbox / test mode.
11. PHP/Laravel integration path (official SDK vs HTTP; **no mandatory new package** without approval).
12. Documentation quality and operational limits (rate limits, event ordering).
13. Fees and settlement (indicative only — confirm in merchant agreement).

Technical claims below should be validated against **official provider documentation** at implementation time; this log may age.

---

## 4. Conditional provider shortlist

Use this table **after** **OPEN-005** (jurisdiction) is known. No single winner is declared without owner input.

| Provider | Typical fit when… | USD | EGP | KWD | Hosted checkout | Webhooks | Idempotency | Sandbox | Refunds (MVP) | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Stripe** | US/EU/UK incorporated merchants; global cards; strong API culture | Yes | Limited / country-dependent | Limited | Checkout Session | Signed (`Stripe-Signature`) | Request idempotency keys | Yes | API exists — **out of MVP scope** | EGP/KWD acceptance depends on **account country** and Stripe’s country list; not assumed for all three |
| **Paymob** | Egypt-focused merchant; local cards/wallets | Often USD/EGP per product | Strong | Unlikely native | Redirect / iframe | HMAC webhooks | Transaction references | Yes | Provider-dependent | Strong for **EGP**; verify USD/KWD needs |
| **Amazon Payment Services** (PayFort) | MENA merchants; multi-currency acquirer | Often | Often | **KWD common** | Hosted | Signature on response/callback | Order/ref ids | Yes | Deferred | Common in Gulf; confirm merchant country |
| **Tap Payments** | GCC / MENA | Often | Often | **KWD** | Hosted | Signature | Reference ids | Yes | API exists — deferred | Popular in Kuwait/Gulf; confirm settlement |
| **PayPal** | Alternative wallet; brand recognition | Yes | Yes (varies) | Limited | Redirect | Webhooks IPN/REST | Partial | Yes | Deferred | May not match all three currencies in one merchant profile |

### Selection strategy (when jurisdiction is known)

1. Filter by **merchant onboarding eligibility** in registration country.
2. Require **hosted checkout** (or equivalent) and **webhook signature verification**.
3. Intersect **charge currencies** with USD/EGP/KWD enabled in `currencies.transaction_enabled`.
4. Prefer **one primary driver** for MVP (`PaymentGatewayContract` single binding — see integration plan); avoid multi-provider platform unless required.
5. Record choice as **OPEN-001** resolution → new **Approved** `PAY-005` entry (owner sign-off).

---

## 5. Questions for the project owner (blocking **OPEN-001**)

1. In which **country is the business legally registered**?
2. Where will the **merchant settlement bank account** be held?
3. Which **customer countries** must be accepted at launch?
4. Which of **USD, EGP, KWD** must be chargeable on day one (all three or subset)?
5. Are **local payment methods** required, or cards-only sufficient?
6. Is **English + Arabic** checkout copy enough, or provider-hosted locale required?
7. Confirm **no refunds in MVP** (**PAY-004** Deferred) — acceptable?

---

## 6. Related documents

- [PAYMENT_PROVIDER_INTEGRATION_PLAN.md](PAYMENT_PROVIDER_INTEGRATION_PLAN.md)
- [PROJECT_DECISIONS.md](PROJECT_DECISIONS.md) — **OPEN-001**, **OPEN-005**
- [MVP_SCOPE.md](MVP_SCOPE.md)