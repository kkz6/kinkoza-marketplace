# Storefront module

`kinkoza/storefront` is the HTTP and presentation layer for the marketplace. It composes the Catalog, Cart, and Sales modules into server-driven Livewire pages while keeping business rules in their owning domain actions.

The module owns routes, Livewire components, form validation, page layouts, visitor cart identity, localized error presentation, and request-level abuse protection. It does not own ecommerce tables or duplicate checkout, inventory, publication, or cart invariants.

## Dependencies

```text
storefront
├── catalog   listing discovery and creation
├── cart      guest and buyer cart operations
└── sales     checkout and order confirmation
```

The dependency direction stops at this presentation package. Domain modules never depend on Storefront.

The package is discovered through `Kinkoza\Storefront\Providers\StorefrontServiceProvider`. The modular host loads the package routes and `storefront::` views.

## Directory map

```text
resources/views/
├── layouts/store.blade.php
└── livewire/                  Page templates
routes/storefront-routes.php  Public and authenticated web routes
src/Actions/                  Presentation actions and route adapters
src/Http/Livewire/            Page components
src/Http/Livewire/Forms/      Listing form validation and DTO mapping
src/Providers/                Livewire namespace and rate limiters
src/Support/                  Cart identity and safe domain errors
tests/Feature/                End-to-end presentation boundary tests
```

## Routes

| Method | URI | Name | Access and limit |
| --- | --- | --- | --- |
| `GET` | `/` | `home` | Public; 120 requests/minute per account or IP |
| `POST` | `/locale/{locale}` | `locale.update` | Public; supported locales only; 30 requests/minute |
| `GET` | `/listings/{slug}` | `storefront.listings.show` | Public when published; sellers may view their own non-public listing; 30 requests/minute |
| `GET` | `/cart` | `storefront.cart.show` | Public; 30 requests/minute |
| `GET` | `/sell` | `storefront.listings.create` | Authenticated and email verified; 30 requests/minute |
| `GET` | `/checkout` | `storefront.checkout.show` | Authenticated and email verified; 10 requests/minute |
| `GET` | `/orders/{order}` | `storefront.orders.show` | Authenticated and email verified; buyer-owned orders only |

Rate limits are registered in `StorefrontServiceProvider`. Authentication, email verification, and throttling are registered as persistent Livewire middleware so their protection remains active on later component requests.

## Required marketplace pages

The three required marketplace pages are first-class Livewire routes:

1. `/` is the public search and landing page. It exposes only listings inside their publication window.
2. `/listings/{slug}` is the public detail page. A seller may also open their own draft or pending listing, while another account receives a `404` from the database-scoped query.
3. `/sell` is the create-listing page. The route requires authentication and verified email, and the `save` method repeats policy authorization before accepting hydrated form state.

Cart, checkout, and order-confirmation pages extend the demonstration but are not required to understand the listing flow.

## Action orchestration

Storefront calls domain use cases through Laravel Actions. A synchronous call such as `AddListingToCart::run(...)` resolves the action through Laravel's container and forwards the arguments to its typed `handle(...)` method. The presentation layer depends on these use-case entry points, while the Cart and Sales actions directly own the locks, multi-record transactions, and domain invariants their workflows require.

`Kinkoza\Storefront\Actions\UpdateLocale` is mounted directly as the `POST /locale/{locale}` route handler. Laravel invokes its `handle(Request, string): RedirectResponse` method through the action's invokable-controller adapter. `RevealSellerContact` is a synchronous action invoked with `::run(...)` so authorization, throttling, and audit logging remain one reusable boundary.

## Page components

### `ListingsIndex`

Builds the public catalog query using published records only. Search, category, country, minimum price, maximum price, and sort state are reflected in the URL. Price input is normalized to integer minor units, and results use cursor pagination with deterministic secondary ordering.

All user-controlled query structure comes from a whitelist. Category and country strings must resolve to backed enums; sort values select one of three predefined order clauses; invalid price strings do not reach the SQL query. The seller relation is eager-loaded with a narrow column selection to avoid an N+1 on the result cards.

Livewire's `#[Url(history: true)]` state makes filters shareable and adds filter changes to browser history. Cursor pagination deliberately trades arbitrary page-number jumps for stable next/previous navigation as the listings table grows. The filter hook currently calls `resetPage()` without naming the `cursor` paginator, so a filter change from a later cursor page can retain stale cursor state. Correcting that call and adding a regression test are required before this behavior is considered complete. The current feature suite proves title and composed category/country/price filtering, but it does not yet assert sort order, cursor traversal, query-string hydration, or back-button history behavior.

### `ListingShow`

Loads a listing by slug and re-applies publication or seller-ownership scope on every computed lookup. It calls `Kinkoza\Cart\Actions\AddListingToCart::run(...)` and prevents self-purchase at the UI boundary before the action repeats the check inside its transaction.

Seller contact information is not part of the normal listing query. A signed-in buyer must explicitly reveal it through `Kinkoza\Storefront\Actions\RevealSellerContact::run(...)`; the action repeats policy authorization, applies a five-attempts-per-minute limit, and emits a notice-level audit log. The locked reveal flag cannot be changed by the browser, and authorization runs again before contact data is returned.

### `CreateListing`

Uses `ListingForm` to validate user input and translate it into `Kinkoza\Catalog\Data\CreateListingData`. It aliases the identically named Catalog action and calls `Kinkoza\Catalog\Actions\CreateListing::run(...)`; that action owns sequence and slug allocation, persistence, cache invalidation, and seller-verification publication rules. A requested publication from an unverified seller can therefore become `pending-review` rather than bypassing the Catalog boundary.

`ListingForm` validates the title, description, enum-backed category/country/currency, decimal price, city, publication window, inventory, and optional HTTP(S) image URL. The seller ID, slug, workflow status, sequence, and version never come from public form input.

The POC assumes seller business data was established before this page. It does not collect or edit company name, VAT/registration number, or phone, and those account columns are currently nullable. Seeded sellers contain the data needed to demonstrate the marketplace, but a newly registered account can reach listing creation without completing a business profile. A production onboarding flow must validate and lock that profile, retain verification evidence, and prevent publication until the minimum seller identity is complete.

### `CartShow`

Resolves a guest or buyer cart with `GetOrCreateCart::run(...)`. The cart identifier is locked, every computed query is scoped back to the current identity, and `UpdateCartItemQuantity::run(...)` and `RemoveCartItem::run(...)` receive the cart version supplied by the rendered state.

### `CartItemCount`

Renders the active cart's total quantity in both desktop and mobile navigation. It initializes through `GetCartItemCount::run(...)` and listens for `cart-updated`, so additions, quantity changes, and removals update the badge without a page reload. The public count is locked against browser mutation, and its accessible label is localized.

### `CheckoutShow`

Locks the cart identifier, cart version, and a generated ULID idempotency key at mount time. It resolves the cart with `GetOrCreateCart::run(...)` and places the order with `Kinkoza\Sales\Actions\CheckoutCart::run(...)`. The Checkout action performs the atomic order, invoice, inventory, and cart-conversion work directly. Successful checkout redirects to the owned order confirmation; expected domain failures become stable user messages, while unexpected exceptions are reported and hidden behind a generic message.

### `OrderConfirmation`

Scopes the route-bound order to `Auth::id()` both at mount and during later Livewire requests. It eager-loads the order lines and invoice graph needed by the confirmation page.

## Cart identity

`Kinkoza\Storefront\Support\CartIdentity` is the adapter between Laravel authentication/session state and the Cart module:

- `buyer()` returns the authenticated host `User`, or `null` for a visitor.
- `guestToken()` returns a normalized lowercase ULID stored under `storefront.guest_cart_token` in the session.
- Invalid or missing session tokens are replaced instead of being trusted.

The Cart module remains responsible for creating, adopting, merging, and authorizing carts for those identities.

## Localization

The `UpdateLocale` route action accepts only keys from `config/locales.php`, saves the choice in the session, applies it immediately, and persists it to an authenticated user's `locale` column. The host application's `SetLocale` middleware restores the correct locale on subsequent page and Livewire requests.

User-facing domain failures pass through `DomainErrorMessage`, which maps known cart exceptions to translated messages and prevents database or infrastructure details from reaching the browser.

English is the source and fallback language. French covers the marketplace, cart, checkout, order confirmation, validation messages, and transactional notification copy. Price rendering uses Laravel's locale-aware number formatter. The starter-kit authentication and account-settings screens are not fully translated in this MVP, so localization is complete for the marketplace journey rather than for every host-application screen.

## Mobile behavior

The storefront is mobile-first: page grids stack before their `sm`, `md`, or `lg` breakpoints; forms expand progressively; the detail purchase card becomes sticky only on large screens; and Alpine controls a dedicated mobile navigation menu. Images declare dimensions and listing-card images lazy-load to reduce layout shift and initial work.

The public search and detail pages have been checked manually at a 390-pixel viewport without horizontal overflow. There is no automated browser suite, and the authenticated create page has not received equivalent device coverage. Before production, test all three required pages on small iOS and Android viewports, including the software keyboard, long translated labels, cursor navigation, validation focus, dark mode, and reduced motion.

## Validation, authorization, and throttling

- `/sell`, checkout, and order routes require authentication and verified email at the route boundary.
- Every state-changing Livewire method treats hydrated input as untrusted and re-authorizes or re-scopes the model it acts on; mount-time authorization is not treated as sufficient.
- Listing creation and quantity changes use explicit validation. Domain actions repeat publication, cart, inventory, ownership, and concurrency invariants at the application boundary.
- Public identifiers and checkout concurrency tokens use `#[Locked]`; computed records are loaded again using current identity constraints.
- Named route limiters distinguish search, ordinary storefront actions, and checkout. Throttling is persistent across subsequent Livewire requests.
- Search columns and directions are selected from code, not interpolated from URL state.

## Security boundaries

- Livewire identifiers and checkout concurrency tokens use `#[Locked]`.
- Computed models are queried again with current-user or guest-token ownership constraints.
- Listing and contact actions use Laravel policies.
- Seller contact fields are fetched only after successful authorization.
- Order confirmation queries always include the authenticated buyer ID.
- Expected domain failures are translated; unexpected failures are reported server-side and replaced with generic copy.
- Public discovery, cart, listing, locale, and checkout entry points use named rate limiters.
- Laravel's CSRF middleware protects locale and Livewire state-changing requests.

### External image risk

The POC accepts an HTTP(S) image URL and renders it directly. This avoids implementing the image-upload bonus, but it means a seller-controlled host receives buyer IP, user-agent, and referrer information and can replace content after moderation. URL validation does not prove MIME type, dimensions, file size, availability, or safety.

Production should ingest uploads to managed object storage, verify MIME and dimensions, strip metadata, generate responsive variants asynchronously, and serve them through a controlled CDN with a restrictive content-security and referrer policy. Existing remote URLs should be proxied or migrated rather than embedded indefinitely.

### Contact reveal risk

Contact details are absent from the normal listing payload and require an authenticated, email-verified, non-seller buyer. The reveal action authorizes the listing, applies an account quota, and records buyer, listing, and IP in the audit log. The locked reveal flag prevents a browser from setting authorized state directly.

This reduces bulk harvesting but cannot prevent an authorized buyer from copying revealed data or distributing requests across accounts. Production controls should add verified-buyer or KYB requirements where appropriate, longer-window account and organization quotas, anomaly alerts, IP/device signals, and a relay or in-platform messaging option that avoids releasing raw contact data at all.

## Using the presentation layer

Link to named routes rather than hard-coding paths:

```php
use Kinkoza\Catalog\Models\Listing;

route('home');
route('storefront.cart.show');
route('storefront.listings.show', ['slug' => $listing->slug]);
```

Render a component from another Blade view through the registered namespace:

```blade
<livewire:storefront::listings-index />
```

Application code should call the owning Catalog, Cart, or Sales action through `::run(...)` instead of invoking a Livewire component or bypassing the action boundary.

## Production and 100x traffic plan

The SQL search is appropriate for the POC, but `%title%` matching and application-database traffic should not be the only discovery path at sustained crawler volume. The next production stage is:

1. Move sessions, named rate-limit counters, locks, and cache state to shared infrastructure so limits and identity remain consistent across web nodes.
2. Put a CDN and bot-aware edge policy in front of public pages and media. Separate crawler budgets from authenticated buyer actions, and keep reveal quotas server-side.
3. Introduce database full-text search or a dedicated search index with an outbox-driven listing feed. Preserve enum whitelists and publication-window filtering in the indexed document contract.
4. Measure search latency, query counts, result cardinality, Livewire request rate, `429` rates, reveal attempts, image failures, and queue lag. Add slow-query and abuse alerts before increasing limits.
5. Review composite indexes against real filter and sort distributions. Cursor ordering must remain deterministic, and any search index must exclude drafts, scheduled listings, and expired listings before results are returned.
6. Normalize or explicitly constrain cross-currency price filtering and sorting; the POC currently compares integer minor-unit values without exchange-rate conversion.
7. Replace external seller image URLs with the controlled media pipeline described above.
8. Add canonical URLs, crawler directives, a sitemap, and structured listing data without exposing seller contact fields in HTML or metadata.

At 100x traffic, discovery can be eventually consistent, but authorization, publication visibility, inventory, cart ownership, and contact reveal must continue to be checked against authoritative server-side state when the user acts.

## Next steps

- Add the required seller business-profile and KYB onboarding boundary before allowing ordinary registered accounts to request publication.
- Add tests for sort ordering, cursor pagination, URL hydration/history, guest access to `/sell`, and verified-seller publication through the Livewire page.
- Add a small real-browser mobile suite for the search, detail, create, and contact-reveal journeys.
- Complete French coverage for authentication and account settings if the product claims application-wide bilingual support.
- Replace raw contact release with an optional buyer-seller messaging or relay flow.
- Keep new page orchestration under `src/Http/Livewire`, call owning domain actions through `::run(...)`, and test presentation behavior in `tests/Feature`.

## Testing

Run only this module's feature suite from the repository root:

```bash
herd php artisan test app-modules/storefront/tests/Feature
```

Run the complete formatting, static-analysis, host, and module suite:

```bash
herd composer test
```

The Storefront tests cover discovery filters, public visibility, seller listing creation, cart interaction, checkout ownership, contact reveal authorization, locked Livewire state, localization persistence, safe error presentation, and rate limiting.

Known presentation gaps are sort-order assertions, cursor traversal, URL/history behavior, the `/sell` authentication route, verified-seller publication through the UI, French rendering of the detail/create pages, and real-browser mobile behavior. These are deliberately documented rather than implied by lower-level domain tests.
