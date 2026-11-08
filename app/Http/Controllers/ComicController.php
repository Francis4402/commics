<?php

namespace App\Http\Controllers;

use App\Services\ComicScraperService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComicController extends Controller
{
    protected ComicScraperService $scraper;

    public function __construct(ComicScraperService $scraper)
    {
        $this->scraper = $scraper;
    }

    /**
     * Show comic catalog: automatically loads comics from all available sources!
     * Zero manual switching required.
     */
    public function index(Request $request)
    {
        $sources = config('comic.sources', [$this->scraper->getDefaultSourceUrl()]);
        $customSource = $request->query('source');
        $filterHost = $request->query('filter');
        $search = $request->query('s');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        if ($request->has('refresh')) {
            Cache::flush();
        }

        // If a single custom source was explicitly passed in query
        if (!empty($customSource) && !in_array($customSource, $sources)) {
            try {
                $data = $this->scraper->scrapeCatalog($customSource, $page, $search);

                if (!empty($data['is_single_comic']) && !empty($data['comic'])) {
                    return view('comics.show', [
                        'comic' => $data['comic'],
                        'sourceUrl' => $customSource,
                    ]);
                }

                $host = parse_url($customSource, PHP_URL_HOST) ?? $customSource;
                $sourceName = $this->scraper->getSourceDisplayName($host);
                $singleComics = $data['comics'] ?? [];

                $paginator = new LengthAwarePaginator(
                    array_slice($singleComics, 0, $perPage),
                    max(count($singleComics), $page * $perPage + 24),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );

                return view('comics.index', [
                    'comics' => array_slice($singleComics, 0, $perPage),
                    'allComics' => $singleComics,
                    'paginator' => $paginator,
                    'pagination' => $data['pagination'] ?? [],
                    'pageTitle' => $data['page_title'] ?? 'Comic Directory',
                    'activeSource' => $customSource,
                    'currentFilter' => $host,
                    'categories' => ['All', 'Manhwa', 'Webtoons', 'Action', 'Romance', '18+ Adult', 'Fantasy', 'Drama', 'Comedy'],
                    'activeCategory' => 'All',
                    'sourceStats' => [
                        $host => [
                            'name' => $sourceName,
                            'host' => $host,
                            'url' => $customSource,
                            'count' => count($singleComics),
                        ]
                    ],
                    'search' => $search,
                ]);
            } catch (\Exception $e) {
                return view('comics.index', [
                    'comics' => [],
                    'allComics' => [],
                    'paginator' => null,
                    'pagination' => [],
                    'pageTitle' => 'Error Loading Comics',
                    'activeSource' => $customSource,
                    'currentFilter' => null,
                    'categories' => ['All', 'Manhwa', 'Webtoons', 'Action', 'Romance', '18+ Adult', 'Fantasy', 'Drama', 'Comedy'],
                    'activeCategory' => 'All',
                    'sourceStats' => [],
                    'search' => $search,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Aggregate ALL configured sources concurrently in parallel
        $isSingleFilter = (!empty($filterHost) && $filterHost !== 'all');
        $sourcePage = $isSingleFilter ? $page : (int) ceil($page / 3);
        $batchIndex = $isSingleFilter ? 0 : (($page - 1) % 3);
        $batchOffset = $batchIndex * $perPage;

        $result = $this->scraper->scrapeMultipleCatalogs($sources, $sourcePage, $search, $filterHost);
        $allComics = $result['comics'] ?? [];

        // Define available categories
        $allCategories = ['All', 'Manhwa', 'Webtoons', 'Action', 'Romance', '18+ Adult', 'Fantasy', 'Drama', 'Comedy'];
        $activeCategory = $request->query('category', 'All');

        $filteredComics = $allComics;
        if (!empty($activeCategory) && strtolower($activeCategory) !== 'all') {
            $filteredComics = array_values(array_filter($allComics, function ($c) use ($activeCategory) {
                $cats = array_map('strtolower', $c['categories'] ?? []);
                return in_array(strtolower($activeCategory), $cats);
            }));
        }

        // Paginate slice
        $totalInBatch = count($filteredComics);
        $pageComics = array_slice($filteredComics, $batchOffset, $perPage);

        // Fallback if slice is empty but items exist in current batch
        if (empty($pageComics) && !empty($filteredComics)) {
            $pageComics = array_slice($filteredComics, 0, $perPage);
        }

        // Calculate total for paginator
        $hasMoreBatches = ($totalInBatch >= $perPage);
        $estimatedTotal = max($totalInBatch, $page * $perPage + ($hasMoreBatches ? ($perPage * 3) : 0));

        $paginator = new LengthAwarePaginator(
            $pageComics,
            $estimatedTotal,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('comics.index', [
            'comics' => $pageComics,
            'allComics' => $allComics,
            'paginator' => $paginator,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $estimatedTotal,
                'last_page' => $paginator->lastPage(),
            ],
            'pageTitle' => '',
            'activeSource' => implode(', ', $sources),
            'currentFilter' => $filterHost ?: 'all',
            'categories' => $allCategories,
            'activeCategory' => $activeCategory,
            'sourceStats' => $result['source_stats'] ?? [],
            'search' => $search,
            'errors' => $result['errors'] ?? [],
        ]);
    }

    /**
     * Show comic details and full list of chapters.
     */
    public function show(Request $request, ?string $slug = null)
    {
        $url = $request->query('url');

        if (empty($url) && !empty($slug)) {
            $url = $this->scraper->resolveComicUrlFromSlug($slug);
        }

        if (empty($url)) {
            return redirect()->route('comic.index')->with('error', 'No comic URL provided.');
        }

        if ($request->has('refresh')) {
            Cache::forget('comic_detail_' . md5($url . '_c'));
        }

        try {
            $comic = $this->scraper->scrapeComicDetail($url);
            $comicSlug = $slug ?: ($comic['slug'] ?? $this->scraper->getComicSlug($comic['title'] ?? '', $url));
            $comic['slug'] = $comicSlug;

            return view('comics.show', [
                'comic' => $comic,
                'sourceUrl' => $url,
                'comicSlug' => $comicSlug,
            ]);
        } catch (\Exception $e) {
            Log::error('Comic detail scrape failed: ' . $e->getMessage(), ['url' => $url]);
            return back()->with('error', 'Failed to load comic: ' . $e->getMessage());
        }
    }

    /**
     * Dedicated Reader Page: Renders comic pages directly in the website.
     * ZERO external redirects!
     */
    public function read(Request $request, ?string $comicSlug = null, ?string $chapterSlug = null)
    {
        $chapterUrl = $request->query('url');
        $comicUrl = $request->query('comic');

        // If clean slugs are present in path
        if (!empty($comicSlug)) {
            if (empty($comicUrl)) {
                $comicUrl = $this->scraper->resolveComicUrlFromSlug($comicSlug);
            }
            if (empty($chapterUrl) && !empty($chapterSlug) && !empty($comicUrl)) {
                $chapterUrl = $this->scraper->resolveChapterUrlFromSlug($comicUrl, $comicSlug, $chapterSlug);
            }
        }

        if (empty($chapterUrl)) {
            return redirect()->route('comic.index')->with('error', 'No chapter URL provided.');
        }

        if ($request->has('refresh')) {
            Cache::forget('comic_chapter_' . md5($chapterUrl . '_c'));
        }

        try {
            $chapter = $this->scraper->scrapeChapter($chapterUrl, $comicUrl);

            // If comicUrl was not passed in request query, check if discovered from breadcrumbs
            if (empty($comicUrl) && !empty($chapter['comic_url'])) {
                $comicUrl = $chapter['comic_url'];
            }

            // Sync with parent comic to ensure exact serial order and sequential Prev/Next navigation
            if (!empty($comicUrl)) {
                try {
                    $comic = $this->scraper->scrapeComicDetail($comicUrl);
                    if (!empty($comic['chapters'])) {
                        $chapters = $comic['chapters'];
                        $comicSlug = $comicSlug ?: ($comic['slug'] ?? $this->scraper->getComicSlug($comic['title'] ?? '', $comicUrl));

                        if (empty($chapter['comic_title']) && !empty($comic['title'])) {
                            $chapter['comic_title'] = $comic['title'];
                        }

                        // Locate current chapter in serialized sequence
                        $currentIndex = null;
                        $chapterPath = parse_url($chapterUrl, PHP_URL_PATH);

                        foreach ($chapters as $idx => $ch) {
                            if ($ch['url'] === $chapterUrl) {
                                $currentIndex = $idx;
                                break;
                            }
                        }

                        if ($currentIndex === null && $chapterPath) {
                            foreach ($chapters as $idx => $ch) {
                                if (parse_url($ch['url'], PHP_URL_PATH) === $chapterPath) {
                                    $currentIndex = $idx;
                                    break;
                                }
                            }
                        }

                        if ($currentIndex !== null) {
                            $chapter['serial'] = $chapters[$currentIndex]['serial'] ?? ($currentIndex + 1);
                            $chapter['serial_badge'] = '#' . $chapter['serial'];
                            $chapter['total_chapters'] = count($chapters);

                            // Sequential Previous Chapter (Serial - 1)
                            if ($currentIndex > 0) {
                                $prevChapter = $chapters[$currentIndex - 1];
                                $chapter['prev_url'] = $prevChapter['url'];
                                $chapter['prev_title'] = $prevChapter['title'];
                                $chapter['prev_serial'] = $prevChapter['serial'] ?? $currentIndex;
                                $prevSlug = $prevChapter['slug'] ?? $this->scraper->getChapterSlug($prevChapter['title'], $prevChapter['url'], $prevChapter['number'] ?? 0);
                                $chapter['prev_clean_url'] = route('comic.read', ['comicSlug' => $comicSlug, 'chapterSlug' => $prevSlug]);
                            } else {
                                $chapter['prev_url'] = null;
                                $chapter['prev_clean_url'] = null;
                            }

                            // Sequential Next Chapter (Serial + 1)
                            if ($currentIndex < count($chapters) - 1) {
                                $nextChapter = $chapters[$currentIndex + 1];
                                $chapter['next_url'] = $nextChapter['url'];
                                $chapter['next_title'] = $nextChapter['title'];
                                $chapter['next_serial'] = $nextChapter['serial'] ?? ($currentIndex + 2);
                                $nextSlug = $nextChapter['slug'] ?? $this->scraper->getChapterSlug($nextChapter['title'], $nextChapter['url'], $nextChapter['number'] ?? 0);
                                $chapter['next_clean_url'] = route('comic.read', ['comicSlug' => $comicSlug, 'chapterSlug' => $nextSlug]);
                            } else {
                                $chapter['next_url'] = null;
                                $chapter['next_clean_url'] = null;
                            }
                        }

                        // Serialized sibling chapters for dropdown selector
                        $chapter['sibling_chapters'] = array_map(function ($ch) use ($chapterUrl, $chapterPath, $comicSlug) {
                            $isSelected = ($ch['url'] === $chapterUrl) || ($chapterPath && parse_url($ch['url'], PHP_URL_PATH) === $chapterPath);
                            $chSlug = $ch['slug'] ?? $this->scraper->getChapterSlug($ch['title'], $ch['url'], $ch['number'] ?? 0);
                            return [
                                'serial' => $ch['serial'] ?? null,
                                'number' => $ch['number'] ?? null,
                                'title' => $ch['title'],
                                'slug' => $chSlug,
                                'url' => $ch['url'],
                                'clean_url' => route('comic.read', ['comicSlug' => $comicSlug, 'chapterSlug' => $chSlug]),
                                'selected' => $isSelected,
                            ];
                        }, $chapters);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not sync serialized sibling chapters: ' . $e->getMessage());
                }
            }

            return view('comics.reader', [
                'chapter' => $chapter,
                'chapterUrl' => $chapterUrl,
                'comicUrl' => $comicUrl,
                'comicSlug' => $comicSlug,
                'useProxy' => config('comic.proxy_images', true),
            ]);
        } catch (\Exception $e) {
            Log::error('Chapter reading failed: ' . $e->getMessage(), ['url' => $chapterUrl]);
            return back()->with('error', 'Failed to load chapter reader: ' . $e->getMessage());
        }
    }

    /**
     * High-Performance Image Proxy:
     * - Zero-copy binary file streaming directly via OS kernel
     * - Retains 100% full original resolution & quality without downscaling
     * - 30 days immutable browser cache with native ETag / 304 validation
     * - Bypasses 403 Forbidden / CORS / Hotlinking protections
     */
    public function proxyImage(Request $request)
    {
        $imageUrl = $request->query('url');
        $referer = $request->query('referer');

        if (empty($imageUrl)) {
            return response('', 400);
        }

        $etag = '"' . md5($imageUrl) . '"';

        // Fast client-side ETag validation (304 Not Modified)
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=2592000, immutable');
        }

        // Dedicated disk cache directory for binary image streams
        $cacheDir = storage_path('app/comic_cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $hash = md5($imageUrl);
        $filePath = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.bin';
        $metaPath = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.meta';

        // 1. Direct serve from disk if already cached (0 network overhead)
        if (file_exists($filePath) && filesize($filePath) > 0) {
            $contentType = @file_get_contents($metaPath) ?: 'image/jpeg';
            return response()->file($filePath, [
                'Content-Type' => $contentType,
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=2592000, immutable',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // 2. Fetch original full-quality bytes from source
        try {
            $parsed = parse_url($imageUrl);
            $host = $parsed['host'] ?? '';
            $origin = ($parsed['scheme'] ?? 'https') . '://' . $host;

            $headers = [
                'User-Agent' => config(
                    'comic.user_agent',
                    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
                ),
                'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => $referer ?: $origin . '/',
                'Sec-Fetch-Dest' => 'image',
                'Sec-Fetch-Mode' => 'no-cors',
                'Sec-Fetch-Site' => 'cross-site',
            ];

            $response = Http::withHeaders($headers)
                ->withOptions([
                    'verify' => false,
                    'allow_redirects' => true,
                    'decode_content' => true,
                ])
                ->timeout(15)
                ->get($imageUrl);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type') ?: 'image/jpeg';
                if (str_contains($contentType, ';')) {
                    $contentType = trim(explode(';', $contentType)[0]);
                }
                $body = $response->body();

                // Persist full-quality binary directly to disk
                @file_put_contents($filePath, $body);
                @file_put_contents($metaPath, $contentType);

                return response()->file($filePath, [
                    'Content-Type' => $contentType,
                    'ETag' => $etag,
                    'Cache-Control' => 'public, max-age=2592000, immutable',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }

            return response('', $response->status());
        } catch (\Exception $e) {
            Log::warning('Image proxy fetch failed: ' . $e->getMessage(), ['url' => $imageUrl]);
            return response('', 502);
        }
    }
}
