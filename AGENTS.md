# Agent & Developer Rules

## 1. Project Overview

- This project is a **single-store fashion e-commerce application** built with Laravel 13 and PHP 8.3+.
- The architecture is a **Domain-Oriented Modular Monolith**.
- Business logic belongs to its owning domain under `app/Domains/`.
- HTTP delivery mechanisms belong under `app/Http/`.
- API, storefront, and admin interfaces must reuse the same domain business logic.
- The initial scope is one store. Do not introduce multi-tenancy or SaaS architecture unless explicitly requested.

Before implementing a feature, read:
- `docs/ARCHITECTURE.md`
- `docs/DOMAIN_MAP.md`
- `docs/PROJECT_ROADMAP.md`

Also inspect the relevant existing code and tests. Do not assume the documentation is always more accurate than the implementation.

---

## 2. Architecture and Responsibilities

### Controllers
- Handle HTTP coordination only.
- Receive validated input through Form Requests where appropriate.
- Call the relevant domain Actions or application-level capabilities.
- Return views, redirects, JSON responses, or API Resources.
- Do not contain business rules, database workflows, or complex calculations.

### Form Requests
- Validate incoming HTTP input.
- Handle request-level authorization where appropriate.
- Do not contain business workflows or domain calculations.

### Actions
- Represent a specific use case or business operation.
- Have one clear responsibility.
- Coordinate the necessary domain operations.
- Must not depend on HTTP request or response objects.
- Keep Actions focused; do not create generic Actions that handle unrelated operations.

### Services
- Represent reusable capabilities or workflows within a domain.
- Use them when functionality is shared by multiple use cases or requires a dedicated implementation.
- Do not create Services merely to wrap a single method or move code out of a Controller.
- Avoid large Services that become responsible for an entire domain.

### Models
- Represent domain entities and their persistence.
- Define relationships, casts, scopes, and behavior that naturally belongs to the entity.
- Do not use Models to orchestrate workflows involving multiple domains.
- Do not let Models become containers for unrelated business logic.

### Queries
- Represent reusable and meaningful read operations when separating them improves clarity or avoids duplication.
- Do not create a Query class for every database read.
- Keep simple, local queries close to the use case when appropriate.

### Policies
- Handle authorization decisions.
- Keep authorization separate from input validation and business workflows.
- Enforce authorization on the server side, not only through UI visibility.

### API Resources
- Transform domain data into consistent API response structures.
- Do not expose internal database structures or sensitive fields unnecessarily.
- Do not contain business workflows.

### Contracts and Interfaces
- Introduce Contracts only when a genuine abstraction, replaceable implementation, or integration boundary requires one.
- Do not create interfaces for every class by default.

### Jobs
- Use queued Jobs for work that should execute asynchronously or outside the main HTTP request.
- Jobs should delegate business operations to the appropriate Actions or Services rather than duplicate business rules.

### Events
- Use Events when a domain needs to announce that something meaningful has happened and other parts of the application may react.
- Do not use Events simply to hide ordinary, direct workflow dependencies.

---

## 3. Domain Boundaries and Communication

The application contains these initial domains:

- Identity
- Catalog
- Inventory
- Cart
- Checkout
- Orders
- Payments
- Customers
- Store

Create domain subfolders only when actual classes require them. Avoid creating unnecessary empty structures.

### Domain ownership

Each domain owns its business rules, operations, and data.

Initial ownership:

| Domain | Responsibility |
|---|---|
| Identity | Authentication, users, credentials, and identity-related operations |
| Catalog | Products, product variants, categories, attributes, sizes, colors, SKUs, and product images |
| Inventory | Stock quantities, stock movements, reservations, and releases |
| Cart | Shopping carts and cart items |
| Checkout | Coordination of the checkout use case |
| Orders | Order records, order items, and order lifecycle |
| Payments | Payment operations, provider integrations, and payment status |
| Customers | Customer profiles, contact details, and addresses |
| Store | Store settings and store-level configuration |

These boundaries are the initial ownership model. If a future requirement reveals that a responsibility belongs elsewhere, explain the reason before changing the ownership.

### Cross-domain communication

- When one domain needs another domain to perform an operation, use that domain's appropriate public Action or capability.
- Use Events when one domain needs to notify other domains that something has happened.
- Use Contracts when a real abstraction or replaceable implementation is needed.
- Direct access to another domain's Models or read-only data is acceptable only when intentional and when it does not bypass the owning domain's rules.
- Never directly modify another domain's data in a way that bypasses its business rules.
- Avoid circular dependencies between domains.
- Do not expose internal implementation details unnecessarily.

Example:

Checkout should request stock reservation through an Inventory-owned operation. It must not directly update Inventory tables or duplicate Inventory rules.

### Database ownership

- Each domain owns the tables and data associated with its responsibilities.
- Migrations remain in Laravel's standard `database/migrations/` directory.
- Migration location does not change domain ownership.
- A domain must not silently change another domain's data.
- Cross-domain operations must respect the owning domain's rules and consistency requirements.
- Use database foreign keys and constraints where appropriate to protect data integrity.
- Explain any intentional cross-domain database relationship or constraint.

---

## 4. HTTP and Delivery Structure

HTTP-related code belongs under `app/Http/`.

Expected organization:

- `Controllers/Api/V1/` — API controllers.
- `Controllers/Web/Storefront/` — customer-facing storefront controllers.
- `Controllers/Web/Admin/` — administrative controllers.
- `Requests/Api/` — API Form Requests.
- `Requests/Web/` — web Form Requests.
- `Resources/` — API Resources.
- `Middleware/` — HTTP middleware.

These are organizational conventions, not a requirement to create every directory in advance.

### Shared business logic

- API, storefront, and admin interfaces must use the same domain Actions and business rules where they perform the same operation.
- Never duplicate business logic in separate API and Web controllers.
- Differences between delivery mechanisms should remain in their HTTP validation, authorization, response formatting, and presentation layers.

---

## 5. Shared Code

Use `app/Shared/` only for genuinely cross-domain components that do not naturally belong to one domain.

Examples:
- Shared value objects.
- Base exceptions.
- Cross-domain utilities.
- Common abstractions used by multiple domains.

Rules:
- Do not use Shared as a dumping ground.
- Do not move domain-specific logic into Shared just because multiple classes need it.
- Prefer keeping ownership clear over forcing artificial reuse.

---

## 6. Database and Business Logic Rules

- Use Laravel Eloquent and Query Builder appropriately.
- Use database transactions for workflows that require multiple related database operations to succeed or fail together.
- Use database constraints to protect critical invariants where appropriate.
- Handle concurrency explicitly for operations involving limited stock or other shared resources.
- Avoid unnecessary database queries and N+1 problems.
- Use eager loading when it is appropriate for the actual read operation.
- Do not load large datasets into memory unnecessarily.
- Do not introduce Repository classes by default.
- Do not create abstractions without a clear benefit.

### Data integrity and pricing

- Never trust prices, discounts, totals, or payment amounts submitted by the client.
- Calculate authoritative prices and totals on the server.
- Validate product and variant availability during checkout.
- Recheck stock before confirming an order.
- Prevent overselling through appropriate transaction and concurrency handling.
- Ensure that cart and order access is restricted to their rightful customer or authorized administrator.
- Preserve historical order item details and prices even if catalog information changes later.

---

## 7. Fashion E-Commerce Rules

### Catalog
- Catalog owns products, product variants, categories, attributes, sizes, colors, SKUs, and product images.
- A product may have multiple variants.
- Variant-specific attributes and pricing must be modeled clearly.
- SKU identity belongs to Catalog.

### Inventory
- Inventory owns stock quantities, stock movements, reservations, and reservation releases.
- Inventory identifies stock using the relevant product variant or SKU identifier.
- Other domains must not directly manipulate stock records.

### Cart
- Cart owns shopping cart data and cart item management.
- Cart data is not an authoritative source for final prices or stock availability.
- Checkout must revalidate relevant cart information.

### Checkout
- Checkout coordinates the checkout use case.
- It delegates cart validation, pricing, stock reservation, order creation, and payment operations to their owning domains.
- It must handle payment failures, retries, and duplicate requests safely.
- It must preserve transactional consistency and avoid leaving inconsistent orders or stock reservations.
- The exact sequence of payment, order creation, and stock reservation must be designed before implementation based on the selected payment provider and reservation strategy.
- Do not assume that payment must always happen before or after order creation.

### Orders
- Orders owns order records, order items, and order lifecycle rules.
- Orders must preserve the relevant product description, SKU, variant attributes, and prices at the time of purchase.
- Order history must not depend on current catalog values.

### Payments
- Payments owns payment operations and payment provider integrations.
- Never store sensitive card details unless a properly designed, compliant payment integration explicitly requires it.
- Never expose provider secrets, payment tokens, or sensitive payment data in logs or API responses.
- Payment status changes must be validated and handled safely, including duplicate callbacks and retries.

---

## 8. Security Rules

- Enforce authentication and authorization on the server side.
- Protect all admin operations with appropriate authorization.
- Verify resource ownership before allowing customers to access or modify carts, orders, addresses, or other private resources.
- Validate all external input.
- Never trust client-submitted prices, totals, roles, permissions, ownership identifiers, or payment statuses.
- Protect web routes using Laravel's appropriate middleware and CSRF protection.
- Keep secrets and credentials in environment configuration, not source code.
- Never commit real `.env` files or credentials.
- Do not expose passwords, tokens, secrets, or sensitive personal/payment information in responses or logs.
- Use safe handling for file uploads, including validation of file types and sizes.
- Treat payment callbacks and external integration requests as untrusted until properly verified.
- Avoid leaking internal exceptions, SQL details, or sensitive information to users.

---

## 9. Coding Standards

- Follow PSR-12 as the PHP formatting baseline.
- Follow Laravel 13 conventions for framework-specific code.
- Use Laravel Pint to check and format PHP code when it is available in the project.
- Do not install additional formatting or static-analysis packages without justification and approval.
- Use meaningful and consistent class, method, variable, and database names.
- Declare parameter and return types wherever practical.
- Prefer dependency injection over manually constructing dependencies.
- Keep methods focused on one responsibility.
- Avoid unnecessary comments; explain why when code is not self-explanatory.
- Avoid duplicated code when a clear, maintainable shared implementation is justified.
- Do not suppress errors or exceptions without a justified reason.
- Follow existing project conventions unless there is a clear reason to improve them.

---

## 10. Testing Rules

- Add or update tests for every meaningful feature or behavior change.
- Test successful and failed scenarios.
- Test authorization and resource ownership.
- Test validation failures.
- Test transaction rollback where relevant.
- Test stock concurrency and duplicate operations where relevant.
- Test payment failures, retries, and duplicate callbacks where relevant.
- Prefer testing business behavior through domain Actions and critical workflows.
- Add integration or Feature tests when interactions between domains or HTTP delivery mechanisms need verification.
- Do not delete, weaken, skip, or modify existing tests merely to make a test suite pass.
- If an existing test is incorrect, explain why and correct it without reducing meaningful coverage.

Expected test organization:

- `tests/Feature/Api/`
- `tests/Feature/Web/`
- `tests/Feature/Domains/`
- `tests/Unit/Domains/`

Use the existing project structure when adding tests. Do not create unnecessary test directories.

---

## 11. Laravel Integration

- Prefer Laravel's standard capabilities and conventions.
- Use the Laravel service container for dependency resolution.
- Bind Contracts in `AppServiceProvider` or a dedicated provider only when actual bindings are needed.
- Do not create a Service Provider for every domain by default.
- Use Laravel authentication, authorization, queues, events, notifications, and other framework capabilities appropriately.
- Avoid unnecessary packages and custom framework-like abstractions.
- Keep migrations in `database/migrations/`.

---

## 12. AI Agent Workflow

Before implementing a feature:

1. Read `AGENTS.md` and the relevant architecture documentation.
2. Inspect the existing implementation, related domains, routes, migrations, and tests.
3. Identify the affected domains and their ownership boundaries.
4. Identify security, authorization, data integrity, and performance implications.
5. Present a short implementation plan before significant changes.
6. Implement the complete requested feature without unrelated refactoring.
7. Add or update relevant tests.
8. Run the relevant tests and the full test suite when appropriate.
9. Review the implementation for architecture violations, duplicated logic, security issues, and unnecessary complexity.
10. Update documentation when architecture, domain ownership, workflows, or significant behavior changes.
11. Provide a final report with changed files, implementation summary, verification results, and remaining risks.

### Agent restrictions

Never:
- Implement a feature before understanding the relevant existing code.
- Change architecture or domain ownership without explaining the reason.
- Change database schema without explaining the reason and impact.
- Introduce dependencies without justification and approval.
- Delete or weaken tests to make them pass.
- Modify unrelated functionality.
- Perform broad refactoring when a focused change is requested.
- Duplicate business logic between API and Web.
- Bypass domain ownership rules for convenience.
- Add abstractions, layers, or patterns without a practical need.
- Claim that tests, builds, or commands passed unless they were actually executed.
- Hide failing tests, incomplete work, or known risks in the final report.

For documentation-only tasks, do not modify application code.

For audit-only tasks, do not implement fixes unless explicitly requested.

---

## 13. Project Scope and Engineering Judgment

- Build for the current single-store fashion e-commerce requirements.
- Prefer simple, maintainable solutions over unnecessary complexity.
- Do not introduce multi-tenancy, marketplace functionality, SaaS billing, or other future-product architecture unless explicitly requested.
- Do not add packages without a clear need and approval.
- Do not sacrifice security, data integrity, or maintainability for speed.
- Do not over-engineer features in anticipation of hypothetical requirements.
- When a requirement is unclear and affects architecture, data integrity, security, or business behavior, explain the ambiguity and ask before making an irreversible decision.

The objective is a **professional, secure, maintainable Laravel application with clear domain ownership and responsibilities**, not the maximum number of architectural layers or abstractions.