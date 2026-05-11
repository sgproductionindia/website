# Admin Panel Integration Guide

## Overview
Your beautiful admin-preview.html UI has been partially integrated with the admin.php backend. Here's how to complete the connection:

## What's Been Done ✅
- ✅ Upload Song form connected (method=POST, action=upload)
- ✅ Advertising form connected (method=POST, action=save_ad)
- ✅ Website Settings form connected (method=POST, action=save_site)
- ✅ Artist form connected (method=POST, action=save_artist)
- ✅ All form field names match admin.php expectations

## Quick Start

### Option 1: Use admin-preview.html as-is
The admin-preview.html file is NOW READY to use! Just open it directly and the forms will submit to admin.php.

**Steps:**
1. Upload admin-preview.html to your server
2. Open it in your browser: `http://your-site.com/admin-preview.html`
3. Forms will POST to `admin.php` with proper action values
4. Success/error messages will be handled by admin.php

### Option 2: Merge into admin.php (Recommended)
For a single admin panel file, merge the HTML into admin.php:

1. Open **admin.php** 
2. Find where the HTML output starts (look for `<!doctype html>`)
3. Delete the old HTML section
4. Copy the entire HTML from **admin-preview.html** (from `<!DOCTYPE html>` to `</html>`)
5. Paste it into admin.php where you deleted the old HTML
6. Save admin.php
7. Now you have one integrated file!

## Form Integration Reference

### 1. UPLOAD SONG FORM ✅
**Location:** #uploadSongForm  
**Action:** POST to admin.php  
**Hidden fields:** `action=upload`  
**Required fields:**
- title (text)
- artist (select)
- artistId (select) 
- genre (select)
- audio (file) - MP3 or WAV
- duration (text) - M:S format
- downloadUrl (url) - Direct WAV download link
- cover (file) - Cover image
- wave (select) - sine, square, sawtooth, triangle
- creditText (text) - Optional
- isNew (checkbox) - Show in latest
- isFeatured (checkbox) - Mark featured

### 2. EDIT SONG FORM ⚠️ 
**Status:** Needs form binding  
**Location:** #songEditForm  
**Fields needed:**
```html
<input type="hidden" name="action" value="update_track">
<input type="hidden" name="trackId" id="trackIdInput">
```
**Then add to form:**
- title, artist, artistId, genre, duration, downloadUrl, cover (file), audio (file)
- bpm (number), wave (select), creditText (text)
- isNew, isFeatured (checkboxes)

### 3. DELETE SONG FORM ⚠️
**Status:** Needs form binding  
**Required fields:**
```html
<form method="POST">
  <input type="hidden" name="action" value="delete_track">
  <input type="hidden" name="trackId">
  <input type="checkbox" name="deleteFiles"> Delete uploaded files?
  <button type="submit">Confirm Delete</button>
</form>
```

### 4. SAVE ARTIST FORM ✅
**Location:** #artistForm  
**Required fields:**
- artistId (hidden) - Empty for new, populated for edit
- artistName (text)
- artistStyle (text)
- artistYear (text)
- artistGenres (text) - Comma-separated
- artistImage (file) - Profile image

### 5. DELETE ARTIST FORM ⚠️
**Status:** Needs form binding  
**Required fields:**
```html
<form method="POST">
  <input type="hidden" name="action" value="delete_artist">
  <input type="hidden" name="artistId">
  <button type="submit">Confirm Delete Artist</button>
</form>
```

### 6. SAVE SITE SETTINGS FORM ✅
**Location:** #websiteSettingsForm  
**Required fields:**
- action=save_site
- siteTitle, tagline, youtubeHeading, youtubeText, contactEmail
- instagram, spotify, appleMusic, youtube
- metaDescription, ogImage (file), favicon (file)
- latestCount, tracksPerPage, paginationDemoPages

### 7. SAVE AD FORM ✅
**Location:** #adUpdateForm  
**Required fields:**
- action=save_ad
- adMedia (file) - Image or video
- adLinkUrl (url)
- adEnabled (checkbox)

## Form Submission Flow

### Example: Upload Song
```
1. User fills form with song details
2. User selects audio file (MP3/WAV)
3. User selects cover image
4. User clicks "Upload Song"
5. Form POSTs to admin.php with:
   - action=upload
   - title, artist, genre, etc.
   - File uploads handled by $_FILES['audio'] and $_FILES['cover']
6. admin.php processes the upload (in PHP logic at top of file)
7. On success: redirects to #uploaded-songs with success message
8. On error: re-displays form with error message
9. User sees success/error alert
```

## Adding Error/Success Message Display

Add this to the top of the upload section (before the form):

```html
<div id="alertContainer">
  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error" role="alert">
        <strong>Error:</strong> <?php echo e($error); ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  
  <?php if (!empty($success)): ?>
    <div class="alert alert-success" role="alert">
      <strong>Success!</strong> <?php echo e($success); ?>
    </div>
  <?php endif; ?>
</div>
```

## JavaScript Needed for Interactive Features

You'll need to implement:

### 1. Song List Management
```javascript
// Load songs from PHP and populate #songsList
// Add edit/delete buttons that populate the edit drawer
// Handle search and filtering
```

### 2. Artist Management
```javascript
// Load artists from PHP and populate #artistGrid
// Handle edit mode - populate form from selected artist
// Handle delete confirmation
```

### 3. Genre Management
```javascript
// Load genres from PHP and populate #genreGrid
// Handle edit/delete UI interactions
```

### 4. File Preview
```javascript
// Show image previews after file selection
// Show file size and type info
// Warn about aspect ratio for ads
```

## Important Notes

1. **Authentication:** admin.php has password authentication. The HTML doesn't show the login form. You may need to add that to admin-preview.html if implementing a fresh login.

2. **File Paths:** The form assumes files upload to the correct directories configured in admin.php:
   - Covers: `/uploads/covers`
   - Audio: `/uploads/audio`
   - Ads: `/uploads/ads`
   - Artists: `/uploads/artists`
   - Site media: `/uploads/site`

3. **Permissions:** Make sure these directories are writable by PHP (chmod 755 or 775).

4. **Multipart Forms:** All forms with file uploads must have:
   - `method="POST"`
   - `enctype="multipart/form-data"`

5. **CSRF Protection:** Consider adding CSRF tokens if you implement this in production.

## Testing Checklist

- [ ] Upload a song successfully
- [ ] Edit the song metadata
- [ ] Delete a song
- [ ] Add a new artist
- [ ] Edit artist details
- [ ] Update advertising settings
- [ ] Change website settings
- [ ] Test error messages (try invalid URLs, missing fields)
- [ ] Test success messages
- [ ] Verify files save to correct directories
- [ ] Check that data persists after page reload
- [ ] Test on mobile (responsive design)

## Troubleshooting

### Forms not submitting?
- Check browser console for JavaScript errors
- Ensure `enctype="multipart/form-data"` on file upload forms
- Verify PHP `max_upload_size` allows your files

### Files not uploading?
- Check directory permissions (must be writable)
- Check `post_max_size` and `upload_max_filesize` in php.ini
- Verify MIME types are allowed in admin.php

### Data not saving?
- Check file permissions on data/ directory
- Ensure JSON files are writable
- Look in server error logs for PHP errors

### Styles not loading correctly?
- All CSS is inline in admin-preview.html, should work offline
- Check that custom CSS variables are supported in your browser

## File Structure
```
.
├── admin.php                 ← Backend logic (keep existing)
├── admin-preview.html        ← New modern UI (NOW CONNECTED)
├── admin-integrated.php      ← Reference guide (optional)
├── INTEGRATION_GUIDE.md      ← This file
├── data/
│   ├── tracks.json
│   ├── artists.json
│   ├── settings.json
│   └── ad-stats.json
└── uploads/
    ├── covers/
    ├── audio/
    ├── ads/
    ├── artists/
    └── site/
```

## Next Steps

1. **Test admin-preview.html** as-is with your admin.php backend
2. **Add missing form integrations** (edit/delete forms - see ⚠️ items above)
3. **Implement JavaScript** for interactive features (song list, artist grid, etc.)
4. **Merge into admin.php** once everything works (optional)
5. **Add authentication UI** if needed
6. **Deploy to production** with proper permissions

## Support

If you encounter issues:
1. Check the PHP error logs
2. Use browser DevTools to inspect network requests
3. Verify all form field names match admin.php expectations
4. Test with simple data first (before large files)
5. Check file permissions with `ls -la` on server

Good luck with your integrated admin panel! 🎵
