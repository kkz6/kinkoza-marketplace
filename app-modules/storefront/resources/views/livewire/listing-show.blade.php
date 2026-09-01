<div>
    <div class="mx-auto max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        <a href="{{ route('home') }}#marketplace" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-100">
            <flux:icon.arrow-left class="size-4" />
            {{ __('Back to marketplace') }}
        </a>

        <div class="mt-7 grid items-start gap-10 lg:grid-cols-[minmax(0,1.25fr)_minmax(340px,0.75fr)] lg:gap-14">
            <div>
                <div class="overflow-hidden rounded-2xl bg-zinc-200 dark:bg-zinc-800">
                    <img src="{{ $listing->image_url ?: 'https://picsum.photos/seed/'.$listing->id.'/1400/1100' }}" alt="{{ $listing->title }}" width="1400" height="1100" class="aspect-[4/3] w-full object-cover">
                </div>

                <section class="mt-10 border-t border-zinc-200 pt-8 dark:border-zinc-800">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('About this asset') }}</h2>
                    <p class="mt-4 whitespace-pre-line text-base leading-7 text-zinc-700 dark:text-zinc-300">{{ $listing->description }}</p>
                </section>
            </div>

            <aside class="lg:sticky lg:top-24">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm shadow-zinc-950/5 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/20">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ __($listing->category->label()) }}</span>
                        <span aria-hidden="true">/</span>
                        <span>#{{ str_pad((string) $listing->sequence, 6, '0', STR_PAD_LEFT) }}</span>
                    </div>

                    <h1 class="mt-4 text-3xl font-semibold leading-tight tracking-[-0.035em] sm:text-4xl">{{ $listing->title }}</h1>
                    <p class="mt-6 text-3xl font-semibold tracking-tight">{{ $listing->currency->format($listing->price_minor) }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Net price, excluding VAT') }}</p>

                    <dl class="mt-7 grid gap-4 border-y border-zinc-200 py-6 text-sm dark:border-zinc-800">
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</dt><dd class="text-right font-medium">{{ $listing->city }}, {{ __($listing->country->label()) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Availability') }}</dt><dd class="text-right font-medium">{{ trans_choice('{1} :count unit|[2,*] :count units', $listing->inventory_quantity, ['count' => $listing->inventory_quantity]) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Seller') }}</dt><dd class="text-right font-medium">{{ $listing->seller->company_name ?: $listing->seller->name }}</dd></div>
                    </dl>

                    @if(auth()->id() === $listing->seller_id)
                        <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">{{ __('You own this listing. Its current status is :status.', ['status' => __($listing->status->label())]) }}</div>
                    @elseif($listing->isPubliclyVisible() && $listing->inventory_quantity > 0)
                        <form wire:submit="addToCart" class="mt-6 grid gap-4">
                            <flux:input
                                wire:model="quantity"
                                type="number"
                                name="quantity"
                                :label="__('Quantity')"
                                min="1"
                                :max="$listing->inventory_quantity"
                                class="w-28"
                                inputmode="numeric"
                                data-test="listing-quantity"
                            />
                            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 active:translate-y-px disabled:cursor-wait disabled:opacity-60 dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">
                                <span wire:loading.remove wire:target="addToCart">{{ __('Add to cart') }}</span>
                                <span wire:loading wire:target="addToCart">{{ __('Adding...') }}</span>
                            </button>
                        </form>
                    @else
                        <p class="mt-6 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ __('This asset is not currently available to purchase.') }}</p>
                    @endif
                </div>

                <section class="mt-5 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200"><flux:icon.building-office-2 class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">{{ $listing->seller->is_verified_seller ? __('Verified business seller') : __('Business seller') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $listing->seller->company_name ?: $listing->seller->name }}</p>
                            @if($listing->seller->registration_number)
                                <p class="mt-1 text-xs text-zinc-500">{{ __('Registration :number', ['number' => $listing->seller->registration_number]) }}</p>
                            @endif
                        </div>
                    </div>

                    @if(auth()->id() === $listing->seller_id)
                        <p class="mt-5 rounded-xl bg-zinc-100 p-4 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ __('This is your seller profile.') }}</p>
                    @elseif($sellerContact !== null)
                        <dl class="mt-5 grid gap-3 rounded-xl bg-zinc-100 p-4 text-sm dark:bg-zinc-800">
                            <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt><dd class="mt-1 font-medium"><a class="hover:text-blue-700 dark:hover:text-blue-300" href="mailto:{{ $sellerContact['email'] }}">{{ $sellerContact['email'] }}</a></dd></div>
                            @if($sellerContact['phone'])
                                <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Phone') }}</dt><dd class="mt-1 font-medium"><a class="hover:text-blue-700 dark:hover:text-blue-300" href="tel:{{ $sellerContact['phone'] }}">{{ $sellerContact['phone'] }}</a></dd></div>
                            @endif
                        </dl>
                    @else
                        <button wire:click="revealContact" wire:loading.attr="disabled" type="button" class="mt-5 w-full rounded-full border border-zinc-300 px-5 py-2.5 text-sm font-semibold transition hover:border-zinc-500 active:translate-y-px disabled:cursor-wait disabled:opacity-60 dark:border-zinc-700 dark:hover:border-zinc-500">{{ __('Reveal seller contact') }}</button>
                    @endif

                    <flux:error name="contact" class="mt-3" />
                </section>
            </aside>
        </div>
    </div>
</div>
