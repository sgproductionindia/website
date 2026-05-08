# SG Production Music Download Website

This is a music catalog based on your screenshot, with an optional PHP admin panel for hosting.

Open `index.html` in a browser to preview the website. Open `admin-preview.html` to preview the admin panel UI locally without deploying. In production, this site is deployed through Coolify on an Ubuntu VPS using Docker and Apache/PHP. Uploaded tracks are saved to `data/tracks.json`, website/ad settings are saved to `data/settings.json`, artist profiles are saved to `data/artists.json`, covers go to `uploads/covers/`, preview audio files go to `uploads/audio/`, advertising media goes to `uploads/ads/`, and artist images go to `uploads/artists/`.

Before publishing, change the password at the top of `admin.php`:

```php
const ADMIN_PASSWORD = 'hyqhyp-viKfa3-timfaw';
```

Deployment:

- Local files -> GitHub repository -> Coolify auto deploy -> Docker container -> live website
- Domain: `https://sgproduction.music`
- Admin URL: `https://sgproduction.music/admin.php`
- Server stack: Ubuntu 24.04 + Docker + Coolify
- Apache serves files from `/var/www/html/` inside the container

The Docker image uses `php:8.2-apache`. PHP upload limits are configured in `php-upload.ini`, which is copied into `/usr/local/etc/php/conf.d/` during the Docker build. This is the Docker/Coolify-safe way to allow larger WAV/MP3 uploads.

If uploads should survive redeploys, configure a Coolify persistent volume for:

```text
/var/www/html/uploads
/var/www/html/data
```

The Docker container also fixes ownership for those folders at startup so Apache/PHP can write uploaded covers, preview audio, ads, and JSON data after Coolify mounts the volumes.

Admin panel controls:

- Upload new songs with cover image, MP3/WAV preview file, external WAV download URL, genre, BPM, waveform style, artist profile, new badge, and latest-release status
- Edit, delete, preview, download, and reorder uploaded songs
- Manage homepage title, tagline, YouTube section text, contact email, social links, latest count, songs per page, and demo pagination count
- Manage global advertising media, advertisement on/off state, and optional advertisement click URL
- Add, edit, and delete artist profiles with artist images and assigned genres

Song uploads use two audio fields:

- Preview Song File: MP3 or WAV uploaded to the site for browser playback.
- WAV Download URL: external or hosted WAV link used by the Download buttons.

Clean song URLs are handled by Apache rewrite rules in `.htaccess`, so a song like `Nagin Theme` can open at `/nagin-theme` after Coolify rebuilds the Docker image.

Files:

- `index.html` - page layout and sections
- `styles.css` - dark neon responsive UI
- `script.js` - track data, player, song pages, and uploaded-track loader
- `admin.php` - password-protected admin panel for songs, artists, site settings, and ads
- `admin-preview.html` - static local admin UI preview for fast design checks
- `Dockerfile` - Coolify Docker build for Apache + PHP
- `php-upload.ini` - Docker PHP upload limit settings
- `data/tracks.json` - uploaded song list
- `data/settings.json` - website and advertising settings
- `data/artists.json` - artist page settings
- `assets/` - local artwork used by the site
