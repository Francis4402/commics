# DarkComic — Multi-Source Scraped Comic Reader

A high-performance comic scraping and reading web platform built with Laravel 12 and PHP 8.2+.

Featuring **multi-source aggregation** (loads comics from all your links at once with zero manual switching), a **pure pitch-black OLED dark theme**, **100% mobile/tablet/desktop responsive design**, and an **embedded in-website reader** that never redirects users to external source websites.

---

## ✨ Key Features

1. **Multi-Source Aggregator (No Manual Switching Needed)**:
   - Configure one or multiple comma-separated links in `.env`:
     ```env
     COMIC_SOURCE_URL=https://manhwa18.cc/,https://manga18fx.com/,https://asurascans.com/
     ```
   - All sources load automatically together onto your homepage feed into a unified catalog.
   - Origin badges (`Manhwa18`, `Manga18fx`, `AsuraScans`) and instant filter chips allow 1-tap filtering without reloading.

2. **Zero External Redirects on Reading**:
   - Clicking any comic chapter opens the dedicated in-app reader (`/read?url=...`).
   - All page images are displayed within your website.

3. **Bypass 403 Forbidden / Hotlinking Restrictions**:
   - Built-in secure image proxy (`/proxy/image?url=...`) with simulated browser headers and domain referers.
   - Supports `COMIC_COOKIE` for Cloudflare `cf_clearance` cookies if scraping protected sites.

4. **100% Fully Responsive Layout**:
   - **Mobile (<640px)**: 2-column touch-friendly cards, compact sticky navbar, mobile search bar, full-width thumb-friendly CTAs, swipeable filter chips.
   - **Tablet (640px–1024px)**: 3 to 4-column grid, responsive side-by-side details, 2-column chapter list.
   - **Desktop (>1024px)**: 5 to 6-column expansive poster grid, custom reader width selector (Narrow, Medium, Wide, Fullscreen).

5. **Pure Pitch-Black Dark Theme**:
   - OLED black (`#09090b` / `#050507`), sleek charcoal containers (`#111115`), subtle zinc borders (`#27272a`), and high-contrast crimson/ruby accents (`#e11d48`). Zero blue tint.

---

## 🚀 Getting Started

### 1. Configure Sources in `.env`

```env
# Comic Scraper Settings
COMIC_SOURCE_URL=https://manhwa18.cc/,https://manga18fx.com/,https://asurascans.com/
COMIC_COOKIE=
COMIC_CACHE_TTL=3600
COMIC_PROXY_IMAGES=true
```

### 2. Start the Server

```powershell
php artisan serve
```

Visit [http://localhost:8000](http://localhost:8000).

---

## 🧪 Automated Testing

```powershell
php artisan test
```
All 7 feature and unit tests pass out of the box.
