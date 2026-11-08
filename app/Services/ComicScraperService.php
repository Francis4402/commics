<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class ComicScraperService
{
    protected array $configuredSources;
    protected string $defaultSourceUrl;
    protected string $userAgent;
    protected string $cookie;
    protected int $cacheTtl;
    protected int $timeout;

    public function __construct()
    {
        $this->configuredSources = config('comic.sources', ['https://manhwa18.cc/']);
        $this->defaultSourceUrl = config('comic.source_url', 'https://manhwa18.cc/');
        $this->cookie = config('comic.cookie', '');
        $this->userAgent = config(
            'comic.user_agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );
        $this->cacheTtl = (int) config('comic.cache_ttl', 3600);
        $this->timeout = (int) config('comic.timeout', 15);
    }

    public function getConfiguredSources(): array
    {
        return $this->configuredSources;
    }

    public function getDefaultSourceUrl(): string
    {
        return $this->defaultSourceUrl;
    }

    /**
     * Fetch HTML from any target URL with browser-like headers & cookie support.
     */
    /**
     * Build standard browser-like request headers.
     */
    public function buildRequestHeaders(string $url, ?string $referer = null, ?string $cookie = null): array
    {
        $parsed = parse_url($url);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        $headers = [
            'User-Agent' => $this->userAgent,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Sec-Ch-Ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Referer' => $referer ?: $origin . '/',
        ];

        $effectiveCookie = $cookie ?: $this->cookie;
        if (!empty($effectiveCookie)) {
            $headers['Cookie'] = $effectiveCookie;
        }

        return $headers;
    }

    /**
     * Build appropriate page URL for comic sources.
     */
    public function buildSourcePageUrl(string $url, int $page = 1, ?string $search = null): string
    {
        $url = trim($url);
        if ($page <= 1 && empty($search)) {
            return $url;
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $path = rtrim($path, '/');

        if ($page > 1) {
            $pagePath = $path . '/page/' . $page . '/';
        } else {
            $pagePath = $path . '/';
        }

        $targetUrl = "{$scheme}://{$host}{$pagePath}";

        $queryParams = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
        }
        if (!empty($search)) {
            $queryParams['s'] = $search;
        }

        if (!empty($queryParams)) {
            $targetUrl = rtrim($targetUrl, '/') . '/?' . http_build_query($queryParams);
        }

        return $targetUrl;
    }

    /**
     * Fetch HTML from any target URL with browser-like headers & cookie support.
     */
    public function fetchHtml(string $url, ?string $referer = null, ?string $cookie = null): string
    {
        $headers = $this->buildRequestHeaders($url, $referer, $cookie);
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        $response = Http::withHeaders($headers)
            ->withOptions([
                'verify' => false,
                'allow_redirects' => true,
            ])
            ->timeout($this->timeout)
            ->get($url);

        $body = $response->body();
        $status = $response->status();

        // Check for Cloudflare Challenge / Turnstile 403
        if ($status === 403 || str_contains($body, 'Just a moment...') || str_contains($body, 'cf-turnstile-response')) {
            throw new \Exception(
                "Cloudflare bot protection (403) detected on {$host}. Cloudflare requires a cf_clearance cookie. " .
                "You can paste your browser's cf_clearance cookie into .env (COMIC_COOKIE), or use open sources like https://manhwa18.cc/ or https://manga18fx.com/."
            );
        }

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch target URL ({$url}) - Status code: {$status}");
        }

        return $body;
    }

    /**
     * Scrape and aggregate multiple sources together concurrently into a single feed.
     */
    public function scrapeMultipleCatalogs(array $sources, int $page = 1, ?string $search = null, ?string $filterHost = null): array
    {
        $targetSources = [];
        foreach ($sources as $sourceUrl) {
            $sourceUrl = trim($sourceUrl);
            if (empty($sourceUrl)) continue;

            $host = parse_url($sourceUrl, PHP_URL_HOST) ?? $sourceUrl;
            if (empty($filterHost) || $filterHost === 'all' || $filterHost === $host) {
                $targetSources[$host] = $sourceUrl;
            }
        }

        if (empty($targetSources)) {
            return [
                'comics' => [],
                'source_stats' => [],
                'total_comics' => 0,
                'errors' => [],
            ];
        }

        $cacheKey = 'comic_multi_p' . $page . '_s' . md5(($search ?? '') . '_' . ($filterHost ?? 'all') . '_' . implode(',', $targetSources));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($targetSources, $page, $search) {
            $allComics = [];
            $sourceStats = [];
            $errors = [];
            $sourcesToFetch = [];

            // 1. Check if individual source catalogs are already cached
            foreach ($targetSources as $host => $sourceUrl) {
                $targetUrl = $this->buildSourcePageUrl($sourceUrl, $page, $search);
                $singleCacheKey = 'comic_catalog_' . md5($targetUrl . '_p' . $page . '_s' . ($search ?? '') . '_c' . ($this->cookie ?? ''));

                if (Cache::has($singleCacheKey)) {
                    $cachedData = Cache::get($singleCacheKey);
                    if (is_array($cachedData)) {
                        $this->processSourceCatalog($host, $sourceUrl, $cachedData, $allComics, $sourceStats);
                        continue;
                    }
                }
                $sourcesToFetch[$host] = $sourceUrl;
            }

            // 2. Fetch remaining un-cached sources concurrently in parallel via Http::pool
            if (!empty($sourcesToFetch)) {
                $this->fetchSourcesInParallel($sourcesToFetch, $page, $search, $allComics, $sourceStats, $errors);
            }

            // Deduplicate comics across multiple sources so the exact same story is never shown twice!
            $deduplicatedComics = $this->deduplicateComics($allComics);

            return [
                'comics' => $deduplicatedComics,
                'source_stats' => $sourceStats,
                'total_comics' => count($deduplicatedComics),
                'errors' => $errors,
            ];
        });
    }

    /**
     * Generate a normalized canonical story key to match identical comics across different source links.
     */
    public function normalizeStoryKey(string $title, string $url): string
    {
        $rawTitle = trim($title);

        // 1. Clean Title for comparison
        $cleanTitle = strtolower($rawTitle);
        // Remove text in brackets/parentheses e.g. (Uncensored), [RAW], (Colored), [End], (18+), (Manhwa), [Complete]
        $cleanTitle = preg_replace('/\s*[\(\[\{][^\)\]\}]*[\)\]\}]\s*/', ' ', $cleanTitle);
        // Remove common manga tags and suffixes
        $cleanTitle = preg_replace('/\b(?:uncensored|raw|colored|color|manhwa|webtoon|manga|comic|season\s*\d+|s\d+|chapter\s*\d+)\b/i', '', $cleanTitle);
        // Remove all non-alphanumeric characters
        $alphaTitle = preg_replace('/[^a-z0-9]+/i', '', $cleanTitle);

        // 2. Clean URL slug for comparison
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $slug = strtolower(trim(basename(rtrim($path, '/'))));
        // Remove AsuraScans 8-char hex hash suffix like -ab39de10
        $slug = preg_replace('/-[a-f0-9]{8}$/i', '', $slug);
        // Remove common qualifiers from slug ends
        $slug = preg_replace('/-(?:raw|uncensored|colored|manhwa|webtoon|manga|comic|end|ongoing|18)$/i', '', $slug);
        $alphaSlug = preg_replace('/[^a-z0-9]+/i', '', $slug);

        // If title normalized key has at least 3 characters, use it as primary key
        if (strlen($alphaTitle) >= 3) {
            return $alphaTitle;
        }

        // Fallback to normalized slug
        if (strlen($alphaSlug) >= 3) {
            return $alphaSlug;
        }

        return md5($url);
    }

    /**
     * Deduplicate comic list so the same story never appears twice.
     * Merges mirror sources, categories, and cover images from alternative links into the primary card.
     */
    public function deduplicateComics(array $comics): array
    {
        $unique = [];

        foreach ($comics as $comic) {
            $storyKey = $this->normalizeStoryKey($comic['title'] ?? '', $comic['url'] ?? '');

            if (!isset($unique[$storyKey])) {
                $comic['story_key'] = $storyKey;
                $comic['other_sources'] = [];
                $unique[$storyKey] = $comic;
            } else {
                // Duplicate story detected from another source link!
                // Add alternative source record so user has backup link
                $unique[$storyKey]['other_sources'][] = [
                    'source_name' => $comic['source_name'] ?? 'Alternative Source',
                    'source_host' => $comic['source_host'] ?? '',
                    'url' => $comic['url'] ?? '',
                ];

                // Merge categories from both sources
                if (!empty($comic['categories'])) {
                    $existingCats = $unique[$storyKey]['categories'] ?? [];
                    $unique[$storyKey]['categories'] = array_values(array_unique(array_merge($existingCats, $comic['categories'])));
                }

                // If existing entry has a placeholder or broken cover, upgrade to the better cover
                $currentCover = $unique[$storyKey]['cover'] ?? '';
                $newCover = $comic['cover'] ?? '';
                if (empty($currentCover) || str_contains($currentCover, 'placeholder') || str_starts_with($currentCover, 'data:image/svg')) {
                    if (!empty($newCover) && !str_starts_with($newCover, 'data:image/svg')) {
                        $unique[$storyKey]['cover'] = $newCover;
                    }
                }

                // Merge badge (e.g. 18+) if present on either source
                if (empty($unique[$storyKey]['badge']) && !empty($comic['badge'])) {
                    $unique[$storyKey]['badge'] = $comic['badge'];
                }

                // Prefer the more informative latest_chapter text if current is generic
                if ((empty($unique[$storyKey]['latest_chapter']) || $unique[$storyKey]['latest_chapter'] === 'Comic') && !empty($comic['latest_chapter'])) {
                    $unique[$storyKey]['latest_chapter'] = $comic['latest_chapter'];
                }
            }
        }

        // Ensure every deduplicated comic has its slug and URL cached for instantaneous card click
        foreach ($unique as &$u) {
            if (empty($u['slug'])) {
                $u['slug'] = $this->getComicSlug($u['title'] ?? '', $u['url'] ?? '');
            }
            if (!empty($u['url']) && !empty($u['slug'])) {
                Cache::put('comic_slug_to_url_' . $u['slug'], $u['url'], 86400 * 30);
            }
        }
        unset($u);

        return array_values($unique);
    }

    /**
     * Fetch un-cached comic sources concurrently in parallel via Http::pool.
     */
    protected function fetchSourcesInParallel(array $sources, int $page, ?string $search, array &$allComics, array &$sourceStats, array &$errors): void
    {
        $urlsMap = [];
        $headersMap = [];

        foreach ($sources as $host => $sourceUrl) {
            $targetUrl = $this->buildSourcePageUrl($sourceUrl, $page, $search);
            $urlsMap[$host] = $targetUrl;
            $headersMap[$host] = $this->buildRequestHeaders($targetUrl);
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($urlsMap, $headersMap) {
                $requests = [];
                foreach ($urlsMap as $host => $targetUrl) {
                    $requests[] = $pool->as($host)
                        ->withHeaders($headersMap[$host])
                        ->withOptions(['verify' => false, 'allow_redirects' => true])
                        ->timeout($this->timeout)
                        ->get($targetUrl);
                }
                return $requests;
            });

            foreach ($sources as $host => $sourceUrl) {
                $sourceName = $this->getSourceDisplayName($host);
                $targetUrl = $urlsMap[$host];
                $response = $responses[$host] ?? null;

                if (!$response || !($response instanceof Response) || !$response->successful()) {
                    $status = ($response instanceof Response) ? $response->status() : 'timeout';
                    Log::warning("Parallel scrape failed for {$targetUrl} (Status: {$status})");
                    $errors[$host] = [
                        'name' => $sourceName,
                        'message' => "Source responded with status {$status}",
                    ];
                    continue;
                }

                $body = $response->body();
                if ($response->status() === 403 || str_contains($body, 'Just a moment...') || str_contains($body, 'cf-turnstile-response')) {
                    $errors[$host] = [
                        'name' => $sourceName,
                        'message' => "Cloudflare protection detected. Paste cf_clearance into .env (COMIC_COOKIE).",
                    ];
                    continue;
                }

                try {
                    $crawler = new Crawler($body, $targetUrl);
                    $comics = $this->extractComicItems($crawler, $targetUrl);
                    $pagination = $this->extractPagination($crawler, $targetUrl);

                    $catalogData = [
                        'is_single_comic' => false,
                        'source_url' => $targetUrl,
                        'comics' => $comics,
                        'pagination' => $pagination,
                        'page_title' => $this->extractPageTitle($crawler),
                    ];

                    $singleCacheKey = 'comic_catalog_' . md5($targetUrl . '_p' . $page . '_s' . ($search ?? '') . '_c' . ($this->cookie ?? ''));
                    Cache::put($singleCacheKey, $catalogData, $this->cacheTtl);

                    $this->processSourceCatalog($host, $sourceUrl, $catalogData, $allComics, $sourceStats);
                } catch (\Exception $e) {
                    Log::warning("Parsing failed for {$host}: " . $e->getMessage());
                    $errors[$host] = [
                        'name' => $sourceName,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Http pool execution error: " . $e->getMessage());
            // Fallback to individual scraping
            foreach ($sources as $host => $sourceUrl) {
                try {
                    $data = $this->scrapeCatalog($sourceUrl, $page, $search);
                    $this->processSourceCatalog($host, $sourceUrl, $data, $allComics, $sourceStats);
                } catch (\Exception $ex) {
                    $errors[$host] = [
                        'name' => $this->getSourceDisplayName($host),
                        'message' => $ex->getMessage(),
                    ];
                }
            }
        }
    }

    /**
     * Process catalog data from a single source into aggregated collection.
     */
    protected function processSourceCatalog(string $host, string $sourceUrl, array $data, array &$allComics, array &$sourceStats): void
    {
        $sourceName = $this->getSourceDisplayName($host);
        $comics = $data['comics'] ?? [];

        foreach ($comics as &$c) {
            $c['source_name'] = $sourceName;
            $c['source_host'] = $host;
            $c['source_url'] = $sourceUrl;
            $c['categories'] = $this->detectComicCategories($c);
        }
        unset($c);

        $sourceStats[$host] = [
            'name' => $sourceName,
            'host' => $host,
            'url' => $sourceUrl,
            'count' => count($comics),
        ];

        $allComics = array_merge($allComics, $comics);
    }

    /**
     * Scrape catalog from a single source URL.
     */
    public function scrapeCatalog(?string $url = null, int $page = 1, ?string $search = null, ?string $cookie = null): array
    {
        $baseUrl = $url ?: $this->defaultSourceUrl;
        $targetUrl = $this->buildSourcePageUrl($baseUrl, $page, $search);

        $cacheKey = 'comic_catalog_' . md5($targetUrl . '_p' . $page . '_s' . ($search ?? '') . '_c' . ($cookie ?? ''));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($targetUrl, $cookie) {
            $html = $this->fetchHtml($targetUrl, null, $cookie);
            $crawler = new Crawler($html, $targetUrl);

            $comics = $this->extractComicItems($crawler, $targetUrl);

            // If it has multiple comic cards, it is a catalog
            if (count($comics) >= 3) {
                $pagination = $this->extractPagination($crawler, $targetUrl);
                return [
                    'is_single_comic' => false,
                    'source_url' => $targetUrl,
                    'comics' => $comics,
                    'pagination' => $pagination,
                    'page_title' => $this->extractPageTitle($crawler),
                ];
            }

            // Otherwise check if single comic detail
            if ($this->isChapterOrDetailPage($crawler, $targetUrl)) {
                $detail = $this->extractComicDetail($crawler, $targetUrl);
                if (!empty($detail['chapters'])) {
                    return [
                        'is_single_comic' => true,
                        'comic' => $detail,
                        'source_url' => $targetUrl,
                    ];
                }
            }

            $pagination = $this->extractPagination($crawler, $targetUrl);

            return [
                'is_single_comic' => false,
                'source_url' => $targetUrl,
                'comics' => $comics,
                'pagination' => $pagination,
                'page_title' => $this->extractPageTitle($crawler),
            ];
        });
    }

    /**
     * Scrape comic detail.
     */
    public function scrapeComicDetail(string $comicUrl, ?string $cookie = null): array
    {
        $cacheKey = 'comic_detail_' . md5($comicUrl . '_c' . ($cookie ?? ''));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($comicUrl, $cookie) {
            $html = $this->fetchHtml($comicUrl, null, $cookie);
            $crawler = new Crawler($html, $comicUrl);

            return $this->extractComicDetail($crawler, $comicUrl);
        });
    }

    /**
     * Scrape chapter reading pages.
     */
    public function scrapeChapter(string $chapterUrl, ?string $comicUrl = null, ?string $cookie = null): array
    {
        $cacheKey = 'comic_chapter_' . md5($chapterUrl . '_c' . ($cookie ?? ''));

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($chapterUrl, $comicUrl, $cookie) {
            $html = $this->fetchHtml($chapterUrl, $comicUrl, $cookie);
            $crawler = new Crawler($html, $chapterUrl);

            return $this->extractChapterData($crawler, $chapterUrl, $comicUrl, $html);
        });
    }

    /**
     * Detect single comic detail or reading page.
     */
    protected function isChapterOrDetailPage(Crawler $crawler, string $url): bool
    {
        if ($crawler->filter('.reading-content, #readerarea, .container-chapter-reader, #readarea')->count() > 0) {
            return true;
        }

        if ($crawler->filter('.wp-manga-chapter, ul.sub-chap, .listing-chapters_sub-head, #chapterlist')->count() > 0) {
            return true;
        }

        return false;
    }

    /**
     * Extract comic list items from catalog page.
     */
    protected function extractComicItems(Crawler $crawler, string $baseUrl): array
    {
        $items = [];

        // 1. Container card selectors
        $cardSelectors = [
            '.page-item-detail',
            '.c-tabs-item__content',
            '.badge-pos-1',
            '.bsx',
            '.listupd .bs',
            '.utao',
            '.book-item',
            '.content-genres-item',
            '.list-truyen-item-wrap',
            '.itemupdate',
            '.list-story .story_item',
            '.eg-item',
            '.content-list-item',
            'ul.list-comic li',
            '.comic-item',
            '.film_list-wrap .flw-item',
            '.manga-item',
            '.data-item',
            'article.post',
        ];

        foreach ($cardSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$items, $baseUrl) {
                    $item = $this->parseComicCardNode($node, $baseUrl);
                    if ($item && !empty($item['title']) && !empty($item['url'])) {
                        $items[$item['url']] = $item;
                    }
                });

                if (count($items) >= 4) {
                    break;
                }
            }
        }

        // 2. High-res anchor scan (handles Manhwa18, Manga18fx, AsuraScans, and custom webtoons)
        if (count($items) < 3) {
            $comicLinkPatterns = 'a[href*="/webtoon/"], a[href*="/manga/"], a[href*="/comics/"], a[href*="/comic/"], a[href*="/series/"]';
            $crawler->filter($comicLinkPatterns)->each(function (Crawler $a) use (&$items, $baseUrl) {
                $href = $this->normalizeUrl($a->attr('href') ?? '', $baseUrl);
                if (empty($href) || isset($items[$href])) {
                    return;
                }

                // Skip non-comic routes
                if (
                    preg_match('/chapter[-_]\d+/i', $href) ||
                    str_ends_with($href, '/comics') ||
                    str_ends_with($href, '/manga') ||
                    str_contains($href, '/bookmarks') ||
                    str_contains($href, '/browse')
                ) {
                    return;
                }

                $img = $a->filter('img')->first();
                $cover = null;
                $rawTitle = trim($a->text());

                if ($img->count() > 0) {
                    $cover = $this->extractImageSource($img, $baseUrl);
                    $alt = trim($img->attr('alt') ?? $img->attr('title') ?? '');
                    if (!empty($alt)) {
                        $rawTitle = $alt;
                    }
                }

                if (empty($rawTitle)) {
                    try {
                        $parent = $a->ancestors()->first();
                        $heading = $parent->filter('h3, h4, h5, .title, .name')->first();
                        if ($heading->count() > 0) {
                            $rawTitle = trim($heading->text());
                        }
                    } catch (\Exception $e) {}
                }

                $cleanTitle = $this->cleanComicTitle($rawTitle, $href);

                if (strlen($cleanTitle) > 2 && !preg_match('/^(home|login|register|bookmark|genres?|popular|latest|contact)$/i', $cleanTitle)) {
                    $slug = $this->getComicSlug($cleanTitle, $href);
                    Cache::put('comic_slug_to_url_' . $slug, $href, 86400 * 30);

                    $items[$href] = [
                        'title' => $cleanTitle,
                        'slug' => $slug,
                        'url' => $href,
                        'cover' => $cover ?: $this->placeholderCover($cleanTitle),
                        'latest_chapter' => null,
                        'badge' => str_contains($rawTitle, '18+') ? '18+' : null,
                    ];
                }
            });
        }

        return array_values($items);
    }

    /**
     * Generate clean, human-readable URL slug representing the comic name.
     */
    public function getComicSlug(string $title, string $url): string
    {
        $cleanTitle = $this->cleanComicTitle($title, $url);
        // Strip text in brackets/parentheses e.g. (Uncensored), [RAW], (18+)
        $cleanTitle = preg_replace('/\s*[\(\[\{][^\)\]\}]*[\)\]\}]\s*/', ' ', $cleanTitle);
        $slug = Str::slug($cleanTitle);

        if (empty($slug) || strlen($slug) < 2) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $slug = Str::slug(basename(rtrim($path, '/')));
        }

        return $slug ?: 'comic';
    }

    /**
     * Generate clean, human-readable chapter slug (e.g. chapter-1, chapter-100-5).
     */
    public function getChapterSlug(string $title, string $url, float $number = 0): string
    {
        if ($number > 0) {
            $formattedNum = ((float) $number == (int) $number) ? (string) (int) $number : str_replace('.', '-', (string) $number);
            return 'chapter-' . $formattedNum;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $base = basename(rtrim($path, '/'));
        if (preg_match('/(?:chapter|ch|ep|episode)[-_]?(\d+(?:[-.]\d+)?)/i', $base, $m)) {
            return 'chapter-' . str_replace('.', '-', $m[1]);
        }

        return Str::slug($base) ?: 'chapter-1';
    }

    /**
     * Resolve a comic URL from a clean slug (e.g. "touch-on" -> "https://manhwa18.cc/webtoon/touch-on").
     */
    public function resolveComicUrlFromSlug(string $slug): ?string
    {
        $slug = strtolower(trim($slug));
        if (empty($slug)) {
            return null;
        }

        // 1. Direct cache lookup
        $cachedUrl = Cache::get('comic_slug_to_url_' . $slug);
        if ($cachedUrl && is_string($cachedUrl)) {
            return $cachedUrl;
        }

        // 2. Scan memory/configured sources with common path patterns
        $sources = config('comic.sources', [$this->defaultSourceUrl]);
        $pathVariants = [
            '/webtoon/' . $slug,
            '/manga/' . $slug,
            '/comics/' . $slug,
            '/comic/' . $slug,
            '/series/' . $slug,
        ];

        foreach ($sources as $sourceUrl) {
            $parsed = parse_url($sourceUrl);
            $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

            foreach ($pathVariants as $path) {
                $candidate = $base . $path;
                if (Cache::has('comic_detail_' . md5($candidate . '_c'))) {
                    Cache::put('comic_slug_to_url_' . $slug, $candidate, 86400 * 30);
                    return $candidate;
                }
            }
        }

        // 3. Check memory/cache of recently loaded multi-catalogs (zero network delay)
        $mainCatalogKey = 'comic_multi_p1_s' . md5('_all_' . implode(',', $sources));
        $cachedCatalog = Cache::get($mainCatalogKey);
        if (!empty($cachedCatalog['comics']) && is_array($cachedCatalog['comics'])) {
            foreach ($cachedCatalog['comics'] as $c) {
                $cSlug = $c['slug'] ?? $this->getComicSlug($c['title'] ?? '', $c['url'] ?? '');
                if ($cSlug === $slug && !empty($c['url'])) {
                    Cache::put('comic_slug_to_url_' . $slug, $c['url'], 86400 * 30);
                    return $c['url'];
                }
            }
        }

        // 4. Fallback: Search the catalog for the slug title
        $searchQuery = str_replace(['-', '_'], ' ', $slug);
        try {
            $searchResult = $this->scrapeMultipleCatalogs($sources, 1, $searchQuery);
            $comics = $searchResult['comics'] ?? [];
            foreach ($comics as $c) {
                if ($this->getComicSlug($c['title'] ?? '', $c['url'] ?? '') === $slug) {
                    Cache::put('comic_slug_to_url_' . $slug, $c['url'], 86400 * 30);
                    return $c['url'];
                }
            }
            if (!empty($comics[0]['url'])) {
                Cache::put('comic_slug_to_url_' . $slug, $comics[0]['url'], 86400 * 30);
                return $comics[0]['url'];
            }
        } catch (\Exception $e) {
            Log::warning("Slug search fallback failed for {$slug}: " . $e->getMessage());
        }

        // 5. Default fallback to first source
        $firstSource = $sources[0] ?? $this->defaultSourceUrl;
        $parsed = parse_url($firstSource);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        $fallback = $base . '/webtoon/' . $slug;
        Cache::put('comic_slug_to_url_' . $slug, $fallback, 86400 * 30);

        return $fallback;
    }

    /**
     * Resolve a chapter URL from clean comic slug & chapter slug.
     */
    public function resolveChapterUrlFromSlug(string $comicUrl, string $comicSlug, string $chapterSlug): ?string
    {
        // 1. Direct batch map lookup (O(1) instant memory lookup)
        $batchMap = Cache::get("comic_chapter_slugs_{$comicSlug}");
        if (is_array($batchMap) && isset($batchMap[$chapterSlug])) {
            return $batchMap[$chapterSlug];
        }

        // Backward compatibility single key
        $cacheKey = "comic_chapter_slug_{$comicSlug}_{$chapterSlug}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $comic = $this->scrapeComicDetail($comicUrl);
            $chapters = $comic['chapters'] ?? [];

            // Match by chapter slug or number
            $targetNum = null;
            if (preg_match('/chapter-(\d+(?:[-.]\d+)?)/i', $chapterSlug, $m)) {
                $targetNum = (float) str_replace('-', '.', $m[1]);
            }

            $freshMap = is_array($batchMap) ? $batchMap : [];
            $matchedUrl = null;

            foreach ($chapters as $ch) {
                $cSlug = $ch['slug'] ?? $this->getChapterSlug($ch['title'] ?? '', $ch['url'] ?? '', $ch['number'] ?? 0);
                if (!empty($cSlug) && !empty($ch['url'])) {
                    $freshMap[$cSlug] = $ch['url'];
                }

                if ($cSlug === $chapterSlug) {
                    $matchedUrl = $ch['url'];
                }

                if ($matchedUrl === null && $targetNum !== null && isset($ch['number']) && (float) $ch['number'] === $targetNum) {
                    $matchedUrl = $ch['url'];
                }
            }

            if (!empty($freshMap)) {
                Cache::put("comic_chapter_slugs_{$comicSlug}", $freshMap, 86400 * 30);
            }

            if ($matchedUrl) {
                return $matchedUrl;
            }
        } catch (\Exception $e) {
            Log::warning("Could not resolve chapter slug {$chapterSlug} for {$comicUrl}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Clean messy comic titles.
     */
    protected function cleanComicTitle(string $title, string $url): string
    {
        $title = trim($title);
        // Remove rating prefixes like "9.6", "9.4"
        $title = preg_replace('/^\d+\.\d+\s*/', '', $title);
        // Remove leading chapter prefixes
        $title = preg_replace('/^(?:Chapter\.?\s*\d+(?:\.\d+)?|Ch\.\s*\d+|18\+\s*)+/i', '', $title);
        $title = trim($title, " \t\n\r\0\x0B-–|:");

        if (empty($title)) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $segment = basename($path);
            $segment = preg_replace('/-\d+$/', '', $segment);
            $segment = preg_replace('/-[a-f0-9]{8}$/i', '', $segment); // removes Asura hash suffix like -ab39de10
            $title = ucwords(str_replace(['-', '_'], ' ', $segment));
        }

        return $title ?: 'Comic Title';
    }

    /**
     * Parse single comic card element.
     */
    protected function parseComicCardNode(Crawler $node, string $baseUrl): ?array
    {
        $titleNode = $node->filter('h3 a, h4 a, h5 a, .post-title a, .title a, a.title, .entry-title a, .tt a')->first();
        if ($titleNode->count() === 0) {
            $titleNode = $node->filter('a[href*="/manga/"], a[href*="/webtoon/"], a[href*="/comics/"], a[href*="/comic/"], a')->first();
        }

        if ($titleNode->count() === 0) {
            return null;
        }

        $url = $this->normalizeUrl($titleNode->attr('href') ?? '', $baseUrl);
        $rawTitle = trim($titleNode->attr('title') ?? $titleNode->text());

        $imgNode = $node->filter('img')->first();
        $cover = null;
        if ($imgNode->count() > 0) {
            $cover = $this->extractImageSource($imgNode, $baseUrl);
            if (empty($rawTitle)) {
                $rawTitle = trim($imgNode->attr('alt') ?? $imgNode->attr('title') ?? '');
            }
        }

        $chapNode = $node->filter('.chapter-item a, .btn-link, .chapter a, .chapter, .epxs, .genres-item-chap')->first();
        $latestChapter = $chapNode->count() > 0 ? trim($chapNode->text()) : null;

        $badgeNode = $node->filter('.rating, .score, .numscore, .badge, .type')->first();
        $badge = $badgeNode->count() > 0 ? trim($badgeNode->text()) : null;

        $title = $this->cleanComicTitle($rawTitle, $url);
        $slug = $this->getComicSlug($title, $url);
        Cache::put('comic_slug_to_url_' . $slug, $url, 86400 * 30);

        return [
            'title' => $title,
            'slug' => $slug,
            'url' => $url,
            'cover' => $cover ?: $this->placeholderCover($title),
            'latest_chapter' => $latestChapter,
            'badge' => $badge,
        ];
    }

    /**
     * Extract detailed comic information from comic page.
     */
    public function extractComicDetail(Crawler $crawler, string $comicUrl): array
    {
        $titleNode = $crawler->filter('h1, .post-title h1, .story-info-right h1, .manga-title, .entry-title')->first();
        $title = $titleNode->count() > 0 ? trim($titleNode->text()) : '';

        if (empty($title)) {
            $title = $crawler->filter('title')->first()->text('Comic Details');
            $title = preg_replace('/\s*[-|–]\s*.*$/', '', $title);
        }

        $title = $this->cleanComicTitle($title, $comicUrl);
        $slug = $this->getComicSlug($title, $comicUrl);
        Cache::put('comic_slug_to_url_' . $slug, $comicUrl, 86400 * 30);

        $cover = null;
        $coverNode = $crawler->filter('.summary_image img, .info-image img, .thumb img, .manga-thumb img, .poster img, .entry-content img, .cover img')->first();
        if ($coverNode->count() > 0) {
            $cover = $this->extractImageSource($coverNode, $comicUrl);
        }

        $desc = '';
        $descNode = $crawler->filter('.description-summary, .panel-story-info-description, .entry-content, .story-info-right-extent, .synopsis, #synopsis, .summary, .prose')->first();
        if ($descNode->count() > 0) {
            $desc = trim($descNode->text());
        }

        $genres = [];
        $crawler->filter('.genres-content a, .mgen a, a[href*="genre"], a[href*="genres"]')->each(function (Crawler $a) use (&$genres) {
            $g = trim($a->text());
            if ($g && !in_array($g, $genres)) {
                $genres[] = $g;
            }
        });

        $author = null;
        $authorNode = $crawler->filter('.author-content a, .author, .story-info-right tr:contains("Author") td:last-child')->first();
        if ($authorNode->count() > 0) {
            $author = trim($authorNode->text());
        }

        $chapters = $this->extractChaptersList($crawler, $comicUrl);

        // Store chapter slugs in a single fast map (avoids 100+ separate disk transactions)
        $chapterSlugMap = [];
        foreach ($chapters as $ch) {
            if (!empty($ch['slug']) && !empty($ch['url'])) {
                $chapterSlugMap[$ch['slug']] = $ch['url'];
            }
        }
        if (!empty($chapterSlugMap)) {
            Cache::put("comic_chapter_slugs_{$slug}", $chapterSlugMap, 86400 * 30);
        }

        return [
            'url' => $comicUrl,
            'slug' => $slug,
            'title' => $title,
            'cover' => $cover ?: $this->placeholderCover($title),
            'description' => $desc,
            'genres' => $genres,
            'status' => 'Available',
            'author' => $author,
            'chapters' => $chapters,
            'total_chapters' => count($chapters),
        ];
    }

    /**
     * Extract chapters list from comic page, sorted in ascending serial order.
     * High-speed optimized: uses targeted container inspection and fast attribute checks.
     */
    protected function extractChaptersList(Crawler $crawler, string $baseUrl): array
    {
        $rawChapters = [];

        // 1. High-speed container search (Madara, WP-Manga, MangaStream, MangaReader, custom)
        $containerSelectors = [
            '.wp-manga-chapter',
            '#chapterlist li',
            '.clstyle li',
            '.row-content-chapter li',
            '.chapter-list li',
            '.chapter-list .row',
            '.chapter-list div',
            '.chapters-list li',
            '.sub-chap li',
            'ul.sub-chap li',
            '.list-chapters li',
            '.eplister li',
            'ul.version-chap li',
            '.listing-chapters_sub-head li',
            '.list-chapter li',
            'div.chapter-item',
        ];

        foreach ($containerSelectors as $sel) {
            $nodes = $crawler->filter($sel);
            if ($nodes->count() > 0) {
                $nodes->each(function (Crawler $item) use (&$rawChapters, $baseUrl) {
                    $a = $item->filter('a')->first();
                    if ($a->count() === 0) {
                        return;
                    }
                    $href = $this->normalizeUrl($a->attr('href') ?? '', $baseUrl);
                    if (empty($href) || isset($rawChapters[$href]) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                        return;
                    }

                    $title = trim($a->text());
                    $dateNode = $item->filter('.chapter-release-date, .date, .time, .chapter-date, i.date, span.chapter-time, .post-on')->first();
                    $date = $dateNode->count() > 0 ? trim($dateNode->text()) : null;

                    $number = $this->extractChapterNumber($title, $href);
                    $cleanTitle = $this->cleanChapterTitle($title, $number, $href);
                    $chapterSlug = $this->getChapterSlug($cleanTitle, $href, $number);

                    $rawChapters[$href] = [
                        'title' => $cleanTitle,
                        'slug' => $chapterSlug,
                        'url' => $href,
                        'number' => $number,
                        'date' => $date,
                    ];
                });

                if (count($rawChapters) > 0) {
                    break;
                }
            }
        }

        // 2. Targeted anchor scanning if no containers matched (targets chapter links directly, skips thousands of unrelated page links)
        if (empty($rawChapters)) {
            $targetedAnchors = 'a[href*="chapter"], a[href*="episode"], a[href*="issue"], a[href*="-ch-"], a[href*="/ch-"], a[href*="_ch_"], a[href*="/c-"], a[href*="/read/"]';
            $nodes = $crawler->filter($targetedAnchors);

            if ($nodes->count() > 0) {
                $nodes->each(function (Crawler $a) use (&$rawChapters, $baseUrl) {
                    $href = $this->normalizeUrl($a->attr('href') ?? '', $baseUrl);
                    if (empty($href) || isset($rawChapters[$href]) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                        return;
                    }

                    $title = trim($a->text());
                    $number = $this->extractChapterNumber($title, $href);
                    $cleanTitle = $this->cleanChapterTitle($title, $number, $href);
                    $chapterSlug = $this->getChapterSlug($cleanTitle, $href, $number);

                    $rawChapters[$href] = [
                        'title' => $cleanTitle,
                        'slug' => $chapterSlug,
                        'url' => $href,
                        'number' => $number,
                        'date' => null,
                    ];
                });
            }
        }

        // 3. Ultra-fallback for custom or generic markup: scan anchors only if 0 chapters were found
        if (empty($rawChapters)) {
            $crawler->filter('a')->each(function (Crawler $a) use (&$rawChapters, $baseUrl) {
                $href = $this->normalizeUrl($a->attr('href') ?? '', $baseUrl);
                if (empty($href) || isset($rawChapters[$href]) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    return;
                }

                $title = trim($a->text());
                if (
                    preg_match('/(?:chapter|episode|issue|ch)[-_]?\d+/i', $href) ||
                    preg_match('/(?:chapter|episode|issue|ch\.?)\s*\d+/i', $title)
                ) {
                    $number = $this->extractChapterNumber($title, $href);
                    $cleanTitle = $this->cleanChapterTitle($title, $number, $href);
                    $chapterSlug = $this->getChapterSlug($cleanTitle, $href, $number);

                    $rawChapters[$href] = [
                        'title' => $cleanTitle,
                        'slug' => $chapterSlug,
                        'url' => $href,
                        'number' => $number,
                        'date' => null,
                    ];
                }
            });
        }

        $chapters = array_values($rawChapters);

        // Sort ascending by chapter number (e.g. Chapter 1, 2, 3... 100)
        usort($chapters, function ($a, $b) {
            if ($a['number'] == $b['number']) {
                return 0;
            }
            return ($a['number'] < $b['number']) ? -1 : 1;
        });

        // Assign clean 1-based serial index (#1, #2, #3, ...)
        $serial = 1;
        foreach ($chapters as &$ch) {
            $ch['serial'] = $serial++;
            $ch['serial_badge'] = '#' . $ch['serial'];
            if (empty($ch['slug'])) {
                $ch['slug'] = $this->getChapterSlug($ch['title'], $ch['url'], $ch['number'] ?? 0);
            }
        }
        unset($ch);

        return $chapters;
    }

    /**
     * Extract numeric chapter float from title or URL slug.
     */
    public function extractChapterNumber(string $title, string $url): float
    {
        // 1. Check title for Chapter / Ch. / Episode / Issue
        if (!empty($title)) {
            // e.g. "Chapter 100.5", "Ch. 5", "Episode 12", "Issue 3"
            if (preg_match('/(?:chapter|episode|issue|ch\.?|ep\.?|capitulo)\s*#?\s*(\d+(?:\.\d+)?)/i', $title, $m)) {
                return (float) $m[1];
            }
            // e.g. "Chapter 10-5" -> 10.5
            if (preg_match('/(?:chapter|episode|issue|ch\.?|ep\.?)\s*#?\s*(\d+)[-_](\d+)/i', $title, $m)) {
                return (float) ($m[1] . '.' . $m[2]);
            }
            // e.g. "#12"
            if (preg_match('/#\s*(\d+(?:\.\d+)?)/', $title, $m)) {
                return (float) $m[1];
            }
        }

        // 2. Check URL path / slug
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $slug = basename(rtrim($path, '/'));

        // e.g. chapter-100-5 or chapter-100.5
        if (preg_match('/(?:chapter|episode|issue|ch|ep)[-_](\d+)[-.](\d+)/i', $slug, $m)) {
            return (float) ($m[1] . '.' . $m[2]);
        }
        // e.g. chapter-100
        if (preg_match('/(?:chapter|episode|issue|ch|ep)[-_](\d+)/i', $slug, $m)) {
            return (float) $m[1];
        }

        // 3. Fallback: match standalone number in title (e.g. "Touch On 45")
        if (!empty($title) && preg_match('/\b(\d+(?:\.\d+)?)\b/', $title, $m)) {
            return (float) $m[1];
        }

        // 4. Fallback: trailing number in slug (e.g. ".../45")
        if (preg_match('/(?:[-_\/])(\d+(?:\.\d+)?)$/', rtrim($path, '/'), $m)) {
            return (float) $m[1];
        }

        return 999999.0;
    }

    /**
     * Generate clean, human-readable chapter title.
     */
    public function cleanChapterTitle(string $rawTitle, float $number, string $url): string
    {
        $rawTitle = trim($rawTitle);
        $formattedNum = ((float) $number == (int) $number) ? (string) (int) $number : (string) $number;

        // If title is generic like "Read First", "Read Last", "Read Now" or empty
        if (empty($rawTitle) || preg_match('/^(?:Read\s+First|Read\s+Last|Read\s+Now|Chapter|Episode|Issue)$/i', $rawTitle)) {
            return ($number < 999999) ? "Chapter {$formattedNum}" : 'Chapter';
        }

        // Check if there's a descriptive subtitle after chapter number, e.g. "Chapter 1: The Beginning"
        if (preg_match('/(?:chapter|episode|issue|ch\.?|ep\.?)\s*#?\s*[\d.-]+\s*[:–-]\s*(.+)$/i', $rawTitle, $m)) {
            $subtitle = trim($m[1]);
            if (!empty($subtitle) && !is_numeric($subtitle)) {
                return "Chapter {$formattedNum}: {$subtitle}";
            }
        }

        // If title already starts with Chapter/Ch./Episode/Issue
        if (preg_match('/^(?:chapter|episode|issue|ch\.?|ep\.?)\s*#?\s*[\d.-]+/i', $rawTitle)) {
            return "Chapter {$formattedNum}";
        }

        // If title is just a number (e.g. "01" or "1")
        if (is_numeric($rawTitle) || preg_match('/^#?\d+(?:\.\d+)?$/', $rawTitle)) {
            return "Chapter {$formattedNum}";
        }

        // Return trimmed title, but if it has no chapter keyword and number is valid, prefix it nicely
        if ($number < 999999 && !str_contains(strtolower($rawTitle), 'chapter') && !str_contains(strtolower($rawTitle), 'ch.')) {
            return "Chapter {$formattedNum} - {$rawTitle}";
        }

        return $rawTitle;
    }

    /**
     * Extract chapter images and navigation for reader.
     */
    protected function extractChapterData(Crawler $crawler, string $chapterUrl, ?string $comicUrl, string $rawHtml): array
    {
        $titleNode = $crawler->filter('h1, .entry-title, .chapter-title, .headpost h1')->first();
        $title = $titleNode->count() > 0 ? trim($titleNode->text()) : '';
        if (empty($title)) {
            $title = $crawler->filter('title')->first()->text('Reading Comic');
            $title = preg_replace('/\s*[-|–]\s*.*$/', '', $title);
        }

        $comicTitle = null;
        $breadcrumbLink = $crawler->filter('.breadcrumbs a, .breadcrumb a, a[href*="/comic/"], a[href*="/comics/"], a[href*="/manga/"], a[href*="/webtoon/"]')->first();
        if ($breadcrumbLink->count() > 0) {
            $comicTitle = trim($breadcrumbLink->text());
            if (empty($comicUrl)) {
                $comicUrl = $this->normalizeUrl($breadcrumbLink->attr('href'), $chapterUrl);
            }
        }

        $images = [];

        // 1. Look in dedicated reader containers first
        $readerSelectors = [
            '.read-content img',
            '.read-manga img',
            '.reading-content img',
            '#readerarea img',
            '.container-chapter-reader img',
            '.chapter-images img',
            '#page-view img',
            '.entry-content center img',
        ];

        foreach ($readerSelectors as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $crawler->filter($selector)->each(function (Crawler $img) use (&$images, $chapterUrl) {
                    $src = $this->extractImageSource($img, $chapterUrl);
                    if ($src && $this->isValidComicImage($src) && !in_array($src, $images)) {
                        $images[] = $src;
                    }
                });

                if (count($images) >= 2) {
                    break;
                }
            }
        }

        // 2. If not found in containers, scan general images strictly
        if (empty($images)) {
            $crawler->filter('img')->each(function (Crawler $img) use (&$images, $chapterUrl) {
                if ($img->matches('.logo img, .header img, header img, footer img, .comment img, .comment-left img, .search-loading img')) {
                    return;
                }
                $src = $this->extractImageSource($img, $chapterUrl);
                if ($src && $this->isValidComicImage($src) && !in_array($src, $images)) {
                    if (str_contains($src, 'uploads') || str_contains($src, 'chapter') || str_contains($src, 'page')) {
                        $images[] = $src;
                    }
                }
            });
        }

        // 3. Check JavaScript variables
        if (empty($images)) {
            $images = $this->extractImagesFromJavaScript($rawHtml, $chapterUrl);
        }

        $prevUrl = null;
        $nextUrl = null;

        $prevNode = $crawler->filter('a.prev, a[rel="prev"], a:contains("Prev"), .btn-prev')->first();
        if ($prevNode->count() > 0 && $prevNode->attr('href')) {
            $prev = $this->normalizeUrl($prevNode->attr('href'), $chapterUrl);
            if ($prev !== $chapterUrl && !str_contains($prev, '#')) {
                $prevUrl = $prev;
            }
        }

        $nextNode = $crawler->filter('a.next, a[rel="next"], a:contains("Next"), .btn-next')->first();
        if ($nextNode->count() > 0 && $nextNode->attr('href')) {
            $next = $this->normalizeUrl($nextNode->attr('href'), $chapterUrl);
            if ($next !== $chapterUrl && !str_contains($next, '#')) {
                $nextUrl = $next;
            }
        }

        return [
            'chapter_url' => $chapterUrl,
            'comic_url' => $comicUrl,
            'comic_title' => $comicTitle,
            'title' => $title,
            'images' => $images,
            'total_pages' => count($images),
            'prev_url' => $prevUrl,
            'next_url' => $nextUrl,
            'sibling_chapters' => [],
        ];
    }

    /**
     * Extract JavaScript arrays containing chapter image URLs.
     */
    protected function extractImagesFromJavaScript(string $html, string $baseUrl): array
    {
        $images = [];
        $patterns = [
            '/(?:var|const|let)\s+(?:pages|images|chapter_images|sources|img_list)\s*=\s*(\[[^\]]+\])/i',
            '/\"images\"\s*:\s*(\[[^\]]+\])/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $decoded = json_decode($matches[1], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $src = is_string($item) ? $item : ($item['src'] ?? $item['url'] ?? $item['image'] ?? null);
                        if ($src && is_string($src)) {
                            $fullSrc = $this->normalizeUrl(trim($src), $baseUrl);
                            if ($this->isValidComicImage($fullSrc) && !in_array($fullSrc, $images)) {
                                $images[] = $fullSrc;
                            }
                        }
                    }
                    if (!empty($images)) {
                        break;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Check if an image URL is a valid comic page.
     */
    protected function isValidComicImage(string $url): bool
    {
        $lower = strtolower($url);

        // Never match gif spinners or avatars
        if (str_ends_with($lower, '.gif') || str_contains($lower, 'loading.gif') || str_contains($lower, 'search-loading')) {
            return false;
        }

        $blockedPatterns = [
            'logo', 'banner', 'avatar', 'icon', 'button', 'spinner',
            'facebook', 'twitter', 'discord', 'telegram', 'advertisement',
            'wp-content/themes', 'favicon', 'disqus', 'analytics', 'user-1',
            '/ads/', '_ads_', '-ads-', 'ads.', 'ad-banner', 'adsystem'
        ];

        foreach ($blockedPatterns as $blocked) {
            if (str_contains($lower, $blocked)) {
                return false;
            }
        }

        if (
            preg_match('/\.(jpg|jpeg|png|webp|avif)/i', $url) ||
            str_contains($lower, 'uploads') ||
            str_contains($lower, 'chapter') ||
            str_contains($lower, 'manga') ||
            str_contains($lower, 'cdn')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Extract best image src.
     */
    protected function extractImageSource(Crawler $img, string $baseUrl): ?string
    {
        $attributes = [
            'data-src',
            'data-lazy-src',
            'data-original',
            'src',
            'data-srcset',
            'srcset',
        ];

        foreach ($attributes as $attr) {
            $val = $img->attr($attr);
            if (!empty($val)) {
                if (str_starts_with($val, 'data:image') || str_contains($val, 'blank.gif') || str_contains($val, 'spacer.gif')) {
                    continue;
                }
                return $this->normalizeUrl(trim($val), $baseUrl);
            }
        }

        return null;
    }

    /**
     * Resolve relative URLs to absolute URLs.
     */
    public function normalizeUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);

        if (empty($url)) {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';
        $baseRoot = "{$scheme}://{$host}{$port}";

        if (str_starts_with($url, '/')) {
            return $baseRoot . $url;
        }

        $basePath = $parsedBase['path'] ?? '/';
        $dir = rtrim(dirname($basePath), '/\\');
        return $baseRoot . ($dir ? $dir . '/' : '/') . $url;
    }

    /**
     * Extract pagination links if available.
     */
    protected function extractPagination(Crawler $crawler, string $baseUrl): array
    {
        $prevUrl = null;
        $nextUrl = null;

        $prevNode = $crawler->filter('a.prev, .pagination a.prev, a[rel="prev"]')->first();
        if ($prevNode->count() > 0 && $prevNode->attr('href')) {
            $prevUrl = $this->normalizeUrl($prevNode->attr('href'), $baseUrl);
        }

        $nextNode = $crawler->filter('a.next, .pagination a.next, a[rel="next"]')->first();
        if ($nextNode->count() > 0 && $nextNode->attr('href')) {
            $nextUrl = $this->normalizeUrl($nextNode->attr('href'), $baseUrl);
        }

        return [
            'prev_page_url' => $prevUrl,
            'next_page_url' => $nextUrl,
        ];
    }

    /**
     * Extract clean page title.
     */
    protected function extractPageTitle(Crawler $crawler): string
    {
        $titleNode = $crawler->filter('title')->first();
        if ($titleNode->count() > 0) {
            return trim($titleNode->text());
        }
        return 'Comic Reader';
    }

    /**
     * Human-friendly display name for source host.
     */
    public function getSourceDisplayName(string $host): string
    {
        $clean = preg_replace('/^www\./', '', strtolower($host));
        return match($clean) {
            'manhwa18.cc' => 'Manhwa18',
            'manga18fx.com' => 'Manga18fx',
            'asurascans.com', 'asuracomic.net' => 'AsuraScans',
            'chapmanganato.to', 'manganato.com' => 'Manganato',
            'readallcomics.com' => 'ReadAllComics',
            default => ucfirst(explode('.', $clean)[0] ?? 'Comic')
        };
    }

    /**
     * Determine categories / genres for a comic card.
     */
    public function detectComicCategories(array $comic): array
    {
        $categories = ['All'];
        $title = strtolower($comic['title'] ?? '');
        $url = strtolower($comic['url'] ?? '');
        $host = strtolower($comic['source_host'] ?? '');

        // Manhwa / Webtoons
        if (str_contains($url, '/webtoon/') || str_contains($host, 'manhwa') || str_contains($host, 'asura')) {
            $categories[] = 'Manhwa';
            $categories[] = 'Webtoons';
        }

        // Adult / 18+
        $adultKeywords = ['sex', 'lewd', 'milf', 'banged', 'affair', 'mother', 'ajumma', 'madam', 'uncensored', 'secret class', 'raw', 'hooky', 'bully', 'onahole'];
        if (str_contains($host, '18') || ($comic['badge'] ?? '') === '18+' || $this->strContainsAny($title, $adultKeywords)) {
            $categories[] = '18+ Adult';
        }

        // Action
        $actionKeywords = ['slayer', 'sword', 'hunter', 'knight', 'regression', 'level', 'player', 'barbarian', 'warrior', 'cyberpunk', 'blade', 'demon', 'solo', 'hero', 'battle', 'dragon', 'football', 'fight'];
        if (str_contains($host, 'asura') || $this->strContainsAny($title, $actionKeywords)) {
            $categories[] = 'Action';
        }

        // Romance & Drama
        $romanceKeywords = ['love', 'dating', 'girlfriend', 'affair', 'secret', 'ajumma', 'madam', 'wife', 'mother', 'sister', 'noona', 'petals', 'confession', 'marriage', 'heart', 'romance'];
        if ($this->strContainsAny($title, $romanceKeywords)) {
            $categories[] = 'Romance';
            $categories[] = 'Drama';
        }

        // Fantasy & Supernatural
        $fantasyKeywords = ['dragon', 'magic', 'mage', 'demon', 'hero', 'grimoire', 'saintess', 'witch', 'monster', 'ghost', 'immortal', 'god', 'reincarnat', 'dungeon', 'isekai', 'system'];
        if ($this->strContainsAny($title, $fantasyKeywords)) {
            $categories[] = 'Fantasy';
            $categories[] = 'Supernatural';
        }

        // Comedy & Slice of Life
        $sliceKeywords = ['school', 'class', 'life', 'days', 'work', 'college', 'apartment', 'bully', 'friends', 'crazy'];
        if ($this->strContainsAny($title, $sliceKeywords)) {
            $categories[] = 'Slice of Life';
            $categories[] = 'Comedy';
        }

        return array_values(array_unique($categories));
    }

    protected function strContainsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate an SVG placeholder cover image.
     */
    protected function placeholderCover(string $title): string
    {
        $initial = strtoupper(substr($title, 0, 1) ?: 'C');
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 450' width='300' height='450'>
            <defs>
                <linearGradient id='g' x1='0%' y1='0%' x2='100%' y2='100%'>
                    <stop offset='0%' stop-color='#18181b'/>
                    <stop offset='100%' stop-color='#09090b'/>
                </linearGradient>
            </defs>
            <rect width='100%' height='100%' fill='url(#g)'/>
            <rect x='10' y='10' width='280' height='430' fill='none' stroke='#27272a' stroke-width='2' stroke-dasharray='6,6'/>
            <text x='50%' y='45%' font-family='sans-serif' font-size='64' font-weight='bold' fill='#e11d48' text-anchor='middle' dominant-baseline='middle'>{$initial}</text>
            <text x='50%' y='60%' font-family='sans-serif' font-size='14' fill='#a1a1aa' text-anchor='middle'>Comic Cover</text>
        </svg>";

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
