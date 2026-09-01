<div>
    <div class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">{{ __('Procurement cart') }}</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ __('Review your assets') }}</h1>
            </div>
            <p class="text-sm text-zinc-500">{{ __('Cart #:number', ['number' => str_pad((string) $cart->sequence, 6, '0', STR_PAD_LEFT)]) }}</p>
        </div>

        <flux:error name="cart" class="mt-6" />

        @if($cart->items->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-zinc-100 dark:bg-zinc-800"><flux:icon.shopping-bag class="size-6" /></span>
                <h2 class="mt-5 text-2xl font-semibold">{{ __('Your cart is empty') }}</h2>
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('Browse verified business assets and add the right ones here.') }}</p>
                <a href="{{ route('home') }}#marketplace" class="mt-6 inline-flex rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-800 dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">{{ __('Browse assets') }}</a>
            </div>
        @else
            <div class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach($cart->items as $item)
                        <article wire:key="cart-item-{{ $item->id }}" class="grid grid-cols-[96px_minmax(0,1fr)] gap-5 py-6 sm:grid-cols-[128px_minmax(0,1fr)_auto]">
                            <a href="{{ $item->listing ? route('storefront.listings.show', ['slug' => $item->listing->slug]) : '#' }}" class="overflow-hidden rounded-xl bg-zinc-200 dark:bg-zinc-800">
                                <img src="{{ $item->listing?->image_url ?: 'https://picsum.photos/seed/'.$item->listing_id.'/400/400' }}" alt="{{ $item->title }}" width="400" height="400" class="aspect-square size-full object-cover">
                            </a>

                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $item->sku }}</p>
                                <h2 class="mt-1 font-semibold leading-snug">{{ $item->title }}</h2>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __(':amount each', ['amount' => \Kinkoza\Catalog\Enums\Currency::from($item->currency)->format($item->unit_price_minor)]) }}</p>

                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <flux:input
                                        type="number"
                                        min="1"
                                        :max="$item->listing?->inventory_quantity ?? $item->quantity"
                                        :value="$item->quantity"
                                        :aria-label="__('Quantity for :title', ['title' => $item->title])"
                                        wire:change="updateQuantity('{{ $item->id }}', $event.target.value, {{ $cart->version }})"
                                        size="sm"
                                        class="w-20"
                                        inputmode="numeric"
                                        data-test="cart-item-quantity"
                                    />
                                    <button type="button" wire:click="remove('{{ $item->id }}', {{ $cart->version }})" wire:loading.attr="disabled" class="text-sm font-medium text-zinc-500 underline decoration-zinc-300 underline-offset-4 hover:text-red-700 disabled:opacity-60 dark:text-zinc-400 dark:hover:text-red-300">{{ __('Remove') }}</button>
                                </div>
                            </div>

                            <p class="col-start-2 font-semibold sm:col-auto">{{ \Kinkoza\Catalog\Enums\Currency::from($item->currency)->format($item->line_total_minor) }}</p>
                        </article>
                    @endforeach
                </div>

                <aside class="rounded-2xl border border-zinc-200 bg-white p-6 lg:sticky lg:top-24 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-xl font-semibold">{{ __('Order summary') }}</h2>
                    <dl class="mt-6 grid gap-4 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-zinc-600 dark:text-zinc-400">{{ __('Subtotal') }}</dt><dd class="font-medium">{{ \Kinkoza\Catalog\Enums\Currency::from($cart->currency)->format($cart->subtotal_minor) }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-zinc-200 pt-4 text-base dark:border-zinc-800"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="font-semibold">{{ \Kinkoza\Catalog\Enums\Currency::from($cart->currency)->format($cart->total_minor) }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs leading-5 text-zinc-500">{{ __('Net prices. Tax and collection arrangements are confirmed with each seller.') }}</p>

                    @auth
                        <a href="{{ route('storefront.checkout.show') }}" wire:navigate class="mt-6 block rounded-full bg-blue-700 px-6 py-3 text-center text-sm font-semibold text-white hover:bg-blue-800 active:translate-y-px dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">{{ __('Continue to checkout') }}</a>
                    @else
                        <a href="{{ route('storefront.checkout.show') }}" wire:navigate class="mt-6 block rounded-full bg-blue-700 px-6 py-3 text-center text-sm font-semibold text-white hover:bg-blue-800 active:translate-y-px dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">{{ __('Sign in to checkout') }}</a>
                    @endauth
                </aside>
            </div>
        @endif
    </div>
</div>
