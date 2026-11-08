<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>{{ $chapter['title'] ?? 'Reading Chapter' }} - DarkComic Reader</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        accent: {
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Font Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #050507;
            color: #fafafa;
            -webkit-tap-highlight-color: transparent;
        }
        /* Custom pitch dark scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #050507;
        }
        ::-webkit-scrollbar-thumb {
            background: #27272a;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #e11d48;
        }
        .toolbar-transition {
            transition: transform 0.25s ease-in-out;
        }
    </style>
</head>
<body class="bg-[#050507] text-zinc-100 min-h-screen flex flex-col selection:bg-rose-600 selection:text-white">

    <!-- Top Reading Progress Bar (Ruby Red) -->
    <div id="readingProgressBar" class="fixed top-0 left-0 h-1 bg-gradient-to-r from-rose-600 to-red-500 z-50 transition-all duration-150" style="width: 0%;"></div>

    <!-- Sticky Reader Header Bar (Responsive: Compact on mobile) -->
    <header id="readerHeader" class="sticky top-0 z-40 bg-[#0c0c10]/95 backdrop-blur-md border-b border-zinc-800/90 shadow-2xl toolbar-transition">
        <div class="max-w-7xl mx-auto px-2.5 sm:px-4 py-2 sm:py-2.5 flex items-center justify-between gap-2 sm:gap-3">
            
            <!-- Left: Back button & Titles -->
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                @if(!empty($comicSlug))
                    <a href="{{ route('comic.show', ['slug' => $comicSlug]) }}" 
                       class="px-2 sm:px-2.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-bold text-zinc-300 hover:text-white transition-colors flex items-center gap-1.5 shrink-0"
                       title="Back to Comic Details">
                        <i class="fa-solid fa-arrow-left text-[11px]"></i>
                        <span class="hidden sm:inline">Info</span>
                    </a>
                @elseif(!empty($comicUrl))
                    <a href="{{ route('comic.show', ['url' => $comicUrl]) }}" 
                       class="px-2 sm:px-2.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-bold text-zinc-300 hover:text-white transition-colors flex items-center gap-1.5 shrink-0"
                       title="Back to Comic Details">
                        <i class="fa-solid fa-arrow-left text-[11px]"></i>
                        <span class="hidden sm:inline">Info</span>
                    </a>
                @else
                    <a href="{{ route('comic.index') }}" 
                       class="px-2 sm:px-2.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-bold text-zinc-300 hover:text-white transition-colors flex items-center gap-1.5 shrink-0">
                        <i class="fa-solid fa-house text-[11px]"></i>
                    </a>
                @endif

                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 min-w-0">
                        @if(!empty($chapter['serial']))
                            <span class="px-1.5 py-0.5 rounded bg-rose-600/20 border border-rose-500/30 text-rose-400 font-mono text-[10px] font-black shrink-0">
                                #{{ $chapter['serial'] }}
                            </span>
                        @endif
                        <h1 class="text-xs sm:text-sm font-black text-white truncate max-w-[130px] sm:max-w-xs md:max-w-md">
                            {{ $chapter['title'] }}
                        </h1>
                    </div>
                    @if(!empty($chapter['comic_title']))
                        <p class="text-[9px] sm:text-[10px] text-zinc-400 truncate">{{ $chapter['comic_title'] }}</p>
                    @endif
                </div>
            </div>

            <!-- Center: Chapter Selector Dropdown (Responsive: Hidden on small mobile, visible on tablet+) -->
            @if(!empty($chapter['sibling_chapters']) && count($chapter['sibling_chapters']) > 0)
                <div class="hidden md:flex items-center gap-1">
                    @if(!empty($chapter['prev_url']))
                        <a href="{{ $chapter['prev_clean_url'] ?? route('comic.read', ['url' => $chapter['prev_url'], 'comic' => $comicUrl]) }}" 
                           class="w-8 h-8 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-center text-zinc-300 hover:text-white text-xs transition-colors"
                           title="Previous Chapter: {{ $chapter['prev_title'] ?? '' }}">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    @endif

                    <select onchange="if(this.value) window.location.href=this.value" 
                            class="bg-zinc-900 border border-zinc-800 text-zinc-200 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-rose-600 max-w-[210px] truncate">
                        @foreach($chapter['sibling_chapters'] as $ch)
                            <option value="{{ $ch['clean_url'] ?? route('comic.read', ['url' => $ch['url'], 'comic' => $comicUrl]) }}" 
                                    {{ ($ch['selected'] ?? false) || ($ch['url'] === $chapterUrl) ? 'selected' : '' }}>
                                #{{ $ch['serial'] ?? $loop->iteration }} - {{ $ch['title'] }}
                            </option>
                        @endforeach
                    </select>

                    @if(!empty($chapter['next_url']))
                        <a href="{{ $chapter['next_clean_url'] ?? route('comic.read', ['url' => $chapter['next_url'], 'comic' => $comicUrl]) }}" 
                           class="w-8 h-8 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-center text-zinc-300 hover:text-white text-xs transition-colors"
                           title="Next Chapter: {{ $chapter['next_title'] ?? '' }}">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    @endif
                </div>
            @endif

            <!-- Right Controls: Mode Switch, Width, Fullscreen -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                
                <!-- Reading Mode Toggle (Vertical Scroll vs Single Page) -->
                <div class="flex bg-zinc-900 border border-zinc-800 rounded-lg p-0.5 text-xs">
                    <button type="button" id="modeScrollBtn" onclick="setReadingMode('scroll')" 
                        class="px-2.5 py-1 rounded-md font-bold transition-all bg-rose-600 text-white flex items-center gap-1.5 shadow"
                        title="Continuous Webtoon Scroll Mode">
                        <i class="fa-solid fa-arrows-up-down text-[10px]"></i>
                        <span class="text-[11px] font-bold">Scroll</span>
                    </button>
                    <button type="button" id="modeSingleBtn" onclick="setReadingMode('single')" 
                        class="px-2.5 py-1 rounded-md font-bold transition-all text-zinc-400 hover:text-white flex items-center gap-1.5"
                        title="Single Page Mode">
                        <i class="fa-solid fa-book-open text-[10px]"></i>
                        <span class="text-[11px] font-bold">Paged</span>
                    </button>
                </div>

                <!-- Width Sizer Dropdown (Desktop) -->
                <select id="widthSelector" onchange="setImageWidth(this.value)" 
                    class="bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:border-rose-600 hidden lg:block">
                    <option value="max-w-2xl">Narrow (672px)</option>
                    <option value="max-w-4xl" selected>Medium (896px)</option>
                    <option value="max-w-6xl">Wide (1152px)</option>
                    <option value="max-w-full">Full Width</option>
                </select>

                <!-- Fullscreen Toggle -->
                <button type="button" onclick="toggleFullscreen()" 
                    class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-center text-zinc-300 hover:text-white text-xs transition-colors"
                    title="Toggle Fullscreen">
                    <i id="fullscreenIcon" class="fa-solid fa-expand"></i>
                </button>

                <!-- Refresh Chapter Cache -->
                <a href="{{ route('comic.read', ['url' => $chapterUrl, 'comic' => $comicUrl, 'refresh' => 1]) }}" 
                   class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex items-center justify-center text-zinc-300 hover:text-white text-xs transition-colors"
                   title="Re-scrape Images for this chapter">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </a>
            </div>

        </div>
    </header>

    <!-- Reading Container -->
    <main class="flex-1 flex flex-col items-center justify-center py-2 sm:py-4 px-0 sm:px-4 w-full">
        
        @if(!empty($chapter['images']) && count($chapter['images']) > 0)
            
            <!-- Continuous Webtoon Scroll View -->
            <div id="scrollView" class="w-full max-w-4xl mx-auto flex flex-col items-center transition-all duration-200">
                
                <!-- Notice Banner -->
                <div class="w-full text-center py-2 px-3 mb-3 sm:mb-4 text-xs text-zinc-400 bg-[#101014] rounded-xl border border-zinc-800 max-w-[95%] sm:max-w-full">
                    <span class="text-rose-400 font-bold">{{ count($chapter['images']) }} Pages</span> Loaded &bull; In-Website Reading (Zero Redirects)
                </div>

                @foreach($chapter['images'] as $index => $imageUrl)
                    @php
                        $pageNumber = $index + 1;
                        $proxyUrl = route('comic.proxy', ['url' => $imageUrl, 'referer' => $chapterUrl]);
                        $finalSrc = $useProxy ? $proxyUrl : $imageUrl;
                    @endphp
                    <div class="relative w-full mb-2 sm:mb-3 flex flex-col items-center comic-page-wrapper" data-page="{{ $pageNumber }}">
                        <!-- Page Number Tag -->
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded bg-black/80 backdrop-blur-md text-[9px] sm:text-[10px] text-zinc-400 font-mono z-10 opacity-70 hover:opacity-100 transition-opacity">
                            {{ $pageNumber }} / {{ count($chapter['images']) }}
                        </span>

                        <!-- Page Image (High-Performance Zero-Loss Pipeline) -->
                        <img src="{{ $finalSrc }}" 
                             alt="Page {{ $pageNumber }}" 
                             loading="{{ $pageNumber <= 2 ? 'eager' : 'lazy' }}"
                             fetchpriority="{{ $pageNumber <= 1 ? 'high' : 'auto' }}"
                             decoding="async"
                             data-proxy-src="{{ $proxyUrl }}"
                             data-original-src="{{ $imageUrl }}"
                             onerror="handleImageError(this)"
                             class="comic-page-img w-full h-auto object-contain shadow-2xl transition-opacity duration-300 min-h-[260px] bg-[#0c0c10] sm:rounded-lg">
                        
                        <!-- Image Error Fallback Card -->
                        <div class="image-error-card hidden w-full p-6 my-2 rounded-xl bg-zinc-900 border border-rose-900/40 text-center">
                            <i class="fa-solid fa-image text-2xl sm:text-3xl text-rose-500 mb-2"></i>
                            <p class="text-xs text-rose-300 font-semibold mb-2">Page {{ $pageNumber }} failed to load.</p>
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="retryImage(this)" class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-xs rounded-lg text-white font-bold">
                                    Retry Loading
                                </button>
                                <button onclick="switchImageSource(this)" class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 text-xs rounded-lg text-zinc-300 font-medium">
                                    Switch Source
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Single Page Reader View (Responsive) -->
            <div id="singleView" class="hidden w-full max-w-4xl mx-auto flex flex-col items-center px-2">
                <!-- Single Page Navigation Toolbar (Top) -->
                <div class="w-full flex items-center justify-between bg-zinc-900/90 backdrop-blur-md border border-zinc-800 px-3 sm:px-4 py-2 rounded-xl mb-3 text-xs shadow-lg">
                    <button type="button" onclick="changeSinglePage(-1)" class="px-3 sm:px-4 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white font-bold flex items-center gap-1.5 min-h-[36px] transition-colors">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        <span>Prev</span>
                    </button>
                    <div class="font-mono font-bold text-zinc-200 text-xs sm:text-sm flex items-center gap-1.5">
                        <i class="fa-solid fa-file-image text-rose-500 text-[11px]"></i>
                        <span>Page</span>
                        <span class="singlePageNum text-rose-500 font-black text-sm sm:text-base">1</span>
                        <span class="text-zinc-600">/</span>
                        <span class="text-zinc-400 font-semibold">{{ count($chapter['images']) }}</span>
                    </div>
                    <button type="button" onclick="changeSinglePage(1)" class="px-3 sm:px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold flex items-center gap-1.5 min-h-[36px] transition-all shadow-md shadow-rose-950/40">
                        <span>Next</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>

                <!-- Single Active Image Frame with Click Zones -->
                <div class="relative w-full flex items-center justify-center cursor-pointer select-none min-h-[380px] bg-[#0c0c10] rounded-xl overflow-hidden shadow-2xl border border-zinc-800/80" onclick="handleSingleImageClick(event)">
                    <!-- Loading Spinner -->
                    <div id="singlePageSpinner" class="hidden absolute inset-0 flex items-center justify-center bg-black/60 z-20 pointer-events-none">
                        <div class="flex flex-col items-center gap-2">
                            <i class="fa-solid fa-spinner fa-spin text-3xl text-rose-500"></i>
                            <span class="text-xs text-zinc-400 font-medium">Loading Page...</span>
                        </div>
                    </div>

                    <!-- Left / Right Click Guides on Hover -->
                    <div class="absolute inset-y-0 left-0 w-1/4 flex items-center justify-start pl-3 opacity-0 hover:opacity-100 transition-opacity bg-gradient-to-r from-black/50 to-transparent pointer-events-none z-10">
                        <span class="w-8 h-8 rounded-full bg-black/70 border border-zinc-700 flex items-center justify-center text-white text-xs shadow">
                            <i class="fa-solid fa-chevron-left"></i>
                        </span>
                    </div>
                    <div class="absolute inset-y-0 right-0 w-1/4 flex items-center justify-end pr-3 opacity-0 hover:opacity-100 transition-opacity bg-gradient-to-l from-black/50 to-transparent pointer-events-none z-10">
                        <span class="w-8 h-8 rounded-full bg-rose-600 flex items-center justify-center text-white text-xs shadow">
                            <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    </div>

                    <img id="singlePageImage" src="" alt="Comic Page" 
                         onload="document.getElementById('singlePageSpinner').classList.add('hidden')"
                         onerror="handleSingleImageError(this)"
                         class="max-h-[85vh] w-auto max-w-full object-contain transition-opacity duration-200">
                </div>

                <!-- Single Page Navigation Toolbar (Bottom) -->
                <div class="w-full flex items-center justify-between bg-zinc-900/90 backdrop-blur-md border border-zinc-800 px-3 sm:px-4 py-2 rounded-xl mt-3 text-xs shadow-lg">
                    <button type="button" onclick="changeSinglePage(-1)" class="px-3 sm:px-4 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-white font-bold flex items-center gap-1.5 min-h-[36px] transition-colors">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        <span>Prev</span>
                    </button>
                    <div class="font-mono font-bold text-zinc-300 text-xs">
                        Page <span class="singlePageNum text-rose-500 font-bold">1</span> / {{ count($chapter['images']) }}
                    </div>
                    <button type="button" onclick="changeSinglePage(1)" class="px-3 sm:px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold flex items-center gap-1.5 min-h-[36px] transition-all shadow-md shadow-rose-950/40">
                        <span>Next</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>

            <!-- Bottom Chapter Navigation Bar (Fully Responsive) -->
            <div class="w-full max-w-4xl mx-auto mt-6 sm:mt-10 p-4 sm:p-6 rounded-2xl bg-[#101014] border border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-center sm:text-left">
                    <h4 class="text-xs sm:text-sm font-bold text-white mb-0.5">Finished Reading?</h4>
                    <p class="text-[11px] sm:text-xs text-zinc-400">Continue to the next chapter or pick another</p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2.5 sm:gap-3 w-full sm:w-auto">
                    @if(!empty($chapter['prev_url']))
                        <a href="{{ $chapter['prev_clean_url'] ?? route('comic.read', ['url' => $chapter['prev_url'], 'comic' => $comicUrl]) }}" 
                           class="flex-1 sm:flex-none px-4 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-bold text-white transition-colors flex items-center justify-center gap-2 min-h-[42px]"
                           title="Previous Chapter: {{ $chapter['prev_title'] ?? '' }}">
                            <i class="fa-solid fa-arrow-left text-[10px]"></i>
                            <span>Previous{{ !empty($chapter['prev_serial']) ? ' (#' . $chapter['prev_serial'] . ')' : '' }}</span>
                        </a>
                    @endif

                    @if(!empty($chapter['next_url']))
                        <a href="{{ $chapter['next_clean_url'] ?? route('comic.read', ['url' => $chapter['next_url'], 'comic' => $comicUrl]) }}" 
                           class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white transition-all shadow-lg shadow-rose-950/40 flex items-center justify-center gap-2 min-h-[42px]"
                           title="Next Chapter: {{ $chapter['next_title'] ?? '' }}">
                            <span>Next{{ !empty($chapter['next_serial']) ? ' (#' . $chapter['next_serial'] . ')' : '' }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    @endif

                    @if(!empty($comicSlug))
                        <a href="{{ route('comic.show', ['slug' => $comicSlug]) }}" 
                           class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-xs font-bold text-zinc-300 hover:text-white transition-colors text-center min-h-[42px] flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-list-ol text-[11px] text-rose-500"></i>
                            <span>All Chapters</span>
                        </a>
                    @elseif(!empty($comicUrl))
                        <a href="{{ route('comic.show', ['url' => $comicUrl]) }}" 
                           class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-xs font-bold text-zinc-300 hover:text-white transition-colors text-center min-h-[42px] flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-list-ol text-[11px] text-rose-500"></i>
                            <span>All Chapters</span>
                        </a>
                    @endif
                </div>
            </div>

        @else
            <!-- No Images Found Error Display -->
            <div class="text-center py-16 px-4 max-w-lg bg-[#101014] rounded-3xl border border-zinc-800 mx-3">
                <div class="w-14 h-14 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center mx-auto mb-4 text-2xl border border-rose-500/20">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-white mb-2">No comic pages detected</h3>
                <p class="text-xs text-zinc-400 mb-6 leading-relaxed">
                    The scraper could not locate page image elements for this chapter.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <a href="{{ route('comic.read', ['url' => $chapterUrl, 'comic' => $comicUrl, 'refresh' => 1]) }}" 
                       class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg">
                        Retry Scraping
                    </a>
                    @if(!empty($comicUrl))
                        <a href="{{ route('comic.show', ['url' => $comicUrl]) }}" 
                           class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-zinc-300 rounded-xl text-xs font-semibold">
                            Back to Chapters
                        </a>
                    @endif
                </div>
            </div>
        @endif

    </main>

    <!-- Floating Back to Top Button -->
    <button id="backToTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
        class="fixed bottom-4 sm:bottom-6 right-4 sm:right-6 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-rose-600 hover:bg-rose-500 text-white shadow-xl flex items-center justify-center text-xs sm:text-sm opacity-0 pointer-events-none transition-all duration-300 z-30">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script>
        const chapterImages = @json($chapter['images'] ?? []);
        let currentSingleIndex = 0;
        let readingMode = 'scroll';

        window.addEventListener('scroll', () => {
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
            const progressBar = document.getElementById('readingProgressBar');
            if (progressBar && readingMode === 'scroll') {
                progressBar.style.width = progress + '%';
            }

            const backToTop = document.getElementById('backToTopBtn');
            if (backToTop) {
                if (scrollTop > 400) {
                    backToTop.classList.remove('opacity-0', 'pointer-events-none');
                } else {
                    backToTop.classList.add('opacity-0', 'pointer-events-none');
                }
            }
        });

        function setReadingMode(mode) {
            readingMode = mode;
            const scrollView = document.getElementById('scrollView');
            const singleView = document.getElementById('singleView');
            const modeScrollBtn = document.getElementById('modeScrollBtn');
            const modeSingleBtn = document.getElementById('modeSingleBtn');

            if (mode === 'scroll') {
                scrollView.classList.remove('hidden');
                singleView.classList.add('hidden');
                modeScrollBtn.className = 'px-2.5 py-1 rounded-md font-bold transition-all bg-rose-600 text-white flex items-center gap-1.5 shadow';
                modeSingleBtn.className = 'px-2.5 py-1 rounded-md font-bold transition-all text-zinc-400 hover:text-white flex items-center gap-1.5';

                // Scroll to the active page from paged mode
                const targetWrapper = document.querySelector('.comic-page-wrapper[data-page="' + (currentSingleIndex + 1) + '"]');
                if (targetWrapper) {
                    targetWrapper.scrollIntoView({ behavior: 'smooth' });
                }
            } else {
                // Switching from scroll to paged: detect which page was visible in scroll mode
                const wrappers = document.querySelectorAll('.comic-page-wrapper');
                let foundPage = 0;
                for (let i = 0; i < wrappers.length; i++) {
                    const rect = wrappers[i].getBoundingClientRect();
                    if (rect.bottom > 150) {
                        foundPage = i;
                        break;
                    }
                }
                currentSingleIndex = foundPage;

                scrollView.classList.add('hidden');
                singleView.classList.remove('hidden');
                modeSingleBtn.className = 'px-2.5 py-1 rounded-md font-bold transition-all bg-rose-600 text-white flex items-center gap-1.5 shadow';
                modeScrollBtn.className = 'px-2.5 py-1 rounded-md font-bold transition-all text-zinc-400 hover:text-white flex items-center gap-1.5';
                showSinglePage(currentSingleIndex);
            }
        }

        function showSinglePage(index) {
            if (!chapterImages || chapterImages.length === 0) return;
            if (index < 0) index = 0;
            if (index >= chapterImages.length) index = chapterImages.length - 1;
            
            currentSingleIndex = index;
            document.querySelectorAll('.singlePageNum').forEach(el => el.textContent = index + 1);
            
            const spinner = document.getElementById('singlePageSpinner');
            if (spinner) spinner.classList.remove('hidden');

            const img = document.getElementById('singlePageImage');
            const rawUrl = chapterImages[index];
            const useProxy = {{ $useProxy ? 'true' : 'false' }};
            const proxyBase = "{{ route('comic.proxy', [], false) }}";
            const proxyUrl = proxyBase + "?url=" + encodeURIComponent(rawUrl) + "&referer=" + encodeURIComponent("{{ $chapterUrl }}");
            
            img.dataset.proxySrc = proxyUrl;
            img.dataset.originalSrc = rawUrl;
            img.src = useProxy ? proxyUrl : rawUrl;

            const progressBar = document.getElementById('readingProgressBar');
            if (progressBar) {
                const percent = ((index + 1) / chapterImages.length) * 100;
                progressBar.style.width = percent + '%';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function handleSingleImageError(img) {
            const spinner = document.getElementById('singlePageSpinner');
            if (spinner) spinner.classList.add('hidden');

            const origSrc = img.dataset.originalSrc;
            if (origSrc && img.src !== origSrc) {
                img.src = origSrc;
                return;
            }
        }

        function changeSinglePage(delta) {
            const newIndex = currentSingleIndex + delta;
            if (newIndex < 0) {
                @if(!empty($chapter['prev_url']))
                    if (confirm('You are on Page 1. Continue to Previous Chapter?')) {
                        window.location.href = "{{ $chapter['prev_clean_url'] ?? route('comic.read', ['url' => $chapter['prev_url'], 'comic' => $comicUrl]) }}";
                    }
                @endif
                return;
            }
            if (newIndex >= chapterImages.length) {
                @if(!empty($chapter['next_url']))
                    if (confirm('You finished this chapter! Continue to Next Chapter?')) {
                        window.location.href = "{{ $chapter['next_clean_url'] ?? route('comic.read', ['url' => $chapter['next_url'], 'comic' => $comicUrl]) }}";
                    }
                @endif
                return;
            }
            showSinglePage(newIndex);
        }

        function handleSingleImageClick(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const clickX = event.clientX - rect.left;
            if (clickX < rect.width / 2) {
                changeSinglePage(-1);
            } else {
                changeSinglePage(1);
            }
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                if (readingMode === 'single') changeSinglePage(1);
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                if (readingMode === 'single') changeSinglePage(-1);
            } else if (e.key === 'f' || e.key === 'F') {
                toggleFullscreen();
            }
        });

        function setImageWidth(widthClass) {
            const scrollView = document.getElementById('scrollView');
            const singleView = document.getElementById('singleView');
            
            const classes = ['max-w-2xl', 'max-w-4xl', 'max-w-6xl', 'max-w-full'];
            classes.forEach(c => {
                scrollView.classList.remove(c);
                singleView.classList.remove(c);
            });

            scrollView.classList.add(widthClass);
            singleView.classList.add(widthClass);
        }

        function toggleFullscreen() {
            const icon = document.getElementById('fullscreenIcon');
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {});
                icon.className = 'fa-solid fa-compress';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    icon.className = 'fa-solid fa-expand';
                }
            }
        }

        function handleImageError(img) {
            const wrapper = img.closest('.comic-page-wrapper');
            if (!wrapper) return;

            // If the proxy failed, seamlessly switch to original source without error
            const origSrc = img.dataset.originalSrc;
            if (origSrc && img.src !== origSrc) {
                img.src = origSrc;
                return;
            }

            img.style.display = 'none';
            const errorCard = wrapper.querySelector('.image-error-card');
            if (errorCard) errorCard.classList.remove('hidden');
        }

        function retryImage(button) {
            const wrapper = button.closest('.comic-page-wrapper');
            if (!wrapper) return;
            const img = wrapper.querySelector('img');
            const errorCard = wrapper.querySelector('.image-error-card');
            if (img && errorCard) {
                errorCard.classList.add('hidden');
                img.style.display = '';
                const currentSrc = img.src;
                img.src = '';
                img.src = currentSrc;
            }
        }

        function switchImageSource(button) {
            const wrapper = button.closest('.comic-page-wrapper');
            if (!wrapper) return;
            const img = wrapper.querySelector('img');
            const errorCard = wrapper.querySelector('.image-error-card');
            if (img && errorCard) {
                errorCard.classList.add('hidden');
                img.style.display = '';
                if (img.dataset.originalSrc && img.src === img.dataset.proxySrc) {
                    img.src = img.dataset.originalSrc;
                } else if (img.dataset.proxySrc) {
                    img.src = img.dataset.proxySrc;
                }
            }
        }
    </script>
</body>
</html>
