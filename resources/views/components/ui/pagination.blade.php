@props(['paginator', 'pageName' => 'page'])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        if ($last <= 7) {
            $pages = range(1, $last);
        } else {
            $start = max(2, $current - 1);
            $end = min($last - 1, $current + 1);

            if ($current <= 3) {
                [$start, $end] = [2, 4];
            }
            if ($current >= $last - 2) {
                [$start, $end] = [$last - 3, $last - 1];
            }

            $pages = [1];

            // اگر فقط یک صفحه پنهان می‌ماند، به‌جای «…» خودش نشان داده می‌شود
            if ($start === 3) {
                $pages[] = 2;
            } elseif ($start > 3) {
                $pages[] = '…';
            }

            for ($i = $start; $i <= $end; $i++) {
                $pages[] = $i;
            }

            if ($end === $last - 2) {
                $pages[] = $last - 1;
            } elseif ($end < $last - 2) {
                $pages[] = '…';
            }

            $pages[] = $last;
        }
    @endphp

    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm text-muted" aria-label="صفحه‌بندی">
        <span class="tabular">{{ $paginator->firstItem() }} تا {{ $paginator->lastItem() }} از {{ $paginator->total() }}</span>

        <div class="flex items-center gap-1">
            <button
                type="button"
                wire:click="previousPage('{{ $pageName }}')"
                @disabled($paginator->onFirstPage())
                class="flex size-9 items-center justify-center rounded-control transition hover:bg-line/50 hover:text-fg disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                aria-label="صفحه‌ی قبل"
            >
                <x-ui.icon name="chevron-left" class="size-4.5 rtl:rotate-180" />
            </button>

            @foreach ($pages as $p)
                @if ($p === '…')
                    <span class="flex size-9 items-center justify-center" aria-hidden="true">…</span>
                @else
                    <button
                        type="button"
                        wire:key="page-{{ $pageName }}-{{ $p }}"
                        wire:click="gotoPage({{ $p }}, '{{ $pageName }}')"
                        @if ($p === $current) aria-current="page" @endif
                        aria-label="صفحه‌ی {{ $p }}"
                        @class([
                            'tabular flex h-9 min-w-9 items-center justify-center rounded-control px-2 text-sm font-medium transition',
                            'bg-primary text-on-primary' => $p === $current,
                            'hover:bg-line/50 hover:text-fg' => $p !== $current,
                        ])
                    >{{ $p }}</button>
                @endif
            @endforeach

            <button
                type="button"
                wire:click="nextPage('{{ $pageName }}')"
                @disabled(! $paginator->hasMorePages())
                class="flex size-9 items-center justify-center rounded-control transition hover:bg-line/50 hover:text-fg disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                aria-label="صفحه‌ی بعد"
            >
                <x-ui.icon name="chevron-right" class="size-4.5 rtl:rotate-180" />
            </button>
        </div>
    </nav>
@endif
