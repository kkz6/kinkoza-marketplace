<div>
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8 lg:py-16">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-blue-700 dark:text-blue-300">{{ __('Seller workspace') }}</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ __('List a business asset') }}</h1>
            <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">{{ __('Give procurement teams the facts they need. Verified businesses can publish immediately; other listings enter review.') }}</p>
        </div>

        <form wire:submit="save" class="mt-10 grid gap-8">
            <section class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold">{{ __('Asset details') }}</h2>
                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-medium md:col-span-2">
                        {{ __('Listing title') }}
                        <input wire:model="form.title" autocomplete="off" placeholder="{{ __('Example: 2021 five-axis CNC machining centre') }}" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.title" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium">
                        {{ __('Category') }}
                        <select wire:model="form.category" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">{{ __('Choose a category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->value }}">{{ __($category->label()) }}</option>
                            @endforeach
                        </select>
                        <flux:error name="form.category" />
                    </label>

                    <flux:input
                        wire:model="form.inventoryQuantity"
                        type="number"
                        name="form.inventoryQuantity"
                        :label="__('Quantity available')"
                        min="1"
                        max="10000"
                        inputmode="numeric"
                        data-test="listing-inventory-quantity"
                    />

                    <label class="grid gap-2 text-sm font-medium md:col-span-2">
                        {{ __('Description') }}
                        <textarea wire:model="form.description" rows="7" placeholder="{{ __('Condition, specification, service history, collection requirements, and anything a buyer should know.') }}" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                        <flux:error name="form.description" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium md:col-span-2">
                        {{ __('Image URL') }} <span class="font-normal text-zinc-500">{{ __('optional') }}</span>
                        <input wire:model="form.imageUrl" type="url" inputmode="url" placeholder="https://example.com/asset.jpg" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.imageUrl" />
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold">{{ __('Price and location') }}</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="grid gap-2 text-sm font-medium lg:col-span-2">
                        {{ __('Net price') }}
                        <input wire:model="form.price" inputmode="decimal" placeholder="25000.00" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.price" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium lg:col-span-2">
                        {{ __('Currency') }}
                        <select wire:model="form.currency" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->value }}">{{ $currency->value }} ({{ $currency->symbol() }})</option>
                            @endforeach
                        </select>
                        <flux:error name="form.currency" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium lg:col-span-2">
                        {{ __('Country') }}
                        <select wire:model="form.country" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="">{{ __('Choose a country') }}</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->value }}">{{ __($country->label()) }}</option>
                            @endforeach
                        </select>
                        <flux:error name="form.country" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium lg:col-span-2">
                        {{ __('City') }}
                        <input wire:model="form.city" autocomplete="address-level2" placeholder="Lyon" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.city" />
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold">{{ __('Publication window') }}</h2>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <label class="grid gap-2 text-sm font-medium">
                        {{ __('Available from') }}
                        <input wire:model="form.onlineAt" type="datetime-local" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.onlineAt" />
                    </label>

                    <label class="grid gap-2 text-sm font-medium">
                        {{ __('Available until') }} <span class="font-normal text-zinc-500">{{ __('optional') }}</span>
                        <input wire:model="form.offlineAt" type="datetime-local" class="rounded-xl border-zinc-300 bg-zinc-50 focus:border-blue-600 focus:ring-blue-600 dark:border-zinc-700 dark:bg-zinc-950">
                        <flux:error name="form.offlineAt" />
                    </label>
                </div>

                <label class="mt-6 flex items-start gap-3 rounded-xl bg-zinc-100 p-4 text-sm dark:bg-zinc-800">
                    <input wire:model="form.publish" type="checkbox" class="mt-0.5 rounded border-zinc-300 text-blue-700 focus:ring-blue-600 dark:border-zinc-600 dark:bg-zinc-900">
                    <span><span class="block font-semibold">{{ __('Request publication now') }}</span><span class="mt-1 block text-zinc-600 dark:text-zinc-400">{{ __('Unverified seller accounts are safely routed to manual review.') }}</span></span>
                </label>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('home') }}" class="rounded-full border border-zinc-300 px-6 py-3 text-center text-sm font-semibold hover:border-zinc-500 dark:border-zinc-700 dark:hover:border-zinc-500">{{ __('Cancel') }}</a>
                <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-blue-700 px-7 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 active:translate-y-px disabled:cursor-wait disabled:opacity-60 dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">
                    <span wire:loading.remove wire:target="save">{{ __('Save listing') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>
