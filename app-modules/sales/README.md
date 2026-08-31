# Sales module

`kinkoza/sales` owns the transition from an authenticated buyer's active cart to immutable commercial records. A successful checkout creates one confirmed order, its order lines, one issued invoice, and matching invoice lines while allocating inventory and converting the cart in the same database transaction.

Sales does not own product discovery, cart editing, payment capture, tax, shipping, fulfillment, refunds, or HTTP screens. Catalog owns listing publication and inventory state, Cart owns the reviewed basket, and Storefront invokes the checkout action from Livewire.

## Dependency direction

```text
App shared kernel        Catalog        Cart
(User, sequences)       (Listing)     (Cart aggregate)
          \                 |             /
                         Sales
                           |
                       Storefront
```

Sales declares Cart, Catalog, and Laravel Actions 2.12 as Composer dependencies and also uses the host `User` model and `SequenceGenerator`. Cart and Catalog never depend on Sales.

Laravel discovers `Kinkoza\Sales\Providers\SalesServiceProvider`, which connects `OrderPlaced` to the queued `SendOrderConfirmation` action. Checkout needs no container binding: Laravel Actions resolves `CheckoutCart`, and the action owns the complete transactional workflow.

`routes/sales-routes.php` is intentionally empty. The package exposes application actions rather than an HTTP API.

## Directory map

```text
database/factories/              Order and invoice graph factories
database/migrations/             Orders, order items, invoices, invoice items
routes/sales-routes.php          Empty HTTP adapter placeholder
src/Actions/                     Checkout and queued listener actions
src/Enums/                       Order and invoice lifecycle values
src/Events/                      Committed sales facts
src/Models/                      Commercial record graph
src/Notifications/               Localized order-confirmation email
src/Providers/                   Event registration
tests/Feature/                   Checkout and notification tests
```

## Commercial record graph

```text
User ──< Order >── Cart
          │
          ├──< OrderItem >── Listing
          │        │
          │        └── Seller (User)
          │
          └── Invoice ──< InvoiceItem >── OrderItem
```

All monetary values are unsigned integers in the currency's minor unit. Totals currently equal subtotal because tax, delivery, discounts, and payment fees are outside the MVP.

### `Order`

| Field | Meaning |
| --- | --- |
| `id` | ULID primary key and canonical identifier. |
| `sequence` | Unique numeric sequence allocated under the `orders` counter. |
| `number` | Unique human reference formatted as `ORD-00000001`. |
| `buyer_id` | Required buyer ULID; deletion is restricted. |
| `cart_id` | Unique source-cart ULID; deletion is restricted and one cart can produce only one order. |
| `idempotency_key` | Caller key, unique together with `buyer_id`. |
| `status` | `pending`, `confirmed`, or `cancelled`; checkout creates `confirmed`. |
| `currency`, `subtotal_minor`, `total_minor` | Accepted order currency and totals. |
| `placed_at` | Immutable placement timestamp. |

### `OrderItem`

An order line belongs to an order and seller. Its nullable `listing_id` is set to `null` if the listing is later deleted, while `title`, `currency`, `unit_price_minor`, `quantity`, and `line_total_minor` remain as the accepted cart snapshot. Each line also has a ULID and unique numeric sequence.

### `Invoice`

Each order has one invoice. It has its own ULID, numeric sequence, unique `INV-00000001` reference, `issued`, `paid`, or `void` status, currency, totals, and immutable issue timestamp. Deleting an order cascades to its invoice.

### `InvoiceItem`

Every order item has one invoice item. The invoice line copies the same title, currency, unit price, quantity, and line total and references its source order item uniquely. `listing_id` is retained as an informational nullable ULID without a foreign-key constraint, so invoice history does not depend on a live catalog row.

All four models are fully guarded and use the host `HasUlidAndSequence` concern. Application code should run `CheckoutCart` rather than mass assigning accounting records directly.

## ULIDs and numeric sequences

ULIDs are the relational keys. The numeric sequences provide readable ordering and document numbers; they are never used as foreign keys.

Before entering the inventory transaction, checkout reserves:

- one `orders` sequence;
- one `invoices` sequence;
- a contiguous `order_items` range sized to the reviewed cart; and
- a matching `invoice_items` range.

Order, invoice, and every dependent line ULID can then be assigned before insertion. Reserving ranges reduces sequence-row lock traffic while the inventory transaction is open. If the cart changes or checkout later fails, reserved values may be skipped. Gaps are expected and issued sequence values are never reused.

## Public checkout action

`Kinkoza\Sales\Actions\CheckoutCart` uses Laravel Actions' `AsAction` concern. The action owns sequence reservation, transaction handling, concurrency controls, persistence, and invariant checks behind this typed application boundary:

```php
use App\Models\User;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Actions\CheckoutCart;
use Kinkoza\Sales\Models\Order;

public function handle(
    Cart $cart,
    User $buyer,
    string $idempotencyKey,
    int $expectedVersion,
): Order;
```

Call the action through its static Laravel Actions proxy:

```php
$order = CheckoutCart::run(
    $cart,
    $buyer,
    $idempotencyKey,
    $reviewedVersion,
);
```

The idempotency key is trimmed and must contain between 1 and 64 characters. It should be generated when the buyer opens the review step and remain stable for retries of that same submission. The Storefront currently uses a locked ULID string.

The expected version must be the cart version shown to the buyer. If the cart changes before placement, checkout throws `StaleCartVersion` instead of accepting a different basket.

## Checkout transaction

`CheckoutCart` performs a preflight check, reserves sequences, and then runs the write transaction with up to five database attempts:

1. Lock the cart row and re-check idempotency, buyer ownership, active status, active key, and reviewed version.
2. Read cart-item references in deterministic ULID order.
3. Lock every referenced, currently published listing in deterministic order.
4. Reject missing/unpublished listings, a buyer's own listing, currency disagreement, invalid quantities, or insufficient inventory.
5. Lock the cart items and build line data from the cart's accepted title and price snapshots.
6. Create the confirmed order and all order items.
7. Create the issued invoice and all invoice items.
8. Decrement each listing with a conditional `inventory_quantity >= requested` update and advance its catalog `version`.
9. Mark the cart `converted`, clear its unique `active_key`, set `converted_at`, and increment its version.
10. Dispatch `OrderPlaced`; Laravel delays it until the transaction commits.

Any exception rolls back the order, invoice, line, inventory, and cart-state changes together. Sequence reservations intentionally remain consumed.

## Concurrency and idempotency invariants

- The cart row, its items, and all listings are reloaded inside the transaction; caller-provided Eloquent state is not trusted as current.
- Rows are locked in deterministic order to reduce deadlock risk.
- A conditional inventory decrement is the final oversell guard even after the listing lock.
- `orders.cart_id` is unique, so a cart cannot be converted into two orders.
- `(buyer_id, idempotency_key)` is unique, so one buyer cannot create two orders with the same request key.
- Retrying the same key and cart returns the existing order graph.
- Retrying an already converted cart with a different key still returns its existing order.
- Reusing a buyer's key for another cart is rejected; different buyers may use the same key.
- A unique-constraint race is resolved by loading the winning order and verifying that it belongs to the same buyer and cart.
- The self-purchase rule is repeated at checkout, including for carts first assembled as a guest.

Use MySQL or PostgreSQL for production contention and a transactional storage engine. The default SQLite feature suite validates rollback, stale-state, constraint, and domain behavior, but SQLite does not exercise production row-level lock behavior between independent workers.

## Snapshot rules

Cart line title, currency, and unit price are the commercial values accepted by checkout. Sales deliberately does not replace them with the listing's current title or price. It does re-check the listing's current publication state, seller, currency, and inventory.

Order and invoice lines copy the same values and calculate `line_total_minor` from the stored unit price and quantity. A later listing edit or deletion cannot rewrite those records.

## Events, queues, and localization

`OrderPlaced` implements `ShouldDispatchAfterCommit`. The provider registers `Kinkoza\Sales\Actions\SendOrderConfirmation` as its listener. Laravel Actions' listener decorator falls back to the action's typed `handle(OrderPlaced $event): void` method.

`SendOrderConfirmation` uses `AsAction` and implements `ShouldQueueAfterCommit`. It retries three times with 60- and 300-second backoff and discards the job when serialized models no longer exist.

The action reloads the buyer and invoice, then sends `OrderConfirmation` through Laravel Notifications. The email includes order and invoice references. Because the host `User` implements `HasLocalePreference`, Laravel renders the queued notification using the buyer's stored locale.

Run a worker when using an asynchronous queue:

```bash
herd php artisan queue:work --tries=3
```

After-commit dispatch prevents a listener from observing a rolled-back order. It is not a transactional outbox: a production system requiring guaranteed external delivery should persist an outbox record in the checkout transaction and dispatch it idempotently.

## Extension points

- Keep checkout orchestration, transaction handling, and invariant enforcement together in `CheckoutCart`. Callers and tests should enter the workflow through `CheckoutCart::run()`.
- Model payment authorization/capture as a separate state machine instead of marking invoices paid inside the current checkout transaction.
- Add tax, shipping, discounts, or fees with explicit line/total fields and preserve the same values across order and invoice snapshots.
- Keep fulfillment, cancellation, refund, and invoice-transition operations in focused actions with their own authorization and idempotency rules.
- Add stable post-commit events for new cross-module facts. Use an outbox for delivery guarantees beyond the local queue transaction boundary.
- If inventory changes should update featured catalog results immediately, coordinate a post-commit `CatalogCache` invalidation; checkout currently advances listing versions but does not invalidate that cache.
- Add HTTP or API adapters outside Sales and validate ownership before passing a cart to `CheckoutCart`.

## Testing

Run the focused Sales suite from the repository root:

```bash
herd php artisan test app-modules/sales/tests/Feature
```

Run only checkout behavior or notification behavior:

```bash
herd php artisan test app-modules/sales/tests/Feature/CheckoutCartActionTest.php
herd php artisan test app-modules/sales/tests/Feature/OrderConfirmationNotificationTest.php
```

Run all module tests or the complete quality gate:

```bash
herd php artisan test --testsuite=Modules
herd composer test
```

The suite covers immutable graph creation, inventory and listing-version updates, ownership, self-purchase rejection, idempotency by key and cart, rollback, publication revalidation, stale cart versions, accepted price snapshots, after-commit listener registration, notification references, and buyer-locale delivery.
