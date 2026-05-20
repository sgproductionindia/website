
(() => {
  const sections = document.querySelectorAll('.view-section');
  const navItems = document.querySelectorAll('.nav-item[data-section]');
  const titleEl = document.querySelector('.topbar-title');
  const subEl = document.querySelector('.topbar-sub');
  const topPrimaryAction = document.querySelector('#topPrimaryAction');
  const sidebar = document.querySelector('.sidebar');
  const sidebarScrim = document.querySelector('#sidebarScrim');
  const mobileMenuToggle = document.querySelector('#mobileMenuToggle');
  const settingsToast = document.querySelector('#settingsToast');

  function showToast(message) {
    if (!settingsToast) return;
    settingsToast.textContent = message;
    settingsToast.classList.add('show');
    window.setTimeout(() => settingsToast.classList.remove('show'), 2200);
  }

  function normalizeSection(sectionName) {
    if (!sectionName || sectionName === 'top') return 'dashboard';
    const legacy = {
      'uploaded-songs': 'songs',
      'upload-song': 'upload',
      'global-settings': 'advertising',
      'website-settings': 'settings'
    };
    if (legacy[sectionName]) return legacy[sectionName];
    if (sectionName.startsWith('track-')) return 'songs';
    return sectionName;
  }

  function setMobileMenu(open) {
    sidebar?.classList.toggle('open', open);
    sidebarScrim?.classList.toggle('open', open);
    mobileMenuToggle?.setAttribute('aria-expanded', String(open));
  }

  function focusArtistForm() {
    const form = document.querySelector('#artistForm');
    const input = document.querySelector('#artistNameInput');
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.setTimeout(() => input?.focus(), 180);
  }

  function focusGenreForm() {
    const panel = document.querySelector('#genreFormPanel');
    const input = document.querySelector('#genreNameInput');
    panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.setTimeout(() => input?.focus(), 180);
  }

  function openSection(rawSectionName) {
    const sectionName = normalizeSection(rawSectionName);
    const target = document.querySelector('#' + sectionName + '-section');
    if (!target) return;

    sections.forEach(section => section.classList.remove('active'));
    target.classList.add('active');
    navItems.forEach(item => item.classList.toggle('active', item.dataset.section === sectionName));
    if (titleEl) titleEl.textContent = target.dataset.title || 'Dashboard';
    if (subEl) subEl.textContent = '— ' + (target.dataset.subtitle || 'Admin Studio');
    if (topPrimaryAction) {
      const artistMode = sectionName === 'artists';
      const genreMode = sectionName === 'genres';
      topPrimaryAction.textContent = artistMode ? '+ Add New Artist' : genreMode ? '+ Add Genre' : '+ Upload Song';
      topPrimaryAction.dataset.actionSection = artistMode ? 'artists' : genreMode ? 'genres' : 'upload';
    }
    if (location.hash.replace('#', '') !== sectionName) history.replaceState(null, '', '#' + sectionName);
    setMobileMenu(false);
  }

  navItems.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      openSection(item.dataset.section);
    });
  });

  document.querySelectorAll('[data-action-section]').forEach(button => {
    button.addEventListener('click', () => {
      const section = button.dataset.actionSection;
      openSection(section);
      if (section === 'artists') focusArtistForm();
      if (section === 'genres') focusGenreForm();
    });
  });

  mobileMenuToggle?.addEventListener('click', () => setMobileMenu(!sidebar?.classList.contains('open')));
  sidebarScrim?.addEventListener('click', () => setMobileMenu(false));
  window.addEventListener('keydown', event => { if (event.key === 'Escape') setMobileMenu(false); });
  window.addEventListener('hashchange', () => openSection(location.hash.replace('#', '')));

  function formatFileSize(bytes) {
    if (!bytes) return '0 MB';
    const mb = bytes / 1024 / 1024;
    return mb.toFixed(mb >= 10 ? 0 : 1) + ' MB';
  }

  function formatDurationFromSeconds(seconds) {
    if (!Number.isFinite(seconds) || seconds <= 0) return '0:0';
    const minutes = Math.floor(seconds / 60);
    const remaining = Math.floor(seconds % 60);
    return minutes + ':' + remaining;
  }

  const uploadSongForm = document.querySelector('#uploadSongForm');
  const uploadPreviewFile = document.querySelector('#uploadPreviewFile');
  const previewFileMeta = document.querySelector('#previewFileMeta');
  const previewFileName = document.querySelector('#previewFileName');
  const previewFileSize = document.querySelector('#previewFileSize');
  const uploadDurationInput = document.querySelector('#uploadDurationInput');
  const uploadCoverInput = document.querySelector('#uploadCoverInput');
  const uploadCoverPreview = document.querySelector('#uploadCoverPreview');
  const uploadProgress = document.querySelector('#uploadProgress');
  const uploadProgressFill = document.querySelector('#uploadProgressFill');
  const uploadProgressValue = document.querySelector('#uploadProgressValue');
  const saveDraftButton = document.querySelector('#saveDraftButton');

  uploadPreviewFile?.addEventListener('change', () => {
    const file = uploadPreviewFile.files && uploadPreviewFile.files[0];
    if (!file) return;
    previewFileMeta?.classList.add('is-visible');
    if (previewFileName) previewFileName.textContent = file.name;
    if (previewFileSize) previewFileSize.textContent = formatFileSize(file.size);
    const audio = document.createElement('audio');
    const objectUrl = URL.createObjectURL(file);
    audio.preload = 'metadata';
    audio.src = objectUrl;
    audio.addEventListener('loadedmetadata', () => {
      if (uploadDurationInput) uploadDurationInput.value = formatDurationFromSeconds(audio.duration);
      URL.revokeObjectURL(objectUrl);
    });
    audio.addEventListener('error', () => URL.revokeObjectURL(objectUrl));
  });

  uploadCoverInput?.addEventListener('change', () => {
    const file = uploadCoverInput.files && uploadCoverInput.files[0];
    if (!file || !uploadCoverPreview) return;
    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = 'Selected cover preview';
    uploadCoverPreview.replaceChildren(image);
  });

  uploadSongForm?.addEventListener('submit', () => {
    uploadProgress?.classList.add('is-visible');
    if (uploadProgressFill) uploadProgressFill.style.width = '75%';
    if (uploadProgressValue) uploadProgressValue.textContent = '75%';
  });

  saveDraftButton?.addEventListener('click', () => showToast('Draft saving will be connected when draft status is enabled'));

  const artistForm = document.querySelector('#artistForm');
  const artistNameInput = document.querySelector('#artistNameInput');
  const artistPreviewImage = document.querySelector('#artistPreviewImage');
  const artistImageFileName = document.querySelector('#artistImageFileName');
  const artistGrid = document.querySelector('#artistGrid');
  const artistEmptyState = document.querySelector('#artistEmptyState');

  document.querySelectorAll('[data-artist-image-input]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      const key = input.dataset.previewTarget || 'new';
      const preview = document.querySelector('[data-artist-preview="' + (window.CSS?.escape ? CSS.escape(key) : key) + '"]');
      const name = document.querySelector('[data-file-name="' + (window.CSS?.escape ? CSS.escape(input.dataset.fileNameTarget || 'new') : 'new') + '"]');
      const url = URL.createObjectURL(file);
      if (preview) preview.src = url;
      if (artistPreviewImage && key === 'new') artistPreviewImage.src = url;
      if (name) name.textContent = file.name;
      if (artistImageFileName && key === 'new') artistImageFileName.textContent = file.name;
    });
  });

  document.querySelectorAll('[data-clear-artist]').forEach(button => {
    button.addEventListener('click', () => {
      window.setTimeout(() => {
        if (artistPreviewImage) artistPreviewImage.src = 'assets/artist-photo-1.svg';
        if (artistImageFileName) artistImageFileName.innerHTML = 'No image selected.<br>Square image recommended.';
        artistNameInput?.focus();
      }, 0);
    });
  });
  document.querySelectorAll('[data-artist-focus]').forEach(button => button.addEventListener('click', focusArtistForm));
  if (artistGrid && artistEmptyState) {
    const hasArtists = artistGrid.querySelectorAll('.artist-card').length > 0;
    artistEmptyState.classList.toggle('is-visible', !hasArtists);
    artistGrid.style.display = hasArtists ? 'grid' : 'none';
  }

  const genreForm = document.querySelector('#genreForm');
  const genreNameInput = document.querySelector('#genreNameInput');
  const genreSlugInput = document.querySelector('#genreSlugInput');
  const genreDescriptionInput = document.querySelector('#genreDescriptionInput');
  const genreDescriptionCount = document.querySelector('#genreDescriptionCount');
  const genreSearchInput = document.querySelector('#genreSearchInput');
  const genreGrid = document.querySelector('#genreGrid');
  let genreSlugEdited = false;

  function slugify(value) {
    return String(value).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
  function updateGenreDescriptionCount() {
    if (genreDescriptionCount && genreDescriptionInput) genreDescriptionCount.textContent = String(genreDescriptionInput.value.length);
  }
  genreNameInput?.addEventListener('input', () => {
    if (!genreSlugEdited && genreSlugInput) genreSlugInput.value = slugify(genreNameInput.value);
  });
  genreSlugInput?.addEventListener('input', () => {
    genreSlugEdited = true;
    genreSlugInput.value = slugify(genreSlugInput.value);
  });
  genreDescriptionInput?.addEventListener('input', updateGenreDescriptionCount);
  genreForm?.addEventListener('reset', () => {
    genreSlugEdited = false;
    window.setTimeout(updateGenreDescriptionCount, 0);
  });
  genreSearchInput?.addEventListener('input', () => {
    const query = genreSearchInput.value.trim().toLowerCase();
    genreGrid?.querySelectorAll('.genre-card').forEach(card => {
      card.style.display = !query || (card.dataset.name || '').toLowerCase().includes(query) ? '' : 'none';
    });
  });
  updateGenreDescriptionCount();

  const adMediaInput = document.querySelector('#adMediaInput');
  const adUploadButton = document.querySelector('#adUploadButton');
  const adSelectedPreview = document.querySelector('#adSelectedPreview');
  const adMediaMeta = document.querySelector('#adMediaMeta');
  const adFileName = document.querySelector('#adFileName');
  const adFileSize = document.querySelector('#adFileSize');
  const adDimensions = document.querySelector('#adDimensions');
  const adRatioWarning = document.querySelector('#adRatioWarning');
  const sitewideAdToggle = document.querySelector('#sitewideAdToggle');
  const adStatusPill = document.querySelector('#adStatusPill');

  adUploadButton?.addEventListener('click', () => adMediaInput?.click());

  function checkAdRatio(width, height) {
    if (!width || !height) return;
    if (adDimensions) adDimensions.textContent = width + ' x ' + height;
    const ratio = width / height;
    const target = 9 / 16;
    adRatioWarning?.classList.toggle('is-visible', Math.abs(ratio - target) > 0.04);
  }

  adMediaInput?.addEventListener('change', () => {
    const file = adMediaInput.files && adMediaInput.files[0];
    if (!file || !adSelectedPreview) return;
    const objectUrl = URL.createObjectURL(file);
    adMediaMeta?.classList.add('is-visible');
    if (adFileName) adFileName.textContent = file.name;
    if (adFileSize) adFileSize.textContent = formatFileSize(file.size);
    if (adDimensions) adDimensions.textContent = 'Detecting dimensions...';
    if (file.type.startsWith('video/')) {
      const video = document.createElement('video');
      video.src = objectUrl;
      video.muted = true;
      video.loop = true;
      video.autoplay = true;
      video.playsInline = true;
      video.addEventListener('loadedmetadata', () => checkAdRatio(video.videoWidth, video.videoHeight));
      adSelectedPreview.replaceChildren(video);
      return;
    }
    const image = document.createElement('img');
    image.src = objectUrl;
    image.alt = 'Selected advertisement preview';
    image.addEventListener('load', () => checkAdRatio(image.naturalWidth, image.naturalHeight));
    adSelectedPreview.replaceChildren(image);
  });

  sitewideAdToggle?.addEventListener('change', () => {
    if (!adStatusPill) return;
    adStatusPill.textContent = sitewideAdToggle.checked ? 'Active' : 'Inactive';
    adStatusPill.className = 'status-pill ' + (sitewideAdToggle.checked ? 'published' : 'unlisted');
  });

  const metaDescriptionInput = document.querySelector('#metaDescriptionInput');
  const metaDescriptionCount = document.querySelector('#metaDescriptionCount');
  function updateMetaCounter() {
    if (metaDescriptionCount && metaDescriptionInput) metaDescriptionCount.textContent = String(metaDescriptionInput.value.length);
  }
  metaDescriptionInput?.addEventListener('input', updateMetaCounter);
  updateMetaCounter();

  const songsList = document.querySelector('#songsList');
  const songsEmptyState = document.querySelector('#songsEmptyState');
  const songsPagination = document.querySelector('#songsPagination');
  const songSearchInput = document.querySelector('#songSearchInput');
  const songGenreFilter = document.querySelector('#songGenreFilter');
  const songSortSelect = document.querySelector('#songSortSelect');
  let currentSongPage = 1;

  function getSongRows() { return Array.from(songsList?.querySelectorAll('.song-row') || []); }
  function getFilteredSongRows() {
    const query = (songSearchInput?.value || '').trim().toLowerCase();
    const genre = songGenreFilter?.value || 'all';
    const sort = songSortSelect?.value || 'newest';
    const rows = getSongRows().filter(row => {
      const haystack = ((row.dataset.title || '') + ' ' + (row.dataset.artist || '') + ' ' + (row.dataset.genre || '')).toLowerCase();
      return (!query || haystack.includes(query)) && (genre === 'all' || row.dataset.genre === genre);
    });
    rows.sort((a, b) => {
      if (sort === 'downloads') return Number(b.dataset.downloads || 0) - Number(a.dataset.downloads || 0);
      if (sort === 'az') return (a.dataset.title || '').localeCompare(b.dataset.title || '');
      return new Date(b.dataset.date || 0) - new Date(a.dataset.date || 0);
    });
    return rows;
  }
  function renderSongPagination(totalPages) {
    if (!songsPagination) return;
    songsPagination.innerHTML = '';
    if (totalPages <= 1) return;
    const makeButton = (label, active, disabled, onClick) => {
      const button = document.createElement('button');
      button.className = 'page-btn' + (active ? ' active' : '');
      button.textContent = label;
      button.disabled = disabled;
      button.addEventListener('click', onClick);
      songsPagination.appendChild(button);
    };
    makeButton('<', false, currentSongPage === 1, () => { currentSongPage = Math.max(1, currentSongPage - 1); renderSongs(); });
    for (let index = 1; index <= totalPages; index += 1) makeButton(String(index), index === currentSongPage, false, () => { currentSongPage = index; renderSongs(); });
    makeButton('>', false, currentSongPage === totalPages, () => { currentSongPage = Math.min(totalPages, currentSongPage + 1); renderSongs(); });
  }
  function renderSongs() {
    if (!songsList || !songsEmptyState) return;
    const filtered = getFilteredSongRows();
    const totalPages = Math.max(1, Math.ceil(filtered.length / 10));
    currentSongPage = Math.min(currentSongPage, totalPages);
    const visible = filtered.slice((currentSongPage - 1) * 10, currentSongPage * 10);
    getSongRows().forEach(row => { row.style.display = visible.includes(row) ? 'grid' : 'none'; });
    songsEmptyState.classList.toggle('is-visible', filtered.length === 0);
    songsList.style.display = filtered.length ? 'flex' : 'none';
    renderSongPagination(totalPages);
  }
  [songSearchInput, songGenreFilter, songSortSelect].forEach(control => {
    control?.addEventListener('input', () => { currentSongPage = 1; renderSongs(); });
    control?.addEventListener('change', () => { currentSongPage = 1; renderSongs(); });
  });
  songsList?.addEventListener('click', event => {
    const row = event.target.closest('.song-row');
    if (!row) return;
    if (event.target.closest('[data-edit-song]')) row.classList.toggle('editing');
    if (event.target.closest('[data-delete-song]')) {
      getSongRows().forEach(item => item.classList.remove('confirming'));
      row.classList.add('confirming');
    }
    if (event.target.closest('[data-cancel-delete]')) row.classList.remove('confirming');
  });
  renderSongs();

  const chartData = {
    Downloads: <?= json_encode($downloadChartData, JSON_UNESCAPED_SLASHES) ?>,
    'Page Views': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: 'N/A', label: 'total page views', peak: 'Peak: N/A' },
    'Ad Clicks': { points: '80,238 194,238 308,238 422,238 536,238 650,238 764,238 875,238', values: ['N/A','N/A','N/A','N/A','N/A','N/A','N/A','N/A'], total: <?= json_encode($hasAdData ? number_format($adClicks) : 'N/A') ?>, label: 'total ad clicks', peak: 'Peak: N/A' }
  };
  function renderAnalyticsChart(type) {
    const data = chartData[type] || chartData.Downloads;
    const line = document.querySelector('#analyticsLine');
    const pointLayer = document.querySelector('#analyticsPointLayer');
    const total = document.querySelector('#analyticsChartTotal');
    const peak = document.querySelector('#analyticsChartPeak');
    const summaryLabel = total?.parentElement;
    line?.setAttribute('points', data.points);
    if (pointLayer) {
      pointLayer.innerHTML = data.points.split(' ').map((pair, index) => {
        const [x, y] = pair.split(',').map(Number);
        return '<button class="chart-point" type="button" style="left:' + ((x / 900) * 100) + '%;top:' + ((y / 260) * 100) + '%;" aria-label="' + data.values[index] + ' ' + data.label + '"><span class="chart-point-value">' + data.values[index] + '</span></button>';
      }).join('');
    }
    if (total) total.textContent = data.total;
    if (summaryLabel) summaryLabel.lastChild.textContent = ' ' + data.label;
    if (peak) peak.textContent = data.peak;
  }
  document.querySelectorAll('#analyticsChartToggle button').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('#analyticsChartToggle button').forEach(item => item.classList.remove('active'));
      button.classList.add('active');
      renderAnalyticsChart(button.dataset.chart);
    });
  });
  document.querySelectorAll('[data-analytics-range]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('[data-analytics-range]').forEach(item => item.classList.remove('active'));
      chip.classList.add('active');
    });
  });

  const nsdpTrigger = document.querySelector('#nsdpTrigger');
  const nsdpPopup = document.querySelector('#nsdpPopup');
  const nsdpTriggerLabel = document.querySelector('#nsdpTriggerLabel');
  const nsdpSummary = document.querySelector('#nsdpSummary');
  const nsdpInputFrom = document.querySelector('#nsdpInputFrom');
  const nsdpInputTo = document.querySelector('#nsdpInputTo');
  const nsdpStart = document.querySelector('#analyticsStartDate');
  const nsdpEnd = document.querySelector('#analyticsEndDate');
  const miniMonthLabel = document.querySelector('#miniMonthLabel');
  const miniCalGrid = document.querySelector('#miniCalGrid');

  const parseIsoDate = value => {
    const parts = String(value || '').split('-').map(Number);
    return parts.length === 3 && parts.every(Number.isFinite) ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
  };
  const parseDisplayDate = value => {
    const match = String(value || '').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) return null;
    const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
    return Number.isNaN(date.getTime()) ? null : date;
  };
  const isoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const displayDate = date => `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
  const shortDate = date => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const monthLabel = date => date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const dayKey = date => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  const nsdpState = {
    from: parseIsoDate(nsdpStart?.value),
    to: parseIsoDate(nsdpEnd?.value),
    view: new Date(parseIsoDate(nsdpStart?.value).getFullYear(), parseIsoDate(nsdpStart?.value).getMonth(), 1),
    previousFrom: parseIsoDate(nsdpStart?.value),
    previousTo: parseIsoDate(nsdpEnd?.value)
  };

  function updateNsdpLabels() {
    if (nsdpTriggerLabel) nsdpTriggerLabel.textContent = `${shortDate(nsdpState.from)} – ${shortDate(nsdpState.to)}`;
    if (nsdpSummary) {
      const days = Math.max(1, Math.round((dayKey(nsdpState.to) - dayKey(nsdpState.from)) / 86400000) + 1);
      nsdpSummary.textContent = `${days} day${days === 1 ? '' : 's'} selected`;
    }
    if (nsdpInputFrom) nsdpInputFrom.value = displayDate(nsdpState.from);
    if (nsdpInputTo) nsdpInputTo.value = displayDate(nsdpState.to);
  }

  function renderMiniCalendar() {
    if (!miniCalGrid || !miniMonthLabel) return;
    miniMonthLabel.textContent = monthLabel(nsdpState.view);
    const year = nsdpState.view.getFullYear();
    const month = nsdpState.view.getMonth();
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const todayKey = dayKey(new Date());
    let html = '';
    for (let index = 0; index < offset; index += 1) html += '<button class="hig-mini-day mini-empty" tabindex="-1"></button>';
    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = new Date(year, month, day);
      const key = dayKey(date);
      const start = dayKey(nsdpState.from);
      const end = dayKey(nsdpState.to);
      const classes = ['hig-mini-day'];
      if (key === todayKey) classes.push('mini-today');
      if (key >= start && key <= end) classes.push('mini-in-range');
      if (key === start) classes.push('mini-selected', 'mini-range-start');
      if (key === end) classes.push('mini-selected', 'mini-range-end');
      html += `<button class="${classes.join(' ')}" type="button" data-date="${isoDate(date)}">${day}</button>`;
    }
    miniCalGrid.innerHTML = html;
    miniCalGrid.querySelectorAll('[data-date]').forEach(button => {
      button.addEventListener('click', () => {
        const selected = parseIsoDate(button.dataset.date);
        if (dayKey(selected) < dayKey(nsdpState.from) || dayKey(nsdpState.from) === dayKey(nsdpState.to)) {
          nsdpState.from = selected;
          nsdpState.to = selected;
        } else {
          nsdpState.to = selected;
        }
        updateNsdpLabels();
        renderMiniCalendar();
      });
    });
  }


  window.nsdpParseInput = function (mode, value, apply = false) {
    const parsed = parseDisplayDate(value);
    const input = mode === 'from' ? nsdpInputFrom : nsdpInputTo;
    input?.classList.toggle('error', !parsed);
    if (!parsed) return;
    if (mode === 'from') nsdpState.from = parsed;
    if (mode === 'to') nsdpState.to = parsed;
    if (dayKey(nsdpState.from) > dayKey(nsdpState.to)) {
      const temp = nsdpState.from;
      nsdpState.from = nsdpState.to;
      nsdpState.to = temp;
    }
    nsdpState.view = new Date(nsdpState.from.getFullYear(), nsdpState.from.getMonth(), 1);
    updateNsdpLabels();
    renderMiniCalendar();
    if (apply) window.nsdpApply();
  };
  window.miniCalNav = function (direction) {
    nsdpState.view = new Date(nsdpState.view.getFullYear(), nsdpState.view.getMonth() + direction, 1);
    renderMiniCalendar();
  };
  window.nsdpCancel = function () {
    nsdpState.from = new Date(nsdpState.previousFrom);
    nsdpState.to = new Date(nsdpState.previousTo);
    updateNsdpLabels();
    renderMiniCalendar();
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
  };
  window.nsdpApply = function () {
    if (dayKey(nsdpState.from) > dayKey(nsdpState.to)) return showToast('Start date must be before end date');
    nsdpState.previousFrom = new Date(nsdpState.from);
    nsdpState.previousTo = new Date(nsdpState.to);
    if (nsdpStart) nsdpStart.value = isoDate(nsdpState.from);
    if (nsdpEnd) nsdpEnd.value = isoDate(nsdpState.to);
    updateNsdpLabels();
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
    showToast('Analytics range applied: ' + nsdpStart?.value + ' to ' + nsdpEnd?.value);
  };
  nsdpTrigger?.addEventListener('click', event => {
    event.stopPropagation();
    if (!nsdpPopup) return;
    const open = nsdpPopup.style.display !== 'none';
    nsdpPopup.style.display = open ? 'none' : 'block';
    nsdpTrigger.classList.toggle('open', !open);
    if (!open) renderMiniCalendar();
  });
  nsdpPopup?.addEventListener('click', event => event.stopPropagation());
  document.addEventListener('click', () => {
    nsdpPopup && (nsdpPopup.style.display = 'none');
    nsdpTrigger?.classList.remove('open');
  });
  // Ensure Export CSV button sits after the analytics filter bar
  (function(){
    const moveExportBtn = () => {
      const exportBtn = document.getElementById('analyticsExportCsv');
      const analyticsSection = document.getElementById('analytics-section');
      const filterBar = analyticsSection?.querySelector('.analytics-filter-bar');
      if (exportBtn && filterBar && filterBar.parentNode) {
        filterBar.parentNode.insertBefore(exportBtn, filterBar.nextSibling);
      }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', moveExportBtn); else moveExportBtn();
  })();
  updateNsdpLabels();
  renderMiniCalendar();

  renderAnalyticsChart('Downloads');

  const analyticsTable = document.querySelector('#analyticsTable');
  const analyticsPagination = document.querySelector('#analyticsPagination');
  let analyticsPage = 1;
  let analyticsSortKey = 'index';
  let analyticsSortDir = 1;
  function getAnalyticsRows() { return Array.from(analyticsTable?.querySelectorAll('tbody tr') || []); }
  function renderAnalyticsTable() {
    if (!analyticsTable || !analyticsPagination) return;
    const tbody = analyticsTable.querySelector('tbody');
    const rows = getAnalyticsRows();
    rows.sort((a, b) => {
      const aValue = a.dataset[analyticsSortKey] || '';
      const bValue = b.dataset[analyticsSortKey] || '';
      const numeric = !Number.isNaN(Number(aValue)) && !Number.isNaN(Number(bValue));
      return numeric ? (Number(aValue) - Number(bValue)) * analyticsSortDir : aValue.localeCompare(bValue) * analyticsSortDir;
    });
    rows.forEach(row => tbody.appendChild(row));
    const totalPages = Math.max(1, Math.ceil(rows.length / 10));
    analyticsPage = Math.min(analyticsPage, totalPages);
    rows.forEach((row, index) => { row.style.display = index >= (analyticsPage - 1) * 10 && index < analyticsPage * 10 ? 'table-row' : 'none'; });
    analyticsPagination.innerHTML = '';
    if (totalPages <= 1) return;
    for (let index = 1; index <= totalPages; index += 1) {
      const button = document.createElement('button');
      button.className = 'page-btn' + (index === analyticsPage ? ' active' : '');
      button.textContent = String(index);
      button.addEventListener('click', () => { analyticsPage = index; renderAnalyticsTable(); });
      analyticsPagination.appendChild(button);
    }
  }
  analyticsTable?.querySelectorAll('th[data-sort]').forEach(header => {
    header.addEventListener('click', () => {
      const key = header.dataset.sort;
      analyticsSortDir = analyticsSortKey === key ? analyticsSortDir * -1 : 1;
      analyticsSortKey = key;
      analyticsPage = 1;
      renderAnalyticsTable();
    });
  });
  document.querySelectorAll('#adPerformanceExportCsv, #advertiserDownloadPdf, #advertiserExportCsv, #analyticsExportCsv').forEach(button => {
    button.addEventListener('click', () => {
      alert('Export preview. This will connect to real data when reporting is available.');
    });
  });
  renderAnalyticsTable();

  const notificationFeed = document.querySelector('#notificationFeed');

  function renderNotificationIcons() {
    const icons = {
      download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
      ad: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-miterlimit="10"><polygon points="6,19 3,19 3,13 6,13 29,6 29,26"/><path d="M15,21.8l-0.3,1c-0.5,1.7-2.3,2.6-3.9,2.1l0,0c-1.7-0.5-2.6-2.3-2.1-3.9L9,20"/></svg>',
      system: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/></svg>',
      error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'
    };
    notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
      const icon = item.querySelector('.notification-icon');
      const type = item.dataset.type;
      if (icon && icons[type]) icon.innerHTML = icons[type];
    });
  }

  renderNotificationIcons();

  document.querySelectorAll('[data-notification-filter]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-notification-filter]').forEach(item => item.classList.remove('active'));
      tab.classList.add('active');
      const filter = tab.dataset.notificationFilter;
      notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
        item.style.display = filter === 'all' || item.dataset.type === filter ? 'grid' : 'none';
      });
    });
  });
  document.querySelector('#markAllReadButton')?.addEventListener('click', () => {
    notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
      item.dataset.unread = 'false';
      item.querySelector('.unread-dot')?.remove();
    });
  });
  notificationFeed?.addEventListener('click', event => {
    const dismiss = event.target.closest('.dismiss-notification');
    if (dismiss) dismiss.closest('.notification-item')?.remove();
  });
  const toggleNotificationSettings = document.querySelector('#toggleNotificationSettings');
  const notificationSettingsPanel = document.querySelector('#notificationSettingsPanel');
  toggleNotificationSettings?.addEventListener('click', () => {
    notificationSettingsPanel?.classList.toggle('open');
    toggleNotificationSettings.textContent = notificationSettingsPanel?.classList.contains('open') ? 'Hide Settings' : 'Show Settings';
  });

  openSection(location.hash.replace('#', '') || 'dashboard');
})();
