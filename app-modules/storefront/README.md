# Storefront module

`kinkoza/storefront` is the HTTP and presentation layer for the marketplace. It composes the Catalog, Cart, and Sales modules into server-driven Livewire pages while keeping business rules in their owning domain services.

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
src/Http/Controllers/         Locale update endpoint
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

## Page components

### `ListingsIndex`

Builds the public catalog query using published records only. Search, category, country, minimum price, maximum price, and sort state are reflected in the URL. Price input is normalized to integer minor units, and results use cursor pagination with deterministic secondary ordering.

### `ListingShow`

Loads a listing by slug and re-applies publication or seller-ownership scope on every computed lookup. It delegates cart mutation to `CartServiceInterface` and prevents self-purchase at the UI boundary before the domain service performs its own check.

Seller contact information is not part of the normal listing query. A signed-in, authorized buyer must explicitly reveal it; the action is limited to five attempts per minute and emits a notice-level audit log. The locked reveal flag cannot be changed by the browser, and authorization runs again before contact data is returned.

### `CreateListing`

Uses `ListingForm` to validate user input and translate it into `Kinkoza\Catalog\Data\CreateListingData`. `CreateListingService` owns slug allocation and seller-verification publication rules. A requested publication from an unverified seller can therefore become `pending_review` rather than bypassing the catalog policy.

### `CartShow`

Resolves a guest or buyer cart through `CartServiceInterface`. The cart identifier is locked, every computed query is scoped back to the current identity, and quantity/removal operations include the cart version supplied by the rendered state.

### `CheckoutShow`

Locks the cart identifier, cart version, and a generated ULID idempotency key at mount time. `CheckoutServiceInterface` performs the transactional checkout. Successful checkout redirects to the owned order confirmation; expected domain failures become stable user messages, while unexpected exceptions are reported and hidden behind a generic message.

### `OrderConfirmation`

Scopes the route-bound order to `Auth::id()` both at mount and during later Livewire requests. It eager-loads the order lines and invoice graph needed by the confirmation page.

## Cart identity

`Kinkoza\Storefront\Support\CartIdentity` is the adapter between Laravel authentication/session state and the Cart module:

- `buyer()` returns the authenticated host `User`, or `null` for a visitor.
- `guestToken()` returns a normalized lowercase ULID stored under `storefront.guest_cart_token` in the session.
- Invalid or missing session tokens are replaced instead of being trusted.

The Cart module remains responsible for creating, adopting, merging, and authorizing carts for those identities.

## Localization

`UpdateLocaleController` accepts only keys from `config/locales.php`, saves the choice in the session, applies it immediately, and persists it to an authenticated user's `locale` column. The host application's `SetLocale` middleware restores the correct locale on subsequent page and Livewire requests.

User-facing domain failures pass through `DomainErrorMessage`, which maps known cart exceptions to translated messages and prevents database or infrastructure details from reaching the browser.

## Security boundaries

- Livewire identifiers and checkout concurrency tokens use `#[Locked]`.
- Computed models are queried again with current-user or guest-token ownership constraints.
- Listing and contact actions use Laravel policies.
- Seller contact fields are fetched only after successful authorization.
- Order confirmation queries always include the authenticated buyer ID.
- Expected domain failures are translated; unexpected failures are reported server-side and replaced with generic copy.
- Public discovery, cart, listing, locale, and checkout entry points use named rate limiters.
- Laravel's CSRF middleware protects locale and Livewire state-changing requests.

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

Domain code should call Catalog, Cart, or Sales contracts directly instead of invoking a Livewire component.

## Extending the module

- Add page-level orchestration under `src/Http/Livewire` and keep reusable business rules in the domain that owns them.
- Add a matching Blade template under `resources/views/livewire` and a named route under `routes/storefront-routes.php`.
- Apply explicit authentication, verification, authorization, and a named rate limiter before exposing new state-changing actions.
- Treat every Livewire public property as client input. Lock identifiers and re-scope models during each request.
- Add user-facing strings to the locale files and map new domain exceptions in `DomainErrorMessage` without exposing raw exception text.
- Add presentation behavior to `tests/Feature`; domain invariants belong in the owning module's test directory.

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
