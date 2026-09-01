<?php

declare(strict_types=1);

namespace Kinkoza\Storefront\Http\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Kinkoza\Storefront\Actions\GetAccountDashboard;
use Kinkoza\Storefront\Support\CartIdentity;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Account dashboard')]
class AccountDashboard extends Component
{
    public function render(): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $identity = resolve(CartIdentity::class);

        return view('storefront::livewire.account-dashboard', [
            'dashboard' => GetAccountDashboard::make()->handle($user, $identity->guestToken()),
            'user' => $user,
        ]);
    }
}
