<?php

namespace Kinkoza\Storefront\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Kinkoza\Catalog\Actions\CreateListing as CreateCatalogListing;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Kinkoza\Catalog\Models\Listing;
use Kinkoza\Storefront\Http\Livewire\Forms\ListingForm;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('storefront::layouts.store')]
#[Title('Sell an asset')]
class CreateListing extends Component
{
    public ListingForm $form;

    public function mount(): void
    {
        $this->authorize('create', Listing::class);
        $this->form->onlineAt = now()->format('Y-m-d\TH:i');
    }

    public function save(): void
    {
        $this->authorize('create', Listing::class);

        $seller = Auth::user();

        abort_unless($seller instanceof User, 403);

        $listing = CreateCatalogListing::run($seller, $this->form->toData());

        $message = $listing->status === ListingStatus::PendingReview
            ? __('Your listing was submitted for review.')
            : __('Your listing was saved.');

        session()->flash('status', $message);

        $this->redirectRoute(
            'storefront.listings.show',
            ['slug' => $listing->slug],
            navigate: true,
        );
    }

    public function render(): View
    {
        return view('storefront::livewire.create-listing', [
            'categories' => ListingCategory::cases(),
            'countries' => Country::cases(),
            'currencies' => Currency::cases(),
        ]);
    }
}
