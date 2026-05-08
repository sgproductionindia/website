const defaultArtists = [
  {
    id: "sg-production",
    name: "SG Production",
    style: "Original Mix",
    image: "assets/artist-photo-1.svg",
    year: "2026",
    order: 1,
    trackGenres: ["Original Mix", "Marathi", "Soundcheck", "Hindi"]
  },
  {
    id: "sg-soundcheck",
    name: "SG Soundcheck",
    style: "Soundcheck",
    image: "assets/artist-photo-2.svg",
    year: "2026",
    order: 2,
    trackGenres: ["Soundcheck"]
  },
  {
    id: "marathi-pulse",
    name: "Marathi Pulse",
    style: "Marathi",
    image: "assets/artist-photo-3.svg",
    year: "2025",
    order: 3,
    trackGenres: ["Marathi"]
  },
  {
    id: "hindi-wave",
    name: "Hindi Wave",
    style: "Hindi",
    image: "assets/artist-photo-4.svg",
    year: "2025",
    order: 4,
    trackGenres: ["Hindi"]
  },
  {
    id: "night-circuit",
    name: "Night Circuit",
    style: "Original Mix",
    image: "assets/artist-photo-5.svg",
    year: "2024",
    order: 5,
    trackGenres: ["Original Mix"]
  }
];

const defaultTracks = [
  { id: "midnight-echo", title: "Midnight Echo", genre: "Marathi", duration: "3:42", cover: "assets/cover-1.jpg", bpm: 142, tone: 110, wave: "triangle" },
  { id: "neon-pulse", title: "Neon Pulse", genre: "Soundcheck", duration: "2:55", cover: "assets/cover-2.jpg", bpm: 128, tone: 146.83, wave: "sine" },
  { id: "dark-frequency", title: "Dark Frequency", genre: "Hindi", duration: "4:10", cover: "assets/cover-3.jpg", bpm: 92, tone: 82.41, wave: "sawtooth" },
  { id: "electric-dream", title: "Electric Dream", genre: "Original Mix", duration: "5:20", cover: "assets/cover-4.jpg", bpm: 104, tone: 196, wave: "sine" },
  { id: "shadow-wave", title: "Shadow Wave", genre: "Marathi", duration: "3:15", cover: "assets/cover-5.jpg", bpm: 136, tone: 123.47, wave: "triangle" },
  { id: "lost-signal", title: "Lost Signal", genre: "Soundcheck", duration: "4:05", cover: "assets/cover-6.jpg", bpm: 124, tone: 174.61, wave: "sawtooth" },
  { id: "void-rhythm", title: "Void Rhythm", genre: "Hindi", duration: "2:48", cover: "assets/cover-7.jpg", bpm: 88, tone: 98, wave: "triangle" },
  { id: "raw-energy", title: "Raw Energy", genre: "Marathi", duration: "3:30", cover: "assets/cover-1.jpg", bpm: 150, tone: 130.81, wave: "square" },
  { id: "deep-current", title: "Deep Current", genre: "Original Mix", duration: "4:55", cover: "assets/cover-2.jpg", bpm: 96, tone: 155.56, wave: "sine" },
  { id: "pressure-drop", title: "Pressure Drop", genre: "Soundcheck", duration: "3:22", cover: "assets/cover-3.jpg", bpm: 132, tone: 185, wave: "sawtooth" },
  { id: "static-rush", title: "Static Rush", genre: "Marathi", duration: "2:59", cover: "assets/cover-4.jpg", bpm: 145, tone: 116.54, wave: "square" },
  { id: "night-circuit", title: "Night Circuit", genre: "Hindi", duration: "3:47", cover: "assets/cover-5.jpg", bpm: 94, tone: 87.31, wave: "triangle" },
  { id: "stellar-drift", title: "Stellar Drift", genre: "Original Mix", duration: "5:10", cover: "assets/cover-6.jpg", bpm: 100, tone: 207.65, wave: "sine" },
  { id: "hyper-drive", title: "Hyper Drive", genre: "Soundcheck", duration: "3:01", cover: "assets/cover-7.jpg", bpm: 134, tone: 164.81, wave: "sawtooth" },
  { id: "phantom-bass", title: "Phantom Bass", genre: "Marathi", duration: "3:38", cover: "assets/cover-1.jpg", bpm: 140, tone: 103.83, wave: "triangle" }
];

const artistGrid = document.querySelector("#artistGrid");
const artistSearchInput = document.querySelector("#artistSearchInput");
const focusArtistSearch = document.querySelector("#focusArtistSearch");
const sideNav = document.querySelector(".side-nav");
const mobileMenuToggle = document.querySelector("#mobileMenuToggle");
const mobileBrand = document.querySelector(".mobile-brand");
const artistDirectory = document.querySelector("#artistDirectory");
const artistProfilePage = document.querySelector("#artistProfilePage");
const artistProfileBg = document.querySelector("#artistProfileBg");
const artistProfileImage = document.querySelector("#artistProfileImage");
const artistProfileName = document.querySelector("#artistProfileName");
const artistTrackList = document.querySelector("#artistTrackList");
const relatedArtistGrid = document.querySelector("#relatedArtistGrid");

let artists = [...defaultArtists];
let tracks = [...defaultTracks];
let siteSettings = {
  site: {
    title: "SG Production",
    contactEmail: "bookings@sgproduction.example"
  },
  links: {
    instagram: "https://www.instagram.com/sgproduction.music",
    spotify: "https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb?autoplay=true",
    appleMusic: "https://music.apple.com/in/artist/sg-production/1580814477",
    youtube: "https://www.youtube.com/@sgproductionindia"
  }
};
let activeTrackId = "";
let activeTrackDuration = 0;
let activeStartedAt = 0;
let activePausedAt = 0;
let activeAnimationFrame = 0;

function escapeHTML(value) {
  return value.replace(/[&<>"']/g, (character) => {
    const entities = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    };

    return entities[character];
  });
}

function slugClass(value) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
}

function isLocalAudioPath(url) {
  return /^uploads\/audio\//.test(url);
}

function normalizeTrack(track) {
  if (!track || !track.title) {
    return null;
  }

  const rawDownloadUrl = track.downloadUrl || "";
  const previewUrl = track.previewUrl || track.audioUrl || (isLocalAudioPath(rawDownloadUrl) ? rawDownloadUrl : "");

  return {
    id: track.id || slugClass(track.title),
    title: track.title,
    artist: track.artist || "SG Production",
    artistId: track.artistId || "sg-production",
    genre: track.genre || "Soundcheck",
    duration: track.duration || "0:0",
    cover: track.cover || "assets/cover-1.jpg",
    previewUrl,
    downloadUrl: rawDownloadUrl,
    bpm: Number(track.bpm) || 124,
    tone: Number(track.tone) || 146.83,
    wave: track.wave || "sine"
  };
}

function normalizeArtist(artist) {
  if (!artist || !artist.name) {
    return null;
  }

  return {
    id: artist.id || slugClass(artist.name),
    name: artist.name,
    style: artist.style || "Original Mix",
    image: artist.image || "assets/artist-photo-1.svg",
    year: artist.year || "2026",
    order: Number(artist.order) || 99,
    trackGenres: Array.isArray(artist.trackGenres) && artist.trackGenres.length > 0 ? artist.trackGenres : [artist.style || "Original Mix"]
  };
}

async function fetchJson(path, fallback) {
  try {
    const response = await fetch(path, { cache: "no-store" });

    if (!response.ok) {
      return fallback;
    }

    return await response.json();
  } catch {
    return fallback;
  }
}

function mergeById(primary, fallback) {
  const seen = new Set();
  return [...primary, ...fallback].filter((item) => {
    if (!item || seen.has(item.id)) {
      return false;
    }

    seen.add(item.id);
    return true;
  });
}

function setLink(selector, href) {
  document.querySelectorAll(selector).forEach((element) => {
    if (!href) {
      element.hidden = true;
      return;
    }

    element.hidden = false;
    element.href = href;
  });
}

function applySiteSettings() {
  const { site, links } = siteSettings;

  document.title = `Artists | ${site.title}`;
  setLink('a[aria-label="Instagram"]', links.instagram);
  setLink('a[aria-label="Spotify"]', links.spotify);
  setLink('a[aria-label="Apple Music"]', links.appleMusic);
  setLink('a[aria-label="YouTube"]', links.youtube);
  setLink('a[aria-label="Contact SG Production"]', site.contactEmail ? `mailto:${site.contactEmail}` : "");
}

async function loadCatalogData() {
  const [uploadedTracks, uploadedArtists, loadedSettings] = await Promise.all([
    fetchJson("data/tracks.json", []),
    fetchJson("data/artists.json", []),
    fetchJson("data/settings.json", siteSettings)
  ]);

  const normalizedTracks = Array.isArray(uploadedTracks) ? uploadedTracks.map(normalizeTrack).filter(Boolean) : [];
  const normalizedArtists = Array.isArray(uploadedArtists) ? uploadedArtists.map(normalizeArtist).filter(Boolean) : [];

  tracks = mergeById(normalizedTracks, defaultTracks.map(normalizeTrack).filter(Boolean));
  artists = mergeById(normalizedArtists, defaultArtists.map(normalizeArtist).filter(Boolean));
  siteSettings = {
    ...siteSettings,
    ...loadedSettings,
    site: {
      ...siteSettings.site,
      ...(loadedSettings.site || {})
    },
    links: {
      ...siteSettings.links,
      ...(loadedSettings.links || {})
    }
  };
  applySiteSettings();
}

function trackUrl(track) {
  return /^https?:$/.test(window.location.protocol) ? `/${slugClass(track.title || track.id)}` : `index.html#song-${track.id}`;
}

function renderArtistCard(artist) {
  const card = document.createElement("article");
  card.className = "artist-card";
  card.innerHTML = `
    <a class="artist-card-link" href="artists.html?artist=${escapeHTML(artist.id)}" aria-label="Open ${escapeHTML(artist.name)} artist page">
      <img src="${escapeHTML(artist.image)}" alt="${escapeHTML(artist.name)} artist photo" loading="lazy">
      <div class="artist-card-copy">
        <h3>${escapeHTML(artist.name)}</h3>
      </div>
    </a>
  `;

  return card;
}

function getFilteredArtists() {
  const query = artistSearchInput.value.trim().toLowerCase();

  return artists.filter((artist) => {
    return `${artist.name} ${artist.style} ${artist.trackGenres.join(" ")}`.toLowerCase().includes(query);
  });
}

function renderArtists() {
  const filtered = getFilteredArtists();

  if (filtered.length === 0) {
    artistGrid.innerHTML = '<p class="artist-empty">No artists found.</p>';
    return;
  }

  artistGrid.replaceChildren(...filtered.map(renderArtistCard));
}

function durationToSeconds(duration) {
  const [minutes, seconds] = duration.split(":").map(Number);
  return minutes * 60 + seconds;
}

function formatTimer(seconds) {
  const safeSeconds = Math.max(0, Math.floor(seconds));
  return `${Math.floor(safeSeconds / 60)}:${safeSeconds % 60}`;
}

function icon(name) {
  if (name === "download") {
    return '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path></svg>';
  }

  return '<svg class="play-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="m8 5 11 7-11 7z"></path></svg><svg class="pause-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M8 5v14"></path><path d="M16 5v14"></path></svg>';
}

function getArtistTracks(artist) {
  const matches = tracks.filter((track) => {
    return track.artistId === artist.id || artist.trackGenres.includes(track.genre);
  });

  return artist.id === "sg-production" ? matches.slice(0, 7) : matches.slice(0, 5);
}

function renderTrackRow(track) {
  const row = document.createElement("article");
  row.className = "artist-track-row";
  row.dataset.trackId = track.id;
  row.innerHTML = `
    <div class="artist-track-title">
      <img src="${escapeHTML(track.cover)}" alt="${escapeHTML(track.title)} cover art" loading="lazy">
      <strong>${escapeHTML(track.title)}</strong>
    </div>
    <div class="artist-track-player">
      <button class="artist-track-play" type="button" data-action="play" aria-label="Play ${escapeHTML(track.title)}">${icon("play")}</button>
      <div class="artist-track-progress-shell" data-action="seek" role="slider" tabindex="0" aria-label="${escapeHTML(track.title)} progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <span class="artist-track-progress-bar"></span>
      </div>
      <span class="artist-track-time">0:0/${escapeHTML(track.duration)}</span>
    </div>
    <button class="artist-track-download" type="button" data-action="download" aria-label="Download ${escapeHTML(track.title)}">
      ${icon("download")}
      <span>Download</span>
    </button>
  `;

  return row;
}

function renderRelatedArtists(activeArtist) {
  const related = artists.filter((artist) => artist.id !== activeArtist.id).slice(0, 4);
  relatedArtistGrid.replaceChildren(...related.map(renderArtistCard));
}

function renderArtistProfile(artist) {
  const artistTracks = getArtistTracks(artist);

  artistProfileImage.src = artist.image;
  artistProfileImage.alt = `${artist.name} artist photo`;
  artistProfileName.textContent = artist.name;
  artistProfileBg.style.backgroundImage = `url("${artist.image}")`;
  artistTrackList.replaceChildren(...artistTracks.map(renderTrackRow));
  renderRelatedArtists(artist);
  document.title = `${artist.name} | ${siteSettings.site.title}`;
}

function showDirectory() {
  stopProfilePlayback();
  artistDirectory.hidden = false;
  artistProfilePage.hidden = true;
  document.title = `Artists | ${siteSettings.site.title}`;
}

function showProfile(artist) {
  artistDirectory.hidden = true;
  artistProfilePage.hidden = false;
  renderArtistProfile(artist);
}

function syncProfileRows(elapsed = activePausedAt) {
  artistTrackList.querySelectorAll(".artist-track-row").forEach((row) => {
    const isActive = row.dataset.trackId === activeTrackId && activeTrackDuration > 0;
    const bar = row.querySelector(".artist-track-progress-bar");
    const progress = row.querySelector(".artist-track-progress-shell");
    const time = row.querySelector(".artist-track-time");
    const track = tracks.find((item) => item.id === row.dataset.trackId);
    const total = track ? durationToSeconds(track.duration) : 0;
    const current = isActive ? Math.min(total, elapsed) : 0;
    const percent = total ? Math.min(100, (current / total) * 100) : 0;

    row.classList.toggle("is-playing", isActive && activeAnimationFrame);
    bar.style.width = `${percent}%`;
    progress.setAttribute("aria-valuenow", String(Math.round(percent)));
    progress.setAttribute("aria-valuetext", `${formatTimer(current)} of ${track ? track.duration : "0:0"}`);
    time.textContent = `${formatTimer(current)}/${track ? track.duration : "0:0"}`;
  });
}

function progressFractionFromPointer(event, element) {
  const rect = element.getBoundingClientRect();
  const x = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);

  return rect.width > 0 ? x / rect.width : 0;
}

function seekProfileTrack(track, fraction, shouldPlay = false) {
  const total = durationToSeconds(track.duration);
  const target = Math.min(total, Math.max(0, fraction * total));
  const wasPlaying = activeTrackId === track.id && activeAnimationFrame;

  cancelAnimationFrame(activeAnimationFrame);
  activeAnimationFrame = 0;
  activeTrackId = track.id;
  activeTrackDuration = total;
  activePausedAt = target;

  if (wasPlaying || shouldPlay) {
    activeStartedAt = performance.now() - activePausedAt * 1000;
    activeAnimationFrame = requestAnimationFrame(tickProfileProgress);
  }

  syncProfileRows(activePausedAt);
}

function startProfileSeek(event, row, track) {
  const progress = event.target.closest(".artist-track-progress-shell");

  if (!progress) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  seekProfileTrack(track, progressFractionFromPointer(event, progress));

  const move = (moveEvent) => {
    seekProfileTrack(track, progressFractionFromPointer(moveEvent, progress));
  };

  const stop = () => {
    window.removeEventListener("pointermove", move);
    window.removeEventListener("pointerup", stop);
    window.removeEventListener("pointercancel", stop);
  };

  window.addEventListener("pointermove", move);
  window.addEventListener("pointerup", stop, { once: true });
  window.addEventListener("pointercancel", stop, { once: true });
}

function tickProfileProgress() {
  if (!activeTrackId) {
    return;
  }

  const elapsed = (performance.now() - activeStartedAt) / 1000;

  if (elapsed >= activeTrackDuration) {
    stopProfilePlayback();
    return;
  }

  activePausedAt = elapsed;
  syncProfileRows(elapsed);
  activeAnimationFrame = requestAnimationFrame(tickProfileProgress);
}

function playProfileTrack(track) {
  const isSameTrack = activeTrackId === track.id;

  if (isSameTrack && activeAnimationFrame) {
    cancelAnimationFrame(activeAnimationFrame);
    activeAnimationFrame = 0;
    syncProfileRows(activePausedAt);
    return;
  }

  cancelAnimationFrame(activeAnimationFrame);

  if (!isSameTrack) {
    activePausedAt = 0;
  }

  activeTrackId = track.id;
  activeTrackDuration = durationToSeconds(track.duration);
  activeStartedAt = performance.now() - activePausedAt * 1000;
  activeAnimationFrame = requestAnimationFrame(tickProfileProgress);
  syncProfileRows(activePausedAt);
}

function stopProfilePlayback() {
  cancelAnimationFrame(activeAnimationFrame);
  activeTrackId = "";
  activeTrackDuration = 0;
  activePausedAt = 0;
  activeAnimationFrame = 0;
  syncProfileRows(0);
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

function synthSample(track, seconds = 12) {
  const sampleRate = 44100;
  const total = Math.floor(sampleRate * seconds);
  const samples = new Float32Array(total);
  const beatRate = track.bpm / 60;

  for (let i = 0; i < total; i += 1) {
    const t = i / sampleRate;
    const beat = Math.max(0, Math.sin(2 * Math.PI * beatRate * t));
    const lead = oscillator(track.wave, track.tone, t);
    const bass = Math.sin(2 * Math.PI * (track.tone / 2) * t);
    const fadeIn = Math.min(1, t / 0.18);
    const fadeOut = Math.min(1, (seconds - t) / 0.35);
    samples[i] = (lead * 0.22 + bass * 0.35) * (0.3 + beat * 0.7) * Math.max(0, Math.min(fadeIn, fadeOut));
  }

  return samples;
}

function writeString(view, offset, value) {
  for (let i = 0; i < value.length; i += 1) {
    view.setUint8(offset + i, value.charCodeAt(i));
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

function downloadTrack(track) {
  window.location.href = trackUrl(track);
}

function routeArtistPage() {
  const params = new URLSearchParams(window.location.search);
  const artistId = params.get("artist");
  const artist = artists.find((item) => item.id === artistId);

  if (artist) {
    showProfile(artist);
    return;
  }

  showDirectory();
}

function openMobileMenu() {
  document.body.classList.add("menu-open");
  mobileMenuToggle.classList.add("is-open");
  mobileMenuToggle.setAttribute("aria-expanded", "true");
  mobileMenuToggle.setAttribute("aria-label", "Close menu");
}

function closeMobileMenu() {
  document.body.classList.remove("menu-open");
  mobileMenuToggle.classList.remove("is-open");
  mobileMenuToggle.setAttribute("aria-expanded", "false");
  mobileMenuToggle.setAttribute("aria-label", "Open menu");
}

artistSearchInput.addEventListener("input", renderArtists);
focusArtistSearch.addEventListener("click", () => {
  if (!artistDirectory || artistDirectory.hidden) {
    window.location.href = "artists.html";
    return;
  }

  artistSearchInput.focus();
  closeMobileMenu();
});

artistTrackList.addEventListener("pointerdown", (event) => {
  const row = event.target.closest(".artist-track-row");

  if (!row || !event.target.closest('[data-action="seek"]')) {
    return;
  }

  const track = tracks.find((item) => item.id === row.dataset.trackId);

  if (!track) {
    return;
  }

  startProfileSeek(event, row, track);
});

artistTrackList.addEventListener("click", (event) => {
  const row = event.target.closest(".artist-track-row");

  if (!row) {
    return;
  }

  const track = tracks.find((item) => item.id === row.dataset.trackId);

  if (!track) {
    return;
  }

  if (event.target.closest('[data-action="seek"]')) {
    return;
  }

  if (event.target.closest('[data-action="download"]')) {
    downloadTrack(track);
    return;
  }

  if (event.target.closest('[data-action="play"]')) {
    playProfileTrack(track);
  }
});

artistTrackList.addEventListener("keydown", (event) => {
  const progress = event.target.closest(".artist-track-progress-shell");

  if (!progress) {
    return;
  }

  const row = progress.closest(".artist-track-row");
  const track = row ? tracks.find((item) => item.id === row.dataset.trackId) : null;

  if (!track) {
    return;
  }

  const keys = ["ArrowLeft", "ArrowRight", "Home", "End"];

  if (!keys.includes(event.key)) {
    return;
  }

  event.preventDefault();
  const current = Number(progress.getAttribute("aria-valuenow")) || 0;
  const next = {
    ArrowLeft: Math.max(0, current - 5),
    ArrowRight: Math.min(100, current + 5),
    Home: 0,
    End: 100,
  }[event.key];

  seekProfileTrack(track, next / 100);
});

mobileMenuToggle.addEventListener("click", () => {
  if (document.body.classList.contains("menu-open")) {
    closeMobileMenu();
  } else {
    openMobileMenu();
  }
});

mobileBrand.addEventListener("click", closeMobileMenu);

sideNav.addEventListener("click", (event) => {
  if (event.target.closest("a.nav-link")) {
    closeMobileMenu();
  }
});

window.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeMobileMenu();
  }
});

loadCatalogData().then(() => {
  renderArtists();
  routeArtistPage();
});
