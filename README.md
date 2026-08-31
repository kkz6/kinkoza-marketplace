# Kinkoza Marketplace

Kinkoza is a deliberately focused B2B ecommerce marketplace built with Laravel 13, Livewire 4, Tailwind CSS 4, and PHP 8.4. It covers the essential path from discovering a verified business asset to creating an order and invoice, without carrying the weight of a full ecommerce suite.

Local Laravel Herd site: [https://kinkoza.test](https://kinkoza.test)

![Two professionals inspecting an industrial asset](public/images/marketplace-hero.webp)

## What is implemented

- Public listing discovery with search, category, country, price, sort, publication windows, and cursor pagination.
- Seller listing creation with validation, unique slugs, and a review fallback for sellers that have not passed verification.
- Protected seller contact details with authorization, rate limiting, and an audit log entry on reveal.
- Guest and authenticated carts, including safe guest-cart adoption or merging after sign-in.
- Snapshot prices and titles on cart, order, and invoice lines.
- Authenticated checkout that atomically creates the order, order lines, invoice, and invoice lines while decrementing inventory.
- Email verification, passkeys, two-factor authentication, and account settings from the Laravel Livewire starter kit.
- English and French storefronts with locale-aware currency formatting and queued order-confirmation mail.
- Seeded marketplace data and two ready-to-use demo accounts.

## Architecture

The application is a Laravel host with four Composer path packages under `app-modules/`. Each package owns its domain or presentation code, Laravel Actions, service provider, and Pest feature tests; persistence-owning modules also own their migrations.

| Module | Responsibility | Depends on |
| --- | --- | --- |
| [`catalog`](app-modules/catalog/README.md) | Listings, publication rules, seller policy, categories, currencies, countries, and featured-listing cache | Host user model |
| [`cart`](app-modules/cart/README.md) | Guest/buyer cart identity, line snapshots, totals, versions, and cart mutation locks | `catalog` |
| [`sales`](app-modules/sales/README.md) | Checkout, inventory allocation, orders, invoices, events, and confirmation notifications | `cart`, `catalog` |
| [`storefront`](app-modules/storefront/README.md) | Livewire pages, filters, cart and checkout interactions, locale switching, and route boundaries | `catalog`, `cart`, `sales` |

The host application owns cross-cutting concerns: users and authentication, locale middleware, per-entity numeric sequences, framework configuration, and frontend assets. Application-facing use cases are exposed as classes using `lorisleiva/laravel-actions`. Livewire components call typed action entry points such as `CreateListing::run(...)`, `AddListingToCart::run(...)`, and `CheckoutCart::run(...)`; Laravel resolves each action and delegates to its `handle(...)` method.

Actions own the application workflows and their business logic. Cart and checkout actions enforce their transactions, locks, concurrency checks, and domain invariants directly, and Storefront depends only on those typed action entry points. Actions can also adapt other Laravel entry points: `UpdateLocale` is an invokable route action, and `SendOrderConfirmation` is the queued after-commit listener for `OrderPlaced`.

The dependency direction is intentionally one-way: the storefront composes the domains, sales consumes cart and catalog state, and cart consumes catalog state. Catalog does not depend on checkout or presentation code.

## IDs and readable sequences

Users, listings, carts, cart items, orders, order items, invoices, and invoice items use ULIDs as primary keys. Their foreign keys are ULIDs as well, so an entire dependent record graph can be assigned identifiers before it is inserted.

Every one of those tables also has a unique unsigned `sequence` column. `App\Support\Database\SequenceGenerator` maintains an independent, monotonically increasing counter for each table in the `sequences` table. `HasUlidAndSequence` assigns both values on normal model creation, while checkout reserves contiguous blocks for order and invoice lines to reduce counter-lock traffic.

The numeric sequence is for ordering, support, and human-facing references; the ULID remains the canonical identifier. Orders and invoices expose references such as `ORD-00000001` and `INV-00000001`. Reserved sequence values may have gaps when a later transaction fails, which is expected and avoids reusing an issued value.

## Concurrency and checkout invariants

Cart and checkout code is designed around explicit invariants rather than controller-level best effort:

1. Guest and buyer identities have one active cart key. Cache locks serialize creation, adoption, and merging for those identities.
2. Cart mutations run in retryable database transactions, lock the affected cart, item, and listing rows, then recompute totals from stored line snapshots.
3. A cart `version` acts as an optimistic concurrency token. Quantity changes, removal, and checkout reject stale versions.
4. Checkout requires an idempotency key scoped to the buyer and also enforces one order per cart. Retrying the same request returns the existing order; reusing the key for a different cart is rejected.
5. Published state, currency, and available inventory are checked again under lock. Inventory is decremented with a conditional database update, and the listing version advances with it.
6. Order and invoice lines inherit the title, currency, and unit price accepted in the cart. A later listing edit cannot rewrite a commercial record.
7. Order, invoice, all lines, cart conversion, and inventory changes commit in one transaction. `OrderPlaced` is dispatched after commit and its confirmation listener is queued after commit.

A cart is not an inventory reservation. Stock is allocated only when checkout succeeds.

For production concurrency, use MySQL or PostgreSQL and a shared cache/lock store such as Redis. SQLite is the convenient local and test default, but it does not provide the same row-lock behavior for competing application workers.

## Local setup with Laravel Herd

Requirements:

- Laravel Herd with PHP 8.4 installed
- Composer 2
- Node.js `^20.19` or `>=22.12` and npm

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

The application is then available at [https://kinkoza.test](https://kinkoza.test). Herd serves PHP, so `php artisan serve` is not required.

If macOS prevents Herd's PHP-FPM process from reading a privacy-protected project directory, move the clone into Herd's configured sites directory or grant Herd access in System Settings. For a temporary local fallback, proxy Herd's HTTPS domain to Laravel's development server:

```bash
herd php artisan serve --host=127.0.0.1 --port=8173
herd proxy kinkoza http://127.0.0.1:8173 --secure
```

For frontend development, keep Vite running in a terminal:

```bash
npm run dev
```

To reset the database to the known demo state:

```bash
herd php artisan migrate:fresh --seed
```

## Demo accounts

| Role | Email | Password | Locale and access |
| --- | --- | --- | --- |
| Verified seller | `seller@example.com` | `password` | French preference; can publish listings immediately |
| Buyer | `buyer@example.com` | `password` | English preference; can reveal contact details and check out |

The seed creates twelve listings: ten public listings plus one pending-review listing and one draft listing.

## Locales

Supported locales live in `config/locales.php`; the current set is English (`en`) and French (`fr`). A guest's choice is stored in the session. For an authenticated user, the choice is also persisted to `users.locale` and restored on a new session.

`SetLocale` runs in the web middleware group, and Laravel's `HasLocalePreference` contract carries the account preference into notifications. Prices use Laravel's locale-aware number formatter. English source strings are the fallback; French storefront and order-email strings live in `lang/fr.json`.

The current French coverage targets the marketplace and order confirmation. Upstream starter-kit account screens remain in English in this MVP.

## Queues and email

The default environment uses the database queue. Run a worker while testing checkout notifications:

```bash
herd php artisan queue:work --tries=3
```

`MAIL_MAILER=log` writes the rendered confirmation mail to `storage/logs/laravel.log`. Configure a real mail transport before deployment. The listener retries three times with backoff and does not run until the checkout transaction has committed.

## Debugbar

Laravel Debugbar is installed as a development dependency. It is automatically available in the local environment when `APP_DEBUG=true`. Keep `APP_DEBUG=false` in production; Debugbar data under `storage/debugbar` is ignored by Git.

## Quality checks

The complete PHP quality gate clears cached configuration, checks Pint formatting, runs PHPStan at level 7, and executes the root and module Pest suites:

```bash
herd composer test
```

The checks can also be run independently:

```bash
herd composer lint:check
herd composer types:check
herd php artisan test
npm run build
```

Feature coverage includes publication windows and policies, catalog caching, cart identity isolation and merging, stale-version rejection, inventory and currency checks, checkout rollback and idempotency, snapshot integrity, order ownership, contact-detail authorization, localization, queued notification wiring, and ULID/sequence allocation.

## Reference projects

This implementation uses the following repositories as architectural and product references. Their source trees are kept outside the application through the ignored `references/` directory; the runtime does not depend on them.

- [Bagisto](https://github.com/bagisto/bagisto) informed the ecommerce domain vocabulary, but this project intentionally retains only the basic marketplace flow.
- [Medusa restored frontend](https://github.com/Xjectro/medusajs-restored-frontend) informed the storefront journey, reimplemented here as server-driven Livewire components.
- [Launch](https://github.com/kkz6/launch) informed the package-oriented modular structure and domain boundaries.
- [Senior Laravel Developer Challenge](https://github.com/Kinkoza/Senior-Laravel-Developer-Challenge) supplied the core requirements around architecture, performance, concurrency, and testability.

## MVP scope and tradeoffs

This is the foundation of an ecommerce platform, not a feature-for-feature Bagisto rewrite. The following are intentionally outside the current scope:

- Payment capture, VAT calculation, shipping, fulfillment, refunds, returns, and disputes.
- Admin/back-office screens, automated KYB onboarding, moderation workflows, and role management.
- Multi-vendor settlements, commissions, promotions, coupons, wish lists, reviews, and auctions.
- Product variants, taxonomies beyond the current categories, warehouse-level stock, and cart-time inventory reservations.
- Search infrastructure beyond indexed SQL filters, and media storage beyond validated image URLs.
- Public APIs, webhooks, mobile clients, and a transactional outbox.

The after-commit event prevents notifications from observing rolled-back orders, but a production system that requires guaranteed integration delivery should add an outbox and an idempotent dispatcher. Production should also replace local SQLite/database-cache assumptions with a transactional database, Redis locks/cache, a supervised queue worker, managed object storage, real mail, monitoring, and backups. Set `TRUSTED_PROXIES` to the deployment load balancer CIDR rather than a broad wildcard, and proxy seller media through managed storage with an appropriate content-security policy to avoid third-party image tracking.
