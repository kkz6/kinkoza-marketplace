<?php

namespace Kinkoza\Storefront\Http\Livewire;

use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Kinkoza\Cart\Contracts\Services\CartServiceInterface;
use Kinkoza\Cart\Exceptions\StaleCartVersion;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Support\CartIdentity;
use Kinkoza\Storefront\Support\DomainErrorMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/** @property-read Listing $listing */
#[Layout('storefront::layouts.store')]
#[Title('Asset details')]
class ListingShow extends Component
{
    #[Locked]
    public string $listingId;

    public int $quantity = 1;

    #[Locked]
    public bool $contactRevealed = false;

    public function mount(string $slug): void
    {
        $userId = Auth::id();

        $listing = Listing::query()
            ->where('slug', $slug)
            ->where(function (Builder $query) use ($userId): void {
                $query->published();

                if ($userId) {
                    $query->orWhere('seller_id', $userId);
                }
            })
            ->firstOrFail();

        $this->authorize('view', $listing);
        $this->listingId = (string) $listing->getKey();
    }

    #[Computed]
    public function listing(): Listing
    {
        $userId = Auth::id();
        $listing = Listing::query()
            ->with('seller:id,name,company_name,registration_number,country,is_verified_seller')
            ->whereKey($this->listingId)
            ->where(function (Builder $query) use ($userId): void {
                $query->published();

                if ($userId) {
                    $query->orWhere('seller_id', $userId);
                }
            })
            ->firstOrFail();

        $this->authorize('view', $listing);

        return $listing;
    }

    public function addToCart(
        CartServiceInterface $carts,
        CartIdentity $identity,
    ): void {
        if (Auth::id() === $this->listing->seller_id) {
            $this->addError('quantity', __('You cannot purchase your own listing.'));

            return;
        }

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$this->listing->inventory_quantity],
        ]);

        try {
            $cart = $carts->add(
                $this->listing,
                $this->quantity,
                $identity->buyer(),
                $identity->guestToken(),
            );
        } catch (DomainException|StaleCartVersion $exception) {
            $this->addError('quantity', DomainErrorMessage::for($exception));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('quantity', __('We could not add this asset. Please try again.'));

            return;
        }

        $this->dispatch('cart-updated', count: $cart->items->sum('quantity'));
        session()->flash('status', __('The asset was added to your cart.'));
    }

    public function revealContact(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->authorize('revealContact', $this->listing);

        $key = "contact-reveal:{$user->getAuthIdentifier()}";
        $allowed = RateLimiter::attempt($key, 5, static fn (): bool => true, 60);

        if (! $allowed) {
            $this->addError('contact', __('Too many contact requests. Please wait before trying again.'));

            return;
        }

        Log::notice('Seller contact details revealed.', [
            'buyer_id' => $user->getAuthIdentifier(),
            'listing_id' => $this->listingId,
            'ip' => request()->ip(),
        ]);

        $this->contactRevealed = true;
    }

    public function render(): View
    {
        return view('storefront::livewire.listing-show', [
            'listing' => $this->listing,
            'sellerContact' => $this->sellerContact(),
        ]);
    }

    /**
     * @return array{email: string, phone: string|null}|null
     */
    private function sellerContact(): ?array
    {
        if (! $this->contactRevealed) {
            return null;
        }

        $this->authorize('revealContact', $this->listing);

        $seller = User::query()
            ->whereKey($this->listing->seller_id)
            ->firstOrFail(['email', 'phone']);

        return [
            'email' => (string) $seller->email,
            'phone' => is_string($seller->phone) ? $seller->phone : null,
        ];
    }
}
