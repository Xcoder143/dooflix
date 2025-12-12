# dooflix
A high-fidelity Netflix-style child theme for DooPlay. Features a cinematic billboard, live AJAX search, custom player UI, and a mobile-first design overhaul. (BETA)

# DooPlay Child Theme - Netflix UI (BETA)

This is a comprehensive child theme for the DooPlay WordPress theme. It creates a high-fidelity "Netflix-style" user interface, overhauling the homepage, header, single movie/series pages, and download link tables.

> **⚠️ STATUS: BETA / WORK IN PROGRESS**
> This theme is currently in **BETA**. Some features may be incomplete, unoptimized, or contain placeholder assets. Use in a production environment with caution.

## 🚀 Features & Updates

### 🏠 Homepage (Native Netflix Style)
* **Billboard Slider:** Full-width hero section with auto-fading background, video info, and "Play/More Info" buttons.
* **Lazy Loading:** Implemented `IntersectionObserver` for posters and backgrounds to improve performance.
* **Netflix Rows:** Horizontal scrolling sliders for "Trending Now," "Recently Added," and genre-specific categories.
* **Hover Effects:** Desktop hover-cards that expand to show the trailer, match rating, age rating, and genres.
* **Mobile Optimized:** Specifically designed mobile billboard and vertical layouts for mobile devices.

### 🎩 Header (Sticky & Dynamic)
* **Scroll Effect:** Header turns from transparent to black (`nav_black`) upon scrolling.
* **Live AJAX Search:** "Ghost Card" effect on search results with instant visual feedback.
* **Badges:** Dynamic badges for 4K, HDR, 1080p in search results.
* **Mobile Menu:** Slide-out mobile navigation drawer.

### 🎬 Single Movie Page
* **Cinematic Hero:** Full-screen backdrop with gradient overlays.
* **3D Tilt Cards:** Cast and Director cards have a 3D perspective tilt effect on mouse hover.
* **Fake Player UI:** A custom "Click to Play" overlay before loading the actual iframe/video source.
* **Source Tabs:** AJAX-free tab switching for different video servers.
* **Metadata:** Dynamic "Match %" calculation based on ratings.

### 📺 Single Series Page
* **Season Selector:** Custom AJAX-like tab switching for seasons (no page reload required).
* **Episode List:** Detailed episode rows with thumbnails, plot summaries, and click-to-play functionality.
* **Network Branding:** Auto-detects networks (Netflix, HBO, Disney+, etc.) and displays their SVG logo on the banner.

### 🔗 "Netflinks" Download Module
* **Visual Overhaul:** Replaces standard HTML tables with a grid of "Cards".
* **Server Icons:** Auto-detects domains (Mega, GDrive, Telegram) and applies specific icons.
* **Quality Pills:** SVG-based badges for 4K, 1080p, 720p, and SD.
* **Skeleton Loading:** CSS shimmer effect while links are loading.

## 🛠️ Installation

1.  Ensure the parent theme **DooPlay** is installed.
2.  Download this repository as a ZIP file.
3.  Go to WordPress Dashboard > Appearance > Themes > Add New > Upload.
4.  Upload the ZIP and Activate.
5.  Go to **DooPlay Options** and ensure the specific settings for "Home" and "Player" are configured to allow the child theme overrides.

## 🚧 Known Issues & Incomplete Parts

* **Empty SVGs:** Some quality badge SVGs (480p, 720p) are currently empty placeholders and need content.
* **CSS Cleanup:** There is legacy CSS in some files that needs refactoring (removed unused classes).
* **Hardcoded Fallbacks:** Some image fallbacks point to specific paths that may need adjustment for your specific domain.
* **Translation:** Not all strings are fully localized yet.

## 📝 Changelog

**v1.0 (Beta)**
* Initial commit of the Netflix UI overhaul.
* Integrated `netflinks.js` for download tables.
* Added `netflix-header.js` for live search.
* Refactored `functions.php` to enqueue distinct assets for single posts vs home.

---
*Disclaimer: This is a fan-made modification. Netflix is a registered trademark of Netflix, Inc.*
