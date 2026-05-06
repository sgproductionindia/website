# SG Production Music Download Website

This is a music catalog based on your screenshot, with an optional PHP admin panel for hosting.

Open `index.html` in a browser to preview it. On Hostinger or another PHP hosting plan, open `admin.php` to upload new songs. Uploaded tracks are saved to `data/tracks.json`, covers go to `uploads/covers/`, and audio files go to `uploads/audio/`.

Before publishing, change the password at the top of `admin.php`:

```php
const ADMIN_PASSWORD = 'change-this-password';
```

Files:

- `index.html` - page layout and sections
- `styles.css` - dark neon responsive UI
- `script.js` - track data, player, song pages, and uploaded-track loader
- `admin.php` - password-protected upload panel for cover art and WAV/MP3 files
- `data/tracks.json` - uploaded song list
- `assets/` - local artwork used by the site
