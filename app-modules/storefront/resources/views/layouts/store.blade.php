<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ __('Buy and sell verified business assets across France, Belgium, and Luxembourg.') }}">

        <title>{{ filled($title ?? null) ? __($title).' | '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-[100dvh] bg-zinc-50 font-sans text-zinc-950 selection:bg-blue-200 dark:bg-zinc-950 dark:text-zinc-100 dark:selection:bg-blue-800">
        <header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-zinc-50/90 backdrop-blur-xl dark:border-zinc-800 dark:bg-zinc-950/90" x-data="{ open: false }">
            <nav class="mx-auto flex h-16 max-w-[1400px] items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="{{ __('Primary navigation') }}">
                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-3 font-semibold tracking-tight" aria-label="{{ __('Kinkoza home') }}">
                    <span class="grid size-8 place-items-center rounded-xl bg-blue-700 text-sm font-bold text-zinc-50 dark:bg-blue-400 dark:text-zinc-950">K</span>
                    <span class="text-base">Kinkoza</span>
                </a>

                <div class="hidden items-center gap-7 text-sm font-medium lg:flex">
                    <a href="{{ route('home') }}#marketplace" class="text-zinc-600 transition-colors hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">{{ __('Browse assets') }}</a>
                    <a href="{{ route('storefront.listings.create') }}" wire:navigate class="text-zinc-600 transition-colors hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">{{ __('Sell an asset') }}</a>
                    <a href="{{ route('storefront.cart.show') }}" wire:navigate class="text-zinc-600 transition-colors hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">{{ __('Cart') }}</a>

                    <form method="POST" action="{{ route('locale.update', ['locale' => app()->getLocale() === 'fr' ? 'en' : 'fr']) }}">
                        @csrf
                        <button type="submit" class="rounded-full px-2 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white" aria-label="{{ app()->getLocale() === 'fr' ? 'Switch to English' : 'Passer en français' }}">
                            {{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}
                        </button>
                    </form>

                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="rounded-full border border-zinc-300 px-4 py-2 text-zinc-800 transition hover:border-zinc-500 active:translate-y-px dark:border-zinc-700 dark:text-zinc-100 dark:hover:border-zinc-500">{{ __('Account') }}</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="rounded-full bg-blue-700 px-4 py-2 text-zinc-50 transition hover:bg-blue-800 active:translate-y-px dark:bg-blue-400 dark:text-zinc-950 dark:hover:bg-blue-300">{{ __('Sign in') }}</a>
                    @endauth
                </div>

                <button type="button" class="grid size-10 place-items-center rounded-full border border-zinc-300 lg:hidden dark:border-zinc-700" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="mobile-navigation">
                    <span class="sr-only">{{ __('Toggle navigation') }}</span>
                    <flux:icon.bars-3 class="size-5" />
                </button>
            </nav>

            <div id="mobile-navigation" class="border-t border-zinc-200 px-4 py-4 lg:hidden dark:border-zinc-800" x-show="open" x-collapse x-cloak>
                <div class="mx-auto grid max-w-[1400px] gap-1 text-sm font-medium">
                    <a href="{{ route('home') }}#marketplace" class="rounded-xl px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ __('Browse assets') }}</a>
                    <a href="{{ route('storefront.listings.create') }}" wire:navigate class="rounded-xl px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ __('Sell an asset') }}</a>
                    <a href="{{ route('storefront.cart.show') }}" wire:navigate class="rounded-xl px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ __('Cart') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ __('Account') }}</a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate class="rounded-xl px-3 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ __('Sign in') }}</a>
                    @endauth
                    <form method="POST" action="{{ route('locale.update', ['locale' => app()->getLocale() === 'fr' ? 'en' : 'fr']) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl px-3 py-3 text-left font-semibold uppercase tracking-wider hover:bg-zinc-100 dark:hover:bg-zinc-900">{{ app()->getLocale() === 'fr' ? 'English' : 'Français' }}</button>
                    </form>
                </div>
            </div>
        </header>

        @if(session('status'))
            <div class="fixed inset-x-4 top-20 z-40 mx-auto max-w-lg rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-950 shadow-lg shadow-blue-950/10 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100" role="status">
                {{ session('status') }}
            </div>
        @endif

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 bg-zinc-100/70 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="mx-auto grid max-w-[1400px] gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
                <div>
                    <div class="flex items-center gap-3 font-semibold">
                        <span class="grid size-8 place-items-center rounded-xl bg-blue-700 text-sm font-bold text-zinc-50 dark:bg-blue-400 dark:text-zinc-950">K</span>
                        Kinkoza
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('A safer way for European businesses to exchange professional assets.') }}</p>
                </div>
                <div class="grid content-start gap-3 text-sm">
                    <p class="font-semibold">{{ __('Marketplace') }}</p>
                    <a href="{{ route('home') }}#marketplace" class="text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Browse assets') }}</a>
                    <a href="{{ route('storefront.listings.create') }}" class="text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-100">{{ __('Create a listing') }}</a>
                </div>
                <div class="grid content-start gap-3 text-sm">
                    <p class="font-semibold">{{ __('Launch markets') }}</p>
                    <p class="text-zinc-600 dark:text-zinc-400">{{ __('France, Belgium, Luxembourg') }}</p>
                    <p class="text-zinc-500 dark:text-zinc-500">{{ __('Prices shown net of VAT.') }}</p>
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
