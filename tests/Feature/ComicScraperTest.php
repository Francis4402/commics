<?php

namespace Tests\Feature;

use App\Services\ComicScraperService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComicScraperTest extends TestCase
{
    public function test_catalog_page_loads_successfully()
    {
        $fakeHtml = '
            <html>
                <body>
                    <div class="list-story">
                        <div class="story_item">
                            <h3><a href="https://example.com/comic/batman">Batman Adventures</a></h3>
                            <img src="https://example.com/covers/batman.jpg" alt="Batman">
                            <span class="chapter"><a href="https://example.com/comic/batman/chapter-1">Issue #1</a></span>
                        </div>
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            config('comic.source_url') . '*' => Http::response($fakeHtml, 200),
            'https://example.com/*' => Http::response($fakeHtml, 200),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Batman Adventures');
        // Clean URL showing the comic name:
        $response->assertSee(route('comic.show', ['slug' => 'batman-adventures']));
    }

    public function test_comic_detail_page_loads_with_chapter_links()
    {
        $fakeHtml = '
            <html>
                <body>
                    <h1 class="post-title">Batman Adventures</h1>
                    <div class="summary_image">
                        <img src="https://example.com/covers/batman.jpg">
                    </div>
                    <div class="description-summary">
                        <p>The adventures of the Dark Knight in Gotham City.</p>
                    </div>
                    <ul class="list-chapters">
                        <li><a href="https://example.com/comic/batman/chapter-1">Chapter 1: The Beginning</a></li>
                        <li><a href="https://example.com/comic/batman/chapter-2">Chapter 2: The Joker Strikes</a></li>
                    </ul>
                </body>
            </html>
        ';

        Http::fake([
            'https://example.com/comic/batman' => Http::response($fakeHtml, 200),
        ]);

        $response = $this->get(route('comic.show', ['url' => 'https://example.com/comic/batman']));

        $response->assertStatus(200);
        $response->assertSee('Batman Adventures');
        $response->assertSee('The adventures of the Dark Knight');
        $response->assertSee('Chapter 1: The Beginning');
        
        // Ensure chapter link points to clean internal reader URL with comic name, NOT redirecting externally
        $expectedReaderUrl = route('comic.read', [
            'comicSlug' => 'batman-adventures',
            'chapterSlug' => 'chapter-1',
        ]);
        $response->assertSee($expectedReaderUrl);
    }

    public function test_in_website_reader_page_loads_images_without_external_redirect()
    {
        $fakeHtml = '
            <html>
                <body>
                    <h1 class="chapter-title">Batman Adventures - Chapter 1</h1>
                    <div class="reading-content">
                        <img src="https://example.com/pages/page01.jpg">
                        <img src="https://example.com/pages/page02.jpg">
                        <img src="https://example.com/pages/page03.jpg">
                    </div>
                    <a class="next" href="https://example.com/comic/batman/chapter-2">Next Chapter</a>
                </body>
            </html>
        ';

        Http::fake([
            'https://example.com/comic/batman/chapter-1' => Http::response($fakeHtml, 200),
        ]);

        $response = $this->get(route('comic.read', [
            'url' => 'https://example.com/comic/batman/chapter-1',
            'comic' => 'https://example.com/comic/batman',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Batman Adventures - Chapter 1');
        $response->assertSee('3 Pages');
        $response->assertSee('Loaded');
        
        // Assert images are routed through proxy or directly inside reader
        $response->assertSee(route('comic.proxy'));
        $response->assertSee(urlencode('https://example.com/pages/page01.jpg'));
    }

    public function test_image_proxy_returns_valid_image_response()
    {
        $fakeImageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        Http::fake([
            'https://example.com/pages/page01.jpg' => Http::response($fakeImageBytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->get(route('comic.proxy', [
            'url' => 'https://example.com/pages/page01.jpg',
            'referer' => 'https://example.com/comic/batman/chapter-1',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $actualContent = $response->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse 
            ? file_get_contents($response->baseResponse->getFile()->getPathname())
            : $response->getContent();
        $this->assertEquals($fakeImageBytes, $actualContent);
    }

    public function test_multi_source_aggregation_loads_all_comics_without_switching()
    {
        $source1Html = '<html><body><div class="list-story"><div class="story_item"><h3><a href="https://source1.com/comic/hero1">Hero One</a></h3></div></div></body></html>';
        $source2Html = '<html><body><div class="list-story"><div class="story_item"><h3><a href="https://source2.com/comic/hero2">Hero Two</a></h3></div></div></body></html>';

        config(['comic.sources' => ['https://source1.com/', 'https://source2.com/']]);

        Http::fake([
            'https://source1.com/*' => Http::response($source1Html, 200),
            'https://source2.com/*' => Http::response($source2Html, 200),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Hero One');
        $response->assertSee('Hero Two');
        $response->assertSee('Categories');
    }

    public function test_chapters_are_numbered_and_serialized_in_ascending_order()
    {
        // Out-of-order raw HTML chapters as frequently encountered on scraper sources
        $comicDetailHtml = '
            <html>
                <body>
                    <h1 class="post-title">Solo Leveling</h1>
                    <div class="summary_image"><img src="https://example.com/cover.jpg"></div>
                    <ul class="list-chapters">
                        <li><a href="https://example.com/comic/solo/chapter-10">Chapter 10</a></li>
                        <li><a href="https://example.com/comic/solo/chapter-1">Chapter 1</a></li>
                        <li><a href="https://example.com/comic/solo/chapter-2-5">Chapter 2.5</a></li>
                        <li><a href="https://example.com/comic/solo/chapter-2">Chapter 2</a></li>
                    </ul>
                </body>
            </html>
        ';

        Http::fake([
            'https://example.com/comic/solo' => Http::response($comicDetailHtml, 200),
        ]);

        $response = $this->get(route('comic.show', ['url' => 'https://example.com/comic/solo']));

        $response->assertStatus(200);
        $response->assertSee('Serialized Chapters');
        $response->assertSee('4 Chapters');
        
        // Ensure serial badges #1, #2, #3, #4 exist
        $response->assertSee('#1');
        $response->assertSee('#2');
        $response->assertSee('#3');
        $response->assertSee('#4');

        // First chapter CTA should target Chapter 1
        $response->assertSee('Start Reading (Chapter 1)');
        // Latest chapter CTA should target Chapter 10
        $response->assertSee('Latest (Chapter 10)');
    }

    public function test_reader_navigates_sequentially_through_serialized_chapters()
    {
        $comicDetailHtml = '
            <html>
                <body>
                    <h1 class="post-title">Solo Leveling</h1>
                    <ul class="list-chapters">
                        <li><a href="https://example.com/comic/solo/chapter-3">Chapter 3</a></li>
                        <li><a href="https://example.com/comic/solo/chapter-2">Chapter 2</a></li>
                        <li><a href="https://example.com/comic/solo/chapter-1">Chapter 1</a></li>
                    </ul>
                </body>
            </html>
        ';

        $chapter2Html = '
            <html>
                <body>
                    <h1 class="chapter-title">Chapter 2</h1>
                    <div class="reading-content">
                        <img src="https://example.com/ch2/p1.jpg">
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            'https://example.com/comic/solo' => Http::response($comicDetailHtml, 200),
            'https://example.com/comic/solo/chapter-2' => Http::response($chapter2Html, 200),
        ]);

        // Access reader for Chapter 2
        $response = $this->get(route('comic.read', [
            'url' => 'https://example.com/comic/solo/chapter-2',
            'comic' => 'https://example.com/comic/solo',
        ]));

        $response->assertStatus(200);
        
        // Check current serial badge
        $response->assertSee('#2');

        // Check sequential previous chapter (Serial 1 -> Chapter 1) with clean slug URL
        $response->assertSee('Previous (#1)');
        $response->assertSee('/read/solo-leveling/chapter-1');

        // Check sequential next chapter (Serial 3 -> Chapter 3) with clean slug URL
        $response->assertSee('Next (#3)');
        $response->assertSee('/read/solo-leveling/chapter-3');

        // Check dropdown options contain serial number
        $response->assertSee('#1 - Chapter 1');
        $response->assertSee('#2 - Chapter 2');
        $response->assertSee('#3 - Chapter 3');
    }

    public function test_front_page_pagination_slices_items_and_renders_page_links()
    {
        // Generate 30 comic cards in HTML (exceeds 24 per page)
        $cards = '';
        for ($i = 1; $i <= 30; $i++) {
            $cards .= '<div class="story_item"><h3><a href="https://example.com/comic/hero-' . $i . '">Hero ' . $i . '</a></h3></div>';
        }
        $fakeHtml = '<html><body><div class="list-story">' . $cards . '</div></body></html>';

        Http::fake([
            config('comic.source_url') . '*' => Http::response($fakeHtml, 200),
            'https://example.com/*' => Http::response($fakeHtml, 200),
        ]);

        // Page 1
        $response = $this->get('/?page=1');
        $response->assertStatus(200);
        $response->assertSee('Hero 1');
        $response->assertSee('Hero 24');
        $response->assertDontSee('Hero 25'); // Should be on page 2!
        $response->assertSee('Page <span class="font-extrabold text-white">1</span>', false);
        $response->assertSee('Next');

        // Page 2
        $responsePage2 = $this->get('/?page=2');
        $responsePage2->assertStatus(200);
        $responsePage2->assertSee('Hero 25');
        $responsePage2->assertSee('Prev');
    }

    public function test_image_proxy_returns_304_with_matching_etag()
    {
        $imageUrl = 'https://example.com/pages/page99.jpg';
        $etag = '"' . md5($imageUrl) . '"';

        $response = $this->withHeaders([
            'If-None-Match' => $etag,
        ])->get(route('comic.proxy', [
            'url' => $imageUrl,
        ]));

        $response->assertStatus(304);
        $response->assertHeader('ETag', $etag);
    }

    public function test_duplicate_comics_across_different_sources_are_deduplicated()
    {
        $source1Html = '<html><body><div class="list-story">
            <div class="story_item"><h3><a href="https://source1.com/webtoon/touch-on">Touch On</a></h3></div>
            <div class="story_item"><h3><a href="https://source1.com/webtoon/solo-hero">Solo Hero</a></h3></div>
        </div></body></html>';

        $source2Html = '<html><body><div class="list-story">
            <div class="story_item"><h3><a href="https://source2.com/manga/touch-on">Touch On (Uncensored)</a></h3></div>
            <div class="story_item"><h3><a href="https://source2.com/manga/secret-mission">Secret Mission</a></h3></div>
        </div></body></html>';

        $source3Html = '<html><body><div class="list-story">
            <div class="story_item"><h3><a href="https://source3.com/comics/touch-on">Touch On [RAW]</a></h3></div>
        </div></body></html>';

        config(['comic.sources' => ['https://source1.com/', 'https://source2.com/', 'https://source3.com/']]);

        Http::fake([
            'https://source1.com/*' => Http::response($source1Html, 200),
            'https://source2.com/*' => Http::response($source2Html, 200),
            'https://source3.com/*' => Http::response($source3Html, 200),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        // Ensure Touch On is shown only ONCE (not 3 times!)
        $content = $response->getContent();
        $this->assertEquals(1, substr_count($content, 'title="Touch On"'), 'Touch On was duplicated across sources!');

        // Check that other distinct comics are still preserved
        $response->assertSee('Solo Hero');
        $response->assertSee('Secret Mission');
    }

    public function test_image_proxy_persists_to_disk_and_serves_zero_copy_without_quality_loss()
    {
        $fakeBytes = str_repeat('FULL_QUALITY_IMAGE_BYTES_PIXEL_PERFECT', 100);
        $imageUrl = 'https://example.com/comics/full-quality-page-1.jpg';

        Http::fake([
            $imageUrl => Http::response($fakeBytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        // First request: downloads and caches to disk
        $response1 = $this->get(route('comic.proxy', ['url' => $imageUrl]));
        $response1->assertStatus(200);
        $response1->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('max-age=2592000', $response1->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', $response1->headers->get('Cache-Control'));

        $diskContent1 = file_get_contents($response1->baseResponse->getFile()->getPathname());
        $this->assertEquals($fakeBytes, $diskContent1, 'Bytes were modified or compressed lossily!');

        // Second request: served from disk without calling Http::fake again
        Http::fake([
            $imageUrl => Http::response('SHOULD_NOT_BE_CALLED', 500),
        ]);

        $response2 = $this->get(route('comic.proxy', ['url' => $imageUrl]));
        $response2->assertStatus(200);
        $diskContent2 = file_get_contents($response2->baseResponse->getFile()->getPathname());
        $this->assertEquals($fakeBytes, $diskContent2, 'Disk cached image does not match original bytes!');
    }

    public function test_comic_card_click_url_shows_clean_comic_name_in_slug()
    {
        $fakeHtml = '
            <html>
                <body>
                    <div class="list-story">
                        <div class="story_item">
                            <h3><a href="https://example.com/comic/batman-adventures">Batman Adventures</a></h3>
                            <img src="https://example.com/covers/batman.jpg">
                        </div>
                    </div>
                </body>
            </html>
        ';

        $detailHtml = '
            <html>
                <body>
                    <h1 class="entry-title">Batman Adventures</h1>
                    <div class="chapter-list">
                        <div class="row">
                            <span><a href="https://example.com/comic/batman-adventures/chapter-1">Chapter 1: The Dark Knight</a></span>
                        </div>
                    </div>
                </body>
            </html>
        ';

        $chapterHtml = '
            <html>
                <body>
                    <h1 class="chapter-title">Chapter 1: The Dark Knight</h1>
                    <div class="reading-content">
                        <img src="https://example.com/batman/ch1/p1.jpg">
                    </div>
                </body>
            </html>
        ';

        Http::fake([
            config('comic.source_url') . '*' => Http::response($fakeHtml, 200),
            'https://example.com/comic/batman-adventures' => Http::response($detailHtml, 200),
            'https://example.com/comic/batman-adventures/chapter-1' => Http::response($chapterHtml, 200),
        ]);

        // 1. Front page shows clean slug URL for comic card
        $catalogResponse = $this->get('/');
        $catalogResponse->assertStatus(200);
        $catalogResponse->assertSee('/comic/batman-adventures');
        $catalogResponse->assertDontSee('/comic?url=');

        // 2. Visiting clean comic URL loads detail page
        $detailResponse = $this->get('/comic/batman-adventures');
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Batman Adventures');
        $detailResponse->assertSee('/read/batman-adventures/chapter-1');

        // 3. Visiting clean reader URL loads reader
        $readerResponse = $this->get('/read/batman-adventures/chapter-1');
        $readerResponse->assertStatus(200);
        $readerResponse->assertSee('Batman Adventures');
        $readerResponse->assertSee('Chapter 1');
    }
}
