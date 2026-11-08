<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DarkComic - Scraped Comic Reader')</title>
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
                        },
                        pure: {
                            black: '#000000',
                            950: '#09090b',
                            900: '#121215',
                            850: '#18181b',
                            800: '#27272a',
                            700: '#3f3f46',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Font Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #09090b;
            color: #f4f4f5;
            letter-spacing: -0.01em;
        }
        /* Custom deep dark sleek scrollbar */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }
        ::-webkit-scrollbar-track {
            background: #09090b;
        }
        ::-webkit-scrollbar-thumb {
            background: #27272a;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #e11d48;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-[#09090b] text-zinc-100 min-h-screen flex flex-col selection:bg-rose-600 selection:text-white">

    <!-- Top Loading Progress Indicator (Instant Visual Feedback) -->
    <div id="globalNavProgress" class="fixed top-0 left-0 h-1 bg-gradient-to-r from-rose-600 via-rose-500 to-red-400 z-50 transition-all duration-300 opacity-0 pointer-events-none" style="width: 0%;"></div>

    <!-- Top Navigation Bar (Clean, Highly Readable, No Dropdown) -->
    <nav class="sticky top-0 z-40 bg-[#0c0c0e]/95 backdrop-blur-md border-b border-zinc-800/90 shadow-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 gap-3 sm:gap-6">
                
                <!-- Logo & Brand (High Contrast, Bold) -->
                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('comic.index') }}" class="flex items-center gap-2.5 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-600 to-red-700 flex items-center justify-center shadow-lg shadow-rose-950/50 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-book-skull text-white text-lg"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xl sm:text-2xl font-black tracking-tight text-white group-hover:text-rose-500 transition-colors">DarkComic</span>
                            <span class="text-[10px] uppercase font-bold tracking-widest text-zinc-400 -mt-1 hidden sm:block">Comic Reader</span>
                        </div>
                    </a>
                </div>

                <!-- Search Input Bar (Spacious, Legible, Responsive) -->
                <div class="flex-1 max-w-lg">
                    <form action="{{ route('comic.index') }}" method="GET" class="relative">
                        <div class="relative flex items-center">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 text-zinc-400 text-sm pointer-events-none"></i>
                            <input type="text" name="s" value="{{ request('s') }}" placeholder="Search any comic title..." 
                                class="w-full bg-zinc-900 text-white pl-10 pr-20 py-2 sm:py-2.5 text-xs sm:text-sm rounded-xl border border-zinc-800 focus:border-rose-600 focus:ring-1 focus:ring-rose-600/30 focus:outline-none transition-all placeholder:text-zinc-500 font-medium">
                            <button type="submit" class="absolute right-1.5 px-3 py-1 sm:py-1.5 bg-rose-600 hover:bg-rose-500 text-xs font-bold rounded-lg text-white shadow transition-colors">
                                Search
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right Actions: Simple Refresh Button (No Dropdowns!) -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('comic.index', ['refresh' => 1]) }}" 
                       title="Refresh Comics"
                       class="px-3 py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 hover:text-white rounded-xl text-xs font-bold transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-arrows-rotate text-rose-500"></i>
                        <span class="hidden sm:inline">Refresh</span>
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <!-- Alert Notices -->
    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="p-4 rounded-xl bg-red-950/60 border border-red-800/80 text-red-300 text-sm flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-base text-red-400"></i>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Footer (Pitch Dark, Clean) -->
    <footer class="border-t border-zinc-900 bg-[#060608] mt-16 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-zinc-500">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-rose-600 flex items-center justify-center text-white text-[10px]">
                    <i class="fa-solid fa-book-skull"></i>
                </div>
                <span class="font-bold text-zinc-300">DarkComic Reader</span>
                <span>&bull; Direct In-Browser Reading (Zero External Redirects)</span>
            </div>
            <div class="text-zinc-500 text-[11px]">
                <span>Configured Sources in <code class="text-rose-400 font-mono">.env</code> &bull; All comics load automatically</span>
            </div>
        </div>
    </footer>

    @stack('scripts')
    <script>
        // Global Instant Progress Bar for fast visual feedback on clicks
        (function() {
            const bar = document.getElementById('globalNavProgress');
            if (!bar) return;

            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[href]');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return;

                // Animate progress bar across top
                bar.style.opacity = '1';
                bar.style.width = '35%';
                setTimeout(() => { bar.style.width = '75%'; }, 150);
                setTimeout(() => { bar.style.width = '90%'; }, 400);
            }, { passive: true });
        })();
    </script>
</body>
</html>
