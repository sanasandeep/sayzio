@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        {{-- Mobile: simple Prev / Next --}}
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-3 py-2 text-xs font-medium rounded-lg cursor-default opacity-40"
                      style="background: rgba(124,58,237,0.06); color: var(--text-faint); border: 1px solid rgba(124,58,237,0.10);">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   class="relative inline-flex items-center px-3 py-2 text-xs font-medium rounded-lg transition-colors hover:text-violet-300"
                   style="background: rgba(124,58,237,0.08); color: var(--text-primary); border: 1px solid rgba(124,58,237,0.16);">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   class="relative inline-flex items-center px-3 py-2 ml-2 text-xs font-medium rounded-lg transition-colors hover:text-violet-300"
                   style="background: rgba(124,58,237,0.08); color: var(--text-primary); border: 1px solid rgba(124,58,237,0.16);">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-3 py-2 ml-2 text-xs font-medium rounded-lg cursor-default opacity-40"
                      style="background: rgba(124,58,237,0.06); color: var(--text-faint); border: 1px solid rgba(124,58,237,0.10);">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- Desktop --}}
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-xs leading-5" style="color: var(--text-faint);">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-semibold" style="color: var(--text-dimmed);">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-semibold" style="color: var(--text-dimmed);">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-semibold" style="color: var(--text-dimmed);">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex items-center gap-1">
                    {{-- Previous --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                              class="relative inline-flex items-center justify-center w-9 h-9 text-xs rounded-lg cursor-default opacity-30"
                              style="background: rgba(124,58,237,0.04); color: var(--text-faint); border: 1px solid rgba(124,58,237,0.08);">
                            <i class="fas fa-chevron-left text-[10px]"></i>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"
                           class="relative inline-flex items-center justify-center w-9 h-9 text-xs rounded-lg transition-all hover:text-violet-300"
                           style="background: rgba(124,58,237,0.08); color: var(--text-primary); border: 1px solid rgba(124,58,237,0.16);">
                            <i class="fas fa-chevron-left text-[10px]"></i>
                        </a>
                    @endif

                    {{-- Page Numbers --}}
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true"
                                  class="relative inline-flex items-center justify-center w-9 h-9 text-xs rounded-lg cursor-default"
                                  style="color: var(--text-faint);">
                                {{ $element }}
                            </span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page"
                                          class="relative inline-flex items-center justify-center w-9 h-9 text-xs font-bold rounded-lg cursor-default"
                                          style="background: linear-gradient(135deg, #7c3aed, #a855f7); color: #fff; border: 1px solid rgba(168,85,247,0.5); box-shadow: 0 4px 12px rgba(124,58,237,0.3);">
                                        {{ $page }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                       class="relative inline-flex items-center justify-center w-9 h-9 text-xs font-medium rounded-lg transition-all hover:text-violet-300"
                                       style="background: rgba(124,58,237,0.06); color: var(--text-dimmed); border: 1px solid rgba(124,58,237,0.12);">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                           class="relative inline-flex items-center justify-center w-9 h-9 text-xs rounded-lg transition-all hover:text-violet-300"
                           style="background: rgba(124,58,237,0.08); color: var(--text-primary); border: 1px solid rgba(124,58,237,0.16);">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                              class="relative inline-flex items-center justify-center w-9 h-9 text-xs rounded-lg cursor-default opacity-30"
                              style="background: rgba(124,58,237,0.04); color: var(--text-faint); border: 1px solid rgba(124,58,237,0.08);">
                            <i class="fas fa-chevron-right text-[10px]"></i>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
