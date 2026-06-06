<?php
function sg_artist_meta_e($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sg_artist_slugify($value): string {
  $slug = strtolower(trim((string) $value));
  $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
  return trim($slug, '-');
}

function sg_artist_image_url($image): string {
  $image = trim((string) $image);
  if ($image === '') {
    return 'https://sgproduction.music/assets/sg-logo.svg';
  }
  if (preg_match('#^https?://#i', $image)) {
    return $image;
  }
  if (str_starts_with($image, '/') || str_contains($image, '/')) {
    return 'https://sgproduction.music/' . ltrim($image, '/');
  }
  return 'https://sgproduction.music/uploads/artists/' . $image;
}

$artistTitle = 'Artists — SG Production';
$artistDescription = 'Explore SG Production artists and releases. Free direct download.';
$artistImage = 'https://sgproduction.music/assets/sg-logo.svg';
$artistUrl = 'https://sgproduction.music/artists';
$artistType = 'website';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/artists'), PHP_URL_PATH) ?: '/artists';
if (preg_match('#^/artist/([^/]+)#', $requestPath, $artistMatch)) {
  $artistSlug = sg_artist_slugify(rawurldecode($artistMatch[1]));
  $artistsFile = __DIR__ . '/data/artists.json';
  $artists = [];
  if (is_readable($artistsFile)) {
    $decodedArtists = json_decode((string) file_get_contents($artistsFile), true);
    if (is_array($decodedArtists) && array_is_list($decodedArtists)) {
      $artists = $decodedArtists;
    } elseif (is_array($decodedArtists) && is_array($decodedArtists['artists'] ?? null)) {
      $artists = $decodedArtists['artists'];
    }
  }
  foreach ($artists as $artist) {
    if (!is_array($artist)) {
      continue;
    }
    $candidateSlug = sg_artist_slugify($artist['slug'] ?? $artist['id'] ?? $artist['name'] ?? '');
    if ($candidateSlug !== $artistSlug) {
      continue;
    }
    $artistName = (string) ($artist['name'] ?? 'SG Production Artist');
    $artistTitle = $artistName . ' — SG Production';
    $artistDescription = 'Listen to ' . $artistName . ' tracks on SG Production. Free direct download.';
    $artistImage = sg_artist_image_url($artist['image'] ?? '');
    $artistUrl = 'https://sgproduction.music/artist/' . $artistSlug;
    $artistType = 'profile';
    break;
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XLSFX2N5MS"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XLSFX2N5MS');
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json?v=20260607-pwa-root">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="SG Production">
    <title><?= sg_artist_meta_e($artistTitle) ?></title>
    <script>
      (function() {
        var base = document.createElement("base");
        base.href = /^https?:$/.test(window.location.protocol) ? "/" : window.location.href.replace(/[^/]*$/, "");
        document.head.appendChild(base);
      })();
    </script>
    <meta name="description" content="<?= sg_artist_meta_e($artistDescription) ?>">
    <meta property="og:title" content="<?= sg_artist_meta_e($artistTitle) ?>">
    <meta property="og:description" content="<?= sg_artist_meta_e($artistDescription) ?>">
    <meta property="og:image" content="<?= sg_artist_meta_e($artistImage) ?>">
    <meta property="og:url" content="<?= sg_artist_meta_e($artistUrl) ?>">
    <meta property="og:type" content="<?= sg_artist_meta_e($artistType) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= sg_artist_meta_e($artistTitle) ?>">
    <meta name="twitter:description" content="<?= sg_artist_meta_e($artistDescription) ?>">
    <meta name="twitter:image" content="<?= sg_artist_meta_e($artistImage) ?>">
    <link rel="canonical" href="<?= sg_artist_meta_e($artistUrl) ?>">
    <link rel="icon" href="assets/sg-logo.svg" type="image/svg+xml">
    <link rel="icon" href="/icon-192.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=20260601-audit">
    <link rel="stylesheet" href="transitions.min.css?v=20260524-prod">
    <script src="transitions.min.js?v=20260530-external-links" defer></script>
    <script src="/pwa.js?v=20260607-pwa-root" defer></script>
    <script>
      // Keep clean live URLs, but make local file:// previews navigable.
      if (window.location.protocol === 'file:' || /^(127\.0\.0\.1|localhost|\[::1\])$/.test(window.location.hostname)) {
        function localPreviewHref(href) {
          if (!href) return '';
          if (href === '/') return 'index.html';
          if (href === '/tracks') return 'index.html?view=tracks';
          if (href === '/licensing') return 'index.html?view=licensing';
          if (href === '/about') return 'about.php';
          if (href === '/contact') return 'contact.php';
          if (href === '/artists') return 'artists.html';
          if (href === '/usage-policy' || href === 'usage-policy.php') return 'usage-policy.php';
          if (href.startsWith('/song/')) return 'index.html?song=' + encodeURIComponent(href.replace(/^\/song\//, ''));
          if (href.startsWith('/artist/')) return 'artists.html?artist=' + encodeURIComponent(href.replace(/^\/artist\//, ''));
          return '';
        }

        document.addEventListener('click', function(event) {
          var link = event.target.closest && event.target.closest('a[href]');
          if (!link) return;
          var nextHref = localPreviewHref(link.getAttribute('href'));
          if (!nextHref) return;
          event.preventDefault();
          window.location.href = nextHref;
        }, true);

        document.addEventListener('DOMContentLoaded', function() {
          document.querySelectorAll('a[href]').forEach(function(link) {
            var nextHref = localPreviewHref(link.getAttribute('href'));
            if (nextHref) link.setAttribute('href', nextHref);
          });
        });
      }
    </script>
    <script src="artists.min.js?v=20260525-nav-fix" defer></script>
  </head>
  <body>
    <header class="mobile-topbar" aria-label="Mobile navigation">
      <a class="mobile-brand" href="/" aria-label="SG Production home">
        <span class="nav-logo" aria-hidden="true">
          <svg class="sg-logo" viewBox="0 0 924.99 924.99" aria-hidden="true">
            <path d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"></path>
          </svg>
        </span>
        <span>SG Production</span>
      </a>
      <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </header>

    <div class="app-shell">
      <nav class="side-nav" aria-label="Primary">
        <div class="nav-section nav-main">
          <a class="nav-brand" href="/" aria-label="SG Production home" title="Home">
            <span class="nav-logo" aria-hidden="true">
              <svg class="sg-logo" viewBox="0 0 924.99 924.99" aria-hidden="true">
                <path d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"></path>
              </svg>
            </span>
            <span class="nav-label brand-label">SG Production</span>
          </a>

          <button class="nav-link" id="focusArtistSearch" type="button" aria-label="Search artists" title="Search">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
              </svg>
            </span>
            <span class="nav-label">Search</span>
          </button>
          <a class="nav-link" href="/tracks" aria-label="Music library" title="Music Library">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
              </svg>
            </span>
            <span class="nav-label">Music Library</span>
          </a>
          <a class="nav-link active-line" href="/artists" aria-label="Artists" title="Artists">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                <circle cx="9.5" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </span>
            <span class="nav-label">Artists</span>
          </a>
        </div>

        <div class="nav-spacer" aria-hidden="true"></div>

        <div class="nav-section nav-utility">
          <a class="nav-link" href="/usage-policy" data-policy-link aria-label="Usage policy" title="Usage Policy">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <path d="M14 2v6h6"></path>
                <path d="M16 13H8"></path>
                <path d="M16 17H8"></path>
                <path d="M10 9H8"></path>
              </svg>
            </span>
            <span class="nav-label">Usage Policy</span>
          </a>
          <a class="nav-link" href="/about" aria-label="About SG Production" title="About">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 16v-4"></path>
                <path d="M12 8h.01"></path>
              </svg>
            </span>
            <span class="nav-label">About</span>
          </a>
          <a class="nav-link" href="/contact" aria-label="Contact SG Production" title="Contact">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.91.33 1.8.63 2.65a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.43-1.2a2 2 0 0 1 2.11-.45c.85.3 1.74.51 2.65.63A2 2 0 0 1 22 16.92z"></path>
              </svg>
            </span>
            <span class="nav-label">Contact</span>
          </a>
        </div>

        <div class="nav-section nav-social">
          <a class="nav-link" href="https://www.youtube.com/@sgproductionindia" aria-label="YouTube" title="YouTube">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M22.54 6.42a2.8 2.8 0 0 0-1.97-1.98C18.83 4 12 4 12 4s-6.83 0-8.57.44a2.8 2.8 0 0 0-1.97 1.98A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.8 2.8 0 0 0 1.97 1.98C5.17 20 12 20 12 20s6.83 0 8.57-.44a2.8 2.8 0 0 0 1.97-1.98A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"></path>
                <path d="m10 15 5-3-5-3z"></path>
              </svg>
            </span>
            <span class="nav-label">YouTube</span>
          </a>
          <a class="nav-link" href="https://music.apple.com/in/artist/sg-production/1580814477" aria-label="Apple Music" title="Apple Music">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
              </svg>
            </span>
            <span class="nav-label">Apple Music</span>
          </a>
          <a class="nav-link" href="https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb?autoplay=true" aria-label="Spotify" title="Spotify">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M7.5 10.5c3-1 6.2-.7 9.3.8"></path>
                <path d="M8.2 13.2c2.4-.7 5-.5 7.5.7"></path>
                <path d="M9 15.7c1.8-.5 3.8-.3 5.5.5"></path>
              </svg>
            </span>
            <span class="nav-label">Spotify</span>
          </a>
          <a class="nav-link" href="https://www.instagram.com/sgproduction.music" aria-label="Instagram" title="Instagram">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <rect x="2" y="2" width="20" height="20" rx="5"></rect>
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M17.5 6.5h.01"></path>
              </svg>
            </span>
            <span class="nav-label">Instagram</span>
          </a>
        </div>
      </nav>

      <main class="page artists-page" id="top">
        <section class="section artist-section artist-directory" id="artistDirectory" aria-labelledby="artists-title">
          <div class="artist-title-row">
            <h1 id="artists-title">Artists</h1>
          </div>
          <div class="artist-toolbar">
            <label class="artist-search" for="artistSearchInput">
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
              </svg>
              <input id="artistSearchInput" type="search" autocomplete="off" placeholder="Search Artists">
            </label>
          </div>
          <div class="artist-grid" id="artistGrid"></div>
        </section>

        <section class="artist-profile-page" id="artistProfilePage" aria-labelledby="artistProfileName" hidden>
          <header class="artist-profile-hero">
            <div class="artist-profile-hero-bg" id="artistProfileBg" aria-hidden="true"></div>
            <a class="artist-profile-back" href="/artists">Artists</a>
            <div class="artist-profile-portrait">
              <img loading="lazy" id="artistProfileImage" src="assets/artist-photo-1.svg" alt="">
            </div>
            <div class="artist-profile-heading">
              <h1 id="artistProfileName">SG Production</h1>
            </div>
          </header>

          <section class="artist-profile-tracks" aria-labelledby="popularTracksTitle">
            <h2 id="popularTracksTitle">Releases</h2>
            <div class="artist-track-list track-grid" id="artistTrackList"></div>
          </section>

          <section class="artist-related" aria-labelledby="relatedArtistsTitle">
            <h2 id="relatedArtistsTitle">You might also like...</h2>
            <div class="artist-grid related-artist-grid" id="relatedArtistGrid"></div>
          </section>
        </section>

        <footer class="footer" id="contact">
          <p>© 2026 SG Production. All rights reserved.</p>
        </footer>
      </main>
    </div>
    <script>
      // Track page visit
      (function() {
        var page = location.pathname.split('/').pop().replace('.html', '') || 'index';
        fetch('api/track-visit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'page=' + encodeURIComponent(page)
        }).catch(function() {});
      })();
    </script>

<div class="beta-backdrop" id="betaBackdrop">
  <div class="beta-popup" id="betaPopup">

    <!-- CLOSE -->
    <button class="beta-close" id="betaClose" aria-label="Close">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>

    <!-- DEFAULT STATE -->
    <div id="betaDefault">

      <!-- ICON + BADGE -->
      <div class="beta-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 924.99 924.99" width="32" height="32">
          <rect width="924.99" height="924.99" rx="160" fill="#020608"/>
          <path fill="#fff" d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"/>
        </svg>
      </div>
      <div class="beta-tag">
        <span class="beta-tag-dot"></span>
        Beta Version
      </div>

      <!-- TITLE -->
      <div class="beta-title">Welcome to SG Production</div>

      <!-- DESC -->
      <p class="beta-desc">
        You're one of the first to experience our new music download platform. We're still ironing out the details and you may encounter <strong>glitches or incomplete features</strong> along the way.
      </p>

      <!-- WHAT TO EXPECT -->
      <ul class="beta-list">
        <li>
          <span class="li-icon">⚡</span>
          Some pages or features may not work perfectly yet
        </li>
        <li>
          <span class="li-icon">🎵</span>
          Music library is being uploaded — more tracks coming soon
        </li>
        <li>
          <span class="li-icon">📱</span>
          Mobile experience is being continuously improved
        </li>
      </ul>

      <div class="beta-divider"></div>

      <!-- SUGGESTION FORM -->
      <div class="beta-form">
        <div class="beta-form-label">Share your feedback</div>
        <input type="hidden" name="_subject" value="SG Production Beta Feedback">
        <textarea class="beta-textarea" id="betaFeedback" placeholder="Found a bug? Have a suggestion? Tell us anything..."></textarea>
      </div>

      <!-- ACTIONS -->
      <div class="beta-actions">
        <button class="btn-primary" id="betaSubmit">
          Send Feedback
        </button>
        <button class="btn-secondary" id="betaDismiss">
          Got it
        </button>
      </div>

      <div class="beta-divider"></div>

      <div class="beta-footer">
        This popup will only show once. You can always reach us via the Contact page.
      </div>

    </div>

    <!-- SUCCESS STATE -->
    <div class="beta-success" id="betaSuccess">
      <div class="beta-success-icon">🙏</div>
      <h3>Thank you for your feedback!</h3>
      <p>Your suggestion helps us improve SG Production.<br>We'll take a look and get back to you soon.</p>
      <button class="btn-primary" id="betaSuccessClose" style="margin-top:8px;max-width:160px">
        Close
      </button>
    </div>

  </div>
</div>

<script>
  (function initBetaFeedbackPopup() {
    if (window.sgBetaPopupInit) return;
    window.sgBetaPopupInit = true;

    const backdrop = document.getElementById('betaBackdrop');
    const closeBtn = document.getElementById('betaClose');
    const dismissBtn = document.getElementById('betaDismiss');
    const submitBtn = document.getElementById('betaSubmit');
    const successClose = document.getElementById('betaSuccessClose');
    const defaultState = document.getElementById('betaDefault');
    const successState = document.getElementById('betaSuccess');

    if (!backdrop || !closeBtn || !dismissBtn || !submitBtn || !successClose || !defaultState || !successState) return;

    if (localStorage.getItem('sg_beta_dismissed') === '1') {
      backdrop.style.display = 'none';
      return;
    }

    function closePopup() {
      backdrop.style.opacity = '0';
      backdrop.style.transition = 'opacity .2s ease';
      setTimeout(() => backdrop.style.display = 'none', 200);
      localStorage.setItem('sg_beta_dismissed', '1');
    }

    closeBtn.addEventListener('click', closePopup);
    dismissBtn.addEventListener('click', closePopup);

    submitBtn.addEventListener('click', async function() {
      const text = document.getElementById('betaFeedback')
        .value.trim();

      if (!text) {
        const ta = document.getElementById('betaFeedback');
        ta.focus();
        ta.style.borderColor = 'rgba(255,69,58,0.5)';
        setTimeout(() => {
          ta.style.borderColor = 'rgba(255,255,255,0.1)';
        }, 2000);
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
      submitBtn.style.opacity = '0.7';

      try {
        const response = await fetch(
          'https://formspree.io/f/xkoealzj', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            message: text,
            _subject: 'SG Production Beta Feedback'
          })
        });

        if (response.ok) {
          defaultState.style.display = 'none';
          successState.style.display = 'flex';
          successState.style.animation =
            'pop-in .3s cubic-bezier(0.34,1.56,0.64,1) both';
        } else {
          throw new Error('Failed');
        }
      } catch (error) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Feedback';
        submitBtn.style.opacity = '1';
        alert('Something went wrong. Please try again.');
      }
    });

    successClose.addEventListener('click', closePopup);

    backdrop.addEventListener('click', function(e) {
      if (e.target === backdrop) closePopup();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && backdrop.style.display !== 'none') closePopup();
    });

    setTimeout(function() {
      const dismissed = localStorage.getItem(
        'sg_beta_dismissed'
      );
      if (!dismissed) {
        backdrop.style.display = 'flex';
      }
    }, 1500);
  })();
</script>

  </body>
</html>
