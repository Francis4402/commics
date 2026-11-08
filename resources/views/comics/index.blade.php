@extends('layouts.app')

@section('title', 'Comics Reader - DarkComic')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6">

    <!-- Categories Navigation Bar (Crisp, High-Readability Pills) -->
    <div class="mb-5 sm:mb-7">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-xs font-black uppercase tracking-wider text-zinc-300">Browse Categories</span>
            </div>
            
            <div class="flex items-center gap-2 text-xs">
                <span id="comicsCountBadge" class="font-extrabold text-zinc-200 bg-zinc-900 border border-zinc-800 px-2.5 py-1 rounded-lg">
                    {{ count($comics) }} Comics
                </span>
            </div>
        </div>

        <!-- Category Pills (Server Route + Active State) -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none -mx-3 px-3 sm:mx-0 sm:px-0" id="categoryPillsContainer">
            @php
                $catList = $categories ?? ['All', 'Manhwa', 'Webtoons', 'Action', 'Romance', '18+ Adult', 'Fantasy', 'Drama', 'Comedy'];
                $currentCat = $activeCategory ?? 'All';
            @endphp

            @foreach($catList as $cat)
                @php
                    $isActive = strtolower($currentCat) === strtolower($cat);
                    $catUrl = request()->fullUrlWithQuery(['category' => $cat, 'page' => 1]);
                @endphp
                <a href="{{ $catUrl }}"
                   data-category="{{ $cat }}"
                   class="category-pill px-4 py-2 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2 shrink-0 {{ $isActive ? 'bg-rose-600 text-white shadow-lg shadow-rose-950/50 active-cat' : 'bg-[#121216] hover:bg-zinc-800 text-zinc-300 hover:text-white border border-zinc-800' }}">
                    @if($cat === 'All')
                        <i class="fa-solid fa-shapes text-[11px]"></i>
                    @elseif($cat === 'Action')
                        <i class="fa-solid fa-fire text-[11px] text-amber-400"></i>
                    @elseif($cat === 'Romance')
                        <i class="fa-solid fa-heart text-[11px] text-pink-400"></i>
                    @elseif($cat === '18+ Adult')
                        <i class="fa-solid fa-lock text-[11px] text-rose-400"></i>
                    @elseif($cat === 'Fantasy')
                        <i class="fa-solid fa-wand-magic-sparkles text-[11px] text-purple-400"></i>
                    @elseif($cat === 'Manhwa' || $cat === 'Webtoons')
                        <i class="fa-solid fa-book-open text-[11px] text-emerald-400"></i>
                    @elseif($cat === 'Drama')
                        <i class="fa-solid fa-masks-theater text-[11px] text-blue-400"></i>
                    @elseif($cat === 'Comedy')
                        <i class="fa-solid fa-face-laugh-beam text-[11px] text-yellow-400"></i>
                    @endif
                    <span>{{ $cat }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Active Search Filter Notice -->
    @if(!empty($search))
        <div class="flex items-center justify-between mb-5 pb-3 border-b border-zinc-800/80">
            <div class="text-xs sm:text-sm text-zinc-300">
                Search results for: <span class="font-extrabold text-white">"{{ $search }}"</span>
            </div>
            <a href="{{ route('comic.index') }}" class="text-xs font-bold text-rose-400 hover:text-rose-300">
                Clear Search
            </a>
        </div>
    @endif

    <!-- Single Source Error Notice if any -->
    @if(isset($error))
        <div class="rounded-xl bg-[#171113] border border-rose-900/50 p-4 mb-6 text-zinc-200">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-shield-halved text-rose-500 text-base mt-0.5"></i>
                <div>
                    <h4 class="font-bold text-xs text-white">Notice:</h4>
                    <p class="text-xs text-rose-300/90 mt-0.5">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Comics Grid (Fast, High-Readability Cards with Optimized Loading) -->
    @if(!empty($comics) && count($comics) > 0)
        <div id="comicsGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-5">
            @foreach($comics as $comic)
                @php
                    $comicCategories = array_map('strtolower', $comic['categories'] ?? ['All']);
                    $catJson = json_encode($comicCategories);
                    $coverUrl = $comic['cover'] ?? '';
                    $imgSrc = (config('comic.proxy_images') && !str_starts_with($coverUrl, 'data:'))
                        ? route('comic.proxy', ['url' => $coverUrl, 'referer' => $comic['source_url'] ?? $comic['url']])
                        : $coverUrl;
                @endphp
                <div class="comic-card group relative flex flex-col bg-[#121217] rounded-xl sm:rounded-2xl border border-zinc-800/80 overflow-hidden hover:border-rose-600/60 hover:shadow-2xl hover:shadow-rose-950/20 transition-all duration-300"
                     data-categories="{{ $catJson }}"
                     data-title="{{ strtolower($comic['title']) }}">
                    
                    @php
                        $comicSlug = $comic['slug'] ?? \Illuminate\Support\Str::slug($comic['title']);
                        $comicRoute = route('comic.show', ['slug' => $comicSlug]);
                    @endphp

                    <!-- Comic Cover Poster with Skeleton Placeholder bg -->
                    <a href="{{ $comicRoute }}" class="relative aspect-[2/3] w-full overflow-hidden bg-[#18181f] block">
                        <img src="{{ $imgSrc }}" 
                             alt="{{ $comic['title'] }}" 
                             loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}"
                             fetchpriority="{{ $loop->index < 2 ? 'high' : 'auto' }}"
                             decoding="async"
                             onerror="this.onerror=null; this.src='{{ $comic['cover'] }}';"
                             class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                        
                        <!-- Top Gradient Shadow -->
                        <div class="absolute inset-0 bg-gradient-to-t from-[#121217] via-transparent to-transparent opacity-85 group-hover:opacity-30 transition-opacity"></div>

                        <!-- Source Badge on top-right -->
                        @if(!empty($comic['source_name']))
                            <span class="absolute top-2 right-2 px-2 py-0.5 rounded bg-black/85 backdrop-blur-md text-[10px] font-extrabold text-zinc-200 border border-zinc-700/60 shadow">
                                {{ $comic['source_name'] }}
                            </span>
                        @endif

                        <!-- Age / Type Badge & Mirror Sources on top-left -->
                        <div class="absolute top-2 left-2 flex flex-col gap-1 items-start">
                            @if(!empty($comic['badge']))
                                <span class="px-2 py-0.5 rounded bg-rose-600 text-white text-[10px] font-black uppercase tracking-wider shadow">
                                    {{ $comic['badge'] }}
                                </span>
                            @endif
                            @if(!empty($comic['other_sources']) && count($comic['other_sources']) > 0)
                                <span class="px-1.5 py-0.5 rounded bg-black/85 backdrop-blur-md text-[9px] font-bold text-zinc-300 border border-zinc-700/60 shadow flex items-center gap-1" title="Also available from {{ count($comic['other_sources']) }} other source(s)">
                                    <i class="fa-solid fa-clone text-rose-500 text-[8px]"></i> +{{ count($comic['other_sources']) }} mirrors
                                </span>
                            @endif
                        </div>

                        <!-- Hover Action Pill (Desktop) -->
                        <div class="absolute inset-x-3 bottom-3 hidden sm:flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <span class="w-full py-2 bg-rose-600 text-white text-xs font-bold rounded-lg text-center shadow-lg">
                                View Chapters
                            </span>
                        </div>
                    </a>

                    <!-- Comic Info (High Contrast Typography) -->
                    <div class="p-3 sm:p-4 flex flex-col flex-1 justify-between gap-2 bg-[#121217]">
                        <div>
                            <h3 class="font-extrabold text-xs sm:text-sm text-white group-hover:text-rose-400 transition-colors line-clamp-2 leading-snug tracking-normal" title="{{ $comic['title'] }}">
                                <a href="{{ $comicRoute }}">
                                    {{ $comic['title'] }}
                                </a>
                            </h3>
                        </div>

                        <div class="flex items-center justify-between text-[11px] sm:text-xs text-zinc-400 pt-2 border-t border-zinc-800/80">
                            <span class="truncate text-zinc-400 font-semibold">
                                {{ $comic['latest_chapter'] ?? 'Comic' }}
                            </span>
                            <a href="{{ $comicRoute }}" class="text-rose-400 hover:text-rose-300 font-extrabold shrink-0 ml-1 flex items-center gap-1">
                                Read <i class="fa-solid fa-arrow-right text-[9px]"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Front Page Pagination Controls (Pitch Black Dark Theme) -->
        @if(isset($paginator) && $paginator->hasPages())
            <div class="mt-8 sm:mt-12 pt-6 border-t border-zinc-800/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                
                <!-- Page Summary Info -->
                <div class="text-xs text-zinc-400 font-medium">
                    Page <span class="font-extrabold text-white">{{ $paginator->currentPage() }}</span> 
                    of <span class="font-extrabold text-zinc-300">{{ $paginator->lastPage() }}</span>
                    <span class="text-zinc-600 mx-1.5">•</span>
                    Showing <span class="text-zinc-200 font-bold">{{ count($comics) }}</span> comics on this page
                </div>

                <!-- Pagination Buttons -->
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <!-- Previous Button -->
                    @if($paginator->onFirstPage())
                        <span class="px-3 sm:px-4 py-2 rounded-xl bg-zinc-900/50 border border-zinc-800/50 text-zinc-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" 
                           class="px-3 sm:px-4 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 hover:text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                        </a>
                    @endif

                    <!-- Sliding Page Numbers -->
                    @php
                        $cur = $paginator->currentPage();
                        $last = $paginator->lastPage();
                        $start = max(1, $cur - 2);
                        $end = min($last, $cur + 2);
                    @endphp

                    @if($start > 1)
                        <a href="{{ $paginator->url(1) }}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white text-xs font-bold flex items-center justify-center transition-colors">
                            1
                        </a>
                        @if($start > 2)
                            <span class="text-zinc-600 text-xs px-1">...</span>
                        @endif
                    @endif

                    @for($p = $start; $p <= $end; $p++)
                        @if($p == $cur)
                            <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-rose-600 text-white font-black text-xs flex items-center justify-center shadow-lg shadow-rose-950/50">
                                {{ $p }}
                            </span>
                        @else
                            <a href="{{ $paginator->url($p) }}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white text-xs font-bold flex items-center justify-center transition-colors">
                                {{ $p }}
                            </a>
                        @endif
                    @endfor

                    @if($end < $last)
                        @if($end < $last - 1)
                            <span class="text-zinc-600 text-xs px-1">...</span>
                        @endif
                        <a href="{{ $paginator->url($last) }}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white text-xs font-bold flex items-center justify-center transition-colors">
                            {{ $last }}
                        </a>
                    @endif

                    <!-- Next Button -->
                    @if($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" 
                           class="px-3 sm:px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-lg shadow-rose-950/50 flex items-center gap-1.5">
                            Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </a>
                    @else
                        <span class="px-3 sm:px-4 py-2 rounded-xl bg-zinc-900/50 border border-zinc-800/50 text-zinc-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                            Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </span>
                    @endif

                </div>
            </div>
        @endif

        <!-- No comics matching selected category state -->
        <div id="noCategoryComics" class="hidden text-center py-16 px-4 rounded-3xl bg-[#121217] border border-zinc-800/80 mt-4">
            <i class="fa-solid fa-filter text-2xl text-zinc-500 mb-2"></i>
            <h3 class="text-base font-bold text-white mb-1">No comics in this category</h3>
            <p class="text-xs text-zinc-400 mb-4">Try selecting another category or click All</p>
            <button onclick="filterByCategory('All')" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow-lg">
                Show All Comics
            </button>
        </div>

    @else
        <!-- Empty State -->
        <div class="text-center py-16 sm:py-20 px-4 rounded-3xl bg-[#121217] border border-zinc-800/80">
            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto mb-4 text-xl sm:text-2xl border border-rose-500/20">
                <i class="fa-solid fa-compass"></i>
            </div>
            <h3 class="text-base sm:text-lg font-bold text-white mb-2">No comics loaded</h3>
            <p class="text-xs text-zinc-400 max-w-md mx-auto mb-6">
                Click below to reload and scrape all active sources.
            </p>
            <a href="{{ route('comic.index', ['refresh' => 1]) }}" 
               class="inline-block px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-950/40">
                Refresh All Sources
            </a>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    function filterByCategory(category) {
        const catLower = category.toLowerCase();
        const pills = document.querySelectorAll('.category-pill');
        const cards = document.querySelectorAll('.comic-card');
        const countBadge = document.getElementById('comicsCountBadge');
        const noResults = document.getElementById('noCategoryComics');
        let visibleCount = 0;

        pills.forEach(pill => {
            if (pill.dataset.category.toLowerCase() === catLower) {
                pill.className = 'category-pill px-4 py-2 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2 shrink-0 bg-rose-600 text-white shadow-lg shadow-rose-950/50 active-cat';
            } else {
                pill.className = 'category-pill px-4 py-2 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all flex items-center gap-2 shrink-0 bg-[#121216] hover:bg-zinc-800 text-zinc-300 hover:text-white border border-zinc-800';
            }
        });

        cards.forEach(card => {
            const cardCats = JSON.parse(card.dataset.categories || '[]');
            const matches = catLower === 'all' || cardCats.includes(catLower);

            if (matches) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (countBadge) {
            countBadge.textContent = visibleCount + ' Comics';
        }

        if (noResults) {
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        const url = new URL(window.location);
        if (catLower === 'all') {
            url.searchParams.delete('category');
        } else {
            url.searchParams.set('category', category);
        }
        window.history.replaceState({}, '', url);
    }
</script>
@endpush
