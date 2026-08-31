<?php

namespace Kinkoza\Storefront\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Models\Listing;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('storefront::layouts.store')]
#[Title('Verified business assets')]
class ListingsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, except: '')]
    public string $search = '';

    #[Url(history: true, except: '')]
    public string $category = '';

    #[Url(history: true, except: '')]
    public string $country = '';

    #[Url(history: true, except: '')]
    public string $minimumPrice = '';

    #[Url(history: true, except: '')]
    public string $maximumPrice = '';

    #[Url(history: true, except: 'newest')]
    public string $sort = 'newest';

    public function updated(string $property): void
    {
        if (! in_array($property, [
            'search',
            'category',
            'country',
            'minimumPrice',
            'maximumPrice',
            'sort',
        ], true)) {
            return;
        }

        $this->resetPage();
    }

    public function updatedSearch(string $value): void
    {
        $this->search = mb_substr(trim($value), 0, 100);
    }

    public function render(): View
    {
        $listings = Listing::query()
            ->select([
                'id',
                'seller_id',
                'slug',
                'title',
                'category',
                'currency',
                'price_minor',
                'country',
                'city',
                'online_at',
                'inventory_quantity',
                'image_url',
                'sequence',
            ])
            ->with('seller:id,company_name,country')
            ->published()
            ->when(filled($this->search), function (Builder $query): void {
                $query->whereLike('title', '%'.trim($this->search).'%');
            })
            ->when(ListingCategory::tryFrom($this->category), function (Builder $query, ListingCategory $category): void {
                $query->where('category', $category->value);
            })
            ->when(Country::tryFrom($this->country), function (Builder $query, Country $country): void {
                $query->where('country', $country->value);
            })
            ->when($this->priceToMinor($this->minimumPrice), function (Builder $query, int $minimum): void {
                $query->where('price_minor', '>=', $minimum);
            })
            ->when($this->priceToMinor($this->maximumPrice), function (Builder $query, int $maximum): void {
                $query->where('price_minor', '<=', $maximum);
            });

        match ($this->sort) {
            'price-ascending' => $listings->orderBy('price_minor')->orderBy('id'),
            'price-descending' => $listings->orderByDesc('price_minor')->orderByDesc('id'),
            default => $listings->orderByDesc('online_at')->orderByDesc('id'),
        };

        return view('storefront::livewire.listings-index', [
            'categories' => ListingCategory::cases(),
            'countries' => Country::cases(),
            'listings' => $listings->cursorPaginate(12),
        ]);
    }

    private function priceToMinor(string $value): ?int
    {
        $normalized = str_replace(',', '.', trim($value));

        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
