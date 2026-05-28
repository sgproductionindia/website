(function () {
  "use strict";

  const searchButton = document.querySelector("#focusSearch");

  if (!searchButton || document.querySelector("#searchOverlay")) {
    return;
  }

  const overlay = document.createElement("section");
  overlay.className = "search-overlay";
  overlay.id = "searchOverlay";
  overlay.setAttribute("aria-labelledby", "searchTitle");
  overlay.setAttribute("aria-hidden", "true");
  overlay.innerHTML = `
    <div class="search-panel" role="dialog" aria-modal="true">
      <div class="search-head">
        <div>
          <h2 id="searchTitle">Search</h2>
        </div>
        <button class="search-close" id="searchClose" type="button" aria-label="Close search">
          <svg aria-hidden="true" viewBox="0 0 24 24">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <label class="search-field" for="siteSearchInput">
        <svg aria-hidden="true" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <input id="siteSearchInput" type="search" autocomplete="off" placeholder="Search songs or genres">
      </label>
      <div class="search-results" id="searchResults" role="list"></div>
    </div>
  `;
  document.body.appendChild(overlay);

  const searchInput = overlay.querySelector("#siteSearchInput");
  const searchResults = overlay.querySelector("#searchResults");
  const searchClose = overlay.querySelector("#searchClose");
  let tracks = [];
  let tracksLoaded = false;

  function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;"
    })[char]);
  }

  function slugClass(value) {
    return String(value || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
  }

  function canUseCleanUrls() {
    return /^https?:$/.test(window.location.protocol) && !/^(127\.0\.0\.1|localhost|\[::1\])$/.test(window.location.hostname);
  }

  function songUrl(track) {
    const slug = slugClass(track.title || track.id);
    return canUseCleanUrls() ? `/song/${slug}` : `index.html?song=${encodeURIComponent(slug)}`;
  }

  function normalizeTrack(track) {
    if (!track || !track.title) return null;
    return {
      id: track.id || slugClass(track.title),
      title: track.title,
      genre: track.genre || "Soundcheck",
      cover: track.coverWebp || track.cover || "assets/cover-1.jpg"
    };
  }

  async function loadTracks() {
    if (tracksLoaded) return;
    tracksLoaded = true;

    try {
      const response = await fetch("data/tracks.json", { cache: "no-store" });
      if (!response.ok) return;

      const data = await response.json();
      const rawTracks = Array.isArray(data) ? data : data.tracks;
      tracks = Array.isArray(rawTracks) ? rawTracks.map(normalizeTrack).filter(Boolean) : [];
    } catch {
      tracks = [];
    }
  }

  function getMatches(query) {
    const terms = query.toLowerCase().split(/\s+/).filter(Boolean);

    if (terms.length === 0) {
      return tracks.slice(0, 5);
    }

    return tracks
      .filter((track) => {
        const searchable = `${track.title} ${track.genre}`.toLowerCase();
        return terms.every((term) => searchable.includes(term));
      })
      .slice(0, 12);
  }

  function renderSearchResults() {
    const query = searchInput.value.trim();
    const matches = getMatches(query);

    if (tracks.length === 0) {
      searchResults.innerHTML = '<p class="search-empty">No tracks available yet.</p>';
      return;
    }

    if (matches.length === 0) {
      searchResults.innerHTML = `<p class="search-empty">No results for "${escapeHTML(query)}".</p>`;
      return;
    }

    searchResults.innerHTML = matches.map((track) => `
      <button class="search-result" type="button" data-song-url="${escapeHTML(songUrl(track))}">
        <span class="search-thumb"><img src="${escapeHTML(track.cover)}" alt="" loading="lazy"></span>
        <span class="search-copy">
          <strong>${escapeHTML(track.title)}</strong>
          <span>${escapeHTML(track.genre)}</span>
        </span>
      </button>
    `).join("");
  }

  async function openSearch() {
    document.body.classList.remove("menu-open");
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("search-open");
    searchInput.value = "";
    await loadTracks();
    renderSearchResults();
    window.setTimeout(() => searchInput.focus(), 40);
  }

  function closeSearch() {
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.classList.remove("search-open");
  }

  searchButton.addEventListener("click", openSearch);
  searchInput.addEventListener("input", renderSearchResults);
  searchClose.addEventListener("click", closeSearch);
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) {
      closeSearch();
    }
  });
  searchResults.addEventListener("click", (event) => {
    const result = event.target.closest(".search-result");
    if (!result) return;
    window.location.href = result.dataset.songUrl;
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) {
      closeSearch();
    }
  });
})();
