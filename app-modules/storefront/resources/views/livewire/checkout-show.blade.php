<div>
    <div class="mx-auto max-w-[1100px] px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <a href="{{ route('storefront.cart.show') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-100"><flux:icon.arrow-left class="size-4" />{{ __('Back to cart') }}</a>

        <div class="mt-7 max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">{{ __('Final review') }}</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ __('Confirm your order') }}</h1>
            <p class="mt-4 leading-7 text-zinc-600 dark:text-zinc-400">{{ __('We recheck live inventory while creating the order and invoice in one atomic transaction.') }}</p>
        </div>

        <div class="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800"><h2 class="text-xl font-semibold">{{ __('Assets') }}</h2></div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($cart->items as $item)
                        <article class="flex gap-4 px-6 py-5">
                            <img src="{{ $item->listing?->image_url ?: 'https://picsum.photos/seed/'.$item->listing_id.'/240/240' }}" alt="" width="240" height="240" class="size-16 rounded-xl object-cover sm:size-20">
                            <div class="min-w-0 flex-1"><h3 class="font-semibold">{{ $item->title }}</h3><p class="mt-1 text-sm text-zinc-500">{{ __('Quantity :count', ['count' => $item->quantity]) }}</p></div>
                            <p class="font-semibold">{{ \Kinkoza\Catalog\Enums\Currency::from($item->currency)->format($item->line_total_minor) }}</p>
                        </article>
                    @empty
                        <p class="px-6 py-10 text-zinc-600 dark:text-zinc-400">{{ __('Your cart is empty. Return to the marketplace before checking out.') }}</p>
                    @endforelse
                </div>
            </section>

            <aside class="rounded-2xl border border-zinc-200 bg-white p-6 lg:sticky lg:top-24 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold">{{ __('Order total') }}</h2>
                <dl class="mt-6 grid gap-4">
                    <div class="flex justify-between gap-4 text-sm"><dt class="text-zinc-600 dark:text-zinc-400">{{ __('Subtotal') }}</dt><dd>{{ \Kinkoza\Catalog\Enums\Currency::from($cart->currency)->format($cart->subtotal_minor) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 pt-4 text-lg font-semibold dark:border-zinc-800"><dt>{{ __('Total') }}</dt><dd>{{ \Kinkoza\Catalog\Enums\Currency::from($cart->currency)->format($cart->total_minor) }}</dd></div>
                </dl>

                <flux:error name="checkout" class="mt-5" />

                <button type="button" wire:click="placeOrder" wire:loading.attr="disabled" @disabled($cart->items->isEmpty()) class="mt-6 w-full rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-50 dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">
                    <span wire:loading.remove wire:target="placeOrder">{{ __('Place order') }}</span>
                    <span wire:loading wire:target="placeOrder">{{ __('Securing inventory...') }}</span>
                </button>

                <div class="mt-5 flex gap-3 text-xs leading-5 text-zinc-500"><flux:icon.lock-closed class="mt-0.5 size-4 shrink-0" /><p>{{ __('Your checkout token prevents duplicate orders if this request is retried.') }}</p></div>
            </aside>
        </div>
    </div>
</div>
