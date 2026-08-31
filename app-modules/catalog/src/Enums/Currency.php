<?php

namespace Kinkoza\Catalog\Enums;

use Illuminate\Support\Number;

enum Currency: string
{
    case EUR = 'EUR';
    case GBP = 'GBP';

    public function label(): string
    {
        return match ($this) {
            self::EUR => 'Euro',
            self::GBP => 'British pound',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::GBP => '£',
        };
    }

    public function decimalPlaces(): int
    {
        return 2;
    }

    public function symbolPosition(): string
    {
        return 'before';
    }

    /**
     * @return array{
     *     symbol: string,
     *     decimal_places: int,
     *     symbol_position: string
     * }
     */
    public function formattingMetadata(): array
    {
        return [
            'symbol' => $this->symbol(),
            'decimal_places' => $this->decimalPlaces(),
            'symbol_position' => $this->symbolPosition(),
        ];
    }

    public function format(int $amountMinor): string
    {
        $formatted = Number::currency(
            $amountMinor / (10 ** $this->decimalPlaces()),
            in: $this->value,
            locale: app()->getLocale(),
            precision: $this->decimalPlaces(),
        );

        if (is_string($formatted)) {
            return $formatted;
        }

        return $this->symbol().number_format(
            $amountMinor / (10 ** $this->decimalPlaces()),
            $this->decimalPlaces(),
        );
    }
}
