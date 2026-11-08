@extends('layouts.app')

@section('title', ($comic['title'] ?? 'Comic Details') . ' - DarkComic')

@section('content')
<div class="relative">
    <!-- Backdrop banner with dark blurred poster image -->
    <div class="absolute inset-x-0 top-0 h-72 sm:h-96 overflow-hidden pointer-events-none opacity-15 filter blur-3xl -z-10">
        @php
            $coverUrl = $comic['cover'] ?? '';
            $backdropSrc = (config('comic.proxy_images') && !str_starts_with($coverUrl, 'data:'))
                ? route('comic.proxy', ['url' => $coverUrl, 'referer' => $comic['url']])
                : $coverUrl;
        @endphp
        <img src="{{ $backdropSrc }}" alt="" class="w-full h-full object-cover">
    </div>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
        
        <!-- Breadcrumbs (Responsive truncation) -->
        <nav class="flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs text-zinc-400 mb-4 sm:mb-6">
            <a href="{{ route('comic.index') }}" class="hover:text-white transition-colors flex items-center gap-1">
                <i class="fa-solid fa-house text-zinc-500"></i> <span class="hidden sm:inline">Home</span>
            </a>
            <i class="fa-solid fa-chevron-right text-[9px] text-zinc-600"></i>
            <span class="text-rose-500 font-bold truncate max-w-[200px] sm:max-w-md">{{ $comic['title'] }}</span>
        </nav>

        <!-- Comic Header Card (Responsive layout: Stacked on mobile, side-by-side on desktop) -->
        <div class="bg-[#101014] rounded-2xl sm:rounded-3xl border border-zinc-800/90 p-4 sm:p-7 shadow-2xl mb-6 sm:mb-10">
            <div class="flex flex-col sm:flex-row gap-5 sm:gap-8 items-center sm:items-start">
                
                <!-- Cover Poster (Responsive: Compact width on mobile, full poster on desktop) -->
                <div class="w-44 sm:w-60 md:w-64 shrink-0 mx-auto sm:mx-0">
                    <div class="aspect-[2/3] rounded-xl sm:rounded-2xl overflow-hidden bg-black border border-zinc-800 shadow-2xl relative group">
                        <img src="{{ $backdropSrc }}" 
                             alt="{{ $comic['title'] }}" 
                             onerror="this.onerror=null; this.src='{{ $comic['cover'] }}';"
                             class="w-full h-full object-cover">
                        
                        @if(!empty($comic['status']))
                            <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md bg-zinc-900/90 backdrop-blur-md border border-zinc-700 text-white text-[9px] sm:text-[10px] font-black uppercase tracking-wider shadow">
                                {{ $comic['status'] }}
                            </span>
                        @endif
                    </div>

                    <!-- Scraped Source Pill -->
                    <div class="mt-3 p-2.5 rounded-xl bg-zinc-950 border border-zinc-800/80 text-[10px] sm:text-[11px] text-zinc-400">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="font-bold text-zinc-300">Source Host</span>
                            <span class="text-rose-400 font-mono font-bold">Active</span>
                        </div>
                        <a href="{{ $comic['url'] }}" target="_blank" rel="noopener noreferrer" 
                           class="text-zinc-400 hover:text-rose-400 truncate block font-mono text-[10px] transition-colors" title="{{ $comic['url'] }}">
                            {{ parse_url($comic['url'], PHP_URL_HOST) }} <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                        </a>
                    </div>
                </div>

                <!-- Comic Details -->
                <div class="flex-1 flex flex-col justify-between w-full">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2 sm:mb-3">
                            <span class="px-2.5 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[11px] sm:text-xs font-bold">
                                {{ count($comic['chapters'] ?? []) }} Chapters Available
                            </span>
                        </div>

                        <h1 class="text-xl sm:text-3xl md:text-4xl font-black text-white tracking-tight mb-2 sm:mb-3 leading-tight">
                            {{ $comic['title'] }}
                        </h1>

                        @if(!empty($comic['author']))
                            <p class="text-xs text-zinc-400 mb-3 sm:mb-4">
                                <span class="text-zinc-500 font-medium">Author / Artist:</span> 
                                <span class="text-zinc-200 font-semibold">{{ $comic['author'] }}</span>
                            </p>
                        @endif

                        <!-- Genres Tags -->
                        @if(!empty($comic['genres']))
                            <div class="flex flex-wrap gap-1.5 mb-4 sm:mb-5">
                                @foreach($comic['genres'] as $genre)
                                    <span class="px-2.5 py-0.5 sm:px-3 sm:py-1 rounded-lg bg-zinc-900 border border-zinc-800 text-zinc-300 text-[11px] sm:text-xs font-semibold">
                                        {{ $genre }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Synopsis -->
                        @if(!empty($comic['description']))
                            <div class="space-y-1 mb-5 sm:mb-6">
                                <h3 class="text-[10px] sm:text-xs uppercase tracking-wider font-extrabold text-zinc-400">Synopsis</h3>
                                <p class="text-xs sm:text-sm text-zinc-300 leading-relaxed max-h-36 sm:max-h-48 overflow-y-auto pr-2">
                                    {{ $comic['description'] }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Reading CTAs (Full width on mobile, inline on desktop) -->
                    <div class="pt-4 sm:pt-6 border-t border-zinc-800/80 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 sm:gap-3">
                        @if(!empty($comic['chapters']) && count($comic['chapters']) > 0)
                            @php
                                $cSlug = $comic['slug'] ?? \Illuminate\Support\Str::slug($comic['title']);
                                $firstChapter = reset($comic['chapters']);
                                $latestChapter = end($comic['chapters']);
                                $firstSlug = $firstChapter['slug'] ?? 'chapter-1';
                                $latestSlug = $latestChapter['slug'] ?? ('chapter-' . count($comic['chapters']));
                            @endphp
                            
                            <a href="{{ route('comic.read', ['comicSlug' => $cSlug, 'chapterSlug' => $firstSlug]) }}" 
                               class="w-full sm:w-auto px-5 sm:px-6 py-3 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs sm:text-sm font-bold transition-all shadow-lg shadow-rose-950/50 flex items-center justify-center gap-2">
                                <i class="fa-solid fa-book-open"></i>
                                Start Reading ({{ $firstChapter['title'] ?? 'Chapter #1' }})
                            </a>

                            @if($latestChapter['url'] !== $firstChapter['url'])
                                <a href="{{ route('comic.read', ['comicSlug' => $cSlug, 'chapterSlug' => $latestSlug]) }}" 
                                   class="w-full sm:w-auto px-4 sm:px-5 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 hover:text-white rounded-xl text-xs sm:text-sm font-bold transition-colors flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-bolt text-rose-500"></i>
                                    Latest ({{ $latestChapter['title'] ?? 'Latest' }})
                                </a>
                            @endif
                        @endif

                        <a href="{{ route('comic.show', ['slug' => $comic['slug'] ?? \Illuminate\Support\Str::slug($comic['title']), 'refresh' => 1]) }}" 
                           class="w-full sm:w-auto px-4 py-3 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-400 hover:text-zinc-200 rounded-xl text-xs sm:text-sm font-semibold transition-colors flex items-center justify-center gap-1.5"
                           title="Refresh chapter list">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Refresh Chapters</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chapters List Section (Responsive Grid & Search) -->
        <div class="bg-[#101014] rounded-2xl sm:rounded-3xl border border-zinc-800/90 p-4 sm:p-7">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 sm:gap-4 mb-5 pb-4 border-b border-zinc-800">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-rose-500/10 text-rose-500 border border-rose-500/20 flex items-center justify-center font-bold text-xs sm:text-sm shrink-0">
                        <i class="fa-solid fa-list-ol"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-xl font-bold text-white">Serialized Chapters</h2>
                            @if(!empty($comic['chapters']))
                                <span class="px-2 py-0.5 rounded-md bg-rose-500/10 text-rose-400 border border-rose-500/20 font-mono text-[11px] font-bold">
                                    {{ count($comic['chapters']) }} Chapters
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] sm:text-xs text-zinc-400">Chapters ordered sequentially with serial tags. Zero external redirects.</p>
                    </div>
                </div>

                <!-- Chapter Filter & Sort Controls -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <div class="relative flex-1 md:w-48">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-500 text-xs"></i>
                        <input type="text" id="chapterSearchInput" placeholder="Filter chapter or #..." 
                            class="w-full bg-zinc-900 border border-zinc-800 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-rose-600">
                    </div>
                    <button type="button" id="sortChaptersBtn" 
                        class="px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs text-zinc-300 hover:text-white rounded-xl flex items-center gap-1.5 font-semibold transition-colors shrink-0">
                        <i id="sortIcon" class="fa-solid fa-arrow-down-1-9 text-rose-500"></i>
                        <span id="sortOrderLabel">#1 First</span>
                    </button>
                </div>
            </div>

            <!-- Chapters Grid: 1 col on mobile, 2 on tablet, 3-4 on desktop -->
            @if(!empty($comic['chapters']) && count($comic['chapters']) > 0)
                <div id="chaptersContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-2.5">
                    @foreach($comic['chapters'] as $chapter)
                        @php
                            $cSlug = $comic['slug'] ?? \Illuminate\Support\Str::slug($comic['title']);
                            $chSlug = $chapter['slug'] ?? ('chapter-' . ($chapter['serial'] ?? $loop->iteration));
                        @endphp
                        <a href="{{ route('comic.read', ['comicSlug' => $cSlug, 'chapterSlug' => $chSlug]) }}" 
                           class="chapter-item group p-2.5 sm:p-3 rounded-xl bg-zinc-900/60 hover:bg-rose-950/20 border border-zinc-800/80 hover:border-rose-600/50 flex items-center justify-between transition-all duration-150 min-h-[50px]"
                           data-serial="{{ $chapter['serial'] ?? $loop->iteration }}"
                           data-title="{{ strtolower($chapter['title']) }}">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <!-- Serial Badge -->
                                <div class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-800 group-hover:border-rose-600/40 group-hover:bg-rose-600 text-zinc-400 group-hover:text-white flex items-center justify-center font-mono font-bold text-xs shrink-0 transition-colors shadow-sm">
                                    #{{ $chapter['serial'] ?? $loop->iteration }}
                                </div>
                                <div class="min-w-0">
                                    <span class="chapter-title block text-xs font-bold text-zinc-200 group-hover:text-rose-400 truncate transition-colors">
                                        {{ $chapter['title'] }}
                                    </span>
                                    @if(!empty($chapter['date']))
                                        <span class="text-[10px] text-zinc-500 block truncate">
                                            <i class="fa-regular fa-clock text-[9px] mr-1"></i>{{ $chapter['date'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs text-zinc-500 group-hover:text-rose-400 group-hover:translate-x-0.5 transition-all shrink-0 ml-2">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-zinc-400">
                    <i class="fa-solid fa-circle-exclamation text-2xl text-zinc-600 mb-2"></i>
                    <p class="text-sm">No chapters found for this comic.</p>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    const searchInput = document.getElementById('chapterSearchInput');
    const container = document.getElementById('chaptersContainer');
    const sortBtn = document.getElementById('sortChaptersBtn');
    const sortIcon = document.getElementById('sortIcon');
    const sortLabel = document.getElementById('sortOrderLabel');
    let isReversed = false;

    if (searchInput && container) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const items = container.querySelectorAll('.chapter-item');
            items.forEach(item => {
                const title = (item.getAttribute('data-title') || '').toLowerCase();
                const serial = item.getAttribute('data-serial') || '';
                if (title.includes(query) || serial === query || ('#' + serial) === query) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    if (sortBtn && container) {
        sortBtn.addEventListener('click', () => {
            const items = Array.from(container.children);
            items.reverse();
            items.forEach(item => container.appendChild(item));
            isReversed = !isReversed;
            if (isReversed) {
                sortLabel.textContent = '#Latest First';
                sortIcon.className = 'fa-solid fa-arrow-down-9-1 text-rose-500';
            } else {
                sortLabel.textContent = '#1 First';
                sortIcon.className = 'fa-solid fa-arrow-down-1-9 text-rose-500';
            }
        });
    }
</script>
@endpush
