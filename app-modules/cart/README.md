# Cart module

`kinkoza/cart` owns the mutable basket domain for the marketplace. It creates one active cart per guest or buyer identity, stores line-item snapshots, enforces single-currency and inventory rules, merges a guest cart after sign-in, calculates totals, and protects mutations with cache locks, database transactions, row locks, and optimistic versions.

The module deliberately stops at the cart boundary. Catalog owns listing publication and inventory data, Sales owns checkout, inventory deduction, orders, and invoices, and Storefront owns HTTP/Livewire concerns such as session token creation and current-user authorization.

## Dependency direction

```text
App shared kernel                  Catalog
(User, ULID/sequence support)      (Listing)
             \                    /
                      Cart
                    /      \
             Storefront    Sales
             (HTTP/UI)     (checkout)
```

Cart declares `kinkoza/catalog` as a package dependency and uses the host application's `User`, `HasUlidAndSequence`, and `SequenceGenerator`. Catalog and the shared kernel do not depend on Cart. Sales and Storefront consume Cart; Cart does not call either consumer.

The Composer path package is discovered through `Kinkoza\Cart\Providers\CartServiceProvider`. Public use cases and their business logic live in Laravel Action classes, so the provider needs no workflow binding. `routes/cart-routes.php` is intentionally empty because Cart exposes PHP actions rather than an HTTP API.

## Directory map

```text
composer.json                    Package metadata and Catalog dependency
database/factories/              Cart and cart-item test factories
database/migrations/             carts and cart_items tables
routes/cart-routes.php           Empty HTTP adapter placeholder
src/Actions/                      Public Laravel Action entry points
src/Enums/                       Cart lifecycle status
src/Exceptions/                  Expected cart-domain failures
src/Models/                      Eloquent cart aggregate
src/Providers/                   Package discovery provider
tests/Feature/                   Pest action/invariant tests
```

The module currently has no controllers, jobs, events, commands, or views.

## Data model

### `Kinkoza\Cart\Models\Cart`

| Column | Storage and rule |
| --- | --- |
| `id` | ULID primary key |
| `sequence` | Unique unsigned bigint allocated from the shared `sequences` row named `carts` |
| `buyer_id` | Nullable ULID foreign key to `users`; set to `null` if the user is deleted |
| `guest_token` | Nullable, indexed ULID supplied by the presentation adapter |
| `active_key` | Nullable unique string: `buyer:{user-ulid}` or `guest:{guest-ulid}` |
| `currency` | Three-character cart currency; defaults from `config('app.currency', 'EUR')` until the first item is added |
| `status` | `active`, `converted`, or `abandoned`, cast to `CartStatus` |
| `subtotal_minor` | Unsigned integer subtotal in the currency's minor unit |
| `total_minor` | Unsigned integer total in the currency's minor unit |
| `version` | Unsigned optimistic-concurrency version, initially `1` |
| `converted_at` | Nullable conversion timestamp managed by Sales |
| timestamps | Laravel `created_at` and `updated_at` |

`active_key` is populated only while a cart is active. Its unique constraint is the database backstop that prevents two active carts for the same identity. Conversion or abandonment clears it, allowing that identity to receive a new active cart later.

Relationships:

- `buyer()` belongs to the host `App\Models\User`.
- `items()` has many `CartItem` records.

### `Kinkoza\Cart\Models\CartItem`

| Column | Storage and rule |
| --- | --- |
| `id` | ULID primary key |
| `sequence` | Unique unsigned bigint allocated from the shared `sequences` row named `cart_items` |
| `cart_id` | Required ULID foreign key; deleting the cart cascades to its items |
| `listing_id` | Nullable ULID foreign key; deleting a listing sets it to `null` so the commercial snapshot remains |
| `sku` | Snapshot identifier; currently the listing ULID |
| `title` | Listing-title snapshot |
| `currency` | Three-character currency snapshot |
| `unit_price_minor` | Unit-price snapshot in minor units |
| `line_total_minor` | `unit_price_minor * quantity` |
| `quantity` | Positive unsigned quantity |
| timestamps | Laravel `created_at` and `updated_at` |

The unique index on `cart_id` plus `listing_id` keeps one live line per listing in a cart. Re-adding that listing increments the existing quantity instead of creating another line.

Both models are fully guarded. The owning actions therefore perform explicit `forceFill`/`forceCreate` writes instead of accepting arbitrary request arrays.

### ULIDs and numeric sequences

`Cart` and `CartItem` use the host `App\Models\Concerns\HasUlidAndSequence` trait:

- Laravel's `HasUlids` generates the distributed primary key.
- `App\Support\Database\SequenceGenerator` allocates the numeric sequence under a database transaction and `lockForUpdate()`, with up to five transaction attempts.
- Foreign keys and aggregate relationships use ULIDs. The sequence is an independent, human-friendly ordering/reference value, not a relational key or a database auto-increment column.

Sequence allocation is unique and increasing per sequence name. Consumers must not assume sequences are gapless.

## Guest, buyer, and security identity

The Cart actions derive an active identity key rather than trusting a cart ID supplied by a browser:

| Identity | Persisted identity |
| --- | --- |
| Guest | `buyer_id = null`, normalized `guest_token`, `active_key = guest:{token}` |
| Buyer | `buyer_id = {user ULID}`, `guest_token = null`, `active_key = buyer:{user ULID}` |

`GetOrCreateCart::run()` and `AddListingToCart::run()` require a lowercase-normalizable, valid ULID guest token. An authenticated buyer still supplies the session's guest token so the action can restore or merge the cart created before sign-in. `Kinkoza\Storefront\Support\CartIdentity` creates and persists that token; session and authentication concerns remain outside Cart.

When a visitor signs in, `GetOrCreateCart::run($buyer, $guestToken)` behaves as follows:

1. If only a guest cart exists, the same cart is adopted: `buyer_id` and the buyer key are set, `guest_token` is cleared, and the version increments.
2. If only a buyer cart exists, it is returned.
3. If neither exists, a buyer cart is created.
4. If both exist and one is empty, the empty cart is abandoned and the non-empty cart survives.
5. If both contain compatible items, quantities are combined, missing lines move to the buyer cart, the guest cart is abandoned, and buyer totals/version are recalculated.
6. If currencies differ, a referenced listing is no longer published, or a combined quantity exceeds inventory, the merge is refused without modifying either cart; the existing buyer cart is returned.

The non-destructive refusal preserves the guest cart under its guest key rather than silently dropping items. When duplicate listing lines merge successfully, the guest line's title, currency, and unit-price snapshot becomes the combined buyer line's snapshot.

### Security boundary

The module guarantees that identity-based lookup derives `active_key` from the authenticated buyer or normalized guest token, that item mutations verify the item belongs to the supplied cart, and that fresh cart state—and listing state where inventory matters—is checked inside the write transaction. Models are fully guarded, self-purchase is rejected for authenticated buyers, and converted or abandoned carts cannot be mutated through the public actions.

The guest token is a bearer identifier and must remain in a protected server-side session; it should not be accepted from a route parameter or arbitrary request field. Storefront replaces invalid session values, applies CSRF protection, and re-queries carts with the current `buyer_id` or `guest_token` before calling a mutation action.

`UpdateCartItemQuantity` and `RemoveCartItem` deliberately do not accept an actor. They enforce aggregate integrity, not HTTP authorization. Every adapter must scope or authorize the `Cart` before calling them. A future API, CLI, or webhook adapter must repeat that boundary rather than trusting a submitted cart ULID.

## Public actions

Cart exposes five Laravel Actions. Calling `::run(...)` resolves the action through Laravel's container and forwards the arguments to its typed `handle(...)` method:

```text
GetOrCreateCart::run(?User $buyer, string $guestToken): Cart;

GetCartItemCount::run(?User $buyer, string $guestToken): int;

AddListingToCart::run(
    Listing $listing,
    int $quantity,
    ?User $buyer,
    string $guestToken,
    ?int $expectedVersion = null,
): Cart;

UpdateCartItemQuantity::run(
    Cart $cart,
    string $itemId,
    int $quantity,
    int $expectedVersion,
): Cart;

RemoveCartItem::run(
    Cart $cart,
    string $itemId,
    int $expectedVersion,
): Cart;
```

Each action owns its complete use-case implementation, including the required locks, transactions, identity restoration, snapshots, totals, and invariant checks. Reusable cart mechanics shared by multiple actions live in `Kinkoza\Cart\Actions\Concerns\InteractsWithCarts`; cross-module callers still enter the domain through a focused action rather than depending on those mechanics.

A version-aware guest flow looks like this:

```php
use Illuminate\Support\Str;
use Kinkoza\Cart\Actions\AddListingToCart;
use Kinkoza\Cart\Actions\GetOrCreateCart;
use Kinkoza\Cart\Actions\RemoveCartItem;
use Kinkoza\Cart\Actions\UpdateCartItemQuantity;
use Kinkoza\Catalog\Models\Listing;

$guestToken = strtolower((string) Str::ulid());
$listing = Listing::query()->published()->firstOrFail();

$cart = GetOrCreateCart::run(null, $guestToken);
$cart = AddListingToCart::run($listing, 2, null, $guestToken, $cart->version);

$item = $cart->items->sole();
$cart = UpdateCartItemQuantity::run($cart, $item->id, 3, $cart->version);
$cart = RemoveCartItem::run($cart, $item->id, $cart->version);
```

For sign-in restoration, pass both the authenticated user and the same session token:

```php
$cart = GetOrCreateCart::run($buyer, $guestToken);
```

`AddListingToCart::run()` permits a `null` expected version for callers that do not yet hold cart state. Once a cart version has been rendered or returned, every later write should send it back so stale writes fail explicitly; omitting it should not become the default for an established cart.

Expected domain failures are represented by:

- `CartNotActive` when a converted/abandoned cart is mutated.
- `CurrencyMismatch` when a second currency is introduced.
- `InsufficientInventory` when requested or merged quantity exceeds current stock.
- `ListingUnavailable` when the listing is deleted, unpublished, outside its publication window, or otherwise unavailable.
- `SelfPurchaseNotAllowed` when an authenticated buyer tries to add their own listing.
- `StaleCartVersion` when the expected version differs from the locked row.
- `InvalidArgumentException` for a non-positive quantity or invalid guest ULID.

Invalid cart/item identifiers can also produce Eloquent model-not-found failures. Lock acquisition and database failures are infrastructure errors and are not translated into cart-domain exceptions here.

## Mutation and concurrency invariants

### Locking

The Cart actions combine two kinds of lock:

- Cache locks serialize work by identity (`cart:identity:{sha256(active-key)}`) or by cart (`cart:{cart-ulid}`). Locks have a 10-second lease and wait for up to 5 seconds.
- Database transactions retry up to five times and use `lockForUpdate()` for the active cart, affected item, and relevant published listing rows.

Guest restoration acquires the buyer identity lock before the guest identity lock, then reads cart rows by sorted active key. Multi-listing merge queries listings and item rows in deterministic ID order. This consistent ordering reduces deadlock risk.

The database constraints on `carts.active_key` and `cart_items(cart_id, listing_id)` remain the final race-condition backstop. `createOrFirst()` handles a concurrent active-cart insert that loses the unique-key race.

These guarantees assume every application process shares the same lock backend and the database honors row-level locks. They serialize concurrent work for one identity or cart; independent carts remain available for parallel processing. Optimistic versions still reject a client that submits state rendered before another successful mutation.

### Test and local limitations

The feature suite uses the array cache and in-memory SQLite. Array locks coordinate only code running inside the same PHP process, and SQLite does not reproduce MySQL or PostgreSQL row-lock, deadlock, or lock-wait behavior. The tests therefore verify domain branches, transaction rollback, unique constraints, stale versions, and deterministic merge behavior, but they do not prove correctness under competing workers.

Local SQLite is appropriate for fast feedback, not for signing off production contention. Before release, the same scenarios must run as multi-process integration tests against the selected production database and Redis, including simultaneous first-cart creation, duplicate adds, guest adoption, merge conflicts, stale updates, and lock timeouts.

### Production topology

Production should use Redis as a shared atomic cache/lock store and MySQL or PostgreSQL with a transactional storage engine and real row-level locking. All application workers must share the same Redis namespace and primary database. Mutable cart reads and writes must stay on the primary; replicas are suitable only for lag-tolerant reporting or historical reads.

Lock lease and wait values should be tuned from measured transaction latency rather than increased blindly. Monitor acquisition time, timeout count, database retries, stale-version failures, unique-constraint races, and non-destructive merge refusals. A lock timeout is an infrastructure failure and should be reported with cart/identity context without logging the raw guest token.

### Optimistic versions

- New carts start at version `1`.
- Each successful add, quantity update, removal, or merged-total recalculation increments the cart version.
- Adoption and abandonment also increment the affected cart version.
- Sales increments the version once more when checkout converts the cart.
- A mismatched `expectedVersion` throws `StaleCartVersion` before item data changes.

### Currency, prices, totals, and inventory

- The first item changes an empty cart to the listing currency.
- Every later item must use that same currency.
- Monetary values are integers in minor units; floating-point prices are not used.
- A new cart line snapshots the listing ULID as `sku`, plus title, currency, and unit price. Later quantity changes use that stored unit price.
- Re-adding an existing line keeps its existing price snapshot and increments quantity.
- `subtotal_minor` is recomputed as the sum of line snapshots, and `total_minor` currently equals subtotal. Tax, shipping, and discounts are not implemented in this module.
- Add and update lock the published listing and validate quantity against current `inventory_quantity`, but a cart does not reserve or decrement stock.
- Sales revalidates locked listings and atomically decrements inventory during checkout.
- If a listing is deleted, `listing_id` becomes `null`; the snapshot remains displayable and removable, but it cannot be updated or checked out.

Self-purchase is rejected when an authenticated buyer adds a listing. Sales repeats that check at checkout so a cart assembled under an earlier identity cannot bypass the rule.

## Lifecycle and checkout handoff

```text
new active cart (version 1)
        |
        +-- add/update/remove --> active cart (version + 1)
        |
        +-- successful sign-in merge --> surviving active buyer cart
        |                                source cart becomes abandoned
        |
        +-- Sales checkout --> converted, active_key cleared,
                               converted_at set, version + 1
```

`CartStatus` has `Active`, `Converted`, and `Abandoned` cases. Cart itself creates and mutates active carts and privately marks merge sources abandoned. `Kinkoza\Sales\Actions\CheckoutCart::run(...)` is the checkout handoff; the action verifies buyer ownership and cart version, creates the order/invoice graph, decrements stock, then clears `active_key` and sets `converted_at` in the same transaction.

Converted and abandoned carts remain as historical records but cannot be mutated through the public Cart actions.

## Deliberate exclusions

- A cart checks current availability but does not reserve or decrement inventory. Sales is the only allocation boundary.
- Tax, VAT, shipping, discounts, promotions, deposits, and payment fees are not represented; totals equal the subtotal.
- Cart does not own HTTP routes, sessions, authentication, translated UI errors, checkout, orders, invoices, payments, or fulfillment.
- There is no scheduled expiry or deletion of abandoned guest carts and no user-facing saved-cart workflow.
- Cart emits no lifecycle events and has no transactional outbox. No downstream integration should infer a committed fact from an in-progress mutation.
- Cross-currency carts and automatic currency conversion are intentionally rejected rather than priced with an implicit exchange rate.

## Evolution at 100x traffic

The current design scales horizontally across different carts because lock keys are identity- or cart-specific. A single hot cart remains intentionally serialized: allowing concurrent writers to the same aggregate would weaken totals and version guarantees.

At 100 times the proof-of-concept traffic, evolve the module in this order:

1. Run Redis and the production database as managed, observable services; load-test the real topology with many PHP workers and failure injection before changing the locking model.
2. Measure active-cart lookup, cart-item lookup, lock wait, retry, and stale-write rates. Use query plans to validate the existing unique/index paths before adding indexes based on observed workload.
3. Add an idempotency key to externally retried cart mutation commands so network retries cannot double-apply an add after the caller loses the response.
4. Archive or partition old converted and abandoned carts according to retention requirements, keeping the active-cart working set and its indexes small. Historical/reporting queries can then move away from the primary write path.
5. Add an idempotent abandoned-guest-cart cleanup action and schedule it in bounded batches. It must use the same cart lock before changing or deleting an aggregate.
6. Publish metrics and alerts for contention, failed locks, database retries, merge refusal, and mutation latency. Multi-region writes would additionally require a single owner region per cart or a redesigned consistency model; shared Redis alone is not sufficient.

## Prioritized next steps

1. Add the Redis plus MySQL/PostgreSQL multi-process contention suite; this is the production-readiness gate for the existing design.
2. Add mutation idempotency and structured lock/retry metrics so safe retry behavior is observable.
3. Define abandoned-cart retention and implement the locked cleanup action.
4. Introduce an explicit pricing model only when tax, discounts, or shipping enter scope, and copy the same accepted values into Sales snapshots.
5. Evaluate inventory reservation only after defining expiry, release, checkout, and failure semantics across Cart, Catalog, and Sales.

## Extension rules

- Add a focused action under `src/Actions` for each new public use case. Give it a typed `handle(...)` method and test callers through `ActionName::run(...)`.
- Keep each use case's business logic in its owning action. Extract only genuinely shared cart mechanics to a focused concern under `src/Actions/Concerns`.
- Keep HTTP, session, authentication, and translated presentation errors in Storefront or another adapter rather than adding them to domain actions.
- Laravel Actions can be faked with helpers such as `AddListingToCart::shouldRun()` when a presentation test needs to isolate orchestration from domain work.
- Add new domain failures under `src/Exceptions` and map safe user-facing text in Storefront's `DomainErrorMessage`.
- Extending totals with discounts, tax, or shipping requires explicit schema and action changes plus matching Sales checkout semantics. `recalculate()` is private and currently supports subtotal-only totals; it is not an overridable pricing hook.
- Inventory reservation is a cross-module workflow. The current actions perform availability checks only; checkout remains the deduction boundary.
- Add events only for stable, committed lifecycle facts. Use an outbox if delivery guarantees are required beyond the local transaction.
- Add lifecycle values only with matching migration/default, action, factory, checkout, and test updates.

## Testing

Run the Cart module's feature tests from the repository root:

```bash
herd php artisan test app-modules/cart/tests/Feature
```

Run the focused action and domain-invariant test file:

```bash
herd php artisan test app-modules/cart/tests/Feature/CartActionsTest.php
```

Run the complete formatting, static-analysis, host, and module suite:

```bash
herd composer test
```

The Cart suite covers repeated adds, snapshot-based totals, inventory validation, self-purchase rejection, stale versions, identity isolation, guest adoption and merge, non-destructive currency/stock merge refusal, quantity updates, removal, and unpublished listings. Checkout conversion and true inventory deduction are tested in the Sales module; Livewire ownership scoping is tested in Storefront.
