
  // Tab switching
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      tab.closest('.tabs').querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });

  const sections = document.querySelectorAll('.view-section');
  const navItems = document.querySelectorAll('.nav-item[data-section]');
  const titleEl = document.querySelector('.topbar-title');
  const subEl = document.querySelector('.topbar-sub');
  const topPrimaryAction = null; // removed from topbar
  const sidebar = document.querySelector('.sidebar');
  const sidebarScrim = document.querySelector('#sidebarScrim');
  const mobileMenuToggle = document.querySelector('#mobileMenuToggle');

  function setMobileMenu(open) {
    sidebar?.classList.toggle('open', open);
    sidebarScrim?.classList.toggle('open', open);
    mobileMenuToggle?.setAttribute('aria-expanded', String(open));
  }

  function openSection(sectionName) {
    const target = document.querySelector(`#${sectionName}-section`);
    if (!target) return;

    sections.forEach(section => section.classList.remove('active'));
    target.classList.add('active');

    navItems.forEach(item => {
      item.classList.toggle('active', item.dataset.section === sectionName);
    });

    titleEl.textContent = target.dataset.title || 'Dashboard';
    subEl.textContent = `— ${target.dataset.subtitle || 'Admin Studio'}`;
    history.replaceState(null, '', `#${sectionName}`);
    setMobileMenu(false);
  }

  navItems.forEach(item => {
    item.addEventListener('click', e => {
      e.preventDefault();
      openSection(item.dataset.section);
    });
  });

  mobileMenuToggle?.addEventListener('click', () => {
    setMobileMenu(!sidebar?.classList.contains('open'));
  });

  sidebarScrim?.addEventListener('click', () => setMobileMenu(false));

  window.addEventListener('keydown', event => {
    if (event.key === 'Escape') setMobileMenu(false);
  });

  document.querySelectorAll('[data-action-section]').forEach(button => {
    button.addEventListener('click', () => {
      openSection(button.dataset.actionSection);
      if (button.dataset.actionSection === 'artists') focusArtistForm();
      if (button.dataset.actionSection === 'genres') focusGenreForm();
    });
  });

  window.addEventListener('hashchange', () => {
    const nextSection = location.hash.replace('#', '') || 'dashboard';
    if (document.querySelector(`#${nextSection}-section`)) openSection(nextSection);
  });

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
  const websiteSettingsForm = document.querySelector('#websiteSettingsForm');
  const metaDescriptionInput = document.querySelector('#metaDescriptionInput');
  const metaDescriptionCount = document.querySelector('#metaDescriptionCount');
  const ogImageInput = document.querySelector('#ogImageInput');
  const ogImagePreview = document.querySelector('#ogImagePreview');
  const faviconInput = document.querySelector('#faviconInput');
  const faviconPreview = document.querySelector('#faviconPreview');
  const settingsToast = document.querySelector('#settingsToast');
  const adMediaInput = document.querySelector('#adMediaInput');
  const adSelectedPreview = document.querySelector('#adSelectedPreview');
  const adMediaMeta = document.querySelector('#adMediaMeta');
  const adFileName = document.querySelector('#adFileName');
  const adFileSize = document.querySelector('#adFileSize');
  const adDimensions = document.querySelector('#adDimensions');
  const adRatioWarning = document.querySelector('#adRatioWarning');
  const sitewideAdToggle = document.querySelector('#sitewideAdToggle');
  const adStatusPill = document.querySelector('#adStatusPill');
  const adUpdateForm = document.querySelector('#adUpdateForm');

  function showToast(message) {
    if (!settingsToast) return;
    const defaultText = settingsToast.textContent || 'Settings saved ✓';
    settingsToast.textContent = message;
    settingsToast.classList.add('show');
    setTimeout(() => {
      settingsToast.classList.remove('show');
      settingsToast.textContent = defaultText;
    }, 2200);
  }

  function formatFileSize(bytes) {
    if (!bytes) return '0 MB';
    const mb = bytes / 1024 / 1024;
    return `${mb.toFixed(mb >= 10 ? 0 : 1)} MB`;
  }

  function formatDurationFromSeconds(seconds) {
    if (!Number.isFinite(seconds) || seconds <= 0) return '0:0';
    const minutes = Math.floor(seconds / 60);
    const remaining = Math.floor(seconds % 60);
    return `${minutes}:${remaining}`;
  }

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
    audio.addEventListener('error', () => {
      if (uploadDurationInput && !uploadDurationInput.value) uploadDurationInput.value = '0:0';
      URL.revokeObjectURL(objectUrl);
    });
  });

  uploadCoverInput?.addEventListener('change', () => {
    const file = uploadCoverInput.files && uploadCoverInput.files[0];
    if (!file || !uploadCoverPreview) return;
    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = 'Selected cover preview';
    uploadCoverPreview.replaceChildren(image);
  });

  function runUploadProgress(message) {
    let progress = 0;
    uploadProgress?.classList.add('is-visible');
    if (uploadProgressFill) uploadProgressFill.style.width = '0%';
    if (uploadProgressValue) uploadProgressValue.textContent = '0%';
    const timer = setInterval(() => {
      progress = Math.min(100, progress + 12);
      if (uploadProgressFill) uploadProgressFill.style.width = `${progress}%`;
      if (uploadProgressValue) uploadProgressValue.textContent = `${progress}%`;
      if (progress >= 100) {
        clearInterval(timer);
        setTimeout(() => alert(message), 180);
      }
    }, 140);
  }

  uploadSongForm?.addEventListener('submit', event => {
    event.preventDefault();
    runUploadProgress('Upload preview complete. This UI will connect to admin.php for the live website.');
  });

  saveDraftButton?.addEventListener('click', () => {
    runUploadProgress('Draft saved in preview.');
  });

  function updateMetaCounter() {
    if (metaDescriptionCount && metaDescriptionInput) {
      metaDescriptionCount.textContent = String(metaDescriptionInput.value.length);
    }
  }

  function previewImageUpload(input, preview, label) {
    const file = input.files && input.files[0];
    if (!file || !preview) return;
    const image = document.createElement('img');
    image.src = URL.createObjectURL(file);
    image.alt = label;
    preview.replaceChildren(image);
  }

  metaDescriptionInput?.addEventListener('input', updateMetaCounter);
  updateMetaCounter();

  ogImageInput?.addEventListener('change', () => previewImageUpload(ogImageInput, ogImagePreview, 'OG image preview'));
  faviconInput?.addEventListener('change', () => previewImageUpload(faviconInput, faviconPreview, 'Favicon preview'));

  websiteSettingsForm?.addEventListener('submit', event => {
    event.preventDefault();
    settingsToast?.classList.add('show');
    setTimeout(() => settingsToast?.classList.remove('show'), 2200);
  });

  function checkAdRatio(width, height) {
    if (!width || !height || !adDimensions || !adRatioWarning) return;
    adDimensions.textContent = `${width} x ${height}`;
    const ratio = width / height;
    const target = 9 / 16;
    const isClose = Math.abs(ratio - target) < 0.04;
    adRatioWarning.classList.toggle('is-visible', !isClose);
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
    adStatusPill.className = `status-pill ${sitewideAdToggle.checked ? 'published' : 'draft'}`;
  });

  adUpdateForm?.addEventListener('submit', event => {
    event.preventDefault();
    if (settingsToast) settingsToast.textContent = 'Advertising saved ✓';
    settingsToast?.classList.add('show');
    setTimeout(() => {
      settingsToast?.classList.remove('show');
      if (settingsToast) settingsToast.textContent = 'Settings saved ✓';
    }, 2200);
  });

  const artistForm = document.querySelector('#artistForm');
  const artistNameInput = document.querySelector('#artistNameInput');
  /* ── ARTIST MANAGEMENT JS ── */
  (function() {
    const IC_EDIT  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>`;
    const IC_TRASH = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>`;

    const artists = [
      { id:1, name:'SG Production',  bio:'', img:null },
      { id:2, name:'SG Soundcheck',  bio:'', img:null },
      { id:3, name:'Marathi Pulse',  bio:'', img:null },
      { id:4, name:'Hindi Wave',     bio:'', img:null },
    ];
    let nextArtistId = 5;
    let editingArtistId = null;

    function getInitials(name) {
      return name.trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0,2);
    }

    function avatarHTML(a) {
      if (a.img) return `<img src="${a.img}" alt="${a.name}" style="width:100%;height:100%;object-fit:cover;display:block;">`;
      const ini = getInitials(a.name);
      return `<svg viewBox="0 0 72 72" width="72" height="72" xmlns="http://www.w3.org/2000/svg">
        <rect width="72" height="72" fill="var(--bg-tertiary)"/>
        <text x="36" y="46" text-anchor="middle"
          font-family="-apple-system,BlinkMacSystemFont,'SF Pro Text',sans-serif"
          font-size="22" font-weight="600" fill="rgba(235,235,245,0.5)">${ini}</text>
      </svg>`;
    }

    function renderArtists() {
      const grid  = document.getElementById('artistGrid');
      const empty = document.getElementById('artistEmptyState');
      const count = document.getElementById('artistCount');
      if (!grid) return;
      if (count) count.textContent = artists.length;
      if (!artists.length) {
        if (empty) empty.style.display = 'flex';
        grid.innerHTML = '';
        return;
      }
      if (empty) empty.style.display = 'none';
      grid.innerHTML = artists.map((a, i) => `
        <article class="artist-card" style="animation-delay:${i*35}ms;cursor:pointer;" onclick="window._amEdit(${a.id})">
          <div class="artist-avatar">${avatarHTML(a)}</div>
          <div class="artist-name">${a.name}</div>
          <div class="artist-card-actions">
            <button class="btn btn-primary" type="button" style="min-height:32px;padding:0 12px;font-size:12px;border-radius:var(--radius-sm);"
              onclick="event.stopPropagation(); window._amEdit(${a.id})">
              ${IC_EDIT} Edit
            </button>
            <button class="btn" type="button" style="min-height:32px;padding:0 12px;font-size:12px;border-radius:var(--radius-sm);background:var(--sys-red-bg);color:var(--sys-red);border:1px solid rgba(255,69,58,0.3);"
              onclick="event.stopPropagation(); window._amDelete(${a.id})">
              ${IC_TRASH} Delete
            </button>
          </div>
        </article>`).join('');
    }

    function resetAvatar() {
      const wrap = document.getElementById('amAvatarWrap');
      if (wrap) wrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="44" height="44" style="opacity:0.25;"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>`;
    }

    function clearArtistForm() {
      const nameEl = document.getElementById('artistNameInput');
      const bioEl  = document.getElementById('artistBioInput');
      const hint   = document.getElementById('artistImageFileName');
      const title  = document.getElementById('artistFormTitle');
      const fileEl = document.getElementById('artistImageInput');
      if (nameEl)  nameEl.value  = '';
      if (bioEl)   bioEl.value   = '';
      if (hint)    hint.textContent = 'No image selected. Square image recommended.';
      if (title)   title.textContent = 'Add Artist';
      if (fileEl)  fileEl.value  = '';
      resetAvatar();
      editingArtistId = null;
    }

    window._amEdit = function(id) {
      const a = artists.find(x => x.id === id);
      if (!a) return;
      const nameEl = document.getElementById('artistNameInput');
      const bioEl  = document.getElementById('artistBioInput');
      const title  = document.getElementById('artistFormTitle');
      const hint   = document.getElementById('artistImageFileName');
      const wrap   = document.getElementById('amAvatarWrap');
      if (nameEl) nameEl.value = a.name;
      if (bioEl)  bioEl.value  = a.bio || '';
      if (title)  title.textContent = 'Edit Artist';
      if (hint)   hint.textContent  = a.img ? 'Image loaded' : 'No image selected. Square image recommended.';
      if (wrap)   wrap.innerHTML = avatarHTML(a);
      editingArtistId = id;
      // Scroll to form
      const panel = document.getElementById('artistFormPanel');
      if (panel) panel.scrollIntoView({ behavior:'smooth', block:'start' });
      setTimeout(() => { if (nameEl) nameEl.focus(); }, 300);
    };

    window._amDelete = function(id) {
      const idx = artists.findIndex(x => x.id === id);
      if (idx !== -1) artists.splice(idx, 1);
      if (editingArtistId === id) clearArtistForm();
      renderArtists();
    };

    // Form submit
    const artistForm = document.getElementById('artistForm');
    artistForm?.addEventListener('submit', e => {
      e.preventDefault();
      const nameEl = document.getElementById('artistNameInput');
      const name   = (nameEl?.value || '').trim();
      if (!name) {
        if (nameEl) {
          nameEl.style.borderColor = 'var(--sys-red)';
          nameEl.style.boxShadow   = '0 0 0 3px var(--sys-red-bg)';
          nameEl.focus();
          setTimeout(() => { nameEl.style.borderColor = ''; nameEl.style.boxShadow = ''; }, 2000);
        }
        return;
      }
      const wrap   = document.getElementById('amAvatarWrap');
      const imgEl  = wrap?.querySelector('img');
      const imgSrc = imgEl ? imgEl.src : null;
      const bio    = (document.getElementById('artistBioInput')?.value || '').trim();

      if (editingArtistId) {
        const a = artists.find(x => x.id === editingArtistId);
        if (a) { a.name = name; a.bio = bio; if (imgSrc) a.img = imgSrc; }
      } else {
        artists.push({ id: nextArtistId++, name, bio, img: imgSrc });
      }
      clearArtistForm();
      renderArtists();
      showToast('Artist saved');
    });

    // Clear buttons
    document.getElementById('clearArtistBtn')?.addEventListener('click', clearArtistForm);
    document.getElementById('clearArtistBtn2')?.addEventListener('click', clearArtistForm);

    // Add new artist button
    document.getElementById('addArtistBtn')?.addEventListener('click', () => {
      clearArtistForm();
      setTimeout(() => document.getElementById('artistNameInput')?.focus(), 50);
    });

    // File input — only triggered by .am-upload-btn click
    document.getElementById('artistImageInput')?.addEventListener('change', e => {
      const file = e.target.files && e.target.files[0];
      if (!file) return;
      const hint = document.getElementById('artistImageFileName');
      const wrap = document.getElementById('amAvatarWrap');
      const reader = new FileReader();
      reader.onload = ev => {
        if (wrap) wrap.innerHTML = `<img src="${ev.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;display:block;">`;
        if (hint) hint.textContent = file.name;
      };
      reader.readAsDataURL(file);
    });

    // Init
    renderArtists();
  })();

  const initialSection = location.hash.replace('#', '');
  if (initialSection) openSection(initialSection);

  /* ── ANALYTICS CHART ── */
  const chartData = {
    Downloads: {
      points: '80,210 194,178 308,192 422,120 536,145 650,82 764,112 875,54',
      values: ['420', '560', '510', '760', '650', '870', '740', '920'],
      total: '3,847', label: 'total downloads', peak: 'Peak: May 9 · 920'
    },
    'Page Views': {
      points: '80,198 194,152 308,168 422,102 536,126 650,64 764,78 875,42',
      values: ['920', '1.2k', '1.1k', '1.5k', '1.3k', '1.8k', '1.7k', '2.0k'],
      total: '8,120', label: 'total page views', peak: 'Peak: May 9 · 2,020'
    },
    'Ad Clicks': {
      points: '80,225 194,214 308,218 422,188 536,202 650,164 764,176 875,142',
      values: ['18', '24', '21', '42', '36', '58', '49', '66'],
      total: '284', label: 'total ad clicks', peak: 'Peak: May 9 · 66'
    }
  };

  function renderAnalyticsChart(type) {
    const data = chartData[type] || chartData.Downloads;
    const line        = document.querySelector('#analyticsLine');
    const pointLayer  = document.querySelector('#analyticsPointLayer');
    const totalEl     = document.querySelector('#analyticsChartTotal');
    const peakEl      = document.querySelector('#analyticsChartPeak');
    const summaryEl   = totalEl?.parentElement;

    if (line) line.setAttribute('points', data.points);

    if (pointLayer) {
      pointLayer.innerHTML = data.points.split(' ').map((pair, idx) => {
        const [x, y] = pair.split(',').map(Number);
        const left = (x / 900) * 100;
        const top  = (y / 260) * 100;
        const val  = data.values[idx];
        return `<button class="chart-point" type="button" style="left:${left}%;top:${top}%;" aria-label="${val} ${data.label}">
          <span class="chart-point-value">${val}</span>
        </button>`;
      }).join('');
    }

    if (totalEl) totalEl.textContent = data.total;
    if (summaryEl) {
      const textNode = Array.from(summaryEl.childNodes).find(n => n.nodeType === 3 && n.textContent.trim());
      if (textNode) textNode.textContent = ` ${data.label}`;
    }
    if (peakEl) peakEl.textContent = data.peak;
  }

  document.querySelectorAll('#analyticsChartToggle button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#analyticsChartToggle button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderAnalyticsChart(btn.dataset.chart);
    });
  });

  renderAnalyticsChart('Downloads');

  /* ── ANALYTICS TABLE ── */
  const analyticsTable      = document.querySelector('#analyticsTable');
  const analyticsPagination = document.querySelector('#analyticsPagination');
  const analyticsExportCsv  = document.querySelector('#analyticsExportCsv');
  const analyticsStartDate  = document.querySelector('#analyticsStartDate');
  const analyticsEndDate    = document.querySelector('#analyticsEndDate');
  const analyticsApplyRange = document.querySelector('#analyticsApplyRange');
  let analyticsPage = 1;
  let analyticsSortKey = 'index';
  let analyticsSortDir = 1;

  function renderAnalyticsTable() {
    if (!analyticsTable || !analyticsPagination) return;
    const tbody = analyticsTable.querySelector('tbody');
    const rows  = Array.from(tbody?.querySelectorAll('tr') || []);
    rows.sort((a, b) => {
      const av = a.dataset[analyticsSortKey] || '';
      const bv = b.dataset[analyticsSortKey] || '';
      const num = !isNaN(Number(av)) && !isNaN(Number(bv));
      return num ? (Number(av) - Number(bv)) * analyticsSortDir
                 : av.localeCompare(bv) * analyticsSortDir;
    });
    rows.forEach(r => tbody.appendChild(r));
    const total = Math.max(1, Math.ceil(rows.length / 10));
    analyticsPage = Math.min(analyticsPage, total);
    rows.forEach((r, i) => {
      r.style.display = (i >= (analyticsPage-1)*10 && i < analyticsPage*10) ? '' : 'none';
    });
    analyticsPagination.innerHTML = '';
    if (total <= 1) return;
    for (let p = 1; p <= total; p++) {
      const btn = document.createElement('button');
      btn.className = `page-btn${p === analyticsPage ? ' active' : ''}`;
      btn.textContent = String(p);
      btn.addEventListener('click', () => { analyticsPage = p; renderAnalyticsTable(); });
      analyticsPagination.appendChild(btn);
    }
  }

  analyticsTable?.querySelectorAll('th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      analyticsSortDir = analyticsSortKey === key ? analyticsSortDir * -1 : 1;
      analyticsSortKey = key;
      analyticsPage = 1;
      renderAnalyticsTable();
    });
  });

  /* ── APPLE HIG DATE RANGE PICKER ── */
  (function() {
    const MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    // Week starts Monday (like screenshot)
    const DOW_OFFSET = 1; // 0=Sun,1=Mon
    const TODAY = new Date(2026, 4, 17);

    let vY = 2026, vM = 4;
    let fromDate = new Date(2026, 4, 2);
    let toDate   = new Date(2026, 4, 9);
    let pickerMode = 'from'; // 'from'|'to'
    let isOpen = false;

    /* ── Helpers ── */
    function same(a,b){ return a&&b&&a.toDateString()===b.toDateString(); }
    function fmtInput(d){ if(!d) return ''; const dd=String(d.getDate()).padStart(2,'0'); const mm=String(d.getMonth()+1).padStart(2,'0'); return `${dd}/${mm}/${d.getFullYear()}`; }
    function fmtShort(d){ if(!d) return '?'; return MONTHS[d.getMonth()].slice(0,3)+' '+d.getDate(); }
    function daysBetween(a,b){ return Math.round(Math.abs((b-a)/(864e5))); }
    function parseInput(str){
      // Accept DD/MM/YYYY or DD-MM-YYYY or YYYY-MM-DD
      str = str.trim();
      let m;
      if((m=str.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/))){
        const d=new Date(+m[3],+m[2]-1,+m[1]);
        return isNaN(d) ? null : d;
      }
      if((m=str.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/))){
        const d=new Date(+m[1],+m[2]-1,+m[3]);
        return isNaN(d) ? null : d;
      }
      return null;
    }

    /* ── Update UI ── */
    function updateSummary(){
      const trig  = document.getElementById('nsdpTriggerLabel');
      const sumEl = document.getElementById('nsdpSummary');
      if(fromDate && toDate){
        const days = daysBetween(fromDate,toDate);
        if(trig) trig.textContent = fmtShort(fromDate)+' – '+fmtShort(toDate);
        if(sumEl) sumEl.textContent = days+' day'+(days!==1?'s':'')+' selected';
      } else if(fromDate){
        if(trig) trig.textContent = fmtShort(fromDate)+' – ?';
        if(sumEl) sumEl.textContent = 'Select end date';
      } else {
        if(trig) trig.textContent = 'Select range';
        if(sumEl) sumEl.textContent = 'Select start date';
      }
    }

    function syncInputs(){
      const fi = document.getElementById('nsdpInputFrom');
      const ti = document.getElementById('nsdpInputTo');
      if(fi) fi.value = fromDate ? fmtInput(fromDate) : '';
      if(ti) ti.value = toDate   ? fmtInput(toDate)   : '';
    }

    /* ── Render calendar ── */
    function render(){
      const grid  = document.getElementById('miniCalGrid');
      const lbl   = document.getElementById('miniMonthLabel');
      if(!grid||!lbl) return;
      lbl.textContent = MONTHS[vM]+' '+vY;

      // First day of month, offset for Mon start
      const firstRaw   = new Date(vY, vM, 1).getDay(); // 0=Sun
      const firstOffset= (firstRaw - DOW_OFFSET + 7) % 7;
      const daysInMonth= new Date(vY, vM+1, 0).getDate();

      let html='';
      for(let i=0;i<firstOffset;i++) html+='<button class="hig-mini-day mini-empty" tabindex="-1"></button>';
      for(let d=1;d<=daysInMonth;d++){
        const dt = new Date(vY,vM,d);
        let cls  = 'hig-mini-day';
        const isToday = same(dt,TODAY);
        const isFrom  = same(dt,fromDate);
        const isTo    = same(dt,toDate);
        const inRange = fromDate&&toDate&&dt>fromDate&&dt<toDate;
        if(isToday) cls+=' mini-today';
        if(isFrom&&isTo)    cls+=' mini-selected mini-range-start mini-range-end';
        else if(isFrom)     cls+=' mini-selected mini-range-start';
        else if(isTo)       cls+=' mini-selected mini-range-end';
        else if(inRange)    cls+=' mini-in-range';
        html+=`<button class="${cls}" type="button" data-d="${vY}-${vM+1}-${d}">${d}</button>`;
      }
      grid.innerHTML = html;

      grid.querySelectorAll('.hig-mini-day:not(.mini-empty)').forEach(btn=>{
        btn.addEventListener('click',()=>{
          const [y,m,d]=btn.dataset.d.split('-').map(Number);
          const clicked=new Date(y,m-1,d);
          if(pickerMode==='from'){
            fromDate=clicked;
            if(toDate&&toDate<fromDate) toDate=null;
            pickerMode='to';
          } else {
            if(clicked<fromDate){ toDate=fromDate; fromDate=clicked; }
            else toDate=clicked;
            pickerMode='from';
          }
          syncInputs();
          updateSummary();
          render();
        });
      });

      updateSummary();
    }

    /* ── Open / close ── */
    function openPicker(){
      const popup = document.getElementById('nsdpPopup');
      const btn   = document.getElementById('nsdpTrigger');
      if(!popup) return;
      isOpen=true;
      popup.style.display='block';
      btn?.classList.add('open');
      pickerMode='from';
      syncInputs();
      render();
      setTimeout(()=>document.getElementById('nsdpInputFrom')?.focus(),50);
    }

    function closePicker(){
      const popup=document.getElementById('nsdpPopup');
      const btn  =document.getElementById('nsdpTrigger');
      if(!popup) return;
      isOpen=false;
      popup.style.display='none';
      btn?.classList.remove('open');
    }

    /* ── Open / close ── */
    window.nsdpParseInput = function(mode, val, apply=false){
      const parsed = parseInput(val);
      const inputEl = document.getElementById(mode==='from'?'nsdpInputFrom':'nsdpInputTo');
      if(parsed){
        inputEl?.classList.remove('error');
        if(mode==='from'){ fromDate=parsed; if(toDate&&toDate<fromDate) toDate=null; }
        else             { toDate=parsed;   if(fromDate&&toDate<fromDate){ const tmp=fromDate; fromDate=toDate; toDate=tmp; } }
        updateSummary();
        // Navigate calendar to the month of the entered date
        vY=parsed.getFullYear(); vM=parsed.getMonth();
        render();
      } else if(val.length===10){
        inputEl?.classList.add('error');
      }
    };

    /* ── Nav ── */
    window.miniCalNav = function(dir){
      vM+=dir;
      if(vM>11){ vM=0; vY++; }
      if(vM<0) { vM=11; vY--; }
      render();
    };

    window.jumpToday = function(){
      const t=new Date(TODAY);
      vY=t.getFullYear(); vM=t.getMonth();
      if(pickerMode==='from'){ fromDate=t; pickerMode='to'; }
      else { toDate=t; if(toDate<fromDate){ const tmp=fromDate; fromDate=toDate; toDate=tmp; } }
      syncInputs(); updateSummary(); render();
    };

    /* ── Cancel / Apply ── */
    window.nsdpCancel = function(){
      fromDate=new Date(2026,4,2);
      toDate  =new Date(2026,4,9);
      syncInputs(); updateSummary();
      closePicker();
    };

    window.nsdpApply = function(){
      if(!fromDate||!toDate){ showToast('Select both start and end dates'); return; }
      showToast('Range: '+fmtShort(fromDate)+' – '+fmtShort(toDate));
      document.querySelectorAll('[data-analytics-range]').forEach(c=>c.classList.remove('active'));
      closePicker();
    };

    // Wire old analyticsApplyRange if still referenced
    document.getElementById('analyticsApplyRange')?.addEventListener('click', window.nsdpApply);

    // Stop clicks inside popup from bubbling to document
    document.getElementById('nsdpPopup')?.addEventListener('click', e => e.stopPropagation());

    // Trigger button
    document.getElementById('nsdpTrigger')?.addEventListener('click', e => {
      e.stopPropagation();
      isOpen ? closePicker() : openPicker();
    });

    // Close ONLY on true outside click
    document.addEventListener('click', () => {
      if (!isOpen) return;
      closePicker();
    });

    // Close on Escape key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && isOpen) closePicker();
    });

    // Init trigger label
    updateSummary();
  })();

  // Analytics date range chips
  document.querySelectorAll('[data-analytics-range]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('[data-analytics-range]').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
    });
  });

  renderAnalyticsTable();

  /* ── NOTIFICATIONS ── */
  const notificationFeed        = document.querySelector('#notificationFeed');
  const markAllReadButton        = document.querySelector('#markAllReadButton');
  const toggleNotificationSettings = document.querySelector('#toggleNotificationSettings');
  const notificationSettingsPanel  = document.querySelector('#notificationSettingsPanel');

  document.querySelectorAll('[data-notification-filter]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-notification-filter]').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const filter = tab.dataset.notificationFilter;
      notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
        item.style.display = (filter === 'all' || item.dataset.type === filter) ? '' : 'none';
      });
    });
  });

  markAllReadButton?.addEventListener('click', () => {
    notificationFeed?.querySelectorAll('.notification-item').forEach(item => {
      item.dataset.unread = 'false';
      item.querySelector('.unread-dot')?.remove();
    });
  });

  notificationFeed?.addEventListener('click', e => {
    e.target.closest('.dismiss-notification')?.closest('.notification-item')?.remove();
  });

  toggleNotificationSettings?.addEventListener('click', () => {
    notificationSettingsPanel?.classList.toggle('open');
    toggleNotificationSettings.textContent =
      notificationSettingsPanel?.classList.contains('open') ? 'Hide Settings' : 'Show Settings';
  });

  function exportReport() {
    const options = ['PDF Report (for brands/advertisers)', 'CSV Data (for spreadsheets)', 'JSON (for developers)'];
    const choice = confirm('Export Performance Report?\n\nClick OK for PDF, Cancel to skip.');
    if (choice) alert('PDF report generated.\nIncludes: impressions, clicks, CTR, top songs by downloads.');
  }

  // Animate bars on load
  window.addEventListener('load', () => {
    document.querySelectorAll('.bar').forEach((bar, i) => {
      bar.style.opacity = '0';
      setTimeout(() => {
        bar.style.transition = 'opacity .3s, height .5s';
        bar.style.opacity = '1';
      }, i * 60);
    });
  });

  // Calendar picker removed — using compact date inputs

