<div>
    <div class="mx-auto max-w-[1050px] px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="rounded-2xl bg-blue-700 px-6 py-10 text-white sm:px-10 dark:bg-blue-400 dark:text-zinc-950">
            <span class="grid size-12 place-items-center rounded-full bg-white/15 dark:bg-zinc-950/10"><flux:icon.check class="size-7" /></span>
            <p class="mt-7 text-sm font-semibold uppercase tracking-[0.16em] opacity-80">{{ __('Order confirmed') }}</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ $order->number }}</h1>
            <p class="mt-4 max-w-xl text-base leading-7 opacity-85">{{ __('Inventory is reserved, your immutable line snapshots are saved, and invoice :invoice has been issued.', ['invoice' => $order->invoice->number]) }}</p>
        </div>

        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-zinc-800"><h2 class="text-xl font-semibold">{{ __('Order items') }}</h2></div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach($order->items as $item)
                        <article class="flex items-start justify-between gap-5 px-6 py-5">
                            <div><h3 class="font-semibold">{{ $item->title }}</h3><p class="mt-1 text-sm text-zinc-500">{{ __('Quantity :count at :amount', ['count' => $item->quantity, 'amount' => $item->currency->format($item->unit_price_minor)]) }}</p></div>
                            <p class="font-semibold">{{ $item->currency->format($item->line_total_minor) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <aside class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold">{{ __('Invoice summary') }}</h2>
                <dl class="mt-6 grid gap-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Invoice') }}</dt><dd class="font-medium">{{ $order->invoice->number }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Issued') }}</dt><dd class="font-medium">{{ $order->invoice->issued_at->translatedFormat('d M Y, H:i') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500">{{ __('Status') }}</dt><dd class="font-medium capitalize">{{ __($order->invoice->status->value) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-zinc-200 pt-4 text-base dark:border-zinc-800"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="font-semibold">{{ $order->currency->format($order->total_minor) }}</dd></div>
                </dl>

                <a href="{{ route('home') }}#marketplace" class="mt-6 block rounded-full border border-zinc-300 px-5 py-2.5 text-center text-sm font-semibold hover:border-zinc-500 dark:border-zinc-700 dark:hover:border-zinc-500">{{ __('Continue browsing') }}</a>
            </aside>
        </div>
    </div>
</div>
