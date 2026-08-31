<div>
    <section class="mx-auto grid min-h-[calc(100dvh-4rem)] max-w-[1400px] items-center gap-10 px-4 py-12 sm:px-6 md:grid-cols-[0.82fr_1.18fr] lg:gap-16 lg:px-8 lg:py-16">
        <div class="max-w-xl">
            <h1 class="text-4xl font-semibold leading-[1.02] tracking-[-0.045em] text-zinc-950 sm:text-5xl lg:text-6xl dark:text-zinc-50">
                {{ __('Business assets, ready for a second life.') }}
            </h1>
            <p class="mt-6 max-w-lg text-base leading-7 text-zinc-600 sm:text-lg dark:text-zinc-300">
                {{ __('Search verified machinery, fleets, property, and intellectual assets across our launch markets.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#marketplace" class="whitespace-nowrap rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-zinc-50 transition hover:bg-blue-800 active:translate-y-px dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">
                    {{ __('Browse assets') }}
                </a>
                <a href="{{ route('storefront.listings.create') }}" wire:navigate class="whitespace-nowrap rounded-full border border-zinc-300 px-6 py-3 text-sm font-semibold text-zinc-900 transition hover:border-zinc-500 active:translate-y-px dark:border-zinc-700 dark:text-zinc-100 dark:hover:border-zinc-500">
                    {{ __('Sell an asset') }}
                </a>
            </div>
        </div>

        <div class="relative md:-mr-6 lg:-mr-8">
            <div class="absolute -inset-3 -z-10 rounded-2xl bg-blue-100 dark:bg-blue-950/70"></div>
            <img
                src="{{ asset('images/marketplace-hero.webp') }}"
                alt="{{ __('Two professionals inspecting a CNC milling machine') }}"
                width="1536"
                height="1024"
                fetchpriority="high"
                class="aspect-[4/3] w-full rounded-2xl object-cover object-center shadow-2xl shadow-blue-950/15 dark:shadow-black/40"
            >
        </div>
    </section>

    <section class="border-y border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900/50" aria-label="{{ __('Marketplace safeguards') }}">
        <div class="mx-auto grid max-w-[1400px] gap-px bg-zinc-200 sm:grid-cols-2 lg:grid-cols-4 dark:bg-zinc-800">
            <div class="bg-white px-6 py-6 dark:bg-zinc-900">
                <p class="font-semibold">{{ __('Verified sellers') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Company details checked before publication.') }}</p>
            </div>
            <div class="bg-white px-6 py-6 dark:bg-zinc-900">
                <p class="font-semibold">{{ __('Protected contact') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Seller details stay away from bulk scrapers.') }}</p>
            </div>
            <div class="bg-white px-6 py-6 dark:bg-zinc-900">
                <p class="font-semibold">{{ __('Exact pricing') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Integer minor units prevent rounding errors.') }}</p>
            </div>
            <div class="bg-white px-6 py-6 dark:bg-zinc-900">
                <p class="font-semibold">{{ __('Traceable records') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('ULIDs and readable references on every order.') }}</p>
            </div>
        </div>
    </section>

    <section id="marketplace" class="scroll-mt-20">
        <div class="mx-auto max-w-[1400px] px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">{{ __('Find the right business asset') }}</h2>
                <p class="mt-4 text-zinc-600 dark:text-zinc-400">{{ __('Filter current listings and share the URL with your procurement team.') }}</p>
            </div>

            <div class="mt-10 grid items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="rounded-2xl border border-zinc-200 bg-white p-5 lg:sticky lg:top-24 dark:border-zinc-800 dark:bg-zinc-900" aria-label="{{ __('Listing filters') }}">
                    <div class="grid gap-5">
                        <label class="grid gap-2 text-sm font-medium">
                            {{ __('Search by title') }}
                            <input wire:model.live.debounce.350ms="search" type="search" placeholder="{{ __('CNC, warehouse, fleet') }}" class="rounded-xl border-zinc-300 bg-zinc-50 text-zinc-950 placeholder:text-zinc-500 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">
                        </label>

                        <label class="grid gap-2 text-sm font-medium">
                            {{ __('Category') }}
                            <select wire:model.live="category" class="rounded-xl border-zinc-300 bg-zinc-50 text-zinc-950 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">{{ __('All categories') }}</option>
                                @foreach($categories as $categoryOption)
                                    <option value="{{ $categoryOption->value }}">{{ __($categoryOption->label()) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-2 text-sm font-medium">
                            {{ __('Country') }}
                            <select wire:model.live="country" class="rounded-xl border-zinc-300 bg-zinc-50 text-zinc-950 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">{{ __('All countries') }}</option>
                                @foreach($countries as $countryOption)
                                    <option value="{{ $countryOption->value }}">{{ __($countryOption->label()) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="grid gap-2 text-sm font-medium">
                                {{ __('Min price') }}
                                <input wire:model.live.debounce.500ms="minimumPrice" inputmode="decimal" placeholder="0" class="min-w-0 rounded-xl border-zinc-300 bg-zinc-50 text-zinc-950 placeholder:text-zinc-500 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </label>
                            <label class="grid gap-2 text-sm font-medium">
                                {{ __('Max price') }}
                                <input wire:model.live.debounce.500ms="maximumPrice" inputmode="decimal" placeholder="{{ __('Any') }}" class="min-w-0 rounded-xl border-zinc-300 bg-zinc-50 text-zinc-950 placeholder:text-zinc-500 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </label>
                        </div>
                    </div>
                </aside>

                <div>
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Showing currently published assets') }}</p>
                        <label class="grid gap-2 text-sm font-medium sm:w-52">
                            {{ __('Sort') }}
                            <select wire:model.live="sort" class="rounded-xl border-zinc-300 bg-white text-zinc-950 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                <option value="newest">{{ __('Newest first') }}</option>
                                <option value="price-ascending">{{ __('Price: low to high') }}</option>
                                <option value="price-descending">{{ __('Price: high to low') }}</option>
                            </select>
                        </label>
                    </div>

                    <div wire:loading.class="opacity-50" wire:target="search,category,country,minimumPrice,maximumPrice,sort" class="transition-opacity">
                        @if($listings->isEmpty())
                            <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
                                <h3 class="text-xl font-semibold">{{ __('No matching assets') }}</h3>
                                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('Try a broader title, another country, or remove one of the price limits.') }}</p>
                            </div>
                        @else
                            <div class="grid gap-x-5 gap-y-9 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($listings as $listing)
                                    <article wire:key="listing-{{ $listing->id }}" class="group min-w-0">
                                        <a href="{{ route('storefront.listings.show', ['slug' => $listing->slug]) }}" wire:navigate class="block overflow-hidden rounded-2xl bg-zinc-200 dark:bg-zinc-800">
                                            <img
                                                src="{{ $listing->image_url ?: 'https://picsum.photos/seed/'.$listing->id.'/900/900' }}"
                                                alt="{{ $listing->title }}"
                                                width="900"
                                                height="900"
                                                loading="lazy"
                                                class="aspect-square w-full object-cover transition duration-300 group-hover:scale-[1.025] motion-reduce:transition-none"
                                            >
                                        </a>
                                        <div class="mt-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __($listing->category->label()) }}</p>
                                                    <h3 class="mt-1 truncate font-semibold">
                                                        <a href="{{ route('storefront.listings.show', ['slug' => $listing->slug]) }}" wire:navigate class="hover:text-blue-700 dark:hover:text-blue-300">{{ $listing->title }}</a>
                                                    </h3>
                                                </div>
                                                <p class="shrink-0 font-semibold">{{ $listing->currency->format($listing->price_minor) }}</p>
                                            </div>
                                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $listing->city }}, {{ __($listing->country->label()) }}</p>
                                            <p class="mt-1 truncate text-sm text-zinc-500">{{ $listing->seller->company_name }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="mt-12">
                                {{ $listings->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
