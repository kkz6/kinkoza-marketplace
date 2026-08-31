# Catalog module

`kinkoza/catalog` is the marketplace's source of truth for sellable listings. It owns the listing schema, typed catalog values, listing creation, publication visibility, authorization policy, deterministic test data, and the small featured-listings cache.

The module does not own HTTP screens, carts, checkout, orders, invoices, payments, media uploads, moderation workflows, or a search engine. Those concerns belong to higher-level modules or application adapters.

## Boundary and dependency direction

Catalog depends only on Laravel components and these shared application services:

- `App\Models\User` for listing ownership;
- `App\Models\Concerns\HasUlidAndSequence` for identifier behavior;
- `App\Support\Database\SequenceGenerator` for concurrency-safe numeric sequences.

Dependencies point toward Catalog:

- Cart consumes `Listing` when a line is added.
- Sales consumes `Listing` and `Currency` during checkout and inventory reduction.
- Storefront consumes Catalog's model, enums, policy, DTO, and creation service.

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
│   ├── Data/CreateListingData.php
│   ├── Enums/{Country,Currency,ListingCategory,ListingStatus}.php
│   ├── Models/Listing.php
│   ├── Policies/ListingPolicy.php
│   ├── Providers/CatalogServiceProvider.php
│   ├── Services/CreateListingService.php
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

Indexes support public-window, category/price, country/price, and seller/status queries. There is no full-text index.

### ULID and sequence convention

The ULID is the canonical key. Dependent records must reference `listings.id`, never `sequence`. The numeric sequence is a unique, human-friendly reference and ordering tie-breaker.

`CreateListingService` reserves the next `listings` sequence through `SequenceGenerator`, then creates the listing with an explicit ULID. Normal Eloquent creation is also covered by `HasUlidAndSequence` when either identifier is absent. Sequence reservation uses a locked row in the shared `sequences` table. A sequence is reserved before the listing transaction, so gaps after a failed write are valid and must not be treated as corruption.

Creation slugs use `{slugified-title}-{sequence}`. An empty slugified title falls back to `listing-{sequence}`.

The model guards `id`, `sequence`, `seller_id`, `slug`, `status`, and `version` from ordinary mass assignment. Use the creation service when creating application listings; it owns assignment of these protected fields.

## Typed values

- `ListingStatus`: `draft`, `pending-review`, `published`, `expired`.
- `ListingCategory`: machinery/equipment, vehicles/fleet, commercial property, and intangible assets.
- `Country`: France (`FR`), Belgium (`BE`), and Luxembourg (`LU`).
- `Currency`: EUR and GBP, both represented with two decimal places.

`Currency::format()` formats a minor-unit integer using the current Laravel locale. `formattingMetadata()` exposes the symbol, decimal places, and symbol position for clients.

## Public services and usage

### Create a listing

`CreateListingService::create(User $seller, CreateListingData $data): Listing` is the application-facing write operation.

```php
use App\Models\User;
use Carbon\CarbonImmutable;
use Kinkoza\Catalog\Data\CreateListingData;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Catalog\Services\CreateListingService;

final readonly class CreateAssetListing
{
    public function __construct(
        private CreateListingService $listings,
    ) {}

    public function handle(User $seller): Listing
    {
        return $this->listings->create($seller, new CreateListingData(
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
    }
}
```

The service retries the listing transaction up to three times and invalidates the featured cache after commit. A request for `Published` from a user whose `is_verified_seller` flag is false is stored as `PendingReview`.

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

`ownedBy()` accepts either a `User` instance or a seller ULID string. Catalog does not provide a search repository. Storefront currently composes a `published()` query with a title `whereLike`, category, country, minor-unit price filters, deterministic sorting, and cursor pagination.

### Read featured listings

```php
use Kinkoza\Catalog\Support\CatalogCache;

$featured = app(CatalogCache::class)->featuredPublished(12);
```

`featured()` is an alias of `featuredPublished()`. Limits are clamped to `1..50`. Results must be published, inside their publication window, and have inventory above zero. They are ordered by newest `online_at`, then newest `sequence`.

The cache uses Laravel's flexible stale-while-revalidate behavior: 60 seconds fresh and 300 seconds stale. Keys include the value of `catalog:listings:version`; invalidation advances that version instead of scanning or deleting result keys.

Only `CreateListingService` invalidates automatically. Code that updates or deletes listings directly—including publication, price, image, or inventory changes—must call `CatalogCache::invalidate()` after the database transaction commits. The current Sales inventory update does not invalidate this cache, so cached featured results can remain stale within the configured flexible-cache window.

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

Cache invalidation must happen after a successful write transaction. Direct model writes do not emit an observer that invalidates the catalog.

## Events

Catalog currently dispatches no domain events and registers no listeners or observers. Listing creation and cache invalidation are synchronous. If events are introduced, dispatch them after commit so consumers cannot observe rolled-back listings.

## Factories and seed data

`ListingFactory` provides `draft`, `pendingReview`, `published`, `scheduled`, `offline`, `expired`, and `outOfStock` states.

`ListingSeeder` expects an existing `seller@example.com` user, then creates or updates 12 deterministic demo listings owned by that seller. It is intended for the application's coordinated database seeding flow, not standalone use against an empty users table.

## Extension points

- Add catalog write operations as focused services rather than writing guarded lifecycle fields from UI components.
- Add enum cases together with validation, translations/presentation labels, factories, seed data, filters, and tests that consume them.
- Add searchable fields with appropriate database indexes or introduce a dedicated search adapter; no search-engine contract exists yet.
- Invalidate `CatalogCache` after committed create, update, delete, publication, and inventory mutations that can change featured results.
- Keep checkout and inventory reservation orchestration in Sales; keep cart snapshots in Cart.
- If Catalog gains routes or views, register them explicitly in `CatalogServiceProvider`; the placeholders are not loaded today.
- Add domain events only for stable cross-module facts, and preserve the dependency direction by defining those events in Catalog.

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
