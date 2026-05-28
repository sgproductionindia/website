<?php
$pageTitle = 'About SG Production | Bass Music, Halgi Beats & DJ Remixes from India';
$pageDescription = 'SG Production is an independent music project from India specializing in DJ soundcheck music, heavy bass drops, Marathi Halgi beats, subwoofer test tracks, and high-energy DJ remixes. Direct downloads for DJs, bassheads, car audio lovers, and music enthusiasts.';
$pageKeywords = 'SG Production, DJ soundcheck, bass music, Halgi beats, Marathi DJ remix, Hindi DJ remix, subwoofer test, bass test, car speaker test, DJ remix India, Halgi bass mix, soundcheck 2026, bass drops, DJ music India';
include 'header.php';
?>

<style>
.about-hero{padding:56px 48px 48px;border-bottom:1px solid #222}
.hero-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:24px}
.hero-tag{font-size:11px;color:#666;background:#111;border:1px solid #2a2a2a;padding:4px 12px;font-weight:500}
.about-section{padding:48px 48px;border-bottom:1px solid #222}
.about-section:last-of-type{border-bottom:none}
.section-eyebrow{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#666;margin-bottom:10px}
.cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
.card{background:#111;border:1px solid #2a2a2a;padding:24px;display:flex;flex-direction:column;gap:14px}
.card-icon{width:36px;height:36px;background:#181818;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center}
.card-icon svg{width:18px;height:18px;stroke:#aaa;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.card-title{font-size:14px;font-weight:700;color:#fff}
.card-desc{font-size:12.5px;color:#aaa;line-height:1.75}
.expect-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-top:8px}
.expect-item{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#aaa;padding:10px 14px;background:#111;border:1px solid #222;line-height:1.6}
.expect-item::before{content:'-';color:#666;flex-shrink:0;margin-top:1px}
.chips-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
.chip{background:#111;border:1px solid #2a2a2a;padding:10px 20px;font-size:13px;font-weight:600;color:#fff}
.platforms-list{display:flex;flex-direction:column;gap:8px;margin-top:8px;max-width:560px}
.platform-row{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#111;border:1px solid #222;text-decoration:none;transition:background .15s;gap:16px}
.platform-row:hover{background:#181818}
.platform-left{display:flex;align-items:center;gap:12px}
.platform-icon{width:32px;height:32px;background:#181818;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center}
.platform-icon svg{width:15px;height:15px;stroke:#aaa;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.platform-name{font-size:13px;font-weight:600;color:#fff}
.platform-label{font-size:12px;color:#666}
.platform-arrow{color:#666;font-size:14px;flex-shrink:0}
.tagline-band{text-align:center;padding:56px 48px;border-top:1px solid #222}
.tagline-band p{font-size:22px;font-weight:700;color:#fff;letter-spacing:-.3px;line-height:1.4}
.tagline-band .sub{font-size:13px;color:#666;margin-top:10px;font-weight:400}
@media(max-width:900px){.cards-grid{grid-template-columns:1fr}.expect-grid{grid-template-columns:1fr}}
@media(max-width:700px){.about-hero{padding:32px 20px}.about-section{padding:32px 20px}.tagline-band{padding:40px 20px}.tagline-band p{font-size:18px}}
</style>

<main class="about-page">
  <section class="about-hero">
    <h1>About SG Production</h1>
    <p>Your ultimate destination for powerful soundcheck music, heavy bass drops, Halgi energy, and DJ remixes from India.</p>
    <div class="hero-tags" aria-label="Music categories">
      <span class="hero-tag">DJ Soundcheck</span>
      <span class="hero-tag">Bass Music</span>
      <span class="hero-tag">Halgi Beats</span>
      <span class="hero-tag">Marathi Remix</span>
      <span class="hero-tag">Hindi DJ Remix</span>
      <span class="hero-tag">Subwoofer Test</span>
      <span class="hero-tag">India</span>
    </div>
  </section>

  <section class="about-section">
    <div class="section-eyebrow">Who We Are</div>
    <p>SG Production is an independent music production project from India specializing in high-energy DJ music, bass-heavy soundcheck tracks, Marathi Halgi beats, and premium DJ remixes.</p>
    <p>We create music for DJs, car audio lovers, bassheads, and music enthusiasts — tracks designed to shake your system and elevate your vibe. No barriers. No paywalls. Just direct downloads.</p>
  </section>

  <section class="about-section">
    <div class="section-eyebrow">What We Make</div>
    <div class="cards-grid">
      <article class="card">
        <div class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M11 5 6 9H2v6h4l5 4V5z"></path><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>
        </div>
        <div class="card-title">Soundcheck &amp; Bass Music</div>
        <div class="card-desc">Premium bass mixes, DJ soundcheck tracks, car speaker bass tests, subwoofer test tones, and low-frequency test tracks. Perfect for tuning DJ setups, home theaters, car subwoofers, and studio monitors.</div>
      </article>

      <article class="card">
        <div class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
        </div>
        <div class="card-title">Halgi &amp; DJ Remixes</div>
        <div class="card-desc">Festival Halgi beats, Marathi Halgi bass mix, Hindi DJ remixes, mashups, clean DJ edits, and club-ready remixes across multiple styles.</div>
      </article>

      <article class="card">
        <div class="card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path></svg>
        </div>
        <div class="card-title">Direct Downloads</div>
        <div class="card-desc">Every track available for direct download. High-quality audio for DJs, car audio setups, home theaters, and music lovers who want to own what they love.</div>
      </article>
    </div>
  </section>

  <section class="about-section">
    <div class="section-eyebrow">What You Can Expect</div>
    <div class="expect-grid">
      <div class="expect-item">Halgi Bass Mix sessions</div>
      <div class="expect-item">Soundcheck 2025 &amp; 2026 tracks</div>
      <div class="expect-item">Marathi Halgi beats</div>
      <div class="expect-item">Low-frequency subwoofer test tracks</div>
      <div class="expect-item">Clean DJ edits and mashups</div>
      <div class="expect-item">Club-ready remixes</div>
      <div class="expect-item">Heavy drops and hard kicks</div>
      <div class="expect-item">Weekly new uploads</div>
    </div>
  </section>

  <section class="about-section">
    <div class="section-eyebrow">Made For</div>
    <div class="chips-row">
      <span class="chip">DJs &amp; Performers</span>
      <span class="chip">Car Audio &amp; Bassheads</span>
      <span class="chip">Home Theater Enthusiasts</span>
      <span class="chip">Music Lovers &amp; Fans</span>
    </div>
  </section>

  <section class="about-section">
    <div class="section-eyebrow">Find Us On</div>
    <div class="platforms-list">
      <a class="platform-row" href="https://www.youtube.com/@sgproductionindia" target="_blank" rel="noopener">
        <span class="platform-left">
          <span class="platform-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-2C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 2A29.94 29.94 0 0 0 1 12a29.94 29.94 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 2C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-2A29.94 29.94 0 0 0 23 12a29.94 29.94 0 0 0-.46-5.58z"></path><path d="m10 15 5-3-5-3z"></path></svg></span>
          <span><span class="platform-name">YouTube</span><span class="platform-label">Subscribe for weekly uploads</span></span>
        </span>
        <span class="platform-arrow">↗</span>
      </a>

      <a class="platform-row" href="https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb" target="_blank" rel="noopener">
        <span class="platform-left">
          <span class="platform-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M8 11.2c2.6-.7 5.8-.4 8 1"></path><path d="M8.5 14c2.1-.5 4.7-.3 6.5.8"></path><path d="M9 16.5c1.6-.3 3.3-.2 4.7.5"></path></svg></span>
          <span><span class="platform-name">Spotify</span><span class="platform-label">Stream on Spotify</span></span>
        </span>
        <span class="platform-arrow">↗</span>
      </a>

      <a class="platform-row" href="https://music.apple.com/in/artist/sg-production/1580814477" target="_blank" rel="noopener">
        <span class="platform-left">
          <span class="platform-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg></span>
          <span><span class="platform-name">Apple Music</span><span class="platform-label">Listen on Apple Music</span></span>
        </span>
        <span class="platform-arrow">↗</span>
      </a>

      <a class="platform-row" href="https://www.instagram.com/sgproduction.music" target="_blank" rel="noopener">
        <span class="platform-left">
          <span class="platform-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path></svg></span>
          <span><span class="platform-name">Instagram</span><span class="platform-label">Follow for updates</span></span>
        </span>
        <span class="platform-arrow">↗</span>
      </a>
    </div>
  </section>

  <section class="tagline-band">
    <p>"Feel the bass. Test the power. Turn up with SG Production. 🎧🔥"</p>
    <div class="sub">Built for loud systems, clean downloads, and independent music lovers.</div>
  </section>
</main>

<script src="page-search.js?v=20260528-page-search" defer></script>

<?php include 'footer.php'; ?>
