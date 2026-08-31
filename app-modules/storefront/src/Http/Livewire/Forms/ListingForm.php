<?php

namespace Kinkoza\Storefront\Http\Livewire\Forms;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Kinkoza\Catalog\Data\CreateListingData;
use Kinkoza\Catalog\Enums\Country;
use Kinkoza\Catalog\Enums\Currency;
use Kinkoza\Catalog\Enums\ListingCategory;
use Kinkoza\Catalog\Enums\ListingStatus;
use Livewire\Form;

class ListingForm extends Form
{
    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $price = '';

    public string $currency = 'EUR';

    public string $country = '';

    public string $city = '';

    public string $onlineAt = '';

    public string $offlineAt = '';

    public int $inventoryQuantity = 1;

    public string $imageUrl = '';

    public bool $publish = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:140'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'category' => ['required', Rule::enum(ListingCategory::class)],
            'price' => ['required', 'regex:/^\d{1,10}([.,]\d{1,2})?$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'country' => ['required', Rule::enum(Country::class)],
            'city' => ['required', 'string', 'max:120'],
            'onlineAt' => ['required', 'date'],
            'offlineAt' => ['nullable', 'date', 'after:onlineAt'],
            'inventoryQuantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'imageUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'publish' => ['boolean'],
        ];
    }

    public function toData(): CreateListingData
    {
        $this->validate();

        return new CreateListingData(
            title: trim($this->title),
            description: trim($this->description),
            category: ListingCategory::from($this->category),
            status: $this->publish ? ListingStatus::Published : ListingStatus::Draft,
            currency: Currency::from($this->currency),
            priceMinor: $this->priceMinor(),
            country: Country::from($this->country),
            city: trim($this->city),
            onlineAt: CarbonImmutable::parse($this->onlineAt),
            offlineAt: filled($this->offlineAt) ? CarbonImmutable::parse($this->offlineAt) : null,
            inventoryQuantity: $this->inventoryQuantity,
            imageUrl: filled($this->imageUrl) ? trim($this->imageUrl) : null,
        );
    }

    private function priceMinor(): int
    {
        $normalized = str_replace(',', '.', $this->price);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
