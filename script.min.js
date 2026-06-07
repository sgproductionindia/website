const tracks = [];

const latestGrid = document.querySelector("#latestGrid");
const latestSection = document.querySelector("#latest");
const trackGrid = document.querySelector("#trackGrid");
const trackPagination = document.querySelector("#trackPagination");
const loadMoreButton = document.querySelector("#loadMore");
const player = document.querySelector("#player");
const playerCover = document.querySelector("#playerCover");
const playerTitle = document.querySelector("#playerTitle");
const playerGenre = document.querySelector("#playerGenre");
const playerToggle = document.querySelector("#playerToggle");
const playerPrev = document.querySelector("#playerPrev");
const playerNext = document.querySelector("#playerNext");
const playerLike = document.querySelector("#playerLike");
const playerMute = document.querySelector("#playerMute");
const volumeSlider = document.querySelector("#volumeSlider");
const playerVolBtn = document.querySelector(".player-volume");
const playerVolPopup = document.querySelector("#volumePopup");
const progressShell = document.querySelector("#progressShell");
const progressBar = document.querySelector("#progressBar");
const progressTime = document.querySelector("#progressTime");
const progressElapsed = document.querySelector("#progressElapsed");
const progressTotal = document.querySelector("#progressTotal");
const sideNav = document.querySelector(".side-nav");
const mobileMenuToggle = document.querySelector("#mobileMenuToggle");
const sectionNavLinks = Array.from(document.querySelectorAll(".nav-link[data-section-nav]"));
const activeSections = ["latest", "all-tracks", "licensing", "contact"];
const songPage = document.querySelector("#songPage");
const songAd = document.querySelector("#songAd");
const songBack = document.querySelector("#songBack");
const songGenre = document.querySelector("#songGenre");
const songPageTitle = document.querySelector("#songPageTitle");
const songArtist = document.querySelector("#songArtist");
const songPlay = document.querySelector("#songPlay");
const songWaveform = document.querySelector("#songWaveform");
const songDuration = document.querySelector("#songDuration");
const songDownload = document.querySelector("#songDownload");
const creditText = document.querySelector("#creditText");
const searchOverlay = document.querySelector("#searchOverlay");
const searchInput = document.querySelector("#siteSearchInput");
const searchResults = document.querySelector("#searchResults");
const searchClose = document.querySelector("#searchClose");
const playerClose = document.querySelector("#playerClose");
const NEW_BADGE_DAYS = 7;
const NEW_BADGE_MS = NEW_BADGE_DAYS * 24 * 60 * 60 * 1000;
const shellView = document.createElement("section");
shellView.className = "shell-page-view";
shellView.id = "shellPageView";
shellView.hidden = true;
songPage.insertAdjacentElement("afterend", shellView);
const LOCAL_PREVIEW_TRACKS = [
  {
    id: "banger",
    title: "Banger",
    artist: "SG Production",
    artistId: "sg-production",
    genre: "Original Mix",
    duration: "2:07",
    cover: "assets/cover-1.jpg",
    previewUrl: "",
    downloadUrl: "",
    creditText: "Demo preview track for layout testing.",
    createdAt: "2026-05-30",
    isNew: true,
    isFeatured: true,
    likes: 0,
    bpm: 128,
    tone: 110,
    wave: "sine"
  }
];

if (player) {
  player.style.display = "none";
  player.classList.remove("is-visible");
}

if (playerCover) {
  playerCover.textContent = "";
  playerCover.style.backgroundImage = "";
  playerCover.style.backgroundColor = "#111";
}

let selectedTrack = null;
let audioContext = null;
let activeSource = null;
let activeGain = null;
let activeAudio = null;
let activeAudioUrl = "";
let startedAt = 0;
let pausedAt = 0;
let isPlaying = false;
let playingTrackId = null;
let animationFrame = 0;
let waveformResizeFrame = 0;
let audioPlayToken = 0;
let waveformRenderToken = 0;
let currentVolume = 0.8;
let isMuted = false;
let playerVolTimer = 0;
let pendingPlayerSeekFraction = null;
let playerSeekActive = false;
const likeSyncInFlight = new Set();
const waveformCache = new Map();
const waveformInflight = new Map(); // `${track.id}:${barCount}` → Promise<string[]|null>
let allTracks = [];
let allTracksPage = 1;
let shellArtists = [];
let shellPolicyObserver = null;
let lastTrackGridColumnCount = 0;
let trackGridResizeTimer = 0;
let siteSettings = {
  site: {
    title: "SG Production",
    tagline: "Official Music Website",
    youtubeHeading: "Subscribe on YouTube",
    youtubeText: "Watch latest music releases, behind-the-scenes clips, and official SG Production updates on the YouTube channel.",
    contactEmail: ""
  },
  links: {
    instagram: "https://www.instagram.com/sgproduction.music",
    spotify: "https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb?autoplay=true",
    appleMusic: "https://music.apple.com/in/artist/sg-production/1580814477",
    youtube: "https://www.youtube.com/@sgproductionindia"
  },
  seo: {
    metaDescription: "SG Production is an independent artist music catalog with direct downloads, latest releases, and original tracks.",
    ogImage: "assets/sg-logo.svg",
    favicon: "assets/sg-logo.svg"
  },
  catalog: {
    latestCount: 5,
    paginationDemoPages: 12
  },
  advertising: {
    enabled: false,
    mediaUrl: "",
    mediaType: "",
    linkUrl: "",
    gridAd: {
      enabled: false,
      imageUrl: "",
      name: "",
      subtext: "",
      buttonText: "Learn more",
      buttonColor: "#ffffff",
      buttonTextColor: "#000000",
      linkUrl: "",
      position: 8
    }
  }
};

function showPlayer() {
  if (!player || tracks.length === 0) {
    return;
  }

  player.style.display = "";
  player.classList.add("is-visible", "active");
  document.body.classList.add("player-visible");
}

function hidePlayer() {
  if (!player) {
    return;
  }

  player.style.display = "none";
  player.classList.remove("is-visible", "active", "is-playing");
  document.body.classList.remove("player-visible");
}

function setAudioLoadingState(loading) {
  player?.classList.toggle("is-buffering", loading);
  playerToggle?.classList.toggle("is-buffering", loading);
  songPlay?.classList.toggle("is-buffering", loading);
  player?.setAttribute("aria-busy", loading ? "true" : "false");
}

const PREVIEW_SECONDS = 12;
const TRACKS_PER_PAGE = window.TRACKS_PER_PAGE || 10;
const WAVEFORM_MAX_BARS = 180;
const WAVEFORM_ANALYSIS_SECONDS = 30;
const hasAdOnLoad = true;
const initialTracks = hasAdOnLoad ? TRACKS_PER_PAGE - 1 : TRACKS_PER_PAGE;
let tracksShown = getTracksToShow(initialTracks);
let allTracksPerPage = TRACKS_PER_PAGE;
let demoTrackPageCount = 12;
let latestTrackCount = 5;

function setMobileMenu(open) {
  document.body.classList.toggle("menu-open", open);
  mobileMenuToggle.setAttribute("aria-expanded", String(open));
  mobileMenuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
}

function slugClass(value) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
}

function canUseCleanUrls() {
  return /^https?:$/.test(window.location.protocol) && !/^(127\.0\.0\.1|localhost|\[::1\])$/.test(window.location.hostname);
}

function trackSlug(track) {
  return slugClass(track.title || track.id);
}

function trackUrl(track) {
  return canUseCleanUrls() ? `/song/${trackSlug(track)}` : `index.html?song=${encodeURIComponent(trackSlug(track))}`;
}

function normalizeLocalPreviewLinks() {
  if (canUseCleanUrls()) {
    return;
  }

  document.querySelectorAll('a[href^="/"]').forEach((link) => {
    const href = link.getAttribute("href") || "/";

    if (href === "/") {
      link.setAttribute("href", "index.html");
    } else if (href === "/tracks") {
      link.setAttribute("href", "index.html?view=tracks");
    } else if (href === "/licensing") {
      link.setAttribute("href", "index.html?view=licensing");
    } else if (href === "/about") {
      link.setAttribute("href", "about.php");
    } else if (href === "/contact") {
      link.setAttribute("href", "contact.php");
    } else if (href === "/usage-policy") {
      link.setAttribute("href", "usage-policy.php");
    } else if (href === "/artists") {
      link.setAttribute("href", "artists.html");
    } else if (href.startsWith("/song/")) {
      link.setAttribute("href", `index.html?song=${encodeURIComponent(href.replace(/^\/song\//, ""))}`);
    } else if (href.startsWith("/artist/")) {
      link.setAttribute("href", `artists.html?artist=${encodeURIComponent(href.replace(/^\/artist\//, ""))}`);
    }
  });
}

function downloadEndpoint(track) {
  return `api/download.php?id=${encodeURIComponent(track.id || trackSlug(track))}`;
}

function isLocalAudioPath(url) {
  return /^\/?uploads\/audio\//.test(String(url || ""));
}

function escapeHTML(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[char]);
}

function normalizeMediaPath(value) {
  const path = String(value || "").trim();

  if (!path) {
    return "";
  }

  if (/^(https?:|data:|blob:)/i.test(path)) {
    return path;
  }

  if (window.location.protocol === "file:" && path.startsWith("/")) {
    return path.replace(/^\/+/, "");
  }

  return path;
}

function normalizeUploadedTrack(track) {
  if (!track || !track.title) {
    return null;
  }

  const id = String(track.id || track.slug || slugClass(track.title));
  const slug = String(track.slug || slugClass(track.title || id));
  const rawDownloadUrl = track.downloadUrl || "";
  const rawAudioUrl = track.audioUrl || track.fullAudioUrl || track.fullUrl || track.fileUrl || track.file || track.audio || "";
  const fullAudioUrl = [
    track.fullAudioUrl,
    track.fullUrl,
    rawDownloadUrl,
    rawAudioUrl,
    track.fileUrl,
    track.file,
    track.audio
  ].map((value) => String(value || "").trim()).find((value) => isLocalAudioPath(value)) || "";
  const previewUrl = track.previewUrl || rawAudioUrl || fullAudioUrl;
  const cover = track.cover || track.coverUrl || track.coverPath || track.image || track.imageUrl || track.artwork || track.thumbnail || "";

  return {
    id,
    slug,
    title: track.title,
    artist: track.artist || "SG Production",
    genre: track.genre || "Soundcheck",
    duration: track.duration || "0:0",
    cover: cover || "assets/cover-1.jpg",
    coverWebp: track.coverWebp || track.cover_webp || track.webp || "",
    previewUrl,
    audioUrl: rawAudioUrl,
    fullAudioUrl,
    downloadUrl: rawDownloadUrl,
    creditText: track.creditText || "",
    likes: Number(track.likes ?? track.likeCount ?? 0) || 0,
    createdAt: track.createdAt || track.uploadedAt || track.date || "",
    isNew: Boolean(track.isNew),
    isFeatured: Boolean(track.isFeatured || track.isNew),
    artistId: track.artistId || "sg-production",
    bpm: Number(track.bpm) || 124,
    tone: Number(track.tone) || 146.83,
    wave: track.wave || "sine"
  };
}

function preferredTrackCover(track) {
  return normalizeMediaPath(track?.coverWebp || track?.cover || "assets/cover-1.jpg");
}

function playerCoverCandidates(track) {
  const candidates = [
    track?.coverWebp,
    track?.cover,
    track?.coverUrl,
    track?.coverPath,
    track?.image,
    track?.imageUrl,
    track?.artwork,
    track?.thumbnail,
    "assets/cover-1.jpg"
  ].map(normalizeMediaPath).filter(Boolean);

  return [...new Set(candidates)];
}

function setPlayerCover(track) {
  if (!playerCover) {
    return;
  }

  const covers = playerCoverCandidates(track);
  let image = playerCover.querySelector("img");

  if (!image) {
    image = document.createElement("img");
    image.loading = "lazy";
    image.decoding = "async";
    playerCover.appendChild(image);
  }

  image.alt = track?.title ? `${track.title} cover art` : "";
  image.style.opacity = covers.length ? "1" : "0";

  const applyCover = (index) => {
    const coverUrl = covers[index] || "";

    if (!coverUrl) {
      image.removeAttribute("src");
      image.style.opacity = "0";
      playerCover.style.backgroundImage = "";
      return;
    }

    image.onerror = () => applyCover(index + 1);
    image.onload = () => {
      image.style.opacity = "1";
      playerCover.style.backgroundImage = `url("${coverUrl}")`;
    };

    image.src = coverUrl;
    playerCover.style.backgroundImage = `url("${coverUrl}")`;
  };

  playerCover.style.backgroundColor = "#111";
  applyCover(0);
}

function updatePlayerTitle(title) {
  if (!playerTitle) {
    return;
  }

  const text = String(title || "Select a track");
  playerTitle.textContent = text;
  playerTitle.classList.remove("is-scrolling");

  window.setTimeout(() => {
    const wrapper = playerTitle.parentElement;

    if (!wrapper) {
      return;
    }

    if (playerTitle.scrollWidth > wrapper.clientWidth) {
      playerTitle.textContent = `${text}\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0${text}`;
      playerTitle.classList.add("is-scrolling");
    }
  }, 100);
}

function applyPlayerVolume() {
  const volume = isMuted ? 0 : currentVolume;

  if (activeAudio) {
    activeAudio.volume = volume;
    activeAudio.muted = isMuted;
    window.currentAudio = activeAudio;
  }

  if (activeGain) {
    activeGain.gain.value = 0.82 * volume;
  }

  playerMute?.classList.toggle("is-muted", isMuted);
  playerMute?.setAttribute("aria-label", isMuted ? "Unmute" : "Mute");
}

function updatePlayerLike(track) {
  if (!playerLike) {
    return;
  }

  const liked = Boolean(track?.id && localStorage.getItem(`liked_${track.id}`) === "true");
  playerLike.classList.toggle("is-liked", liked);
  playerLike.setAttribute("aria-pressed", String(liked));
}

function getLikeClientId() {
  const key = "sg_like_client_id";
  let id = localStorage.getItem(key);

  if (!id) {
    id = window.crypto?.randomUUID ? window.crypto.randomUUID() : `client-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(key, id);
  }

  return id;
}

function sendTrackLike(track, liked) {
  if (!track?.id || !/^https?:$/.test(window.location.protocol)) {
    return Promise.resolve(null);
  }

  const payload = JSON.stringify({
    id: track.id,
    slug: track.slug || track.id,
    action: liked ? "like" : "unlike",
    client_id: getLikeClientId()
  });
  const postLike = (endpoint) => fetch(endpoint, {
    method: "POST",
    cache: "no-store",
    credentials: "same-origin",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: payload
  }).then((response) => (response.ok ? response.json() : null)).catch(() => null);

  return postLike("/?api=like")
    .then((data) => (data?.ok ? data : postLike("/api/like")))
    .then((data) => (data?.ok ? data : postLike("/api/like.php")))
    .then((data) => {
      if (!data || !data.ok || typeof data.likes === "undefined") {
        return;
      }

      track.likes = Number(data.likes) || 0;
      const match = tracks.find((item) => item.id === track.id);
      if (match) {
        match.likes = track.likes;
      }
      const allMatch = allTracks.find((item) => item.id === track.id);
      if (allMatch) {
        allMatch.likes = track.likes;
      }

      return data;
    })
    .catch(() => null);
}

function syncStoredLikeWithServer(track) {
  if (!track?.id || !/^https?:$/.test(window.location.protocol)) {
    return;
  }

  const likedKey = `liked_${track.id}`;

  if (localStorage.getItem(likedKey) !== "true" || likeSyncInFlight.has(track.id)) {
    return;
  }

  likeSyncInFlight.add(track.id);
  sendTrackLike(track, true).then((data) => {
    if (data?.ok) {
      localStorage.setItem(`liked_sync_${track.id}`, "true");
    }
  }).finally(() => {
    likeSyncInFlight.delete(track.id);
  });
}

function syncAllStoredLikesWithServer() {
  if (!/^https?:$/.test(window.location.protocol)) {
    return;
  }

  allTracks.forEach((track) => syncStoredLikeWithServer(track));
}

function playTrackByOffset(offset) {
  if (!tracks.length) {
    return;
  }

  const currentIndex = selectedTrack ? tracks.findIndex((track) => track.id === selectedTrack.id) : -1;
  const nextIndex = currentIndex >= 0
    ? (currentIndex + offset + tracks.length) % tracks.length
    : offset > 0 ? 0 : tracks.length - 1;
  playTrack(tracks[nextIndex]);
}

function shouldShowNewBadge(track) {
  if (!track?.isNew) {
    return false;
  }

  const uploadedAt = Date.parse(track.createdAt || track.uploadedAt || track.date || "");

  if (!Number.isFinite(uploadedAt)) {
    return false;
  }

  const age = Date.now() - uploadedAt;
  return age >= 0 && age < NEW_BADGE_MS;
}

async function loadUploadedTracks() {
  try {
    const response = await fetch("data/tracks.json", { cache: "no-store" });

    if (!response.ok) {
      return window.location.protocol === "file:" ? LOCAL_PREVIEW_TRACKS.map(normalizeUploadedTrack).filter(Boolean) : [];
    }

    const data = await response.json();
    const uploadedTracks = Array.isArray(data) ? data : data.tracks;

    if (!Array.isArray(uploadedTracks)) {
      return [];
    }

    return uploadedTracks.map(normalizeUploadedTrack).filter(Boolean);
  } catch {
    return window.location.protocol === "file:" ? LOCAL_PREVIEW_TRACKS.map(normalizeUploadedTrack).filter(Boolean) : [];
  }
}

async function loadSiteSettings() {
  try {
    const response = await fetch("data/settings.json", { cache: "no-store" });

    if (!response.ok) {
      return siteSettings;
    }

    const settings = await response.json();
    return {
      ...siteSettings,
      ...settings,
      site: {
        ...siteSettings.site,
        ...(settings.site || {})
      },
      links: {
        ...siteSettings.links,
        ...(settings.links || {})
      },
      seo: {
        ...siteSettings.seo,
        ...(settings.seo || {})
      },
      catalog: {
        ...siteSettings.catalog,
        ...(settings.catalog || {})
      },
      advertising: {
        ...siteSettings.advertising,
        ...(settings.advertising || {})
      }
    };
  } catch (error) {
    return siteSettings;
  }
}

function setLink(selector, href) {
  const elements = document.querySelectorAll(selector);

  elements.forEach((element) => {
    if (!href) {
      element.hidden = true;
      return;
    }

    element.hidden = false;
    element.href = href;
  });
}

function absoluteAssetUrl(path) {
  if (!path) {
    return "";
  }

  try {
    return new URL(path, window.location.origin).href;
  } catch {
    return path;
  }
}

function setMetaTag(attribute, name, value) {
  if (!value) {
    return;
  }

  let tag = document.head.querySelector(`meta[${attribute}="${name}"]`);

  if (!tag) {
    tag = document.createElement("meta");
    tag.setAttribute(attribute, name);
    document.head.append(tag);
  }

  tag.content = value;
}

function setIconLink(rel, href) {
  if (!href) {
    return;
  }

  let link = document.head.querySelector(`link[rel="${rel}"]`);

  if (!link) {
    link = document.createElement("link");
    link.rel = rel;
    document.head.append(link);
  }

  link.href = href;
}

function setCanonicalLink(href) {
  if (!href) {
    return;
  }

  let link = document.head.querySelector('link[rel="canonical"]');

  if (!link) {
    link = document.createElement("link");
    link.rel = "canonical";
    document.head.append(link);
  }

  link.href = href;
}

function updateShareMeta(title, description, image, url) {
  const shareTitle = title || siteSettings.site.title;
  const shareDescription = description || siteSettings.seo.metaDescription || siteSettings.site.tagline;
  const shareImage = absoluteAssetUrl(image || siteSettings.seo.ogImage || "assets/cover-1.jpg");
  const shareUrl = url || window.location.href.split("#")[0] || window.location.href;

  setMetaTag("name", "description", shareDescription);
  setMetaTag("property", "og:title", shareTitle);
  setMetaTag("property", "og:description", shareDescription);
  setMetaTag("property", "og:image", shareImage);
  setMetaTag("property", "og:url", shareUrl);
  setMetaTag("property", "og:type", "website");
  setMetaTag("name", "twitter:card", "summary_large_image");
  setMetaTag("name", "twitter:title", shareTitle);
  setMetaTag("name", "twitter:description", shareDescription);
  setMetaTag("name", "twitter:image", shareImage);
  setCanonicalLink(shareUrl);
}

function songMetaDescription(track) {
  return `${track.title} by SG Production. Listen and download free at sgproduction.music`;
}

function routeMeta(path = "/") {
  const cleanPath = path.replace(/\/+$/g, "") || "/";
  const site = siteSettings.site;
  const seo = siteSettings.seo;

  if (cleanPath === "/tracks") {
    return {
      title: `Music Library | ${site.title}`,
      description: `Browse latest SG Production tracks, preview songs, and download original releases directly from the music library.`,
      path: "/tracks"
    };
  }

  if (cleanPath === "/licensing") {
    return {
      title: `Licensing | ${site.title}`,
      description: `Read SG Production music usage and licensing information for creators, reels, edits, and commercial projects.`,
      path: "/licensing"
    };
  }

  return {
    title: `${site.title} | Direct Music Downloads`,
    description: seo.metaDescription || site.tagline,
    path: "/"
  };
}

function applyRouteMeta(path = "/") {
  const meta = routeMeta(path);
  document.title = meta.title;
  updateShareMeta(meta.title, meta.description, siteSettings.seo.ogImage, siteUrl(meta.path));
}

function publicRoutePath(href) {
  const raw = String(href || "").trim();
  if (!raw || raw.startsWith("#") || raw.startsWith("mailto:") || raw.startsWith("tel:")) return "";

  let path = raw;
  try {
    const url = new URL(raw, window.location.href);
    if (/^https?:$/.test(url.protocol) && url.origin !== window.location.origin) return "";
    path = `${url.pathname}${url.search}`;
  } catch {
    path = raw.replace(/^\.\//, "");
  }

  const clean = path.split("?")[0].replace(/\/+$/g, "") || "/";
  const leaf = clean.split("/").pop() || clean;
  const params = new URLSearchParams(path.split("?")[1] || "");

  if (clean === "/" || clean === "/index.html" || leaf === "index.html") return "/";
  if (clean === "/tracks" || params.get("view") === "tracks") return "/tracks";
  if (clean === "/licensing" || params.get("view") === "licensing") return "/licensing";
  if (clean === "/about" || clean === "/about.php" || leaf === "about.php") return "/about";
  if (clean === "/contact" || clean === "/contact.php" || leaf === "contact.php") return "/contact";
  if (clean === "/usage-policy" || clean === "/usage-policy.php" || leaf === "usage-policy.php") return "/usage-policy";
  if (clean === "/artists" || clean === "/artists.html" || leaf === "artists.html") return "/artists";
  if (clean.startsWith("/artist/")) return clean;

  return "";
}

function publicRouteUrl(path) {
  if (!path) return "";
  if (!canUseCleanUrls()) {
    if (path === "/") return "index.html";
    if (path === "/tracks") return "index.html?view=tracks";
    if (path === "/licensing") return "index.html?view=licensing";
    if (path === "/about") return "about.php";
    if (path === "/contact") return "contact.php";
    if (path === "/usage-policy") return "usage-policy.php";
    if (path === "/artists") return "artists.html";
    if (path.startsWith("/artist/")) return `artists.html?artist=${encodeURIComponent(path.replace(/^\/artist\//, ""))}`;
  }
  return path;
}

function shellFetchUrl(path) {
  if (!canUseCleanUrls()) {
    if (path === "/about") return "about.php";
    if (path === "/contact") return "contact.php";
    if (path === "/usage-policy") return "usage-policy.php";
  }
  if (path === "/about") return "/about";
  if (path === "/contact") return "/contact";
  if (path === "/usage-policy") return "/usage-policy";
  return "";
}

function setShellNavActive(path) {
  const normalized = path.replace(/\/+$/g, "") || "/";
  document.querySelectorAll(".side-nav .nav-link[href], .mobile-brand[href]").forEach((link) => {
    const linkPath = publicRoutePath(link.getAttribute("href"));
    const active = Boolean(linkPath) && (linkPath === normalized || (normalized.startsWith("/artist/") && linkPath === "/artists"));
    link.classList.toggle("active-line", active);
    link.setAttribute("aria-current", active ? "page" : "false");
  });
}

function setHomeContentVisible(visible) {
  [document.querySelector(".hero"), document.querySelector("#all-tracks"), document.querySelector("#licensing"), document.querySelector("main.page > .footer")].forEach((element) => {
    if (element) element.hidden = !visible;
  });
  if (latestSection) latestSection.hidden = !visible || latestTrackCount <= 0;
}

function hideSongPageForShell() {
  songPage.hidden = true;
  document.body.classList.remove("song-view");
}

function showShellView(path, title = "") {
  hideSongPageForShell();
  setHomeContentVisible(false);
  shellView.hidden = false;
  document.body.classList.add("shell-view");
  setShellNavActive(path);
  if (title) document.title = title;
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function closeShellView() {
  shellView.hidden = true;
  shellView.replaceChildren();
  shellView.className = "shell-page-view";
  document.body.classList.remove("shell-view");
  if (shellPolicyObserver) {
    shellPolicyObserver.disconnect();
    shellPolicyObserver = null;
  }
  setHomeContentVisible(true);
}

function normalizeShellLinks(root) {
  root.querySelectorAll("a[href]").forEach((link) => {
    const path = publicRoutePath(link.getAttribute("href"));
    if (path) link.setAttribute("href", publicRouteUrl(path));
  });
}

function initShellPolicyToc() {
  if (shellPolicyObserver) shellPolicyObserver.disconnect();
  const sections = shellView.querySelectorAll(".policy-section");
  const tocLinks = shellView.querySelectorAll(".toc-list a");
  if (!sections.length || !tocLinks.length) return;

  shellPolicyObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      tocLinks.forEach((link) => link.classList.remove("active"));
      shellView.querySelector(`.toc-list a[href="#${entry.target.id}"]`)?.classList.add("active");
    });
  }, { rootMargin: "-20% 0px -70% 0px" });

  sections.forEach((section) => shellPolicyObserver.observe(section));
}

async function loadStaticShellPage(path, updateUrl = true) {
  const url = shellFetchUrl(path);
  if (!url) return false;

  try {
    const response = await fetch(url, { cache: "no-store" });
    if (!response.ok) return false;
    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, "text/html");
    const sourceMain = doc.querySelector("main.page");
    if (!sourceMain) return false;

    const pageStyles = Array.from(doc.querySelectorAll("style")).map((style) => `<style data-shell-style>${style.textContent}</style>`).join("");
    shellView.className = `shell-page-view ${sourceMain.className.replace(/\bpage\b/g, "").trim()}`;
    shellView.innerHTML = `${pageStyles}${sourceMain.innerHTML}`;
    normalizeShellLinks(shellView);
    showShellView(path, doc.title || siteSettings.site.title);
    updateShareMeta(doc.title || siteSettings.site.title, doc.querySelector('meta[name="description"]')?.content || siteSettings.seo.metaDescription, doc.querySelector('meta[property="og:image"]')?.content || siteSettings.seo.ogImage, siteUrl(path));
    if (path === "/usage-policy") initShellPolicyToc();
    if (updateUrl) history.pushState({ type: "shell", path }, "", publicRouteUrl(path));
    return true;
  } catch {
    return false;
  }
}

function mediaArtworkUrl(path) {
  if (!path) {
    return "";
  }

  try {
    return new URL(path, window.location.href).href;
  } catch {
    return absoluteAssetUrl(path);
  }
}

function mediaArtworkType(path) {
  const cleanPath = String(path || "").split("?")[0].toLowerCase();

  if (cleanPath.endsWith(".png")) return "image/png";
  if (cleanPath.endsWith(".webp")) return "image/webp";
  if (cleanPath.endsWith(".svg")) return "image/svg+xml";
  return "image/jpeg";
}

function updateMediaSession(track) {
  if (!("mediaSession" in navigator)) {
    return;
  }

  if (!track) {
    navigator.mediaSession.playbackState = "paused";
    return;
  }

  navigator.mediaSession.playbackState = isPlaying ? "playing" : "paused";
  setupMediaSessionControls();

  if (!("MediaMetadata" in window)) {
    return;
  }

  const artworkUrl = mediaArtworkUrl(preferredTrackCover(track) || siteSettings.seo.ogImage || "assets/cover-1.jpg");
  const artworkType = mediaArtworkType(artworkUrl);

  navigator.mediaSession.metadata = new MediaMetadata({
    title: track.title || "SG Production Track",
    artist: track.artist || "SG Production",
    album: "SG Production",
    artwork: ["96x96", "128x128", "192x192", "256x256", "384x384", "512x512"].map((sizes) => ({
      src: artworkUrl,
      sizes,
      type: artworkType
    }))
  });
}

function currentPlaybackPosition() {
  if (activeAudio) {
    return activeAudio.currentTime || 0;
  }

  if (activeSource && audioContext) {
    return Math.max(0, audioContext.currentTime - startedAt);
  }

  return pausedAt || 0;
}

function updatePositionState(totalOverride = null, positionOverride = null) {
  if (!("mediaSession" in navigator) || !("setPositionState" in navigator.mediaSession)) {
    return;
  }

  if (!selectedTrack) {
    return;
  }

  const total = Number.isFinite(totalOverride) && totalOverride > 0
    ? totalOverride
    : playableDuration(selectedTrack, Boolean(activeAudio || getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))));
  const position = Number.isFinite(positionOverride)
    ? positionOverride
    : currentPlaybackPosition();

  if (!Number.isFinite(total) || total <= 0 || !Number.isFinite(position)) {
    return;
  }

  try {
    navigator.mediaSession.setPositionState({
      duration: total,
      playbackRate: activeAudio?.playbackRate || 1,
      position: Math.min(total, Math.max(0, position))
    });
  } catch {
    // Some browsers reject position updates until metadata is ready.
  }
}

function updatePlayPauseUI(playing) {
  player?.classList.toggle("is-playing", playing);
  playerToggle?.classList.toggle("is-playing", playing);
  songPlay?.classList.toggle("is-playing", playing && selectedTrack?.id === songPlay.dataset.trackId);

  if ("mediaSession" in navigator) {
    navigator.mediaSession.playbackState = playing ? "playing" : "paused";
  }
}

function playPreviousTrack() {
  playTrackByOffset(-1);
}

function playNextTrack() {
  playTrackByOffset(1);
}

function setupMediaSessionControls() {
  if (!("mediaSession" in navigator)) {
    return;
  }

  const setHandler = (action, handler) => {
    try {
      navigator.mediaSession.setActionHandler(action, handler);
    } catch {
      // Media Session action support varies by mobile browser.
    }
  };

  setHandler("play", () => {
    if (!selectedTrack) return;
    playTrack(selectedTrack);
    updatePlayPauseUI(true);
  });
  setHandler("pause", () => {
    pauseCurrent();
    updatePlayPauseUI(false);
  });
  setHandler("previoustrack", playPreviousTrack);
  setHandler("nexttrack", playNextTrack);
  setHandler("seekbackward", (details) => {
    if (!selectedTrack) return;
    const total = playableDuration(selectedTrack, Boolean(activeAudio || getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))));
    const skipTime = details?.seekOffset || 10;
    setPlayerProgress(total ? (currentPlaybackPosition() - skipTime) / total : 0);
    updatePositionState();
  });
  setHandler("seekforward", (details) => {
    if (!selectedTrack) return;
    const total = playableDuration(selectedTrack, Boolean(activeAudio || getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))));
    const skipTime = details?.seekOffset || 10;
    setPlayerProgress(total ? (currentPlaybackPosition() + skipTime) / total : 0);
    updatePositionState();
  });
  setHandler("seekto", (details) => {
    if (!selectedTrack || !details || !Number.isFinite(details.seekTime)) return;
    const total = playableDuration(selectedTrack, Boolean(activeAudio || getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))));
    setPlayerProgress(total ? details.seekTime / total : 0);
    updatePositionState();
  });
}

function siteUrl(path = "/") {
  return canUseCleanUrls() ? `${window.location.origin}${path}` : window.location.href.split("#")[0];
}

function applyFavicon() {
  const favicon = siteSettings.seo.favicon || "assets/sg-logo.svg";
  setIconLink("icon", favicon);
  setIconLink("apple-touch-icon", "/icon-192.png");
}

function applySiteSettings() {
  const { site, links, catalog, seo } = siteSettings;

  const configuredLatestCount = Number(catalog.latestCount);
  const latestCountLimit = Math.max(1, Math.floor(TRACKS_PER_PAGE / 2));
  latestTrackCount = Number.isFinite(configuredLatestCount) ? Math.max(0, Math.min(latestCountLimit, configuredLatestCount)) : latestCountLimit;
  allTracksPerPage = TRACKS_PER_PAGE;
  demoTrackPageCount = Math.max(1, Math.min(40, Number(catalog.paginationDemoPages) || 12));

  applyRouteMeta("/");
  applyFavicon();

  const siteTitle = document.querySelector("#site-title");
  const siteTagline = document.querySelector("#siteTagline");
  const licenseTitle = document.querySelector("#license-title");
  const youtubeText = document.querySelector("#youtubeText");
  const youtubeSubscribe = document.querySelector("#youtubeSubscribe");

  if (siteTitle) {
    siteTitle.textContent = site.title;
  }

  if (siteTagline) {
    siteTagline.textContent = site.tagline;
  }

  if (licenseTitle) {
    licenseTitle.textContent = site.youtubeHeading;
  }

  if (youtubeText) {
    youtubeText.textContent = site.youtubeText;
  }

  if (youtubeSubscribe && links.youtube) {
    youtubeSubscribe.href = links.youtube;
  }

  setLink('a[aria-label="Instagram"]', links.instagram);
  setLink('a[aria-label="Spotify"]', links.spotify);
  setLink('a[aria-label="Apple Music"]', links.appleMusic);
  setLink('a[aria-label="YouTube"]', links.youtube);
}

function preloadAdMedia(advertising) {
  if (!advertising || !advertising.enabled || !advertising.mediaUrl) {
    return;
  }

  const preload = document.createElement("link");
  preload.rel = "preload";
  preload.href = normalizeMediaPath(advertising.mediaUrl);
  preload.as = adMediaKind(advertising) === "video" ? "video" : "image";
  document.head.append(preload);
}

function icon(name) {
  const icons = {
    play: '<svg class="play-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z"></path></svg><svg class="pause-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5v14"></path><path d="M16 5v14"></path></svg>',
    download: '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path></svg>'
  };

  return icons[name] || "";
}

function durationToSeconds(duration) {
  const [minutes, seconds] = duration.split(":").map(Number);
  return minutes * 60 + seconds;
}

function playableDuration(track, usePreviewDuration = false) {
  const previewDuration = Number(track?.previewDuration);

  if (usePreviewDuration && Number.isFinite(previewDuration) && previewDuration > 0) {
    return previewDuration;
  }

  return durationToSeconds(track?.duration || "0:0");
}

function isNearPlayableEnd(track, seconds) {
  const total = playableDuration(track, true);
  return total > 0 && seconds >= total - 0.35;
}

function formatTimer(seconds) {
  const safeSeconds = Math.max(0, Math.floor(seconds));
  const minutes = String(Math.floor(safeSeconds / 60));
  const remainingSeconds = String(safeSeconds % 60).padStart(2, "0");
  return `${minutes}:${remainingSeconds}`;
}

function buildCreditText(track) {
  return `Song: ${track.artist} - ${track.title}\nGenre: ${track.genre}\nFree Download / Stream: https://sgproduction.music/song/${trackSlug(track)}\nCredit: Music provided by SG Production`;
}

function getPreviewUrl(track) {
  return track.previewUrl || (isLocalAudioPath(track.downloadUrl || "") ? track.downloadUrl : "");
}

function getFullAudioUrl(track) {
  if (!track) {
    return "";
  }

  const downloadUrl = String(track.downloadUrl || "").trim();
  if (downloadUrl) {
    return normalizeMediaPath(downloadUrl);
  }

  const candidates = [
    track.fullAudioUrl,
    track.fullUrl,
    track.audioUrl,
    track.fileUrl,
    track.file,
    track.audio
  ];
  const audioUrl = candidates.map((value) => String(value || "").trim()).find((value) => isLocalAudioPath(value));

  return normalizeMediaPath(audioUrl || "");
}

function getTrackAudioUrl(track, preferFull = false) {
  const previewUrl = normalizeMediaPath(getPreviewUrl(track));
  const fullUrl = getFullAudioUrl(track);

  return preferFull ? (fullUrl || previewUrl) : (previewUrl || fullUrl);
}

function isSongPageActiveForTrack(track) {
  return Boolean(track && !songPage.hidden && songPlay?.dataset?.trackId === track.id);
}

function describeAudioError(audio) {
  const error = audio?.error;

  if (!error) {
    return "Unknown audio error";
  }

  const labels = {
    1: "MEDIA_ERR_ABORTED",
    2: "MEDIA_ERR_NETWORK",
    3: "MEDIA_ERR_DECODE",
    4: "MEDIA_ERR_SRC_NOT_SUPPORTED"
  };

  return `${labels[error.code] || "MEDIA_ERR_UNKNOWN"} (${error.code})`;
}

function createPlayableAudio(src) {
  const audio = new Audio();
  audio.preload = "metadata";
  audio.setAttribute("playsinline", "");
  audio.setAttribute("webkit-playsinline", "");

  const cleanSrc = String(src || "").split("?")[0].toLowerCase();
  const formatChecks = [
    { test: /\.mp3$/i, type: "audio/mpeg", label: "MP3" },
    { test: /\.wav$/i, type: "audio/wav", label: "WAV" },
    { test: /\.m4a$/i, type: "audio/mp4", label: "M4A" },
    { test: /\.aac$/i, type: "audio/aac", label: "AAC" }
  ];
  const format = formatChecks.find((item) => item.test.test(cleanSrc));
  if (format && audio.canPlayType(format.type) === "") {
    console.warn(`${format.label} audio may not be supported by this mobile browser:`, src);
  }

  audio.src = src;
  audio.load();

  return audio;
}

function getSearchMatches(query) {
  const terms = query.toLowerCase().split(/\s+/).filter(Boolean);

  if (terms.length === 0) {
    return tracks.slice(0, 5);
  }

  const includesTerms = (value) => terms.every((term) => value.includes(term));

  return tracks
    .filter((track) => includesTerms(`${track.title} ${track.artist} ${track.genre}`.toLowerCase()))
    .slice(0, 12);
}

function renderSearchResults() {
  const query = searchInput.value.trim();
  const matches = getSearchMatches(query);

  if (matches.length === 0) {
    searchResults.innerHTML = `<p class="search-empty">No results for "${escapeHTML(query)}".</p>`;
    return;
  }

  searchResults.innerHTML = matches.map((track) => {
    return `
      <button class="search-result" type="button" data-result-target="${escapeHTML(track.id)}">
        <span class="search-thumb"><img src="${escapeHTML(preferredTrackCover(track))}" alt="" loading="lazy" width="300" height="300" decoding="async"></span>
        <span class="search-copy">
          <strong>${escapeHTML(track.title)}</strong>
          <span>${escapeHTML(track.artist)} · ${escapeHTML(track.genre)}</span>
        </span>
      </button>
    `;
  }).join("");
}

function openSearch() {
  setMobileMenu(false);
  searchOverlay.classList.add("is-open");
  searchOverlay.setAttribute("aria-hidden", "false");
  document.body.classList.add("search-open");
  renderSearchResults();
  window.setTimeout(() => searchInput.focus(), 40);
}

function closeSearch() {
  searchOverlay.classList.remove("is-open");
  searchOverlay.setAttribute("aria-hidden", "true");
  document.body.classList.remove("search-open");
}

function goToSearchResult(target) {
  closeSearch();
  const track = tracks.find((item) => item.id === target);

  if (track) {
    openSongPage(track);
  }
}

function renderCard(track, isPriority = false) {
  const card = document.createElement("article");
  card.className = "track-card";
  card.tabIndex = 0;
  card.dataset.id = track.id;
  card.dataset.genre = track.genre;

  card.innerHTML = `
    <button class="cover-link" type="button" aria-label="Open ${track.title}">
      <img src="${escapeHTML(preferredTrackCover(track))}" alt="${escapeHTML(track.title)} cover art" loading="${isPriority ? "eager" : "lazy"}" ${isPriority ? 'fetchpriority="high"' : ""} width="300" height="300" decoding="async">
      ${shouldShowNewBadge(track) ? '<span class="badge">New</span>' : ""}
      <span class="duration">${track.duration}</span>
    </button>
    <div class="track-body">
      <div class="track-copy">
        <strong class="track-title">${track.title}</strong>
        <span class="artist-name">${track.artist}</span>
        <span class="genre-pill ${slugClass(track.genre)}">${track.genre}</span>
      </div>
      <div class="card-actions">
        <button class="round-button" type="button" data-action="play" aria-label="Play ${track.title}" title="Play">
          ${icon("play")}
        </button>
      </div>
    </div>
  `;

  card.addEventListener("click", (event) => {
    if (event.target.closest("button")) {
      return;
    }

    openSongPage(track);
  });
  card.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      openSongPage(track);
    }
  });
  card.querySelector(".cover-link").addEventListener("click", (event) => {
    event.stopPropagation();
    openSongPage(track);
  });
  card.querySelector('[data-action="play"]').addEventListener("click", (event) => {
    event.stopPropagation();
    playTrack(track);
  });

  return card;
}

function renderGridAdCard() {
  const g = (siteSettings.advertising || {}).gridAd || {};
  if (!g.enabled || !g.imageUrl) return null;

  const card = document.createElement("article");
  card.className = "track-card grid-ad-card";
  card.innerHTML = `
    <div class="cover-link grid-ad-image" style="background-image:url('${escapeHTML(g.imageUrl)}');background-size:cover;background-position:center;"></div>
    <div class="track-body">
      <div class="track-copy">
        <strong class="track-title">${escapeHTML(g.name || "")}</strong>
        <span class="artist-name">${escapeHTML(g.subtext || "")}</span>
        <span class="genre-pill grid-ad-badge">Ad</span>
      </div>
      <div class="card-actions">
        <a class="grid-ad-btn" ${g.linkUrl ? `href="${escapeHTML(g.linkUrl)}" target="_blank" rel="noopener noreferrer"` : ""} style="background:${escapeHTML(g.buttonColor || "#fff")};color:${escapeHTML(g.buttonTextColor || "#000")}">${escapeHTML(g.buttonText || "Learn more")}</a>
      </div>
    </div>
  `;

  return card;
}

function adMediaKind(advertising) {
  const mediaType = String(advertising?.mediaType || "").toLowerCase();
  const mediaUrl = String(advertising?.mediaUrl || "").toLowerCase().split("?")[0];

  if (mediaType.includes("video") || /\.(mp4|webm|mov|m4v)$/i.test(mediaUrl)) {
    return "video";
  }

  return "image";
}

function videoMimeType(url) {
  const cleanUrl = String(url || "").toLowerCase().split("?")[0];

  if (cleanUrl.endsWith(".webm")) {
    return "video/webm";
  }

  if (cleanUrl.endsWith(".mov")) {
    return "video/quicktime";
  }

  return "video/mp4";
}

function startMutedVideo(media) {
  if (!media || media.tagName !== "VIDEO") {
    return;
  }

  media.load();
  const playPromise = media.play();

  if (playPromise && typeof playPromise.catch === "function") {
    playPromise.catch(() => {
      // Muted autoplay can still be deferred by some browsers; the first frame remains visible.
    });
  }
}

function renderTracks(list, target) {
  if (!list.length) {
    target.innerHTML = '<p class="artist-empty">No tracks available yet. Check back soon.</p>';
    syncPlayingCards();
    return;
  }

  target.replaceChildren(...list.map((track, index) => renderCard(track, index === 0)));
  syncPlayingCards();
}

function shouldHideLatestOnMobile() {
  return window.matchMedia("(max-width: 768px)").matches;
}

function renderLatestReleases() {
  if (!latestSection || !latestGrid) {
    return;
  }

  const featuredTracks = tracks.filter((track) => track.isFeatured || track.isNew);

  if (latestTrackCount <= 0) {
    latestSection.hidden = true;
    return;
  }

  latestSection.hidden = false;
  if (shouldHideLatestOnMobile()) {
    return;
  }
  renderTracks((featuredTracks.length > 0 ? featuredTracks : tracks).slice(0, latestTrackCount), latestGrid);
}

function buildDemoTrackPages(sourceTracks) {
  if (!sourceTracks.length) {
    return [];
  }

  const targetCount = allTracksPerPage * demoTrackPageCount;

  return Array.from({ length: targetCount }, (_, index) => {
    const track = sourceTracks[index % sourceTracks.length];
    const copyIndex = Math.floor(index / sourceTracks.length);

    if (copyIndex === 0) {
      return track;
    }

    return {
      ...track,
      id: `${track.id}-demo-${copyIndex}`,
      isNew: false
    };
  });
}

function getPaginationItems(currentPage, totalPages) {
  if (totalPages <= 5) {
    return Array.from({ length: totalPages }, (_, index) => index + 1);
  }

  return [1, 2, 3, "ellipsis", totalPages - 1, totalPages];
}

function renderPagination(totalPages) {
  if (totalPages <= 1) {
    trackPagination.replaceChildren();
    return;
  }

  const items = [
    `<button class="pagination-button" type="button" data-page="${allTracksPage - 1}" aria-label="Previous page" ${allTracksPage === 1 ? "disabled" : ""}>‹</button>`,
    ...getPaginationItems(allTracksPage, totalPages).map((item) => {
      if (item === "ellipsis") {
        return '<span class="pagination-ellipsis" aria-hidden="true">...</span>';
      }

      return `<button class="pagination-button ${item === allTracksPage ? "is-active" : ""}" type="button" data-page="${item}" aria-label="Page ${item}" ${item === allTracksPage ? 'aria-current="page"' : ""}>${item}</button>`;
    }),
    `<button class="pagination-button" type="button" data-page="${allTracksPage + 1}" aria-label="Next page" ${allTracksPage === totalPages ? "disabled" : ""}>›</button>`
  ];

  trackPagination.innerHTML = items.join("");
}

function getColumnCount() {
  const grid = trackGrid || document.querySelector("#trackGrid, #allTracksGrid, .tracks-grid");
  if (!grid) return 2;

  const template = window.getComputedStyle(grid).getPropertyValue("grid-template-columns");
  const cols = template.split(/\s+/).filter((value) => value && value !== "none").length;

  return cols > 0 ? cols : 2;
}

function getTracksToShow(baseCount) {
  const cols = getColumnCount();
  return Math.ceil(baseCount / cols) * cols;
}

function showTracks() {
  renderAllTracksPage(1, false);
}

function renderAllTracksPage(page = allTracksPage, shouldScroll = false) {
  const allTracksSection = document.querySelector("#all-tracks");
  if (allTracksSection) {
    allTracksSection.hidden = false;
  }
  if (trackGrid) {
    trackGrid.hidden = false;
  }

  if (!allTracks.length) {
    allTracksPage = 1;
    trackGrid.innerHTML = '<p class="artist-empty">No tracks available yet. Check back soon.</p>';
    trackPagination.replaceChildren();
    if (loadMoreButton) loadMoreButton.style.display = "none";
    syncPlayingCards();
    return;
  }

  if (page <= 1) {
    allTracksPage = 1;
  }
  const adCard = renderGridAdCard();
  const hasAdCard = Boolean(adCard);
  const minimumTrackCount = Math.max(1, getColumnCount() * 2 - (hasAdCard ? 1 : 0));
  const completeRowItemCount = getTracksToShow(Math.max(tracksShown, minimumTrackCount));
  const visibleTrackCount = Math.min(
    allTracks.length,
    Math.max(1, completeRowItemCount - (hasAdCard ? 1 : 0))
  );
  const cards = allTracks.slice(0, visibleTrackCount).map((track, index) => renderCard(track, index === 0));

  if (adCard) {
    const pos = Math.min(Math.max(1, Number((siteSettings.advertising.gridAd || {}).position) || 8), cards.length + 1);
    cards.splice(pos - 1, 0, adCard);
  }

  trackGrid.replaceChildren(...cards);
  lastTrackGridColumnCount = getColumnCount();
  syncPlayingCards();
  trackPagination.replaceChildren();
  if (loadMoreButton) {
    loadMoreButton.style.display = visibleTrackCount >= allTracks.length ? "none" : "flex";
  }

  if (shouldScroll) {
    document.querySelector("#all-tracks").scrollIntoView({ block: "start", behavior: "smooth" });
  }
}

function normalizeShellArtist(artist) {
  if (!artist || !artist.name) return null;
  return {
    id: artist.id || slugClass(artist.name),
    name: artist.name,
    image: artist.imageWebp || artist.image || "assets/artist-photo-1.svg",
    style: artist.style || "Original Mix"
  };
}

async function loadShellArtists() {
  try {
    const response = await fetch("data/artists.json", { cache: "no-store" });
    if (!response.ok) return [];
    const data = await response.json();
    return Array.isArray(data) ? data.map(normalizeShellArtist).filter(Boolean) : [];
  } catch {
    return [];
  }
}

function artistRouteUrl(artist) {
  return publicRouteUrl(`/artist/${artist.id}`);
}

function artistTracks(artist) {
  return allTracks.filter((track) => (track.artistId && track.artistId === artist.id) || slugClass(track.artist || "") === artist.id || track.artist === artist.name);
}

function shellArtistCard(artist) {
  return `
    <article class="artist-card">
      <a class="artist-card-link" href="${escapeHTML(artistRouteUrl(artist))}" aria-label="Open ${escapeHTML(artist.name)} artist page">
        <img src="${escapeHTML(normalizeMediaPath(artist.image))}" alt="${escapeHTML(artist.name)} artist photo" loading="lazy">
        <div class="artist-card-copy">
          <h3>${escapeHTML(artist.name)}</h3>
        </div>
      </a>
    </article>
  `;
}

async function renderShellArtists(path = "/artists", updateUrl = true) {
  if (!shellArtists.length) {
    shellArtists = await loadShellArtists();
  }

  const slug = path.startsWith("/artist/") ? slugClass(decodeURIComponent(path.replace(/^\/artist\//, ""))) : "";
  const artist = slug ? shellArtists.find((item) => item.id === slug) : null;
  shellView.className = "shell-page-view artists-page";

  if (artist) {
    const releases = artistTracks(artist);
    shellView.innerHTML = `
      <section class="artist-profile-page" id="artistProfilePage" aria-labelledby="artistProfileName">
        <header class="artist-profile-hero">
          <div class="artist-profile-hero-bg" style="background-image:url('${escapeHTML(normalizeMediaPath(artist.image))}')" aria-hidden="true"></div>
          <a class="artist-profile-back" href="${escapeHTML(publicRouteUrl("/artists"))}">Artists</a>
          <div class="artist-profile-portrait">
            <img loading="lazy" src="${escapeHTML(normalizeMediaPath(artist.image))}" alt="${escapeHTML(artist.name)}">
          </div>
          <div class="artist-profile-heading">
            <h1 id="artistProfileName">${escapeHTML(artist.name)}</h1>
          </div>
        </header>
        <section class="artist-profile-tracks" aria-labelledby="popularTracksTitle">
          <h2 id="popularTracksTitle">Releases</h2>
          <div class="artist-track-list track-grid" id="shellArtistTrackList"></div>
        </section>
      </section>
      <footer class="footer" id="contact"><p>© 2026 SG Production. All rights reserved.</p></footer>
    `;
    const list = shellView.querySelector("#shellArtistTrackList");
    if (releases.length) {
      list.replaceChildren(...releases.map(renderCard));
    } else {
      list.innerHTML = '<p class="artist-empty">No tracks available yet. Check back soon.</p>';
    }
    syncPlayingCards();
    document.title = `${artist.name} | ${siteSettings.site.title}`;
    updateShareMeta(document.title, `Explore ${artist.name} releases on SG Production.`, artist.image, siteUrl(`/artist/${artist.id}`));
  } else {
    shellView.innerHTML = `
      <section class="section artist-section artist-directory" id="artistDirectory" aria-labelledby="artists-title">
        <div class="artist-title-row">
          <h1 id="artists-title">Artists</h1>
        </div>
        <div class="artist-toolbar">
          <label class="artist-search" for="shellArtistSearchInput">
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input id="shellArtistSearchInput" type="search" autocomplete="off" placeholder="Search Artists">
          </label>
        </div>
        <div class="artist-grid" id="shellArtistGrid"></div>
      </section>
      <footer class="footer" id="contact"><p>© 2026 SG Production. All rights reserved.</p></footer>
    `;
    const input = shellView.querySelector("#shellArtistSearchInput");
    const grid = shellView.querySelector("#shellArtistGrid");
    const paint = () => {
      const query = input.value.trim().toLowerCase();
      const filtered = shellArtists.filter((item) => `${item.name} ${item.style}`.toLowerCase().includes(query));
      grid.innerHTML = filtered.length ? filtered.map(shellArtistCard).join("") : '<p class="artist-empty">No artists available yet.</p>';
    };
    input.addEventListener("input", paint);
    paint();
    document.title = `Artists | ${siteSettings.site.title}`;
    updateShareMeta(document.title, siteSettings.seo.metaDescription || siteSettings.site.tagline, siteSettings.seo.ogImage, siteUrl("/artists"));
  }

  normalizeShellLinks(shellView);
  showShellView(path, document.title);
  if (updateUrl) history.pushState({ type: "shell", path }, "", publicRouteUrl(path));
  return true;
}

function setupAudio() {
  if (!audioContext) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    audioContext = new AudioContextClass();
  }
}

function synthSample(track, seconds = PREVIEW_SECONDS) {
  const sampleRate = 44100;
  const total = Math.floor(sampleRate * seconds);
  const samples = new Float32Array(total);
  const beatRate = track.bpm / 60;
  const sub = track.tone / 2;
  const harmonic = track.genre === "Original Mix" ? 1.5 : 2;

  for (let i = 0; i < total; i += 1) {
    const t = i / sampleRate;
    const beat = Math.max(0, Math.sin(2 * Math.PI * beatRate * t));
    const pulse = 0.28 + 0.72 * Math.pow(beat, track.genre === "Original Mix" ? 2 : 7);
    const sweep = Math.sin(2 * Math.PI * 0.08 * t);
    const lead = oscillator(track.wave, track.tone * (1 + 0.004 * sweep), t);
    const bass = Math.sin(2 * Math.PI * sub * t);
    const overtone = Math.sin(2 * Math.PI * track.tone * harmonic * t + Math.sin(t * 3) * 0.4);
    const texture = Math.sin(2 * Math.PI * (track.tone * 4.03) * t) * Math.sin(2 * Math.PI * 0.6 * t);
    const fadeIn = Math.min(1, t / 0.18);
    const fadeOut = Math.min(1, (seconds - t) / 0.35);
    const envelope = Math.max(0, Math.min(fadeIn, fadeOut));

    samples[i] = (lead * 0.2 + bass * 0.34 + overtone * 0.12 + texture * 0.035) * pulse * envelope;
  }

  return samples;
}

function oscillator(type, frequency, t) {
  const phase = (frequency * t) % 1;

  if (type === "square") {
    return phase < 0.5 ? 1 : -1;
  }

  if (type === "sawtooth") {
    return 2 * phase - 1;
  }

  if (type === "triangle") {
    return 1 - 4 * Math.abs(Math.round(phase - 0.25) - (phase - 0.25));
  }

  return Math.sin(2 * Math.PI * frequency * t);
}

function createBuffer(track) {
  setupAudio();
  const samples = synthSample(track);
  const buffer = audioContext.createBuffer(1, samples.length, 44100);
  buffer.copyToChannel(samples, 0);
  return buffer;
}

async function playTrack(track) {
  if (!track) {
    return;
  }

  const preferFullAudio = isSongPageActiveForTrack(track);
  const audioUrl = getTrackAudioUrl(track, preferFullAudio);
  const sameAudioSource = Boolean(audioUrl) && activeAudioUrl === audioUrl;

  if (selectedTrack?.id === track.id && isPlaying && (!audioUrl || sameAudioSource)) {
    pauseCurrent();
    return;
  }

  const sameTrack = selectedTrack?.id === track.id;

  if (sameTrack && isPlaying && activeAudio && audioUrl && !sameAudioSource) {
    pausedAt = currentPlaybackPosition();
  }

  const resumeOffset = sameTrack && !isNearPlayableEnd(track, pausedAt) ? pausedAt : 0;

  stopCurrent(!sameTrack);
  selectedTrack = track;

  if (audioUrl) {
    const token = audioPlayToken + 1;
    audioPlayToken = token;
    const audio = createPlayableAudio(audioUrl);
    audio.loop = false;
    audio.volume = isMuted ? 0 : currentVolume;
    audio.muted = isMuted;
    activeAudio = audio;
    activeAudioUrl = audioUrl;
    window.currentAudio = audio;
    prefetchWaveform(track);

    const waitForMetadata = () => new Promise((resolve) => {
      if (Number.isFinite(audio.duration) && audio.duration > 0) {
        resolve();
        return;
      }

      audio.addEventListener("loadedmetadata", resolve, { once: true });
      audio.addEventListener("error", resolve, { once: true });
    });

    audio.addEventListener("error", () => {
      console.error("Audio error:", describeAudioError(audio), {
        src: audio.currentSrc || audio.src,
        error: audio.error
      });
      setAudioLoadingState(false);
    });

    audio.addEventListener("stalled", () => {
      console.warn("Audio stalled", audio.currentSrc || audio.src);
    });

    audio.addEventListener("waiting", () => {
      setAudioLoadingState(true);
    });

    audio.addEventListener("playing", () => {
      setAudioLoadingState(false);
    });

    audio.addEventListener("canplay", () => {
      setAudioLoadingState(false);
    });

    audio.addEventListener("ended", () => {
      if (audioPlayToken !== token || activeAudio !== audio) {
        return;
      }
      setAudioLoadingState(false);
      const endedAt = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : playableDuration(track, true);
      track.previewDuration = endedAt;
      isPlaying = false;
      pausedAt = endedAt;
      activeAudio = null;
      activeAudioUrl = "";
      window.currentAudio = null;
      updatePlayerTimer(endedAt, true);
      playingTrackId = null;
      syncPlayer();
      syncPlayingCards();
    });

    isPlaying = true;
    playingTrackId = track.id;
    showPlayer();
    syncPlayer();
    syncPlayingCards();
    updatePlayerTimer(resumeOffset, true);
    setAudioLoadingState(true);

    let playPromise;
    try {
      if (resumeOffset > 0) {
        audio.currentTime = resumeOffset;
      }
      if (window.location.protocol === "https:" && /^http:\/\//i.test(audioUrl)) {
        console.warn("Audio source uses HTTP on an HTTPS page; mobile browsers may block it:", audioUrl);
      }
      playPromise = audio.play();
    } catch (error) {
      playPromise = Promise.reject(error);
    }

    await waitForMetadata();

    if (audioPlayToken !== token || activeAudio !== audio) {
      return;
    }

    const total = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : playableDuration(track);
    if (Number.isFinite(total) && total > 0) {
      track.previewDuration = total;
    }
    const targetOffset = Math.min(Math.max(resumeOffset, 0), Math.max(total - 0.1, 0));
    if (targetOffset > 0 && Math.abs(audio.currentTime - targetOffset) > 0.75) {
      audio.currentTime = targetOffset;
    }

    try {
      await playPromise;
      pausedAt = 0;
      updateMediaSession(track);
      updatePlayPauseUI(true);
      updatePositionState();
      updateProgress();
    } catch {
      if (audioPlayToken !== token || activeAudio !== audio) {
        return;
      }
      console.error("Playback failed:", describeAudioError(audio), audio.error);
      setAudioLoadingState(false);
      isPlaying = false;
      activeAudio = null;
      activeAudioUrl = "";
      window.currentAudio = null;
      syncPlayer();
      syncPlayingCards();
    }
    return;
  }

  setupAudio();

  if (audioContext.state === "suspended") {
    audioContext.resume();
  }

  const source = audioContext.createBufferSource();
  const gain = audioContext.createGain();
  source.buffer = createBuffer(track);
  source.loop = false;
  gain.gain.value = 0.82 * (isMuted ? 0 : currentVolume);
  source.connect(gain);
  gain.connect(audioContext.destination);
  source.start(0, resumeOffset);

  activeSource = source;
  activeGain = gain;
  isPlaying = true;
  playingTrackId = track.id;
  startedAt = audioContext.currentTime - resumeOffset;
  pausedAt = 0;

  source.onended = () => {
    if (activeSource === source) {
      isPlaying = false;
      pausedAt = 0;
      updatePlayerTimer(0, false);
      playingTrackId = null;
      activeSource = null;
      activeGain = null;
      syncPlayer();
      syncPlayingCards();
    }
  };

  syncPlayer();
  showPlayer();
  syncPlayingCards();
  updateMediaSession(track);
  updatePlayPauseUI(true);
  updatePositionState();
  updateProgress();
}

function pauseCurrent() {
  setAudioLoadingState(false);

  if (activeAudio) {
    const audio = activeAudio;
    pausedAt = audio.currentTime;
    audio.pause();
    activeAudio = null;
    window.currentAudio = null;
    isPlaying = false;
    cancelAnimationFrame(animationFrame);
    syncPlayer();
    syncPlayingCards();
    return;
  }

  if (!audioContext || !activeSource) {
    return;
  }

  // Synthetic fallback audio is only PREVIEW_SECONDS long; uploaded files are handled above.
  pausedAt = Math.min(PREVIEW_SECONDS, Math.max(0, audioContext.currentTime - startedAt));
  try {
    activeSource.stop();
  } catch {
    // source may already be stopped by the browser
  }
  activeSource = null;
  activeGain = null;
  isPlaying = false;
  cancelAnimationFrame(animationFrame);
  syncPlayer();
  syncPlayingCards();
}

function stopCurrent(resetPause = true) {
  audioPlayToken += 1;
  setAudioLoadingState(false);

  if (activeAudio) {
    activeAudio.pause();
  }

  if (activeSource) {
    activeSource.onended = null;
    try {
      activeSource.stop();
    } catch {
      // source may already be stopped by the browser
    }
  }

  activeAudio = null;
  activeAudioUrl = "";
  activeSource = null;
  activeGain = null;
  isPlaying = false;
  playingTrackId = null;
  window.currentAudio = null;
  cancelAnimationFrame(animationFrame);

  if (resetPause) {
    pausedAt = 0;
  }

  syncPlayingCards();
}

function syncPlayer() {
  if (!selectedTrack) {
    hidePlayer();
    songPlay.classList.remove("is-playing");
    updateMediaSession(null);
    return;
  }

  player.classList.toggle("is-playing", isPlaying);
  playerToggle.classList.toggle("is-playing", isPlaying);
  songPlay.classList.toggle("is-playing", isPlaying && selectedTrack.id === songPlay.dataset.trackId);
  updatePlayerTitle(selectedTrack.title);
  playerGenre.textContent = `${selectedTrack.artist} · ${selectedTrack.genre}`;
  setPlayerCover(selectedTrack);
  playerCover.setAttribute("aria-label", `Open ${selectedTrack.title}`);
  playerToggle.setAttribute("aria-label", isPlaying ? `Pause ${selectedTrack.title}` : `Play ${selectedTrack.title}`);
  songPlay.setAttribute("aria-label", isPlaying ? `Pause ${selectedTrack.title}` : `Play ${selectedTrack.title}`);
  updatePlayerLike(selectedTrack);
  syncStoredLikeWithServer(selectedTrack);
  applyPlayerVolume();
  updateMediaSession(selectedTrack);
  updatePlayerTimer(pausedAt);
}

function syncPlayingCards() {
  document.querySelectorAll(".track-card").forEach((card) => {
    card.classList.toggle("is-playing", isPlaying && selectedTrack && card.dataset.id === selectedTrack.id);
  });
}

function updateProgress() {
  if (!isPlaying) {
    return;
  }

  if (activeAudio) {
    if (!playerSeekActive) {
      const total = Number.isFinite(activeAudio.duration) && activeAudio.duration > 0 ? activeAudio.duration : playableDuration(selectedTrack, true);
      const elapsed = activeAudio.currentTime;
      progressBar.style.width = `${total ? Math.min(100, (elapsed / total) * 100) : 0}%`;
      updatePlayerTimer(elapsed, true);
    }
    animationFrame = requestAnimationFrame(updateProgress);
    return;
  }

  if (!audioContext) {
    return;
  }

  if (!playerSeekActive) {
    const elapsed = Math.min(PREVIEW_SECONDS, Math.max(0, audioContext.currentTime - startedAt));
    progressBar.style.width = `${(elapsed / PREVIEW_SECONDS) * 100}%`;
    updatePlayerTimer(elapsed, false);
  }
  animationFrame = requestAnimationFrame(updateProgress);
}

function updatePlayerTimer(elapsed = 0, isActualAudio = Boolean(activeAudio || getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack)))) {
  const totalSeconds = playableDuration(selectedTrack, isActualAudio);
  const scaledElapsed = isActualAudio ? Math.min(totalSeconds, elapsed) : Math.min(totalSeconds, (elapsed / PREVIEW_SECONDS) * totalSeconds);
  const percent = totalSeconds ? Math.min(100, (scaledElapsed / totalSeconds) * 100) : 0;
  progressBar.style.width = `${percent}%`;
  const elapsedText = formatTimer(scaledElapsed);
  const totalText = formatTimer(totalSeconds);
  if (progressTime) progressTime.textContent = `${elapsedText} / ${totalText}`;
  if (progressElapsed) progressElapsed.textContent = elapsedText;
  if (progressTotal) progressTotal.textContent = totalText;
  progressShell.setAttribute("aria-valuenow", String(Math.round(percent)));
  progressShell.setAttribute("aria-valuetext", `${formatTimer(scaledElapsed)} of ${formatTimer(totalSeconds)}`);
  updatePositionState(totalSeconds, scaledElapsed);
  if (playingTrackId === selectedTrack.id) {
    const bars = songWaveform.children;
    const playedCount = Math.round((percent / 100) * bars.length);
    for (let i = 0; i < bars.length; i++) {
      bars[i].style.background = i < playedCount ? "#0a84ff" : "";
    }
    songDuration.textContent = `${formatTimer(scaledElapsed)} / ${formatTimer(totalSeconds)}`;
  }
}

function eventClientX(event) {
  if (event.touches && event.touches.length > 0) {
    return event.touches[0].clientX;
  }

  if (event.changedTouches && event.changedTouches.length > 0) {
    return event.changedTouches[0].clientX;
  }

  return event.clientX;
}

function progressFractionFromPointer(event, element) {
  const rect = element.getBoundingClientRect();
  const clientX = eventClientX(event);

  if (!Number.isFinite(clientX) || rect.width <= 0) {
    return 0;
  }

  const clickX = clientX - rect.left;
  const ratio = clickX / rect.width;

  return Math.max(0, Math.min(1, ratio));
}

function setPlayerProgress(fraction, shouldCommit = true) {
  const clamped = Math.min(1, Math.max(0, fraction));
  progressBar.style.width = `${clamped * 100}%`;

  if (activeAudio) {
    if (!activeAudio.duration || Number.isNaN(activeAudio.duration) || !Number.isFinite(activeAudio.duration)) {
      return;
    }

    const total = activeAudio.duration;
    const targetTime = clamped * total;
    pausedAt = targetTime;

    if (!shouldCommit && isPlaying) {
      if (Math.abs(activeAudio.currentTime - targetTime) > 0.15) {
        activeAudio.currentTime = targetTime;
      }
      updatePlayerTimer(targetTime, true);
      return;
    }

    activeAudio.currentTime = targetTime;
    updatePlayerTimer(pausedAt, true);
    return;
  }

  if (getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))) {
    pausedAt = clamped * playableDuration(selectedTrack, true);
    updatePlayerTimer(pausedAt, true);
    return;
  }

  const previewOffset = Math.min(PREVIEW_SECONDS - 0.01, clamped * PREVIEW_SECONDS);

  if (isPlaying && activeSource && audioContext) {
    if (!shouldCommit || progressShell.classList.contains("is-seeking")) {
      // During drag: only update position, defer the restart to when drag ends
      pausedAt = previewOffset;
      updatePlayerTimer(pausedAt, false);
      return;
    }
    activeSource.onended = null;
    activeSource.stop();
    activeSource = null;
    activeGain = null;
    isPlaying = false;
    pausedAt = previewOffset;
    playTrack(selectedTrack);
    return;
  }

  pausedAt = previewOffset;
  updatePlayerTimer(pausedAt, false);
}

function startPlayerSeek(event) {
  event.preventDefault();
  event.stopPropagation();
  playerSeekActive = true;
  progressShell.classList.add("is-seeking");
  if (event.pointerId !== undefined && progressShell.setPointerCapture) {
    progressShell.setPointerCapture(event.pointerId);
  }
  pendingPlayerSeekFraction = progressFractionFromPointer(event, progressShell);
  setPlayerProgress(pendingPlayerSeekFraction, false);

  const move = (moveEvent) => {
    moveEvent.preventDefault();
    pendingPlayerSeekFraction = progressFractionFromPointer(moveEvent, progressShell);
    setPlayerProgress(pendingPlayerSeekFraction, false);
  };

  const stop = () => {
    const finalFraction = pendingPlayerSeekFraction;
    pendingPlayerSeekFraction = null;
    playerSeekActive = false;
    const shouldRestartSynthetic = isPlaying && activeSource && audioContext && !activeAudio;
    progressShell.classList.remove("is-seeking");
    if (event.pointerId !== undefined && progressShell.releasePointerCapture) {
      try {
        progressShell.releasePointerCapture(event.pointerId);
      } catch {
        // Pointer capture can already be released by the browser.
      }
    }
    window.removeEventListener("pointermove", move);
    window.removeEventListener("pointerup", stop);
    window.removeEventListener("pointercancel", stop);
    window.removeEventListener("touchmove", move);
    window.removeEventListener("touchend", stop);
    window.removeEventListener("touchcancel", stop);

    if (finalFraction !== null) {
      setPlayerProgress(finalFraction, !shouldRestartSynthetic);
    }

    // For AudioContext-based playback: restart from the seeked position now that drag is done
    if (shouldRestartSynthetic && isPlaying && activeSource && audioContext) {
      activeSource.onended = null;
      activeSource.stop();
      activeSource = null;
      activeGain = null;
      isPlaying = false;
      playTrack(selectedTrack);
    }
  };

  if (event.type === "touchstart") {
    window.addEventListener("touchmove", move, { passive: false });
    window.addEventListener("touchend", stop, { once: true });
    window.addEventListener("touchcancel", stop, { once: true });
  } else {
    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", stop, { once: true });
    window.addEventListener("pointercancel", stop, { once: true });
  }
}

function writeWav(samples) {
  const sampleRate = 44100;
  const bytesPerSample = 2;
  const buffer = new ArrayBuffer(44 + samples.length * bytesPerSample);
  const view = new DataView(buffer);

  writeString(view, 0, "RIFF");
  view.setUint32(4, 36 + samples.length * bytesPerSample, true);
  writeString(view, 8, "WAVE");
  writeString(view, 12, "fmt ");
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, 1, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * bytesPerSample, true);
  view.setUint16(32, bytesPerSample, true);
  view.setUint16(34, 16, true);
  writeString(view, 36, "data");
  view.setUint32(40, samples.length * bytesPerSample, true);

  let offset = 44;
  samples.forEach((sample) => {
    const clamped = Math.max(-1, Math.min(1, sample));
    view.setInt16(offset, clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff, true);
    offset += bytesPerSample;
  });

  return new Blob([view], { type: "audio/wav" });
}

function writeString(view, offset, value) {
  for (let i = 0; i < value.length; i += 1) {
    view.setUint8(offset + i, value.charCodeAt(i));
  }
}

function downloadTrack(track) {
  if (track.downloadUrl) {
    const link = document.createElement("a");
    link.href = downloadEndpoint(track);
    link.rel = "noreferrer";
    document.body.append(link);
    link.click();
    link.remove();
    return;
  }

  const blob = writeWav(synthSample(track, PREVIEW_SECONDS));
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${track.id}-preview.wav`;
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function generatedWaveformBars(track, barCount) {
  const bars = [];
  const seed = track.id.split("").reduce((total, char) => total + char.charCodeAt(0), 0);

  for (let i = 0; i < barCount; i += 1) {
    const pulse = Math.abs(Math.sin((i + seed) * 0.21));
    const accent = i % 19 < 5 ? 1.45 : 1;
    const height = Math.max(7, Math.round((8 + pulse * 34 + ((i * seed) % 13)) * accent));
    bars.push(`<span style="height:${Math.min(height, 58)}px"></span>`);
  }

  return bars;
}

function getSongWaveformBarCount() {
  const fallbackWidth = Math.min(window.innerWidth - 92, 820);
  const availableWidth = songWaveform.clientWidth || fallbackWidth;
  return Math.max(64, Math.min(WAVEFORM_MAX_BARS, Math.floor(availableWidth / 5)));
}

function paintSongWaveform(bars) {
  if (songPage.hidden) return;
  songWaveform.classList.remove("is-loading");
  songWaveform.innerHTML = bars.join("");

  if (!activeAudio) {
    updatePlayerTimer(pausedAt, Boolean(getTrackAudioUrl(selectedTrack, isSongPageActiveForTrack(selectedTrack))));
  }
}

function showSongWaveformLoading(track, barCount) {
  const loadingBars = generatedWaveformBars(track, barCount);
  songWaveform.classList.add("is-loading");
  songWaveform.innerHTML = loadingBars.join("");
}

// Fetch + decode audio into waveform bar strings.
// Deduplicates concurrent calls for the same track+barCount via waveformInflight.
// Returns null on failure (caller should fall back to generated bars).
async function computeWaveformBars(track, barCount) {
  const audioUrl = getPreviewUrl(track);
  if (!audioUrl) return null;

  const inflightKey = `${track.id}:${barCount}`;
  if (waveformInflight.has(inflightKey)) {
    return waveformInflight.get(inflightKey);
  }

  const promise = (async () => {
    try {
      const response = await fetch(audioUrl, { mode: "cors" });
      if (!response.ok) return null;
      const arrayBuffer = await response.arrayBuffer();
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const audioBuffer = await ctx.decodeAudioData(arrayBuffer);
      ctx.close();

      const analysisLength = Math.min(audioBuffer.length, Math.floor(audioBuffer.sampleRate * WAVEFORM_ANALYSIS_SECONDS));
      const blockSize = Math.max(1, Math.floor(analysisLength / barCount));
      const peaks = [];
      for (let i = 0; i < barCount; i += 1) {
        let max = 0;
        const start = i * blockSize;
        const end = Math.min(start + blockSize, analysisLength);
        for (let channelIndex = 0; channelIndex < audioBuffer.numberOfChannels; channelIndex += 1) {
          const channelData = audioBuffer.getChannelData(channelIndex);
          for (let j = start; j < end; j += 4) { // sample every 4th for 4× decode speed
            const abs = Math.abs(channelData[j]);
            if (abs > max) max = abs;
          }
        }
        peaks.push(max);
      }
      const maxPeak = Math.max(...peaks, 0.001);
      return peaks.map((peak) => `<span style="height:${Math.max(7, Math.round((peak / maxPeak) * 58))}px"></span>`);
    } catch {
      return null;
    } finally {
      waveformInflight.delete(inflightKey);
    }
  })();

  waveformInflight.set(inflightKey, promise);
  return promise;
}

// Background-warm the waveform cache as soon as a track starts playing.
// Called from playTrack so the cache is hot by the time the song page opens.
function prefetchWaveform(track) {
  const audioUrl = getPreviewUrl(track);
  if (!audioUrl) return;
  const barCount = getSongWaveformBarCount();
  const cacheKey = `${track.id}:${audioUrl}:${barCount}`;
  if (waveformCache.has(cacheKey)) {
    if (!songPage.hidden && selectedTrack?.id === track.id) {
      paintSongWaveform(waveformCache.get(cacheKey));
    }
    return;
  }
  computeWaveformBars(track, barCount).then((bars) => {
    if (!bars || waveformCache.has(cacheKey)) return;
    waveformCache.set(cacheKey, bars);
    if (!songPage.hidden && selectedTrack?.id === track.id) {
      paintSongWaveform(bars);
    }
  });
}

async function renderSongWaveform(track) {
  const token = waveformRenderToken + 1;
  waveformRenderToken = token;
  const barCount = getSongWaveformBarCount();
  const audioUrl = getPreviewUrl(track);
  const cacheKey = `${track.id}:${audioUrl || "generated"}:${barCount}`;

  if (waveformCache.has(cacheKey)) {
    paintSongWaveform(waveformCache.get(cacheKey));
    return;
  }

  if (!audioUrl) {
    const generatedBars = generatedWaveformBars(track, barCount);
    waveformCache.set(cacheKey, generatedBars);
    paintSongWaveform(generatedBars);
    return;
  }

  // Show animated placeholder while the real bars load
  if (!songPage.hidden) showSongWaveformLoading(track, barCount);

  const bars = await computeWaveformBars(track, barCount);

  // Always cache the result even if this render is no longer the latest
  const finalBars = bars || generatedWaveformBars(track, barCount);
  waveformCache.set(cacheKey, finalBars);

  if (waveformRenderToken !== token || !selectedTrack || selectedTrack.id !== track.id) return;
  requestAnimationFrame(() => {
    if (waveformRenderToken !== token || !selectedTrack || selectedTrack.id !== track.id) return;
    paintSongWaveform(finalBars);
  });
}

function sendAdEvent(endpoint, track, adUrl = "") {
  if (!track || !endpoint) {
    return Promise.resolve();
  }

  return fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    keepalive: true,
    body: JSON.stringify({
      song_id: track.id,
      song_title: track.title,
      timestamp: new Date().toISOString(),
      ad_url: adUrl,
      referrer: window.location.href
    })
  }).catch(() => {});
}

function revealCreditText(track) {
  const credit = String(track.creditText || "").trim();

  creditText.textContent = credit;
  creditText.hidden = credit === "";
}

function renderSongAd(track) {
  const advertising = siteSettings.advertising || {};
  const mediaUrl = normalizeMediaPath(advertising.mediaUrl);
  const mediaKind = adMediaKind({ ...advertising, mediaUrl });
  const enabled = Boolean(advertising.enabled && mediaUrl);
  let impressionSent = false;

  songPage.classList.toggle("no-ad", !enabled);
  songAd.classList.remove("is-loaded", "is-failed");
  songAd.replaceChildren();

  if (!enabled) {
    return;
  }

  const media = document.createElement(mediaKind === "video" ? "video" : "img");
  media.className = "song-ad-media";

  const markLoaded = () => {
    songAd.classList.add("is-loaded");
    songAd.classList.remove("is-failed");

    if (!impressionSent) {
      impressionSent = true;
      sendAdEvent("/api/ad-impression", track, advertising.linkUrl || "");
    }
  };
  const markFailed = () => {
    songAd.classList.add("is-failed");
    songAd.classList.remove("is-loaded");
    songPage.classList.add("no-ad");
    songAd.replaceChildren();
  };

  if (mediaKind === "video") {
    media.muted = true;
    media.defaultMuted = true;
    media.volume = 0;
    media.loop = true;
    media.autoplay = true;
    media.playsInline = true;
    media.preload = "auto";
    media.setAttribute("muted", "");
    media.setAttribute("autoplay", "");
    media.setAttribute("loop", "");
    media.setAttribute("playsinline", "");
    media.setAttribute("webkit-playsinline", "");
    media.setAttribute("disablepictureinpicture", "");
    media.removeAttribute("controls");
    media.addEventListener("loadedmetadata", markLoaded, { once: true });
    media.addEventListener("loadeddata", markLoaded, { once: true });
    media.addEventListener("canplay", markLoaded, { once: true });
    media.addEventListener("error", markFailed, { once: true });
    const source = document.createElement("source");
    source.src = mediaUrl;
    source.type = videoMimeType(mediaUrl);
    media.append(source);
  } else {
    media.src = mediaUrl;
    media.alt = "Advertisement";
    media.loading = "eager";
    media.decoding = "async";
    media.fetchPriority = "high";
    media.addEventListener("load", markLoaded, { once: true });
    media.addEventListener("error", markFailed, { once: true });
  }

  if (advertising.linkUrl) {
    const link = document.createElement("a");
    link.className = "song-ad-link";
    link.href = advertising.linkUrl;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.setAttribute("aria-label", "Open advertisement");
    link.addEventListener("click", (event) => {
      event.preventDefault();
      const newTab = window.open("", "_blank", "noopener,noreferrer");

      sendAdEvent("/api/ad-click", track, advertising.linkUrl).finally(() => {
        if (newTab) {
          newTab.location.href = advertising.linkUrl;
        } else {
          window.open(advertising.linkUrl, "_blank", "noopener,noreferrer");
        }
      });
    });
    link.append(media);
    songAd.append(link);
    startMutedVideo(media);
    return;
  }

  songAd.append(media);
  startMutedVideo(media);
}

function queueSongWaveformRender() {
  if (songPage.hidden || waveformResizeFrame) {
    return;
  }

  waveformResizeFrame = requestAnimationFrame(() => {
    waveformResizeFrame = 0;
    renderSongWaveform(selectedTrack);
  });
}

function openSongPage(track, updateUrl = true) {
  if (playingTrackId !== track.id) {
    stopCurrent();
    progressBar.style.width = "0%";
    progressShell.setAttribute("aria-valuenow", "0");
  }

  selectedTrack = track;
  closeShellView();
  songPage.hidden = false;
  document.body.classList.add("song-view");
  songPlay.dataset.trackId = track.id;
  songGenre.textContent = track.bpm ? `${track.genre} • ${track.bpm} BPM` : track.genre;
  songPageTitle.textContent = track.title;
  songArtist.textContent = track.artist;
  songDuration.textContent = `0:00 / ${track.duration}`;
  songDownload.href = downloadEndpoint(track);
  revealCreditText(track);
  document.title = `${track.title} | ${siteSettings.site.title}`;
  updateShareMeta(`${track.title} — SG Production`, songMetaDescription(track), preferredTrackCover(track) || siteSettings.seo.ogImage, siteUrl(trackUrl(track)));
  setMetaTag("property", "og:type", "music.song");
  setMetaTag("property", "og:site_name", "SG Production");
  renderSongWaveform(track);
  renderSongAd(track);
  syncPlayer();
  clearActiveNav();
  window.scrollTo({ top: 0, behavior: "smooth" });

  if (updateUrl) {
    history.pushState({ type: "song", slug: trackSlug(track) }, "", trackUrl(track));
  }
}

function closeSongPage(updateUrl = true) {
  document.body.classList.remove("song-view");
  songPage.hidden = true;
  applyRouteMeta("/tracks");

  if (updateUrl) {
    history.pushState(null, "", canUseCleanUrls() ? "/tracks" : "index.html?view=tracks");
  }

  scrollToRouteSection(window.location.pathname, "auto");
  updateActiveNav();
}

function getTrackFromLocation() {
  const params = new URLSearchParams(window.location.search);
  const querySlug = params.get("song");

  if (querySlug) {
    const cleanSlug = slugClass(querySlug);
    return allTracks.find((item) => item.id === cleanSlug || trackSlug(item) === cleanSlug) || tracks.find((item) => item.id === cleanSlug || trackSlug(item) === cleanSlug) || null;
  }

  if (!canUseCleanUrls()) {
    return null;
  }

  const path = decodeURIComponent(window.location.pathname).replace(/\/+$/g, "");

  if (!path.startsWith("/song/")) {
    return null;
  }

  const slug = slugClass(path.replace(/^\/song\//, ""));

  return allTracks.find((item) => trackSlug(item) === slug || item.id === slug) || tracks.find((item) => trackSlug(item) === slug || item.id === slug) || null;
}

function routeSectionFromLocation() {
  const params = new URLSearchParams(window.location.search);
  const queryView = params.get("view");
  const path = canUseCleanUrls() ? window.location.pathname.replace(/\/+$/g, "") || "/" : "";

  if (queryView === "tracks" || path === "/tracks") {
    closeShellView();
    hideSongPageForShell();
    applyRouteMeta("/tracks");
    scrollToRouteSection("/tracks", "auto");
    return true;
  }

  if (queryView === "licensing" || path === "/licensing") {
    closeShellView();
    hideSongPageForShell();
    applyRouteMeta("/licensing");
    scrollToRouteSection("/licensing", "auto");
    return true;
  }

  if (path === "/" || path === "/index.html" || queryView === "home") {
    closeShellView();
    hideSongPageForShell();
    applyRouteMeta("/");
    scrollToRouteSection("/", "auto");
    return true;
  }

  const publicPath = publicRoutePath(path);
  if (publicPath && publicPath !== path && publicPath !== "/") {
    openShellRoute(publicPath, false);
    return true;
  }

  if (["/about", "/contact", "/usage-policy", "/artists"].includes(path) || path.startsWith("/artist/")) {
    openShellRoute(path, false);
    return true;
  }

  return false;
}

function openSongFromLocation() {
  const track = getTrackFromLocation();

  if (track) {
    openSongPage(track, false);

    if (new URLSearchParams(window.location.search).get("autoplay") === "1") {
      playTrack(track);
    }

    return;
  }

  routeSectionFromLocation();
}

function normalizeInitialUrl() {
  if (!canUseCleanUrls()) {
    return;
  }

  if (window.location.pathname.endsWith("/index.html")) {
    history.replaceState(null, "", "/");
  }
}

async function initializeCatalog() {
  const [uploadedTracks, loadedSettings] = await Promise.all([
    loadUploadedTracks(),
    loadSiteSettings()
  ]);

  siteSettings = loadedSettings;
  applySiteSettings();
  preloadAdMedia(siteSettings.advertising);

  if (uploadedTracks.length > 0) {
    tracks.unshift(...uploadedTracks);
  }

  if (window.location.protocol === "file:" && tracks.length === 0) {
    tracks.unshift(...LOCAL_PREVIEW_TRACKS.map(normalizeUploadedTrack).filter(Boolean));
  }

  allTracks = tracks;
  syncAllStoredLikesWithServer();
  normalizeInitialUrl();
  renderLatestReleases();

  renderAllTracksPage(1);

  if (window.location.protocol === "file:" && tracks.length === 1 && tracks[0]?.id === "banger") {
    selectedTrack = tracks[0];
    showPlayer();
  }

  syncPlayer();
  setupMediaSessionControls();
  openSongFromLocation();
}

normalizeLocalPreviewLinks();
initializeCatalog();

document.querySelector("#focusSearch").addEventListener("click", () => {
  openSearch();
});

function setActiveNav(sectionId) {
  const targetId = sectionId === "top" ? "latest" : sectionId;

  sectionNavLinks.forEach((link) => {
    const href = link.getAttribute("href") || "";
    const isTracksLink = href === "/tracks" || href === "index.html?view=tracks";
    const isLicensingLink = href === "/licensing" || href === "index.html?view=licensing";
    const isActive = (targetId === "all-tracks" && isTracksLink) || (targetId === "licensing" && isLicensingLink);
    link.classList.toggle("active-line", isActive);
    link.setAttribute("aria-current", isActive ? "page" : "false");
  });
}

function clearActiveNav() {
  sectionNavLinks.forEach((link) => {
    link.classList.remove("active-line");
    link.setAttribute("aria-current", "false");
  });
}

function routePathToSection(path) {
  const cleanPath = (path || "/").replace(/\/+$/g, "") || "/";

  if (cleanPath === "/tracks") {
    return "all-tracks";
  }

  if (cleanPath === "/licensing") {
    return "licensing";
  }

  if (cleanPath === "/" || cleanPath === "/index.html") {
    return "top";
  }

  return "";
}

function scrollToRouteSection(path = window.location.pathname, behavior = "smooth") {
  const sectionId = routePathToSection(path) || "all-tracks";
  const section = document.getElementById(sectionId);

  if (sectionId === "top") {
    window.scrollTo({ top: 0, behavior });
  } else if (section) {
    section.scrollIntoView({ block: "start", behavior });
  }

  updateActiveNav();
}

async function openShellRoute(path, updateUrl = true) {
  const normalized = publicRoutePath(path) || path;

  if (!normalized) return false;

  if (normalized === "/" || normalized === "/tracks" || normalized === "/licensing") {
    closeShellView();
    hideSongPageForShell();
    applyRouteMeta(normalized);
    if (updateUrl) history.pushState(null, "", publicRouteUrl(normalized));
    scrollToRouteSection(normalized, "smooth");
    return true;
  }

  if (normalized === "/artists" || normalized.startsWith("/artist/")) {
    return renderShellArtists(normalized, updateUrl);
  }

  if (["/about", "/contact", "/usage-policy"].includes(normalized)) {
    return loadStaticShellPage(normalized, updateUrl);
  }

  return false;
}

window.sgShellNavigate = function sgShellNavigate(href) {
  const path = publicRoutePath(href);
  if (!path || path.startsWith("/song/")) return false;

  openShellRoute(path, true).then((handled) => {
    if (!handled) window.location.href = href;
  });
  return true;
};

function updateActiveNav() {
  if (document.body.classList.contains("song-view")) {
    clearActiveNav();
    return;
  }

  if (document.body.classList.contains("shell-view")) {
    return;
  }

  const anchorY = window.scrollY + window.innerHeight * 0.34;
  let currentSection = activeSections[0];

  activeSections.forEach((sectionId) => {
    const section = document.getElementById(sectionId);

    if (section && section.offsetTop <= anchorY) {
      currentSection = sectionId;
    }
  });

  setActiveNav(currentSection);
}

window.addEventListener("scroll", updateActiveNav, { passive: true });
window.addEventListener("resize", updateActiveNav);
updateActiveNav();

playerToggle?.addEventListener("click", () => {
  if (!selectedTrack) return;
  playTrack(selectedTrack);
});

playerPrev?.addEventListener("click", () => {
  playTrackByOffset(-1);
});

playerNext?.addEventListener("click", () => {
  playTrackByOffset(1);
});

playerLike?.addEventListener("click", () => {
  if (!selectedTrack?.id) return;

  const key = `liked_${selectedTrack.id}`;
  const liked = localStorage.getItem(key) !== "true";

  if (liked) {
    localStorage.setItem(key, "true");
  } else {
    localStorage.removeItem(key);
  }

  updatePlayerLike(selectedTrack);
  sendTrackLike(selectedTrack, liked).then((data) => {
    if (!data?.ok) return;
    const syncKey = `liked_sync_${selectedTrack.id}`;
    if (liked) {
      localStorage.setItem(syncKey, "true");
    } else {
      localStorage.removeItem(syncKey);
    }
  });
});

if (playerVolBtn && playerVolPopup) {
  playerVolBtn.addEventListener("mouseenter", () => {
    window.clearTimeout(playerVolTimer);
    playerVolPopup.style.display = "flex";
  });

  playerVolBtn.addEventListener("mouseleave", () => {
    playerVolTimer = window.setTimeout(() => {
      playerVolPopup.style.display = "none";
    }, 300);
  });

  playerVolPopup.addEventListener("mouseenter", () => {
    window.clearTimeout(playerVolTimer);
  });

  playerVolPopup.addEventListener("mouseleave", () => {
    playerVolTimer = window.setTimeout(() => {
      playerVolPopup.style.display = "none";
    }, 300);
  });
}

volumeSlider?.addEventListener("input", () => {
  currentVolume = Math.max(0, Math.min(1, Number(volumeSlider.value) / 100));
  isMuted = currentVolume === 0;
  applyPlayerVolume();
});

playerMute?.addEventListener("click", () => {
  isMuted = !isMuted;
  if (volumeSlider) {
    volumeSlider.value = isMuted ? 0 : Math.round((currentVolume || 1) * 100);
  }
  applyPlayerVolume();
});

playerCover?.addEventListener("click", () => {
  if (!selectedTrack) return;
  openSongPage(selectedTrack);
});

playerClose?.addEventListener("click", () => {
  stopCurrent(true);
  selectedTrack = null;
  hidePlayer();
  if (playerTitle) {
    playerTitle.classList.remove("is-scrolling");
    playerTitle.textContent = "Select a track";
  }
  if (playerVolPopup) {
    playerVolPopup.style.display = "none";
  }
  syncPlayer();
  syncPlayingCards();
});

progressShell?.addEventListener("pointerdown", startPlayerSeek);
if (!window.PointerEvent) {
  progressShell?.addEventListener("touchstart", startPlayerSeek, { passive: false });
}
progressShell?.addEventListener("keydown", (event) => {
  const keys = ["ArrowLeft", "ArrowRight", "Home", "End"];

  if (!keys.includes(event.key)) {
    return;
  }

  event.preventDefault();
  const current = Number(progressShell.getAttribute("aria-valuenow")) || 0;
  const next = {
    ArrowLeft: Math.max(0, current - 5),
    ArrowRight: Math.min(100, current + 5),
    Home: 0,
    End: 100,
  }[event.key];

  setPlayerProgress(next / 100);
});

songBack.addEventListener("click", () => closeSongPage());

songPlay.addEventListener("click", () => {
  const track = tracks.find((item) => item.id === songPlay.dataset.trackId) || selectedTrack;
  playTrack(track);
});

function startSongWaveformSeek(event) {
  if (!selectedTrack) return;
  event.preventDefault();
  event.stopPropagation();
  playerSeekActive = true;
  songWaveform.classList.add("is-seeking");

  let latestFraction = progressFractionFromPointer(event, songWaveform);
  let moved = false;
  const wasPlaying = isPlaying;

  setPlayerProgress(latestFraction, false);

  const move = (moveEvent) => {
    moveEvent.preventDefault();
    moved = true;
    latestFraction = progressFractionFromPointer(moveEvent, songWaveform);
    setPlayerProgress(latestFraction, false);
  };

  const stop = () => {
    playerSeekActive = false;
    songWaveform.classList.remove("is-seeking");
    window.removeEventListener("pointermove", move);
    window.removeEventListener("pointerup", stop);
    window.removeEventListener("pointercancel", stop);
    window.removeEventListener("touchmove", move);
    window.removeEventListener("touchend", stop);
    window.removeEventListener("touchcancel", stop);

    setPlayerProgress(latestFraction, true);

    if (!wasPlaying || !isPlaying) {
      playTrack(selectedTrack);
      return;
    }

    if (!moved) {
      syncPlayer();
      syncPlayingCards();
    }
  };

  if (event.type === "touchstart") {
    window.addEventListener("touchmove", move, { passive: false });
    window.addEventListener("touchend", stop, { once: true });
    window.addEventListener("touchcancel", stop, { once: true });
  } else {
    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", stop, { once: true });
    window.addEventListener("pointercancel", stop, { once: true });
  }
}

songWaveform.addEventListener("pointerdown", startSongWaveformSeek);
if (!window.PointerEvent) {
  songWaveform.addEventListener("touchstart", startSongWaveformSeek, { passive: false });
}

songDownload.addEventListener("click", (event) => {
  event.preventDefault();
  const track = tracks.find((item) => item.id === songPlay.dataset.trackId) || selectedTrack;
  downloadTrack(track);
});

searchInput.addEventListener("input", renderSearchResults);

searchClose.addEventListener("click", closeSearch);

searchOverlay.addEventListener("click", (event) => {
  if (event.target === searchOverlay) {
    closeSearch();
  }
});

searchResults.addEventListener("click", (event) => {
  const result = event.target.closest(".search-result");

  if (!result) {
    return;
  }

  goToSearchResult(result.dataset.resultTarget);
});

trackPagination.addEventListener("click", (event) => {
  const button = event.target.closest("[data-page]");

  if (!button || button.disabled) {
    return;
  }

  renderAllTracksPage(Number(button.dataset.page), true);
});

loadMoreButton?.addEventListener("click", () => {
  const cols = getColumnCount();
  const hasAdCard = Boolean(renderGridAdCard());
  const increment = hasAdCard ? cols * 2 - 1 : cols * 2;
  tracksShown += increment;
  showTracks();
});

window.addEventListener("resize", () => {
  window.clearTimeout(trackGridResizeTimer);
  trackGridResizeTimer = window.setTimeout(() => {
    const nextColumnCount = getColumnCount();
    if (nextColumnCount !== lastTrackGridColumnCount) {
      showTracks();
    }
    renderLatestReleases();
  }, 200);
});

document.addEventListener("click", (event) => {
  const link = event.target.closest("a[href]");
  if (!link || link.target === "_blank" || link.hasAttribute("download") || event.metaKey || event.ctrlKey || event.shiftKey) {
    return;
  }

  const path = publicRoutePath(link.getAttribute("href"));
  if (!path || path.startsWith("/song/")) {
    return;
  }

  event.preventDefault();
  setMobileMenu(false);
  openShellRoute(path, true).then((handled) => {
    if (!handled) window.location.href = link.href;
  });
}, true);

document.querySelectorAll('.side-nav a[href^="/"], .mobile-brand[href^="/"]').forEach((link) => {
  link.addEventListener("click", (event) => {
    if (event.defaultPrevented) {
      return;
    }

    const href = link.getAttribute("href") || "/";
    const path = publicRoutePath(href);

    if (!path || path.startsWith("/song/")) {
      return;
    }

    setMobileMenu(false);

    event.preventDefault();
    openShellRoute(path, true).then((handled) => {
      if (!handled) window.location.href = href;
    });
  });
});

document.querySelector(".mobile-brand").addEventListener("click", () => setMobileMenu(false));

mobileMenuToggle.addEventListener("click", () => {
  setMobileMenu(!document.body.classList.contains("menu-open"));
});

window.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeSearch();
    setMobileMenu(false);
  }
});

window.addEventListener("resize", () => {
  if (window.innerWidth > 780) {
    setMobileMenu(false);
  }

  queueSongWaveformRender();
});

window.addEventListener("popstate", () => {
  const track = getTrackFromLocation();

  if (track) {
    openSongPage(track, false);
    return;
  }

  if (document.body.classList.contains("song-view")) {
    closeSongPage(false);
    return;
  }

  routeSectionFromLocation();
});

sideNav.addEventListener("pointerenter", () => {
  sideNav.classList.add("is-expanded");
});

sideNav.addEventListener("pointerleave", () => {
  sideNav.classList.remove("is-expanded");
});

sideNav.addEventListener("focusin", () => {
  sideNav.classList.add("is-expanded");
});

sideNav.addEventListener("focusout", () => {
  requestAnimationFrame(() => {
    if (!sideNav.contains(document.activeElement)) {
      sideNav.classList.remove("is-expanded");
    }
  });
});

(function initBetaFeedbackPopup() {
  if (window.sgBetaPopupInit) return;

  const backdrop = document.getElementById('betaBackdrop');
  const closeBtn = document.getElementById('betaClose');
  const dismissBtn = document.getElementById('betaDismiss');
  const submitBtn = document.getElementById('betaSubmit');
  const successClose = document.getElementById('betaSuccessClose');
  const defaultState = document.getElementById('betaDefault');
  const successState = document.getElementById('betaSuccess');

  if (!backdrop || !closeBtn || !dismissBtn || !submitBtn || !successClose || !defaultState || !successState) return;
  window.sgBetaPopupInit = true;

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


(function() {
  const btnShare = document.getElementById('btnShare');
  const sharePopup = document.getElementById('sharePopup');
  const copyLink = document.getElementById('copyLink');
  const shareWA = document.getElementById('shareWhatsApp');
  const shareIG = document.getElementById('shareInstagram');
  const shareTW = document.getElementById('shareTwitter');
  const shareMore = document.getElementById('shareMore');
  const toast = document.getElementById('shareToast');

  if (!btnShare || !sharePopup || !toast) return;

  function currentShareData() {
    const track = selectedTrack;
    const songUrl = track ? siteUrl(trackUrl(track)) : window.location.href;
    const songTitle = track?.title || document.querySelector(
      '.song-title, h1, #songPageTitle'
    )?.textContent?.trim() || 'SG Production Track';
    const shareText = '🎵 ' + songTitle
      + ' by SG Production — Listen & Download free!';

    return { songUrl, songTitle, shareText };
  }

  btnShare.addEventListener('click', function(e) {
    e.stopPropagation();
    sharePopup.classList.toggle('is-open');
  });

  document.addEventListener('click', function() {
    sharePopup.classList.remove('is-open');
  });

  sharePopup.addEventListener('click', function(e) {
    e.stopPropagation();
  });

  copyLink?.addEventListener('click', function() {
    const { songUrl } = currentShareData();
    navigator.clipboard.writeText(songUrl)
      .then(function() {
        showToast('🔗 Link copied to clipboard!');
        sharePopup.classList.remove('is-open');
      });
  });

  shareWA?.addEventListener('click', function() {
    const { songUrl } = currentShareData();
    const songTitle = (document.querySelector(
      '#songPageTitle, .song-title, h1'
    )?.textContent?.trim() || 'SG Production Track').replace(/^[◆🔷�\uFFFD\s]+/, '');

    const songGenre = document.querySelector(
      '#songGenre, .song-genre, .genre-tag, .track-genre'
    )?.textContent?.trim() || '';

    const whatsappText =
      '🎵 *' + songTitle + '* by SG Production\n' +
      (songGenre ? '🎧 Genre: ' + songGenre + '\n' : '') +
      '⬇️ Free Download — No sign up needed\n' +
      '🔊 Stream & Download now:\n' +
      songUrl + '\n' +
      '\n' +
      '━━━━━━━━━━━━━━\n' +
      '\n' +
      'SG Production • Official Music Website\n' +
      'www.sgproduction.music';

    window.open(
      'https://wa.me/?text=' +
      encodeURIComponent(whatsappText),
      '_blank'
    );
    sharePopup.classList.remove('is-open');
  });

  if (shareIG) {
    shareIG.addEventListener('click', function() {
      const { songUrl } = currentShareData();
      navigator.clipboard.writeText(songUrl)
        .then(function() {
          showToast('🔗 Link copied — paste it on Instagram!');
        });
      sharePopup.classList.remove('is-open');
    });
  }

  shareTW?.addEventListener('click', function() {
    const { songUrl, shareText } = currentShareData();
    const text = encodeURIComponent(shareText);
    const url = encodeURIComponent(songUrl);
    window.open(
      'https://twitter.com/intent/tweet?text='
      + text + '&url=' + url, '_blank'
    );
    sharePopup.classList.remove('is-open');
  });

  shareMore?.addEventListener('click', function() {
    const { songUrl, songTitle, shareText } = currentShareData();
    if (navigator.share) {
      navigator.share({
        title: songTitle + ' — SG Production',
        text: shareText,
        url: songUrl
      });
    } else {
      navigator.clipboard.writeText(songUrl)
        .then(function() {
          showToast('🔗 Link copied!');
        });
    }
    sharePopup.classList.remove('is-open');
  });

  function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(function() {
      toast.classList.remove('show');
    }, 2500);
  }
})();
