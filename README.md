# SG Production Music Download Website

This is a music catalog based on your screenshot, with an optional PHP admin panel for hosting.

Open `index.html` in a browser to preview it. In production, this site is deployed through Coolify on an Ubuntu VPS using Docker and Apache/PHP. Uploaded tracks are saved to `data/tracks.json`, covers go to `uploads/covers/`, audio files go to `uploads/audio/`, and advertising media goes to `uploads/ads/`.

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

Files:

- `index.html` - page layout and sections
- `styles.css` - dark neon responsive UI
- `script.js` - track data, player, song pages, and uploaded-track loader
- `admin.php` - password-protected upload panel for cover art and WAV/MP3 files
- `Dockerfile` - Coolify Docker build for Apache + PHP
- `php-upload.ini` - Docker PHP upload limit settings
- `data/tracks.json` - uploaded song list
- `assets/` - local artwork used by the site
