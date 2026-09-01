# Kinkoza Marketplace

Kinkoza is a focused proof of concept for a pan-European B2B asset marketplace. It covers public asset discovery, protected seller information, authenticated listing creation, guest and buyer carts, and an atomic order and invoice checkout without attempting to reproduce a complete commerce suite.

Local Laravel Herd site: [https://kinkoza.test](https://kinkoza.test)

![Two professionals inspecting an industrial asset](public/images/marketplace-hero.webp)

## Product scope

The three core marketplace pages are implemented as server-driven Livewire components:

| Page | Route | Access | Purpose |
| --- | --- | --- | --- |
| Listing search | `/` | Public | Search, filter, sort, and cursor-paginate currently published assets. |
| Listing detail | `/listings/{slug}` | Public for published assets; owners can view their own non-public assets | Inspect an asset, add it to a cart, and deliberately reveal protected seller contact details. |
| Create listing | `/sell` | Authenticated and email verified | Validate and create a draft, pending-review, or published listing. |

The application also includes a cart, authenticated checkout, owned order confirmation, invoice generation, locale switching, and starter-kit account security.

Implemented behavior includes:

- Title search and allow-listed category, country, price, and sort inputs.
- Publication-window enforcement at the database-query boundary.
- Seller-owned draft visibility without leaking drafts to guests or other accounts.
- A mock KYB publication gate: only verified sellers publish immediately; other publication requests become pending review.
- Protected seller contact details with authorization, throttling, a locked Livewire reveal flag, and audit logging.
- Guest carts and deterministic guest-cart adoption or merging after sign-in.
- Snapshot prices and titles across cart, order, and invoice lines.
- Authenticated, idempotent checkout that atomically creates orders and invoices while decrementing inventory.
- English and French marketplace copy with locale-aware currency formatting.
- Queued, after-commit order-confirmation notifications.
- Seeded marketplace data and two demo accounts.

## Technology

| Concern | Choice |
| --- | --- |
| Runtime | PHP 8.4 and Laravel 13 |
| UI | Livewire 4, Alpine, Tailwind CSS 4, and Flux |
| Application operations | `lorisleiva/laravel-actions` through `Action::run()` and action adapters |
| Persistence | Relational database; SQLite locally, with MySQL or PostgreSQL recommended for production |
| Tests | Pest 5 |
| Quality | Pint and Larastan/PHPStan at `level: max` |
| Local tooling | Laravel Herd and Laravel Debugbar |
| CI | GitHub Actions on pushes to `main` and pull requests |

The application uses Laravel's slim skeleton. Middleware and exception behavior are configured in `bootstrap/app.php`; there are no resurrected HTTP or console kernel classes. Modern Laravel usage includes backed enums, model `casts()` methods, `#[Scope]` query scopes, `Model::shouldBeStrict()`, `Cache::flexible()`, the `Number` helper, locked Livewire properties, and after-commit events and queued listeners.

## Architecture

The Laravel host owns authentication, users, locale middleware, framework configuration, frontend assets, and shared sequence allocation. Four Composer path packages under `app-modules/` own the application domains and presentation layer.

| Module | Responsibility | Depends on |
| --- | --- | --- |
| [`catalog`](app-modules/catalog/README.md) | Listings, publication rules, seller policy, categories, currencies, countries, and catalog caching | Host user model |
| [`cart`](app-modules/cart/README.md) | Guest and buyer identity, line snapshots, totals, optimistic versions, and mutation locks | Catalog |
| [`sales`](app-modules/sales/README.md) | Checkout, inventory allocation, orders, invoices, events, and confirmation notifications | Cart and Catalog |
| [`storefront`](app-modules/storefront/README.md) | Routes, Livewire pages, account dashboard, form validation, locale switching, contact reveal, and request boundaries | Catalog, Cart, and Sales |

Dependency direction is one-way:

```text
Storefront -> Sales -> Cart -> Catalog -> Host User
             |        |
             +------> Catalog
```

Application workflows live in Laravel Action classes. Presentation code invokes typed entry points such as `CreateListing::run(...)`, `AddListingToCart::run(...)`, and `CheckoutCart::run(...)`. There are no business workflow service wrappers. Shared private cart mechanics live in an action concern; callers cannot bypass the focused actions.

The modular structure is intended to keep ownership obvious without introducing distributed-system boundaries into a proof of concept. Modules share the same Laravel container, database transaction manager, cache, queue, and deployment unit.

## Data model and identifiers

Users, listings, carts, cart items, orders, order items, invoices, and invoice items use ULIDs as primary keys. Foreign keys use the same ULIDs, allowing an entire dependent record graph to receive stable identifiers before insertion.

Each table also has a unique unsigned `sequence` column. `App\Support\Database\SequenceGenerator` maintains independent, monotonically increasing counters in the `sequences` table. `HasUlidAndSequence` assigns both values during normal model creation, while checkout reserves contiguous ranges for order and invoice lines to reduce sequence-row lock traffic.

The ULID is canonical. Numeric sequences exist for support, ordering, and readable references such as `ORD-00000001` and `INV-00000001`. Failed transactions may leave sequence gaps; values are never reused.

Money is stored as integer minor units. Floating-point monetary values are not persisted. A cart snapshots the accepted listing title, currency, and unit price, and Sales copies those snapshots into immutable order and invoice lines.

## Listing lifecycle and seller identity

A listing contains title, slug, description, category, status, currency, net price, country, city, online and offline timestamps, inventory, seller, and an optional external image URL. Category, status, country, and currency are backed enums cast by the model.

A listing is publicly searchable only when:

1. its status is `published`;
2. `online_at` is not in the future; and
3. `offline_at` is null or remains in the future.

The query scope and model visibility method enforce the same rule. Owners can view their own drafts through a seller-scoped database query; other accounts receive a 404 rather than a filtered model after retrieval.

Seller company name, registration/VAT number, email, and phone belong to the host user profile rather than being duplicated on each listing. Seeded sellers contain those fields. The current registration and profile screens do not yet collect or require the complete business profile, so business onboarding is a documented pre-production requirement.

## Search, pagination, indexes, and caching

The public search query selects only card fields, eager-loads the seller fields required by the result cards, starts from the publication scope, and uses parameter-bound conditions. Category and country use enum allow-lists, price input is converted to integer minor units, and sort choices are matched against a fixed set. No user input can select a SQL column or direction.

Results use cursor pagination with deterministic secondary ordering by ULID. Cursor pagination avoids the increasing offset cost of deep numbered pages. The current filter lifecycle resets Laravel's default page name rather than the named cursor paginator; this must be corrected and covered by a multi-page regression test before relying on filter changes from a later cursor page.

Listing indexes currently support:

- public status and publication-window queries;
- status, category, and price queries;
- status, country, and price queries; and
- seller, status, and update-time queries.

Title search intentionally remains an SQL `LIKE '%term%'` implementation for the proof of concept. A leading wildcard cannot use a normal B-tree title index. Combined filters may use one composite index and still require a temporary sort. Before a large catalog, query plans must be measured against production data and search should move to database full-text search or a dedicated search engine.

`CatalogCache` demonstrates Laravel's stale-while-revalidate `Cache::flexible()` primitive with versioned keys for featured published listings. Listing creation invalidates that version. The required search page does not currently read this cache, and checkout inventory changes do not invalidate it. Production work must either connect the cache to an actual read path with complete invalidation or remove it rather than retain unused caching code.

EUR and GBP are supported, but the current price filter and sort compare stored minor-unit values without currency normalization. A production search must require a currency filter or compare converted reporting amounts from a versioned exchange-rate source.

## Security boundaries

Security decisions are enforced on the server rather than through hidden UI controls:

- `/sell`, checkout, and order routes require authentication and verified email where appropriate.
- `ListingPolicy` covers viewing, creation, ownership writes, and contact reveal.
- Non-public listing visibility is scoped in SQL by publication or seller ownership.
- Livewire identifiers, cart versions, checkout idempotency keys, and contact state use `#[Locked]` where client mutation would be unsafe.
- Computed Livewire models are queried again with the current account or guest identity on every request.
- Public component methods validate input and repeat authorization or domain ownership checks at mutation time.
- Search enums and sort directions are allow-listed; values remain bound query parameters.
- Domain models guard identity, ownership, lifecycle, and accounting fields from public mass assignment.
- Blade output is escaped. The only raw starter-kit SVG output is generated by the trusted two-factor QR-code provider.
- Seller email and phone are not selected or rendered with the public listing. A verified non-owner must invoke the reveal action.
- Contact reveal is policy-authorized, limited to five attempts per minute per account, and logged with buyer, listing, and request IP context.
- Login, two-factor, passkey, search, listing, locale, cart, and checkout surfaces are throttled.
- `.env` files, authentication files, private keys, dependencies, generated builds, and Debugbar data are ignored.

Known security hardening still required for production:

- add layered contact quotas by account and IP, longer daily limits, and a stronger verified-business requirement to resist account farming;
- explicitly throttle registration and password-reset submissions in addition to login;
- move seller media to managed object storage or proxy it, because direct external image URLs disclose visitor IP addresses to third parties;
- define a restrictive content security policy and production proxy trust boundary;
- add automated dependency, secret, and application security scanning; and
- retain contact-reveal audit events in a queryable security log with alerting.

## Cart and checkout concurrency

Cart and checkout workflows protect explicit invariants:

1. A guest or buyer identity has one active cart key. Cache locks serialize cart creation, adoption, and merging.
2. Cart mutations use retryable database transactions and lock the affected cart, item, and listing rows.
3. Cart totals are recalculated from line snapshots after every mutation.
4. A cart `version` acts as an optimistic concurrency token; stale updates, removal, and checkout are rejected.
5. Checkout requires a buyer-scoped idempotency key and enforces one order per cart.
6. Published status, currency, inventory, ownership, and cart state are checked again while rows are locked.
7. Inventory is decremented with a conditional update so stock cannot fall below zero.
8. Order, invoice, line records, cart conversion, and inventory changes commit in one transaction.
9. `OrderPlaced` is dispatched after commit, and its notification action is queued after commit.

A cart is not an inventory reservation. Availability is checked while shopping, but stock is allocated only by successful checkout.

SQLite and the array cache make local tests fast, but they cannot prove row-lock or distributed-lock behavior. Production concurrency verification requires MySQL or PostgreSQL, Redis, and multi-process integration tests that force overlapping cart and checkout attempts.

## Localization

Supported locales are configured in `config/locales.php`; the current set is English (`en`) and French (`fr`). Guest choice is stored in the session. Authenticated choice is also persisted to `users.locale` and restored on later sessions.

`SetLocale` runs in the web middleware group. The user implements Laravel's locale-preference contract so queued notifications render in the account language. Currency formatting uses Laravel's `Number` helper and the current locale.

French coverage targets marketplace pages, domain errors, validation, and order confirmation. Starter-kit account screens remain primarily English and are not presented as fully localized.

## Queues and delivery guarantees

The default environment uses the database queue. Checkout dispatches `OrderPlaced` only after its database transaction commits. `SendOrderConfirmation` is a queued Laravel Action with three attempts, backoff, and missing-model cleanup.

Run a worker while testing notifications:

```bash
herd php artisan queue:work --tries=3
```

`MAIL_MAILER=log` writes mail to `storage/logs/laravel.log`. Configure a real transport before deployment.

After-commit dispatch prevents workers from observing rolled-back orders, but it does not guarantee that an event will be delivered if the process fails after commit and before enqueueing. Financial or integration delivery guarantees require a transactional outbox and idempotent consumers.

## Local setup with Laravel Herd

Requirements:

- Laravel Herd with PHP 8.4;
- Composer 2; and
- Node.js `^20.19` or `>=22.12` with npm.

From the project directory:

```bash
cp .env.example .env
touch database/database.sqlite
herd link kinkoza --secure --isolate=8.4 --update-env
herd composer install
herd php artisan key:generate
herd php artisan migrate --seed
npm ci
npm run build
herd open
```

The application is available at [https://kinkoza.test](https://kinkoza.test). Herd normally serves PHP directly, so `artisan serve` is not required.

If macOS prevents Herd's PHP-FPM process from reading a privacy-protected project directory, move the clone into Herd's configured sites directory or grant Herd access in System Settings. A temporary fallback is:

```bash
herd php artisan serve --host=127.0.0.1 --port=8173
herd proxy kinkoza http://127.0.0.1:8173 --secure
```

The fallback depends on the foreground Artisan process and will return 502 when that process stops. Moving the project or fixing access is the durable solution.

For frontend development:

```bash
npm run dev
```

To reset the demo data:

```bash
herd php artisan migrate:fresh --seed
```

## Portable local setup

The project does not require Herd. With PHP 8.4 and the required extensions available:

```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

Open the URL printed by Artisan. Run `php artisan queue:work --tries=3` in another process when testing queued notifications.

## Demo accounts and seed data

| Role | Email | Password | Locale and access |
| --- | --- | --- | --- |
| Verified seller | `seller@example.com` | `password` | French preference; complete demo business profile; can publish immediately |
| Buyer | `buyer@example.com` | `password` | English preference; can reveal contact details and check out |

The seed creates twelve deterministic listings: ten public listings plus one pending-review listing and one draft. They cover all four categories, all three launch countries, EUR and GBP, and plausible B2B asset titles. All listings currently belong to one seller, use a shared description pattern, and use deterministic placeholder images; multiple seller companies and richer descriptions are a known demo-data improvement.

## Debugbar

Laravel Debugbar is installed as a development dependency and is available locally when `APP_DEBUG=true`. Keep `APP_DEBUG=false` in production. Debugbar data under `storage/debugbar` is ignored.

## Quality checks and CI

The complete PHP quality gate clears cached configuration, checks Pint formatting, runs Larastan/PHPStan at `level: max`, and executes the root and module Pest suites:

```bash
herd composer test
```

Run checks independently with:

```bash
herd composer lint:check
herd composer types:check
herd php artisan test
npm run build
```

The GitHub Actions workflow runs on pushes to `main` and pull requests with PHP 8.4 and Node 22. It installs the application, builds assets, and runs the same PHP quality gate. Workflow actions are pinned, repository permissions are read-only, and checkout credentials are not persisted.

Static analysis covers the host application, bootstrap, configuration, database, and routes plus every module's production source, factories, migrations, seeders, and routes. There is no PHPStan baseline and no ignored-error configuration; CI fails on any reported error.

Feature coverage includes publication windows, seller policies, filters, cart identity isolation and merging, stale-version rejection, inventory and currency checks, rollback and idempotency, immutable snapshots, order ownership, contact authorization, locked Livewire state, localization, queued notification wiring, and ULID/sequence allocation.

Important remaining test gaps are sort ordering, cursor transitions, filter URL/history behavior, registration and password-reset throttling, larger query-count fixtures, true browser coverage of the authenticated create page, and real database/cache concurrency.

## Traffic at 100 times the proof-of-concept load

The current design assumes a small catalog and modest request volume. Scaling should be driven by measurements rather than by adding infrastructure pre-emptively.

### Public traffic and crawler control

- Put static assets and public listing images behind a CDN.
- Add edge caching for anonymous listing-detail responses where personalization is absent.
- Apply WAF and bot-management rules before requests reach Laravel.
- Separate limits for human search, automated crawlers, authenticated accounts, contact reveals, and write operations.
- Introduce daily contact budgets and anomaly detection across account, IP, network, and listing dimensions.
- Publish explicit crawler policy, sitemap, canonical URLs, and structured listing data while blocking sensitive or account routes.

### Search and database

- Capture slow-query logs and production `EXPLAIN` plans using a representative catalog fixture.
- Add covering indexes for the filter-and-sort combinations actually observed rather than every theoretical combination.
- Move title search to PostgreSQL full text or a dedicated search engine when leading-wildcard latency or database CPU crosses the agreed threshold.
- Keep cursor pagination and stable sort keys; avoid deep offset pagination.
- Add a required currency filter or normalized reporting-price projection before presenting cross-currency ranges.
- Use PostgreSQL or MySQL with connection pooling and read replicas for read-heavy discovery traffic.

### Cache and invalidation

- Use Redis for cache, rate limits, sessions, and distributed locks.
- Cache only bounded, high-reuse projections such as featured lists, facets, and anonymous detail views; do not cache every arbitrary filter combination.
- Emit committed catalog-change events and advance cache versions after create, update, publication, deletion, and inventory changes.
- Measure cache hit rate and stale-response age. Remove caches that do not reduce a measured bottleneck.

### Queues and integrations

- Separate queues for user-visible notifications, media processing, search indexing, and integration delivery.
- Scale workers by queue latency and failure rate, with dead-letter handling and idempotent retry behavior.
- Add a transactional outbox for order, payment, inventory, and settlement events.
- Keep checkout synchronous only for database state that must commit atomically; move mail and external integrations off the request path.

### Media, observability, and operations

- Replace seller-provided external image URLs with validated private uploads, malware scanning, responsive variants, and signed or proxied delivery.
- Add application performance monitoring, centralized structured logs, error tracking, queue dashboards, and database/cache metrics.
- Define service-level objectives for search latency, checkout success, queue delay, and contact-reveal abuse response.
- Load-test public search and overlapping checkout attempts before each material traffic increase.
- Run managed backups and regularly test restoration, not only backup creation.

## Deliberate scope and tradeoffs

This repository is a foundation for a basic ecommerce marketplace, not a complete production platform.

Deliberately excluded:

- Payment authorization and capture, escrow, VAT, shipping, fulfillment, cancellation, refunds, returns, and disputes.
- Admin and moderation screens, automated KYB onboarding, and role management.
- Multi-vendor settlement, commission, promotions, coupons, wish lists, reviews, and auctions.
- Product variants, warehouse stock, reservations, and advanced catalog taxonomies.
- Full-text infrastructure, search analytics, and exchange-rate conversion.
- Managed media upload and transformation.
- Public APIs, webhooks, mobile clients, and a transactional outbox.
- Full localization of starter-kit account screens.
- Production observability, feature flags, and automated lifecycle jobs.

The proof of concept intentionally keeps deployment as one Laravel application and one relational data model. That preserves transaction boundaries and makes domain ownership visible without prematurely introducing network calls or eventual consistency between modules.

## What was difficult or ambiguous

- The requested product slice ends at listing creation, while a useful ecommerce foundation also needs carts and commercial records. The implementation adds a narrow cart and checkout path but deliberately stops before payment, tax, fulfillment, and settlement.
- Seller business information can be normalized on the account or snapshotted per listing. The current model keeps it on the account, which avoids duplication but requires a business-onboarding flow that is not yet implemented.
- Caching arbitrary search combinations would create a large key space with difficult invalidation. The implementation demonstrates a bounded featured cache instead, but it is not connected to the required search page and should not be presented as a solved search-cache strategy.
- SQLite is ideal for a quick local setup but cannot demonstrate production row-lock behavior. The concurrency code is written for a transactional database, while the limitation is kept explicit rather than hidden by unit tests.
- A price range across EUR and GBP is ambiguous without selecting a currency or defining an exchange-rate snapshot. The current comparison is intentionally simple and must not be carried into production unchanged.
- Publication windows determine visibility without mutating a listing to `expired`. This avoids depending on a scheduler for correctness, but a separate status-transition workflow would still be valuable for operations and reporting.
- External image URLs kept the proof of concept inside its time box, but they are not an acceptable long-term media or privacy design.

## Prioritized next steps

### Before presenting this as production-ready

1. Add business-profile onboarding and require company name, registration/VAT number, country, phone, verified email, and KYB state at the appropriate lifecycle boundary.
2. Reset the named cursor paginator when filters change and add sort, cursor, URL hydration, and browser-history tests.
3. Connect caching to a measured read path or remove it; invalidate catalog projections after every relevant committed mutation.
4. Add layered contact-reveal and authentication throttling.
5. Test cart and checkout races with PostgreSQL or MySQL plus Redis across multiple processes.
6. Replace direct external images with managed media and a content security policy.

### Next commerce capabilities

1. Payment and escrow state machines with idempotent provider webhooks.
2. VAT, delivery, discounts, cancellation, refund, fulfillment, and settlement workflows.
3. Transactional outbox, search indexing, and operational dashboards.
4. Moderation and KYB administration.
5. Broader localization and accessibility/browser regression coverage.
