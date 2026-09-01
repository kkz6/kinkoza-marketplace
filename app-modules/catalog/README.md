# Catalog module

`kinkoza/catalog` is the marketplace's source of truth for sellable listings. It owns the listing schema, typed catalog values, listing creation, publication visibility, authorization policy, deterministic test data, and the small featured-listings cache.

The module does not own HTTP screens, carts, checkout, orders, invoices, payments, media uploads, moderation workflows, or a search engine. Those concerns belong to higher-level modules or application adapters.

## Boundary and dependency direction

Catalog depends on Laravel components, `lorisleiva/laravel-actions`, and these shared application services:

- `App\Models\User` for listing ownership;
- `App\Models\Concerns\HasUlidAndSequence` for identifier behavior;
- `App\Support\Database\SequenceGenerator` for concurrency-safe numeric sequences.

Dependencies point toward Catalog:

- Cart consumes `Listing` when a line is added.
- Sales consumes `Listing` and `Currency` during checkout and inventory reduction.
- Storefront consumes Catalog's model, enums, policy, DTO, and creation action.

Catalog must remain unaware of Cart, Sales, and Storefront. Its `routes/catalog-routes.php` file is currently empty, and `CatalogServiceProvider` does not register routes or views.

## Directory map

```text
catalog/
├── composer.json
├── database/
│   ├── factories/ListingFactory.php
│   ├── migrations/*_create_listings_table.php
│   └── seeders/ListingSeeder.php
├── routes/catalog-routes.php
├── src/
│   ├── Actions/CreateListing.php
│   ├── Data/CreateListingData.php
│   ├── Enums/{Country,Currency,ListingCategory,ListingStatus}.php
│   ├── Models/Listing.php
│   ├── Policies/ListingPolicy.php
│   ├── Providers/CatalogServiceProvider.php
│   └── Support/CatalogCache.php
└── tests/Feature/
```

Laravel package discovery loads `CatalogServiceProvider`. The provider loads the module migrations, registers `ListingPolicy`, and binds `CatalogCache` as a singleton.

## Listing data model

The `listings` table contains:

| Field | Meaning |
| --- | --- |
| `id` | ULID primary key used by relationships and APIs. |
| `sequence` | Unique unsigned numeric reference generated from the shared `sequences` table. |
| `seller_id` | ULID foreign key to `users.id`; database deletion cascades to the listing. |
| `title`, `slug`, `description` | Listing content; `slug` is unique. |
| `category` | `ListingCategory` string-backed enum. |
| `status` | `ListingStatus` string-backed enum. |
| `currency` | Three-character `Currency` value. |
| `price_minor` | Unsigned integer price in the currency's minor unit; floats are not stored. |
| `country`, `city` | `Country` code and free-form city. |
| `online_at`, `offline_at` | Inclusive publication start and optional exclusive publication end. |
| `inventory_quantity` | Unsigned available quantity, defaulting to `1`. |
| `image_url` | Optional external image URL. Catalog does not upload or transform it. |
| `version` | Revision counter, defaulting to `1`; Sales increments it when inventory is decremented. It is not currently a general optimistic-lock API. |
| `created_at`, `updated_at` | Laravel timestamps. |

The migration contains four deliberate composite indexes:

| Index | Intended query |
| --- | --- |
| `status, online_at, offline_at` | Public publication-window filtering. |
| `status, category, price_minor` | Category results with a price range or price ordering. |
| `status, country, price_minor` | Country results with a price range or price ordering. |
| `seller_id, status, updated_at` | An owner's listings grouped by workflow state. |

These indexes support the proof-of-concept filters, but they are not a claim that every filter and sort combination is covered. The title search uses a leading-wildcard `LIKE`, there is no full-text index, the deterministic `id` sort tie-breaker is not explicitly part of the composites, and a query combining category and country may use only one of those filter indexes. Those limitations are acceptable for the current small dataset and are addressed in the 100x evolution plan below.

### Seller metadata boundary

Catalog stores only `seller_id`. Company name, registration number, country, email, phone, email verification, and the mock seller-verification flag belong to the host `App\Models\User`; they are not duplicated on a listing.

This has three consequences:

- Public search may eager-load only the non-sensitive seller columns it needs, currently company name and country.
- Catalog's `revealContact` policy decides whether a caller may reveal contact information, but Catalog never returns the email or phone. Storefront performs the authorized, throttled, and logged lookup.
- Seller profile completeness is outside this module. `CreateListing` accepts the supplied host user and does not require company or contact fields itself. A production onboarding or KYB workflow must establish those fields before a seller is allowed to publish.

Seller metadata is live rather than snapshotted on the listing. Renaming a company therefore changes how its existing listings are presented. Commercial snapshots created at checkout belong to Sales, not Catalog.

### ULID and sequence convention

The ULID is the canonical key. Dependent records must reference `listings.id`, never `sequence`. The numeric sequence is a unique, human-friendly reference and ordering tie-breaker.

The `CreateListing` action reserves the next `listings` sequence through `SequenceGenerator`, then creates the listing with an explicit ULID. Normal Eloquent creation is also covered by `HasUlidAndSequence` when either identifier is absent. Sequence reservation uses a locked row in the shared `sequences` table. A sequence is reserved before the listing transaction, so gaps after a failed write are valid and must not be treated as corruption.

Creation slugs use `{slugified-title}-{sequence}`. An empty slugified title falls back to `listing-{sequence}`.

The model guards `id`, `sequence`, `seller_id`, `slug`, `status`, and `version` from ordinary mass assignment. Use the creation action when creating application listings; it owns assignment of these protected fields.

## Typed values

- `ListingStatus`: `draft`, `pending-review`, `published`, `expired`.
- `ListingCategory`: machinery/equipment, vehicles/fleet, commercial property, and intangible assets.
- `Country`: France (`FR`), Belgium (`BE`), and Luxembourg (`LU`).
- `Currency`: EUR and GBP, both represented with two decimal places.

`Currency::format()` formats a minor-unit integer using the current Laravel locale. `formattingMetadata()` exposes the symbol, decimal places, and symbol position for clients.

## Public actions and usage

### Create a listing

`CreateListing::run(User $seller, CreateListingData $data): Listing` is the application-facing write operation. Laravel Actions resolves the action and delegates to its typed `handle(User, CreateListingData): Listing` method.

```php
use Carbon\CarbonImmutable;
use Kinkoza\Catalog\Actions\CreateListing;
use Kinkoza\Catalog\Data\CreateListingData;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;

$listing = CreateListing::run($seller, new CreateListingData(
    title: 'Five-axis CNC machine',
    description: 'A maintained production machine with service history.',
    category: ListingCategory::MachineryEquipment,
    status: ListingStatus::Published,
    currency: Currency::EUR,
    priceMinor: 12_500_000,
    country: Country::France,
    city: 'Lyon',
    onlineAt: CarbonImmutable::now(),
    inventoryQuantity: 1,
));
```

The action retries the listing transaction up to three times and invalidates the featured cache after commit. A request for `Published` from a user whose `is_verified_seller` flag is false is stored as `PendingReview`.

`CreateListingData` is typed but does not perform semantic validation. HTTP, CLI, and import adapters must validate title lengths, non-negative minor-unit prices, inventory bounds, supported enum values, URL shape, and publication-date ordering before constructing it. The Storefront Livewire form is one such adapter.

### Query listings

```php
use Kinkoza\Catalog\Models\Listing;

$publicListings = Listing::query()
    ->published()
    ->latest('online_at')
    ->get();

$sellerListings = Listing::query()
    ->ownedBy($seller)
    ->latest('updated_at')
    ->get();
```

`ownedBy()` accepts either a `User` instance or a seller ULID string. Catalog does not provide a search repository. Storefront owns the public search adapter and currently composes this model query with:

- `published()` as the mandatory starting scope;
- case-insensitive substring matching on `title` through `whereLike`;
- allow-listed `ListingCategory` and `Country` enum values;
- minimum and maximum prices converted to integer minor units;
- a fixed sort allow-list: newest, price ascending, or price descending;
- a deterministic ULID tie-breaker; and
- cursor pagination in pages of 12.

The result query selects only card fields and eager-loads the seller projection used by the template, avoiding an N+1 query. Search and filter state belongs to Storefront and is reflected in the URL there. Catalog deliberately keeps SQL column names and sort directions out of request input.

Cursor pagination avoids the growing offset cost of page-number pagination, but it does not make an unindexed sort free. The default and price queries can still require a filesort or temporary sort for some filter combinations. The current seed contains 12 believable listings and the test suite verifies filter semantics, not production-scale query plans or latency.

### Read featured listings

```php
use Kinkoza\Catalog\Support\CatalogCache;

$featured = resolve(CatalogCache::class)->featuredPublished(12);
```

`featured()` is an alias of `featuredPublished()`. Limits are clamped to `1..50`. Results must be published, inside their publication window, and have inventory above zero. They are ordered by newest `online_at`, then newest `sequence`.

The cache uses Laravel's flexible stale-while-revalidate behavior: 60 seconds fresh and 300 seconds stale. Keys include the value of `catalog:listings:version`; invalidation advances that version instead of scanning or deleting result keys. Superseded keys expire naturally.

This is a small featured-listings cache, not a cache for arbitrary search combinations. The current Storefront search does not call `CatalogCache`, so every public result page is read from the database. That choice avoids a high-cardinality cache-key space and complicated invalidation while the proof of concept is small, but it also means the cache does not reduce crawler traffic on the current search page.

#### Invalidation ownership

Invalidation belongs to the workflow that commits a change capable of altering featured results:

| Change | Current owner | Current behavior |
| --- | --- | --- |
| Listing creation | `CreateListing` | Invalidates after the listing transaction commits. |
| Publication, price, image, or deletion | No application workflow yet | A future owning action must invalidate after commit. |
| Inventory deduction | Sales checkout | Does not currently invalidate Catalog's cache. |
| Publication-window passage | Time | No event is emitted; freshness/stale TTLs bound the delay. |

The current Sales inventory update can therefore leave an out-of-stock listing in an already-populated featured cache until its flexible-cache window expires. A future cross-module solution should publish an after-commit Catalog event or call a Catalog-owned invalidation action without making Catalog depend on Sales. Direct model writes and factories do not invalidate automatically.

## Invariants

### Publication

A listing is public only when all of these conditions hold:

1. `status` is `ListingStatus::Published`;
2. `online_at` is not in the future;
3. `offline_at` is null or strictly in the future.

`Listing::isPubliclyVisible()` and the `published()` scope implement the same rule. Inventory is deliberately not part of public visibility; only the featured cache excludes out-of-stock rows.

The module does not currently implement status-transition rules, moderator actions, scheduled publication jobs, or expiry mutation. A caller with direct persistence access must not assume those workflows exist.

### Authorization

`ListingPolicy` enforces the current application rules:

- anyone may call `viewAny`;
- the seller may view their own non-public listing;
- everyone else may view only a publicly visible listing;
- any authenticated `User` may create;
- only the owning seller may update or delete;
- seller contact may be revealed only to an authenticated, email-verified, non-owner user while the listing is public.

There is no administrator override in this policy. Higher-level entry points should authorize through Laravel's Gate rather than duplicate these checks:

```php
use Illuminate\Support\Facades\Gate;

Gate::authorize('view', $listing);
Gate::authorize('revealContact', $listing);
```

### Search and cache

Public discovery must begin with `published()` so drafts, scheduled listings, expired statuses, and elapsed publication windows do not leak. Database filtering should use enum backing values when a raw value is required. User-provided sort fields must be allow-listed by the caller.

Cache invalidation must happen after a successful write transaction. Direct model writes do not emit an observer that invalidates the catalog. The public Storefront search currently bypasses `CatalogCache` entirely.

## Events

Catalog currently dispatches no domain events and registers no listeners or observers. Listing creation and cache invalidation are synchronous. If events are introduced, dispatch them after commit so consumers cannot observe rolled-back listings.

## Factories and seed data

`ListingFactory` provides `draft`, `pendingReview`, `published`, `scheduled`, `offline`, `expired`, and `outOfStock` states.

`ListingSeeder` expects an existing `seller@example.com` user, then creates or updates 12 deterministic demo listings owned by that seller. It is intended for the application's coordinated database seeding flow, not standalone use against an empty users table.

## Current limitations

- The application has no listing edit, delete, moderation, or status-transition workflow. The corresponding policy abilities exist, but no HTTP or action entry point consumes them.
- `CreateListingData` provides types, not semantic validation or authorization. Storefront validates its Livewire form and authorizes the current user before calling the action; any future CLI, import, API, or queue adapter must do the same.
- The SQL title match is a substring scan. There is no full-text, typo-tolerant, accent-aware, or relevance-ranked search.
- The public search is not cached, and the featured cache is not rendered by the current Storefront.
- Only creation invalidates the featured cache automatically. Inventory, publication, price, image, and deletion changes need explicit after-commit integration.
- `image_url` is an external URL. Catalog does not upload, inspect, proxy, resize, or sign media.
- Seller company and contact fields are nullable host-user data and are not snapshotted on a listing.
- The small deterministic seed and SQLite test environment verify behavior, not production query plans or crawler-scale throughput.

## Evolution at 100x traffic

The first scaling step is measurement, not replacing SQL by default. Capture slow-query samples and representative `EXPLAIN` plans on the production database with realistic listing cardinality and filter distributions. Add query-count and latency budgets for the default page and the most common filter combinations.

If indexed SQL remains sufficient:

1. Add database-specific covering indexes for the measured sort paths, including the deterministic `id` tie-breaker where the engine needs it.
2. Keep cursor pagination and limit the supported sort/filter combinations to ones with predictable plans.
3. Cache only demonstrably hot, low-cardinality result sets such as the unfiltered first page or curated featured IDs. Cache identifiers or projections rather than broad hydrated model graphs.
4. Move cache and rate-limit storage from the application database to Redis, and coordinate invalidation through after-commit Catalog events.
5. Put anonymous read traffic behind an appropriate CDN or edge cache while preserving personalized seller and contact boundaries.

If substring search or filter combinations dominate cost, introduce a dedicated search adapter backed by Scout with Meilisearch or Typesense. Index only public listing documents, update them from after-commit events, retain the database as the source of truth, and make removal from search idempotent. Search-engine adoption must not weaken the `published()` visibility invariant: drafts and elapsed publication windows must be excluded before results reach a caller.

Seller media should move to managed object storage with validated uploads, queued processing, responsive variants, and controlled delivery. External seller URLs should not remain a direct browser dependency at production scale.

## Prioritized next steps

1. Add a production-database search benchmark with a large factory seed, query-count assertions, and `EXPLAIN` snapshots for newest and price sorts.
2. Add the measured sort indexes or a dedicated search adapter; do not add speculative composites for every possible filter combination.
3. Introduce Catalog-owned after-commit events for listing and inventory visibility changes, then invalidate or refresh cached projections from idempotent listeners.
4. Either connect a low-cardinality featured/default result cache to Storefront or remove the unused cache until there is a real reader.
5. Add authorized listing update, publication, and deletion actions that validate input, enforce ownership, advance `version`, and own cache invalidation.
6. Define a seller-profile/KYB completeness rule before publication and keep contact retrieval behind Storefront's separate reveal boundary.
7. Replace external image URLs with managed media storage and queued processing.

Throughout those changes, keep checkout and inventory reservation orchestration in Sales, cart snapshots in Cart, and Catalog free of dependencies on either consumer. If Catalog gains routes or views, register them explicitly in `CatalogServiceProvider`; the placeholder route file is not loaded today.

## Tests

Run commands from the repository root. The focused module suite is:

```bash
herd php artisan test app-modules/catalog/tests
```

Run a single behavior file with:

```bash
herd php artisan test app-modules/catalog/tests/Feature/PublicationWindowTest.php
```

Run every module suite or the complete quality gate with:

```bash
herd php artisan test --testsuite=Modules
herd composer test
```

The Catalog feature tests cover listing creation and seller verification, slug uniqueness, localized currency metadata, publication windows and scopes, policy rules, featured-cache versioning, and deterministic seed data.
