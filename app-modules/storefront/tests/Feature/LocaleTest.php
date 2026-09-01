<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Kinkoza\Storefront\Http\Livewire\CreateListing;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a visitor can switch the storefront locale through the session', function (): void {
    $this->from(route('home'))
        ->post(route('locale.update', ['locale' => 'fr']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="fr"', false)
        ->assertSee('Des actifs professionnels prêts pour une seconde vie.')
        ->assertSee('Parcourir les actifs');

    expect(session('locale'))->toBe('fr');
});

test('an authenticated locale choice is saved as the account preference', function (): void {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->post(route('locale.update', ['locale' => 'fr']))
        ->assertRedirect();

    expect($user->fresh()->preferredLocale())->toBe('fr');
});

test('the account preference localizes a fresh authenticated session', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="fr"', false)
        ->assertSee('Trouvez le bon actif professionnel');
});

test('unsupported locales are rejected', function (): void {
    $this->post('/locale/de')->assertNotFound();
});

test('listing validation uses French messages and attribute names', function (): void {
    $seller = User::factory()->create(['locale' => 'fr']);

    App::setLocale('fr');

    Livewire::actingAs($seller)
        ->test(CreateListing::class)
        ->set('form.title', '')
        ->call('save')
        ->assertHasErrors(['form.title' => 'required'])
        ->assertSee('Le champ titre de l’annonce est obligatoire.');
});

test('the selected locale persists across Livewire updates', function (): void {
    $this->post(route('locale.update', ['locale' => 'fr']))->assertRedirect();
    $initialHtml = $this->get(route('home'))->getContent();

    expect(preg_match_all('/wire:snapshot="([^"]+)"/', $initialHtml, $matches))->toBeGreaterThan(0);

    $snapshot = collect($matches[1])
        ->map(fn (string $encodedSnapshot): string => html_entity_decode($encodedSnapshot, ENT_QUOTES | ENT_HTML5))
        ->first(fn (string $candidate): bool => data_get(json_decode($candidate, true), 'memo.name') === 'storefront::listings-index');

    expect($snapshot)->toBeString()
        ->and(json_decode($snapshot, true))->toBeArray();

    App::setLocale('en');

    $response = $this->withHeader('X-Livewire', 'true')->postJson(
        route('default-livewire.update'),
        [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['search' => 'no-results-for-this-query'],
                'calls' => [],
            ]],
        ],
    );

    $response->assertOk();

    expect($response->json('components.0.effects.html'))
        ->toContain('Aucun actif correspondant');

    expect(App::getLocale())->toBe('fr');
});
