<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
    <section class="overflow-hidden rounded-3xl bg-zinc-950 px-6 py-8 text-white shadow-xl shadow-zinc-950/10 sm:px-8 lg:flex lg:items-end lg:justify-between lg:gap-8 lg:px-10 lg:py-10 dark:bg-zinc-900">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-300">{{ __('Account overview') }}</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Welcome back, :name', ['name' => $user->name]) }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-zinc-300 sm:text-base">{{ __('Manage purchases, listings, and business activity from one place.') }}</p>
        </div>

        <div class="mt-6 flex flex-wrap gap-3 lg:mt-0 lg:justify-end">
            <flux:button :href="route('home')" wire:navigate variant="filled" icon="magnifying-glass">
                {{ __('Browse assets') }}
            </flux:button>
            <flux:button :href="route('storefront.listings.create')" wire:navigate variant="primary" icon="plus">
                {{ __('Sell an asset') }}
            </flux:button>
        </div>
    </section>

    <section aria-labelledby="account-summary-heading">
        <h2 id="account-summary-heading" class="sr-only">{{ __('Account summary') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active listings') }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $dashboard->activeListingCount }}</p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300"><flux:icon.building-office-2 class="size-5" /></span>
                </div>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No listings awaiting review|{1} :count listing awaiting review|[2,*] :count listings awaiting review', $dashboard->pendingListingCount, ['count' => $dashboard->pendingListingCount]) }}</p>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Cart units') }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $dashboard->cartItemCount }}</p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"><flux:icon.shopping-bag class="size-5" /></span>
                </div>
                <a href="{{ route('storefront.cart.show') }}" wire:navigate class="mt-4 inline-flex text-xs font-medium text-blue-700 hover:underline dark:text-blue-300">{{ __('Review cart') }}</a>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Purchases') }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $dashboard->purchaseCount }}</p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300"><flux:icon.clipboard-document-list class="size-5" /></span>
                </div>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Confirmed marketplace orders') }}</p>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Units sold') }}</p>
                        <p class="mt-2 text-3xl font-semibold tabular-nums">{{ $dashboard->unitsSold }}</p>
                    </div>
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300"><flux:icon.check-badge class="size-5" /></span>
                </div>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No sales orders yet|{1} Across :count sales order|[2,*] Across :count sales orders', $dashboard->salesOrderCount, ['count' => $dashboard->salesOrderCount]) }}</p>
            </article>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="recent-orders-heading">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
                <div>
                    <h2 id="recent-orders-heading" class="font-semibold">{{ __('Recent purchases') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your latest marketplace orders.') }}</p>
                </div>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($dashboard->recentOrders as $order)
                    <a href="{{ route('storefront.orders.show', ['order' => $order->id]) }}" wire:navigate class="grid gap-3 px-5 py-4 transition hover:bg-zinc-50 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:px-6 dark:hover:bg-zinc-800/60">
                        <div>
                            <p class="font-medium">{{ $order->number }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $order->placed_at->diffForHumans() }}</p>
                        </div>
                        <flux:badge :color="$order->status->value === 'confirmed' ? 'green' : 'zinc'" size="sm">{{ __(ucfirst($order->status->value)) }}</flux:badge>
                        <p class="font-semibold tabular-nums sm:text-right">{{ $order->currency->format($order->total_minor) }}</p>
                    </a>
                @empty
                    <div class="px-6 py-12 text-center">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400"><flux:icon.shopping-bag class="size-6" /></span>
                        <h3 class="mt-4 font-semibold">{{ __('No purchases yet') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your completed orders will appear here.') }}</p>
                        <flux:button :href="route('home')" wire:navigate variant="ghost" class="mt-4">{{ __('Explore the marketplace') }}</flux:button>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid content-start gap-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="business-status-heading">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="business-status-heading" class="font-semibold">{{ __('Business status') }}</h2>
                    @if($dashboard->isVerifiedSeller)
                        <flux:badge color="green" size="sm">{{ __('Verified seller') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('Verification pending') }}</flux:badge>
                    @endif
                </div>

                <div class="mt-5 grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-4 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800/70">
                        <span>{{ __('Email verified') }}</span>
                        <flux:icon.check-circle class="size-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-800/70">
                        <span>{{ __('Business profile') }}</span>
                        @if($dashboard->hasBusinessProfile)
                            <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ __('Complete') }}</span>
                        @else
                            <span class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ __('Needs details') }}</span>
                        @endif
                    </div>
                </div>

                <flux:button :href="route('profile.edit')" wire:navigate variant="ghost" class="mt-4 w-full">{{ __('Manage account') }}</flux:button>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="quick-actions-heading">
                <h2 id="quick-actions-heading" class="font-semibold">{{ __('Quick actions') }}</h2>
                <div class="mt-4 grid gap-2">
                    <flux:button :href="route('storefront.listings.create')" wire:navigate variant="ghost" icon="plus" class="justify-start">{{ __('Create a listing') }}</flux:button>
                    <flux:button :href="route('storefront.cart.show')" wire:navigate variant="ghost" icon="shopping-bag" class="justify-start">{{ __('Review cart') }}</flux:button>
                    <flux:button :href="route('security.edit')" wire:navigate variant="ghost" icon="shield-check" class="justify-start">{{ __('Security settings') }}</flux:button>
                </div>
            </section>
        </div>
    </div>

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="recent-listings-heading">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:px-6">
            <div>
                <h2 id="recent-listings-heading" class="font-semibold">{{ __('Your listings') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No listings created|{1} :count listing in total|[2,*] :count listings in total', $dashboard->totalListingCount, ['count' => $dashboard->totalListingCount]) }}</p>
            </div>
            <flux:button :href="route('storefront.listings.create')" wire:navigate variant="primary" size="sm" icon="plus">{{ __('New listing') }}</flux:button>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($dashboard->recentListings as $listing)
                <a href="{{ route('storefront.listings.show', ['slug' => $listing->slug]) }}" wire:navigate class="grid gap-3 px-5 py-4 transition hover:bg-zinc-50 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center sm:px-6 dark:hover:bg-zinc-800/60">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $listing->title }}</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $listing->city }}, {{ __($listing->country->label()) }}</p>
                    </div>
                    <flux:badge :color="$listing->status->value === 'published' ? 'green' : ($listing->status->value === 'pending-review' ? 'amber' : 'zinc')" size="sm">{{ __($listing->status->label()) }}</flux:badge>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ trans_choice('{1} :count unit|[2,*] :count units', $listing->inventory_quantity, ['count' => $listing->inventory_quantity]) }}</p>
                    <p class="font-semibold tabular-nums sm:text-right">{{ $listing->currency->format($listing->price_minor) }}</p>
                </a>
            @empty
                <div class="px-6 py-12 text-center">
                    <h3 class="font-semibold">{{ __('Start selling on Kinkoza') }}</h3>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create your first business asset listing and reach verified buyers.') }}</p>
                    <flux:button :href="route('storefront.listings.create')" wire:navigate variant="primary" class="mt-4">{{ __('Create a listing') }}</flux:button>
                </div>
            @endforelse
        </div>
    </section>
</div>
