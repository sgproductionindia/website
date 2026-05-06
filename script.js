const tracks = [
  {
    id: "midnight-echo",
    title: "Midnight Echo",
    artist: "SG Production",
    genre: "Marathi",
    duration: "3:42",
    cover: "assets/cover-1.jpg",
    isNew: true,
    bpm: 142,
    tone: 110,
    wave: "triangle"
  },
  {
    id: "neon-pulse",
    title: "Neon Pulse",
    artist: "SG Production",
    genre: "Soundcheck",
    duration: "2:55",
    cover: "assets/cover-2.jpg",
    isNew: true,
    bpm: 128,
    tone: 146.83,
    wave: "sine"
  },
  {
    id: "dark-frequency",
    title: "Dark Frequency",
    artist: "SG Production",
    genre: "Hindi",
    duration: "4:10",
    cover: "assets/cover-3.jpg",
    isNew: false,
    bpm: 92,
    tone: 82.41,
    wave: "sawtooth"
  },
  {
    id: "electric-dream",
    title: "Electric Dream",
    artist: "SG Production",
    genre: "Original Mix",
    duration: "5:20",
    cover: "assets/cover-4.jpg",
    isNew: false,
    bpm: 104,
    tone: 196,
    wave: "sine"
  },
  {
    id: "shadow-wave",
    title: "Shadow Wave",
    artist: "SG Production",
    genre: "Marathi",
    duration: "3:15",
    cover: "assets/cover-5.jpg",
    isNew: false,
    bpm: 136,
    tone: 123.47,
    wave: "triangle"
  },
  {
    id: "lost-signal",
    title: "Lost Signal",
    artist: "SG Production",
    genre: "Soundcheck",
    duration: "4:05",
    cover: "assets/cover-6.jpg",
    isNew: false,
    bpm: 124,
    tone: 174.61,
    wave: "sawtooth"
  },
  {
    id: "void-rhythm",
    title: "Void Rhythm",
    artist: "SG Production",
    genre: "Hindi",
    duration: "2:48",
    cover: "assets/cover-7.jpg",
    isNew: false,
    bpm: 88,
    tone: 98,
    wave: "triangle"
  },
  {
    id: "raw-energy",
    title: "Raw Energy",
    artist: "SG Production",
    genre: "Marathi",
    duration: "3:30",
    cover: "assets/cover-1.jpg",
    isNew: false,
    bpm: 150,
    tone: 130.81,
    wave: "square"
  },
  {
    id: "deep-current",
    title: "Deep Current",
    artist: "SG Production",
    genre: "Original Mix",
    duration: "4:55",
    cover: "assets/cover-2.jpg",
    isNew: false,
    bpm: 96,
    tone: 155.56,
    wave: "sine"
  },
  {
    id: "pressure-drop",
    title: "Pressure Drop",
    artist: "SG Production",
    genre: "Soundcheck",
    duration: "3:22",
    cover: "assets/cover-3.jpg",
    isNew: false,
    bpm: 132,
    tone: 185,
    wave: "sawtooth"
  },
  {
    id: "static-rush",
    title: "Static Rush",
    artist: "SG Production",
    genre: "Marathi",
    duration: "2:59",
    cover: "assets/cover-4.jpg",
    isNew: false,
    bpm: 145,
    tone: 116.54,
    wave: "square"
  },
  {
    id: "night-circuit",
    title: "Night Circuit",
    artist: "SG Production",
    genre: "Hindi",
    duration: "3:47",
    cover: "assets/cover-5.jpg",
    isNew: false,
    bpm: 94,
    tone: 87.31,
    wave: "triangle"
  },
  {
    id: "stellar-drift",
    title: "Stellar Drift",
    artist: "SG Production",
    genre: "Original Mix",
    duration: "5:10",
    cover: "assets/cover-6.jpg",
    isNew: false,
    bpm: 100,
    tone: 207.65,
    wave: "sine"
  },
  {
    id: "hyper-drive",
    title: "Hyper Drive",
    artist: "SG Production",
    genre: "Soundcheck",
    duration: "3:01",
    cover: "assets/cover-7.jpg",
    isNew: false,
    bpm: 134,
    tone: 164.81,
    wave: "sawtooth"
  },
  {
    id: "phantom-bass",
    title: "Phantom Bass",
    artist: "SG Production",
    genre: "Marathi",
    duration: "3:38",
    cover: "assets/cover-1.jpg",
    isNew: false,
    bpm: 140,
    tone: 103.83,
    wave: "triangle"
  }
];

const latestGrid = document.querySelector("#latestGrid");
const trackGrid = document.querySelector("#trackGrid");
const trackPagination = document.querySelector("#trackPagination");
const player = document.querySelector("#player");
const playerCover = document.querySelector("#playerCover");
const playerTitle = document.querySelector("#playerTitle");
const playerGenre = document.querySelector("#playerGenre");
const playerToggle = document.querySelector("#playerToggle");
const playerDownload = document.querySelector("#playerDownload");
const progressShell = document.querySelector("#progressShell");
const progressBar = document.querySelector("#progressBar");
const progressTime = document.querySelector("#progressTime");
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

let selectedTrack = tracks[0];
let audioContext = null;
let activeSource = null;
let activeGain = null;
let activeAudio = null;
let startedAt = 0;
let pausedAt = 0;
let isPlaying = false;
let animationFrame = 0;
let waveformResizeFrame = 0;
let allTracks = [];
let allTracksPage = 1;
let siteSettings = {
  advertising: {
    enabled: false,
    mediaUrl: "",
    mediaType: ""
  }
};

const PREVIEW_SECONDS = 12;
const ALL_TRACKS_PER_PAGE = 15;
const DEMO_TRACK_PAGE_COUNT = 12;

function setMobileMenu(open) {
  document.body.classList.toggle("menu-open", open);
  mobileMenuToggle.setAttribute("aria-expanded", String(open));
  mobileMenuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
}

function slugClass(value) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
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

function normalizeUploadedTrack(track) {
  if (!track || !track.title) {
    return null;
  }

  const id = track.id || slugClass(track.title);

  return {
    id,
    title: track.title,
    artist: track.artist || "SG Production",
    genre: track.genre || "Soundcheck",
    duration: track.duration || "0:0",
    cover: track.cover || "assets/cover-1.jpg",
    downloadUrl: track.downloadUrl || "",
    isNew: Boolean(track.isNew),
    bpm: Number(track.bpm) || 124,
    tone: Number(track.tone) || 146.83,
    wave: track.wave || "sine"
  };
}

async function loadUploadedTracks() {
  try {
    const response = await fetch("data/tracks.json", { cache: "no-store" });

    if (!response.ok) {
      return [];
    }

    const data = await response.json();
    const uploadedTracks = Array.isArray(data) ? data : data.tracks;

    if (!Array.isArray(uploadedTracks)) {
      return [];
    }

    return uploadedTracks.map(normalizeUploadedTrack).filter(Boolean);
  } catch {
    return [];
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
      advertising: {
        ...siteSettings.advertising,
        ...(settings.advertising || {})
      }
    };
  } catch (error) {
    return siteSettings;
  }
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

function formatTimer(seconds) {
  const safeSeconds = Math.max(0, Math.floor(seconds));
  const minutes = String(Math.floor(safeSeconds / 60));
  const remainingSeconds = String(safeSeconds % 60);
  return `${minutes}:${remainingSeconds}`;
}

function buildCreditText(track) {
  return `Song: ${track.artist} - ${track.title}\nGenre: ${track.genre}\nFree Download / Stream: sgproduction.example/${track.id}\nCredit: Music provided by SG Production`;
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
        <span class="search-thumb"><img src="${escapeHTML(track.cover)}" alt=""></span>
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

function renderCard(track) {
  const card = document.createElement("article");
  card.className = "track-card";
  card.tabIndex = 0;
  card.dataset.id = track.id;
  card.dataset.genre = track.genre;

  card.innerHTML = `
    <button class="cover-link" type="button" aria-label="Open ${track.title}">
      <img src="${track.cover}" alt="${track.title} cover art" loading="lazy">
      ${track.isNew ? '<span class="badge">New</span>' : ""}
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

function renderTracks(list, target) {
  target.replaceChildren(...list.map(renderCard));
  syncPlayingCards();
}

function buildDemoTrackPages(sourceTracks) {
  const targetCount = ALL_TRACKS_PER_PAGE * DEMO_TRACK_PAGE_COUNT;

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

function renderAllTracksPage(page = allTracksPage, shouldScroll = false) {
  const totalPages = Math.max(1, Math.ceil(allTracks.length / ALL_TRACKS_PER_PAGE));
  allTracksPage = Math.min(Math.max(1, page), totalPages);
  const start = (allTracksPage - 1) * ALL_TRACKS_PER_PAGE;

  renderTracks(allTracks.slice(start, start + ALL_TRACKS_PER_PAGE), trackGrid);
  renderPagination(totalPages);

  if (shouldScroll) {
    document.querySelector("#all-tracks").scrollIntoView({ block: "start", behavior: "smooth" });
  }
}

function setupAudio() {
  if (!audioContext) {
    audioContext = new AudioContext();
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

function playTrack(track) {
  if (selectedTrack.id === track.id && isPlaying) {
    pauseCurrent();
    return;
  }

  const sameTrack = selectedTrack.id === track.id;
  const resumeOffset = sameTrack ? pausedAt : 0;

  stopCurrent(!sameTrack);
  selectedTrack = track;

  if (track.downloadUrl) {
    activeAudio = new Audio(track.downloadUrl);
    activeAudio.currentTime = resumeOffset;
    activeAudio.addEventListener("ended", () => {
      isPlaying = false;
      pausedAt = 0;
      syncPlayer();
      syncPlayingCards();
    });
    activeAudio.play().catch(() => {
      isPlaying = false;
      syncPlayer();
      syncPlayingCards();
    });

    isPlaying = true;
    pausedAt = 0;
    syncPlayer();
    player.classList.add("active");
    syncPlayingCards();
    updateProgress();
    return;
  }

  setupAudio();

  if (audioContext.state === "suspended") {
    audioContext.resume();
  }

  const source = audioContext.createBufferSource();
  const gain = audioContext.createGain();
  source.buffer = createBuffer(track);
  source.loop = true;
  gain.gain.value = 0.82;
  source.connect(gain);
  gain.connect(audioContext.destination);
  source.start(0, resumeOffset);

  activeSource = source;
  activeGain = gain;
  isPlaying = true;
  startedAt = audioContext.currentTime - resumeOffset;
  pausedAt = 0;

  source.onended = () => {
    if (activeSource === source) {
      isPlaying = false;
      syncPlayer();
      syncPlayingCards();
    }
  };

  syncPlayer();
  player.classList.add("active");
  syncPlayingCards();
  updateProgress();
}

function pauseCurrent() {
  if (activeAudio) {
    pausedAt = activeAudio.currentTime;
    activeAudio.pause();
    activeAudio = null;
    isPlaying = false;
    cancelAnimationFrame(animationFrame);
    syncPlayer();
    syncPlayingCards();
    return;
  }

  if (!audioContext || !activeSource) {
    return;
  }

  pausedAt = (audioContext.currentTime - startedAt) % PREVIEW_SECONDS;
  activeSource.stop();
  activeSource = null;
  activeGain = null;
  isPlaying = false;
  cancelAnimationFrame(animationFrame);
  syncPlayer();
  syncPlayingCards();
}

function stopCurrent(resetPause = true) {
  if (activeAudio) {
    activeAudio.pause();
    activeAudio.removeAttribute("src");
    activeAudio.load();
  }

  if (activeSource) {
    activeSource.onended = null;
    activeSource.stop();
  }

  activeAudio = null;
  activeSource = null;
  activeGain = null;
  isPlaying = false;
  cancelAnimationFrame(animationFrame);

  if (resetPause) {
    pausedAt = 0;
  }
}

function syncPlayer() {
  player.classList.toggle("is-playing", isPlaying);
  songPlay.classList.toggle("is-playing", isPlaying && selectedTrack.id === songPlay.dataset.trackId);
  playerTitle.textContent = selectedTrack.title;
  playerGenre.textContent = `${selectedTrack.artist} · ${selectedTrack.genre}`;
  playerCover.style.backgroundImage = `url("${selectedTrack.cover}")`;
  playerToggle.setAttribute("aria-label", isPlaying ? `Pause ${selectedTrack.title}` : `Play ${selectedTrack.title}`);
  songPlay.setAttribute("aria-label", isPlaying ? `Pause ${selectedTrack.title}` : `Play ${selectedTrack.title}`);
  updatePlayerTimer(pausedAt);
}

function syncPlayingCards() {
  document.querySelectorAll(".track-card").forEach((card) => {
    card.classList.toggle("is-playing", isPlaying && card.dataset.id === selectedTrack.id);
  });
}

function updateProgress() {
  if (!isPlaying) {
    return;
  }

  if (activeAudio) {
    const total = Number.isFinite(activeAudio.duration) ? activeAudio.duration : durationToSeconds(selectedTrack.duration);
    const elapsed = activeAudio.currentTime;
    progressBar.style.width = `${Math.min(100, (elapsed / total) * 100)}%`;
    updatePlayerTimer(elapsed, true);
    animationFrame = requestAnimationFrame(updateProgress);
    return;
  }

  if (!audioContext) {
    return;
  }

  const elapsed = (audioContext.currentTime - startedAt) % PREVIEW_SECONDS;
  progressBar.style.width = `${(elapsed / PREVIEW_SECONDS) * 100}%`;
  updatePlayerTimer(elapsed, false);
  animationFrame = requestAnimationFrame(updateProgress);
}

function updatePlayerTimer(elapsed = 0, isActualAudio = Boolean(selectedTrack.downloadUrl)) {
  const totalSeconds = durationToSeconds(selectedTrack.duration);
  const scaledElapsed = isActualAudio ? Math.min(totalSeconds, elapsed) : Math.min(totalSeconds, (elapsed / PREVIEW_SECONDS) * totalSeconds);
  const percent = totalSeconds ? Math.min(100, (scaledElapsed / totalSeconds) * 100) : 0;
  progressBar.style.width = `${percent}%`;
  progressTime.textContent = `${formatTimer(scaledElapsed)}/${formatTimer(totalSeconds)}`;
  progressShell.setAttribute("aria-valuenow", String(Math.round(percent)));
  progressShell.setAttribute("aria-valuetext", `${formatTimer(scaledElapsed)} of ${formatTimer(totalSeconds)}`);
}

function progressFractionFromPointer(event, element) {
  const rect = element.getBoundingClientRect();
  const x = Math.min(Math.max(event.clientX - rect.left, 0), rect.width);

  return rect.width > 0 ? x / rect.width : 0;
}

function setPlayerProgress(fraction) {
  const clamped = Math.min(1, Math.max(0, fraction));
  progressBar.style.width = `${clamped * 100}%`;

  if (activeAudio) {
    const total = Number.isFinite(activeAudio.duration) ? activeAudio.duration : durationToSeconds(selectedTrack.duration);
    activeAudio.currentTime = clamped * total;
    pausedAt = activeAudio.currentTime;
    updatePlayerTimer(pausedAt, true);
    return;
  }

  if (selectedTrack.downloadUrl) {
    pausedAt = clamped * durationToSeconds(selectedTrack.duration);
    updatePlayerTimer(pausedAt, true);
    return;
  }

  const previewOffset = Math.min(PREVIEW_SECONDS - 0.01, clamped * PREVIEW_SECONDS);

  if (isPlaying && activeSource && audioContext) {
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
  setPlayerProgress(progressFractionFromPointer(event, progressShell));

  const move = (moveEvent) => {
    setPlayerProgress(progressFractionFromPointer(moveEvent, progressShell));
  };

  const stop = () => {
    window.removeEventListener("pointermove", move);
    window.removeEventListener("pointerup", stop);
  };

  window.addEventListener("pointermove", move);
  window.addEventListener("pointerup", stop, { once: true });
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
    link.href = track.downloadUrl;
    link.download = "";
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

function renderSongWaveform(track) {
  const bars = [];
  const seed = track.id.split("").reduce((total, char) => total + char.charCodeAt(0), 0);
  const fallbackWidth = Math.min(window.innerWidth - 92, 620);
  const availableWidth = songWaveform.clientWidth || fallbackWidth;
  const barCount = Math.max(42, Math.min(150, Math.floor(availableWidth / 5)));

  for (let i = 0; i < barCount; i += 1) {
    const pulse = Math.abs(Math.sin((i + seed) * 0.21));
    const accent = i % 19 < 5 ? 1.45 : 1;
    const height = Math.max(7, Math.round((8 + pulse * 34 + ((i * seed) % 13)) * accent));
    bars.push(`<span style="height:${Math.min(height, 58)}px"></span>`);
  }

  songWaveform.innerHTML = bars.join("");
}

function renderSongAd() {
  const advertising = siteSettings.advertising || {};
  const enabled = Boolean(advertising.enabled && advertising.mediaUrl);

  songPage.classList.toggle("no-ad", !enabled);
  songAd.replaceChildren();

  if (!enabled) {
    return;
  }

  const media = document.createElement(advertising.mediaType === "video" ? "video" : "img");
  media.className = "song-ad-media";

  if (advertising.mediaType === "video") {
    media.src = advertising.mediaUrl;
    media.muted = true;
    media.loop = true;
    media.autoplay = true;
    media.playsInline = true;
    media.controls = true;
  } else {
    media.src = advertising.mediaUrl;
    media.alt = "Advertisement";
  }

  songAd.append(media);
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
  selectedTrack = track;
  songPage.hidden = false;
  document.body.classList.add("song-view");
  songPlay.dataset.trackId = track.id;
  songGenre.textContent = track.genre;
  songPageTitle.textContent = track.title;
  songArtist.textContent = track.artist;
  songDuration.textContent = track.duration;
  creditText.textContent = buildCreditText(track);
  renderSongWaveform(track);
  renderSongAd();
  syncPlayer();
  clearActiveNav();
  window.scrollTo({ top: 0, behavior: "smooth" });

  if (updateUrl) {
    history.pushState({ songId: track.id }, "", `#song-${track.id}`);
  }
}

function closeSongPage(updateUrl = true) {
  document.body.classList.remove("song-view");
  songPage.hidden = true;

  if (updateUrl) {
    history.pushState(null, "", "#all-tracks");
  }

  document.querySelector("#all-tracks").scrollIntoView({ block: "start" });
  updateActiveNav();
}

function openSongFromHash() {
  const songId = window.location.hash.replace("#song-", "");
  const track = allTracks.find((item) => item.id === songId) || tracks.find((item) => item.id === songId);

  if (track) {
    openSongPage(track, false);
  }
}

async function initializeCatalog() {
  const [uploadedTracks, loadedSettings] = await Promise.all([
    loadUploadedTracks(),
    loadSiteSettings()
  ]);

  siteSettings = loadedSettings;

  if (uploadedTracks.length > 0) {
    tracks.unshift(...uploadedTracks);
  }

  selectedTrack = tracks[0];
  allTracks = buildDemoTrackPages(tracks);
  renderTracks(tracks.slice(0, 5), latestGrid);
  renderAllTracksPage(1);
  syncPlayer();
  openSongFromHash();
}

initializeCatalog();

document.querySelector("#focusSearch").addEventListener("click", () => {
  openSearch();
});

function setActiveNav(sectionId) {
  const targetId = sectionId === "top" ? "latest" : sectionId;

  sectionNavLinks.forEach((link) => {
    const href = link.getAttribute("href");
    const isActive = href === `#${targetId}`;
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

function updateActiveNav() {
  if (document.body.classList.contains("song-view")) {
    clearActiveNav();
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

playerToggle.addEventListener("click", () => {
  playTrack(selectedTrack);
});

playerDownload.addEventListener("click", () => {
  openSongPage(selectedTrack);
});

progressShell.addEventListener("pointerdown", startPlayerSeek);
progressShell.addEventListener("keydown", (event) => {
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

songDownload.addEventListener("click", () => {
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

document.querySelectorAll('.side-nav a[href^="#"]').forEach((link) => {
  link.addEventListener("click", () => {
    setMobileMenu(false);

    if (document.body.classList.contains("song-view")) {
      document.body.classList.remove("song-view");
      songPage.hidden = true;
    }
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
  if (window.location.hash.startsWith("#song-")) {
    openSongFromHash();
    return;
  }

  if (document.body.classList.contains("song-view")) {
    closeSongPage(false);
  }
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
