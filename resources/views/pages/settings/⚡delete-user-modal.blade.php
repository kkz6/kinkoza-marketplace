<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Kinkoza\Cart\Models\Cart;
use Kinkoza\Sales\Models\Order;
use Kinkoza\Sales\Models\OrderItem;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $deleted = DB::transaction(function () use ($user): bool {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $hasRetainedRecords = Order::query()
                ->where('buyer_id', $lockedUser->getKey())
                ->exists() || OrderItem::query()
                ->where('seller_id', $lockedUser->getKey())
                ->exists();

            if ($hasRetainedRecords) {
                return false;
            }

            Cart::query()
                ->where('buyer_id', $lockedUser->getKey())
                ->delete();

            return (bool) $lockedUser->delete();
        }, attempts: 3);

        if (! $deleted) {
            $this->addError(
                'password',
                __('Accounts with orders or sales must be retained for invoicing. Contact support to request anonymization.'),
            );

            return;
        }

        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                {{ __('Once your account is deleted, its marketplace resources are permanently removed. Accounts connected to orders or sales must instead be retained for invoicing. Please enter your password to continue.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
