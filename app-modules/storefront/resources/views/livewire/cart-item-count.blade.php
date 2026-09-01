<span
    class="inline-flex min-w-5 items-center justify-center rounded-full bg-blue-700 px-1.5 py-0.5 text-xs font-semibold leading-none text-white tabular-nums dark:bg-blue-400 dark:text-zinc-950"
    aria-label="{{ trans_choice('{0} No items in cart|{1} :count item in cart|[2,*] :count items in cart', $count, ['count' => $count]) }}"
    aria-live="polite"
    aria-atomic="true"
>
    {{ $count }}
</span>
