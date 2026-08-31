<?php

declare(strict_types=1);

use App\Support\Database\SequenceGenerator;
use Illuminate\Support\Facades\App;
use Kinkoza\Cart\Exceptions\ListingUnavailable;
use Kinkoza\Sales\Exceptions\EmptyCart;

test('exception diagnostics use the active French locale', function (): void {
    App::setLocale('fr');

    expect(fn () => resolve(SequenceGenerator::class)->reserve('', 1))
        ->toThrow(InvalidArgumentException::class, 'Un nom de séquence est obligatoire.');

    expect(ListingUnavailable::forListing('01TEST')->getMessage())
        ->toBe('L’annonce [01TEST] n’est pas disponible.')
        ->and(EmptyCart::forCheckout()->getMessage())
        ->toBe('Un panier vide ne peut pas être validé.')
        ->and(__('Listing price must be an integer minor-unit amount.'))
        ->toBe('Le prix de l’annonce doit être un montant entier exprimé en unités monétaires mineures.')
        ->and(__('Order and invoice references must be strings.'))
        ->toBe('Les références de la commande et de la facture doivent être des chaînes de caractères.');
});
