<?php
$pageTitle = 'Contact SG Production | Licensing, DMCA & General Inquiries';
$pageDescription = 'Contact SG Production for music licensing inquiries, DMCA takedown requests, or general questions. We respond within 24-48 hours.';

$success = '';
$error = '';
$errors = [];
$old = [
  'name' => '',
  'email' => '',
  'subject' => '',
  'message' => '',
];

function sg_contact_clean($value) {
  return trim((string) $value);
}

function sg_contact_e($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sg_contact_env($key, $default = '') {
  $value = getenv($key);
  return ($value === false || $value === '') ? $default : $value;
}

function sg_contact_send($name, $email, $subject, $message) {
  $host = 'smtp.office365.com';
  $port = 587;
  $secure = 'tls';
  $username = getenv('SMTP_USERNAME') ?: 'support@sgproduction.music';
  $password = getenv('SMTP_PASSWORD') ?: '';
  $from = getenv('SMTP_FROM') ?: 'support@sgproduction.music';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'SG Production';
  $to = getenv('CONTACT_TO') ?: 'support@sgproduction.music';

  $socket = @fsockopen($host, $port, $errno, $errstr, 30);
  if (!$socket) {
    error_log('SG SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
    return false;
  }

  stream_set_timeout($socket, 30);

  function smtp_read($sock) {
    $data = '';
    while ($line = fgets($sock, 515)) {
      $data .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return [(int)substr($data, 0, 3), trim($data)];
  }

  function smtp_cmd($sock, $cmd, $expect) {
    fwrite($sock, $cmd . "\r\n");
    [$code] = smtp_read($sock);
    return $code === $expect;
  }

  [$code] = smtp_read($socket);
  if ($code !== 220) {
    fclose($socket);
    error_log('SG SMTP greeting failed: ' . $code);
    return false;
  }

  $domain = 'sgproduction.music';

  if (!smtp_cmd($socket, 'EHLO ' . $domain, 250)) {
    smtp_cmd($socket, 'HELO ' . $domain, 250);
  }

  fwrite($socket, "STARTTLS\r\n");
  [$code, $response] = smtp_read($socket);
  if ($code !== 220) {
    fclose($socket);
    error_log('SG SMTP STARTTLS failed: ' . $response);
    return false;
  }

  $tlsEnabled = stream_socket_enable_crypto(
    $socket,
    true,
    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
  );
  if (!$tlsEnabled) {
    fclose($socket);
    error_log('SG SMTP TLS upgrade failed');
    return false;
  }

  if (!smtp_cmd($socket, 'EHLO ' . $domain, 250)) {
    fclose($socket);
    error_log('SG SMTP EHLO after TLS failed');
    return false;
  }

  fwrite($socket, "AUTH LOGIN\r\n");
  [$code] = smtp_read($socket);
  if ($code !== 334) {
    fclose($socket);
    error_log('SG SMTP AUTH failed: ' . $code);
    return false;
  }

  fwrite($socket, base64_encode($username) . "\r\n");
  [$code] = smtp_read($socket);
  if ($code !== 334) {
    fclose($socket);
    error_log('SG SMTP username failed: ' . $code);
    return false;
  }

  fwrite($socket, base64_encode($password) . "\r\n");
  [$code] = smtp_read($socket);
  if ($code !== 235) {
    fclose($socket);
    error_log('SG SMTP password failed: ' . $code);
    return false;
  }

  if (!smtp_cmd($socket, 'MAIL FROM:<' . $from . '>', 250)) {
    fclose($socket);
    error_log('SG SMTP MAIL FROM failed');
    return false;
  }

  if (!smtp_cmd($socket, 'RCPT TO:<' . $to . '>', 250)) {
    fclose($socket);
    error_log('SG SMTP RCPT TO failed');
    return false;
  }

  if (!smtp_cmd($socket, 'DATA', 354)) {
    fclose($socket);
    error_log('SG SMTP DATA failed');
    return false;
  }

  $boundary = md5(uniqid());
  $headers = implode("\r\n", [
    'Date: ' . date('r'),
    'From: =?UTF-8?B?'
      . base64_encode($fromName)
      . '?= <' . $from . '>',
    'To: <' . $to . '>',
    'Reply-To: <' . $email . '>',
    'Subject: =?UTF-8?B?'
      . base64_encode('Message from SG Production')
      . '?=',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
  ]);

  $body = base64_encode(
    "New contact message from SG Production\r\n\r\n"
    . "Name: $name\r\n"
    . "Email: $email\r\n"
    . "Subject: $subject\r\n\r\n"
    . "Message:\r\n$message"
  );

  $body = chunk_split($body, 76, "\r\n");

  fwrite($socket, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");

  [$code] = smtp_read($socket);
  $sent = ($code === 250);

  smtp_cmd($socket, 'QUIT', 221);
  fclose($socket);

  if (!$sent) {
    error_log('SG SMTP send failed with code: ' . $code);
  }

  return $sent;
}

function sg_contact_save_message($name, $email, $subject, $message) {
  $dataDir = __DIR__ . '/data';
  $dataFile = $dataDir . '/contact-messages.json';

  if (!is_dir($dataDir) && !mkdir($dataDir, 0775, true)) {
    error_log('SG contact: unable to create data directory.');
    return false;
  }

  $id = date('YmdHis') . '-' . substr(sha1($email . $subject . microtime(true)), 0, 8);
  $record = [
    'id' => $id,
    'createdAt' => date('c'),
    'name' => $name,
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
  ];

  $handle = @fopen($dataFile, 'c+');
  if (!$handle) {
    error_log('SG contact: unable to open contact messages file.');
    return false;
  }

  $saved = false;
  if (flock($handle, LOCK_EX)) {
    $contents = stream_get_contents($handle);
    $messages = $contents ? json_decode($contents, true) : [];
    if (!is_array($messages)) {
      $messages = [];
    }

    $messages[] = $record;
    rewind($handle);
    ftruncate($handle, 0);
    $saved = fwrite($handle, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    fflush($handle);
    flock($handle, LOCK_UN);
  }

  fclose($handle);
  return $saved;
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = sg_contact_clean($_POST['name'] ?? '');
  $email = sg_contact_clean($_POST['email'] ?? '');
  $subject = sg_contact_clean($_POST['subject'] ?? '');
  $message = sg_contact_clean($_POST['message'] ?? '');

  $old = [
    'name' => $name,
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
  ];

  $allowedSubjects = [
    'Licensing Inquiry',
    'DMCA / Copyright Takedown',
    'General Question',
    'Collaboration',
    'Other',
  ];

  if ($name === '') {
    $errors['name'] = 'Full name is required.';
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'A valid email address is required.';
  }

  if ($subject === '' || !in_array($subject, $allowedSubjects, true)) {
    $errors['subject'] = 'Please select a subject.';
  }

  if (strlen($message) < 20) {
    $errors['message'] = 'Message must be at least 20 characters.';
  }

  if (!$errors) {
    $saved = sg_contact_save_message($name, $email, $subject, $message);
    $sent = sg_contact_send($name, $email, $subject, $message);

    if ($sent) {
      $success = 'Message sent successfully. We will get back to you soon.';
      $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    } else {
      $error = 'Something went wrong. Please try again.';
    }
  } else {
    $error = 'Please fix the highlighted fields and try again.';
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XLSFX2N5MS"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XLSFX2N5MS');
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="SG Production">
    <title>Contact SG Production | Licensing, DMCA & General Inquiries</title>
    <script>
      (function() {
        var base = document.createElement("base");
        base.href = /^https?:$/.test(window.location.protocol) ? "/" : window.location.href.replace(/[^/]*$/, "");
        document.head.appendChild(base);
      })();
    </script>
    <meta
      name="description"
      content="Contact SG Production for music licensing inquiries, DMCA takedown requests, or general questions. We respond within 24-48 hours."
    >
    <meta property="og:title" content="Contact SG Production | Licensing, DMCA & General Inquiries">
    <meta property="og:description" content="Have a question, licensing inquiry, or DMCA request? We would love to hear from you.">
    <meta property="og:image" content="https://sgproduction.music/assets/cover-1.jpg">
    <meta property="og:url" content="https://sgproduction.music/contact">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Contact SG Production | Licensing, DMCA & General Inquiries">
    <meta name="twitter:description" content="Have a question, licensing inquiry, or DMCA request? We would love to hear from you.">
    <meta name="twitter:image" content="https://sgproduction.music/assets/cover-1.jpg">
    <link rel="canonical" href="https://sgproduction.music/contact">
    <link rel="icon" href="assets/sg-logo.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/sg-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=20260601-audit">
    <link rel="stylesheet" href="transitions.min.css?v=20260524-prod">
    <script src="transitions.min.js?v=20260530-external-links" defer></script>
    <script src="page-search.js?v=20260528-page-search" defer></script>
    <script>
      // Keep clean live URLs, but make local file:// previews navigable.
      if (window.location.protocol === 'file:' || /^(127\.0\.0\.1|localhost|\[::1\])$/.test(window.location.hostname)) {
        function localPreviewHref(href) {
          if (!href) return '';
          if (href === '/') return 'index.html';
          if (href === '/tracks') return 'index.html?view=tracks';
          if (href === '/licensing') return 'index.html?view=licensing';
          if (href === '/about') return 'about.php';
          if (href === '/contact') return 'contact.php';
          if (href === '/artists') return 'artists.html';
          if (href === '/usage-policy' || href === 'usage-policy.php') return 'usage-policy.php';
          if (href.startsWith('/song/')) return 'index.html?song=' + encodeURIComponent(href.replace(/^\/song\//, ''));
          if (href.startsWith('/artist/')) return 'artists.html?artist=' + encodeURIComponent(href.replace(/^\/artist\//, ''));
          return '';
        }

        document.addEventListener('click', function(event) {
          var link = event.target.closest && event.target.closest('a[href]');
          if (!link) return;
          var nextHref = localPreviewHref(link.getAttribute('href'));
          if (!nextHref) return;
          event.preventDefault();
          window.location.href = nextHref;
        }, true);

        document.addEventListener('DOMContentLoaded', function() {
          document.querySelectorAll('a[href]').forEach(function(link) {
            var nextHref = localPreviewHref(link.getAttribute('href'));
            if (nextHref) link.setAttribute('href', nextHref);
          });
        });
      }
    </script>
  <style>
.contact-hero{padding:36px 48px 32px;border-bottom:1px solid #222}
.contact-hero h1{font-size:32px;font-weight:800;letter-spacing:-.8px;margin:0 0 10px;color:#fff}
.contact-hero p{font-size:14px;color:#aaa;margin:0;max-width:620px;line-height:1.7}
.contact-section{padding:0 0 40px;margin:0 0 40px;border-bottom:1px solid #222}
.contact-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
.contact-section h2{font-size:18px;font-weight:700;color:#fff;margin:0 0 18px;letter-spacing:-.3px}
.contact-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:8px}
.contact-card{background:#111;border:1px solid #2a2a2a;padding:24px;display:flex;flex-direction:column;gap:12px}
.contact-card-icon{width:36px;height:36px;background:#181818;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center}
.contact-card-icon svg{width:18px;height:18px;stroke:#aaa;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.contact-card-title{font-size:14px;font-weight:700;color:#fff}
.contact-card-text{font-size:12.5px;color:#aaa;line-height:1.75;flex:1}
.contact-card-link{font-size:12px;color:#fff;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.contact-card-link:hover{opacity:.7}
.contact-form{display:flex;flex-direction:column;gap:16px;max-width:600px;margin-top:8px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-size:12px;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.05em}
.form-input,.form-select,.form-textarea{background:#111;border:1px solid #2a2a2a;color:#fff;font-family:Inter,-apple-system,BlinkMacSystemFont,"SF Pro Text",sans-serif;font-size:13px;padding:10px 14px;outline:none;transition:border-color .15s;width:100%}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:#555}
.form-input::placeholder,.form-textarea::placeholder{color:#444}
.form-select{appearance:none;cursor:pointer}
.form-select option{background:#111;color:#fff}
.form-textarea{resize:vertical;min-height:120px;line-height:1.6}
.form-error{font-size:11px;color:#ff4444;margin-top:2px}
.form-submit{background:#fff;color:#000;font-family:Inter,-apple-system,BlinkMacSystemFont,"SF Pro Text",sans-serif;font-size:13px;font-weight:700;padding:12px 28px;border:none;cursor:pointer;transition:opacity .15s;align-self:flex-start}
.form-submit:hover{opacity:.85}
.form-success,.form-alert{background:#111;border:1px solid #2a2a2a;padding:16px 20px;font-size:13px;color:#aaa;margin-top:8px;max-width:600px}
.form-success{border-color:#1f7a3d;color:#d7f6df}
.form-alert{border-color:#663030;color:#ffc8c8}
.form-success strong,.form-alert strong{color:#fff}
.contact-info-list{display:flex;flex-direction:column;gap:8px;margin-top:8px;max-width:480px}
.contact-info-row{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;background:#111;border:1px solid #222;text-decoration:none;gap:16px;transition:background .15s}
.contact-info-row:hover{background:#181818}
.contact-info-left{display:flex;align-items:center;gap:12px}
.contact-info-icon{width:30px;height:30px;background:#181818;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.contact-info-icon svg{width:14px;height:14px;stroke:#aaa;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.contact-info-label{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.05em}
.contact-info-value{font-size:13px;color:#fff;font-weight:500}
.contact-notice{background:#111;border:1px solid #2a2a2a;border-left:3px solid #fff;padding:14px 18px;margin-top:16px;font-size:13px;color:#aaa;line-height:1.7;max-width:480px}
.contact-notice strong{color:#fff}
@media(max-width:900px){.contact-cards{grid-template-columns:1fr}.form-row{grid-template-columns:1fr}}
@media(max-width:700px){.contact-hero{padding:24px 20px}}
</style>
</head>
  <body>
    <header class="mobile-topbar" aria-label="Mobile navigation">
      <a class="mobile-brand" href="/" aria-label="SG Production home">
        <span class="nav-logo" aria-hidden="true">
          <svg class="sg-logo" viewBox="0 0 924.99 924.99" aria-hidden="true">
            <path d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"></path>
          </svg>
        </span>
        <span>SG Production</span>
      </a>
      <button class="mobile-menu-toggle" id="mobileMenuToggle" type="button" aria-label="Open menu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </header>

    <div class="app-shell">
      <nav class="side-nav" aria-label="Primary">
        <div class="nav-section nav-main">
          <a class="nav-brand" href="/" aria-label="SG Production home" title="Home">
            <span class="nav-logo" aria-hidden="true">
              <svg class="sg-logo" viewBox="0 0 924.99 924.99" aria-hidden="true">
                <path d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"></path>
              </svg>
            </span>
            <span class="nav-label brand-label">SG Production</span>
          </a>

          <button class="nav-link" id="focusSearch" type="button" aria-label="Search tracks" title="Search">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
              </svg>
            </span>
            <span class="nav-label">Search</span>
          </button>
          <a class="nav-link" href="/tracks" data-section-nav aria-label="Music library" title="Music Library">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
              </svg>
            </span>
            <span class="nav-label">Music Library</span>
          </a>
          <a class="nav-link" href="/artists" aria-label="Artists" title="Artists">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                <circle cx="9.5" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </span>
            <span class="nav-label">Artists</span>
          </a>
        </div>

        <div class="nav-spacer" aria-hidden="true"></div>

        <div class="nav-section nav-social">
          <a class="nav-link" href="https://www.youtube.com/@sgproductionindia" aria-label="YouTube" title="YouTube">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M22.54 6.42a2.8 2.8 0 0 0-1.97-1.98C18.83 4 12 4 12 4s-6.83 0-8.57.44a2.8 2.8 0 0 0-1.97 1.98A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.8 2.8 0 0 0 1.97 1.98C5.17 20 12 20 12 20s6.83 0 8.57-.44a2.8 2.8 0 0 0 1.97-1.98A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"></path>
                <path d="m10 15 5-3-5-3z"></path>
              </svg>
            </span>
            <span class="nav-label">YouTube</span>
          </a>
          <a class="nav-link" href="https://music.apple.com/in/artist/sg-production/1580814477" aria-label="Apple Music" title="Apple Music">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
              </svg>
            </span>
            <span class="nav-label">Apple Music</span>
          </a>
          <a class="nav-link" href="https://open.spotify.com/artist/2FeM1GdzeY1ZnT8rJLYKHb?autoplay=true" aria-label="Spotify" title="Spotify">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M7.5 10.5c3-1 6.2-.7 9.3.8"></path>
                <path d="M8.2 13.2c2.4-.7 5-.5 7.5.7"></path>
                <path d="M9 15.7c1.8-.5 3.8-.3 5.5.5"></path>
              </svg>
            </span>
            <span class="nav-label">Spotify</span>
          </a>
          <a class="nav-link" href="https://www.instagram.com/sgproduction.music" aria-label="Instagram" title="Instagram">
            <span class="nav-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24">
                <rect x="2" y="2" width="20" height="20" rx="5"></rect>
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M17.5 6.5h.01"></path>
              </svg>
            </span>
            <span class="nav-label">Instagram</span>
          </a>
        </div>
      </nav>

      <main class="page" id="top">
        <section class="policy-page contact-page">
          <div class="policy-hero contact-hero">
            <h1>Contact Us</h1>
            <p>Have a question, licensing inquiry, or DMCA request? We'd love to hear from you.</p>
          </div>

          <div class="policy-body">
            <div class="policy-sections">
              <section class="policy-section contact-section" aria-label="Contact options">
            <div class="contact-cards">
              <article class="contact-card">
                <div class="contact-card-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h6"></path></svg>
                </div>
                <div class="contact-card-title">Licensing Inquiry</div>
                <div class="contact-card-text">Want to use our music commercially? Get in touch for pricing and licensing options.</div>
                <a href="mailto:support@sgproduction.music?subject=Licensing%20Inquiry" class="contact-card-link">
                  Send Inquiry ↗
                </a>
              </article>
        
              <article class="contact-card">
                <div class="contact-card-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="contact-card-title">DMCA &amp; Copyright</div>
                <div class="contact-card-text">Copyright owner with a takedown request? We take all valid requests seriously and respond promptly.</div>
                <a href="mailto:support@sgproduction.music?subject=DMCA%20Copyright%20Takedown%20Request" class="contact-card-link">
                  Submit Request ↗
                </a>
              </article>
        
              <article class="contact-card">
                <div class="contact-card-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path></svg>
                </div>
                <div class="contact-card-title">General Questions</div>
                <div class="contact-card-text">Any other questions about SG Production, our music, or the website?</div>
                <a href="mailto:support@sgproduction.music?subject=General%20Question" class="contact-card-link">
                  Get in Touch ↗
                </a>
              </article>
            </div>
              </section>
        
              <section class="policy-section contact-section" id="contact-form" aria-labelledby="contact-form-title">
            <h2 class="section-title" id="contact-form-title">Send Message</h2>
        
            <?php if ($success): ?>
              <div class="form-success" role="status"><strong><?php echo sg_contact_e($success); ?></strong></div>
            <?php elseif ($error): ?>
              <div class="form-alert" role="alert"><strong><?php echo sg_contact_e($error); ?></strong></div>
            <?php endif; ?>
        
            <form class="contact-form" id="contactForm" method="post" action="/contact" novalidate>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="contact-name">Full Name</label>
                  <input class="form-input" id="contact-name" name="name" type="text" value="<?php echo sg_contact_e($old['name']); ?>" required>
                  <?php if (isset($errors['name'])): ?><span class="form-error"><?php echo sg_contact_e($errors['name']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label" for="contact-email">Email Address</label>
                  <input class="form-input" id="contact-email" name="email" type="email" value="<?php echo sg_contact_e($old['email']); ?>" required>
                  <?php if (isset($errors['email'])): ?><span class="form-error"><?php echo sg_contact_e($errors['email']); ?></span><?php endif; ?>
                </div>
              </div>
        
              <div class="form-group">
                <label class="form-label" for="contact-subject">Subject</label>
                <select class="form-select" id="contact-subject" name="subject" required>
                  <option value="">Select a subject...</option>
                  <?php foreach (['Licensing Inquiry', 'DMCA / Copyright Takedown', 'General Question', 'Collaboration', 'Other'] as $option): ?>
                    <option value="<?php echo sg_contact_e($option); ?>" <?php echo $old['subject'] === $option ? 'selected' : ''; ?>><?php echo sg_contact_e($option); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['subject'])): ?><span class="form-error"><?php echo sg_contact_e($errors['subject']); ?></span><?php endif; ?>
              </div>
        
              <div class="form-group">
                <label class="form-label" for="contact-message">Message</label>
                <textarea class="form-textarea" id="contact-message" name="message" minlength="20" required><?php echo sg_contact_e($old['message']); ?></textarea>
                <?php if (isset($errors['message'])): ?><span class="form-error"><?php echo sg_contact_e($errors['message']); ?></span><?php endif; ?>
              </div>
        
              <button class="form-submit" type="submit">Send Message</button>
            </form>
              </section>
        
              <section class="policy-section contact-section" aria-labelledby="other-ways-title">
            <h2 class="section-title" id="other-ways-title">Other Ways to Reach Us</h2>
            <div class="contact-info-list">
              <a class="contact-info-row" href="https://www.youtube.com/@sgproductionindia" target="_blank" rel="noopener">
                <span class="contact-info-left">
                  <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-2C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 2A29.94 29.94 0 0 0 1 12a29.94 29.94 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 2C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-2A29.94 29.94 0 0 0 23 12a29.94 29.94 0 0 0-.46-5.58z"></path><path d="m10 15 5-3-5-3z"></path></svg></span>
                  <span class="contact-info-value">youtube.com/@sgproductionindia</span>
                </span>
              </a>
              <a class="contact-info-row" href="https://www.instagram.com/sgproduction.music" target="_blank" rel="noopener">
                <span class="contact-info-left">
                  <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path></svg></span>
                  <span class="contact-info-value">instagram.com/sgproduction.music</span>
                </span>
              </a>
              <a class="contact-info-row" href="mailto:support@sgproduction.music">
                <span class="contact-info-left">
                  <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path><path d="m22 6-10 7L2 6"></path></svg></span>
                  <span class="contact-info-value">support@sgproduction.music</span>
                </span>
              </a>
            </div>
            <div class="contact-notice"><strong>Response time:</strong> We typically respond within 24-48 hours. For urgent DMCA requests, please mention URGENT in your subject line.</div>
              </section>
            </div>
          </div>
        </section>

        <script>
        (function() {
          var form = document.querySelector('.contact-form');
          if (!form) return;
          form.addEventListener('submit', function(event) {
            var valid = true;
            form.querySelectorAll('[data-client-error]').forEach(function(el) { el.remove(); });
            function showError(field, text) {
              valid = false;
              var error = document.createElement('span');
              error.className = 'form-error';
              error.setAttribute('data-client-error', 'true');
              error.textContent = text;
              field.parentNode.appendChild(error);
            }
            var name = form.elements.name;
            var email = form.elements.email;
            var subject = form.elements.subject;
            var message = form.elements.message;
            if (!name.value.trim()) showError(name, 'Full name is required.');
            if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) showError(email, 'A valid email address is required.');
            if (!subject.value) showError(subject, 'Please select a subject.');
            if (message.value.trim().length < 20) showError(message, 'Message must be at least 20 characters.');
            if (!valid) event.preventDefault();
          });
        })();
        </script>

        <footer class="footer" id="contact">
          <p>© 2026 SG Production. All rights reserved.</p>
        </footer>
      </main>
    </div>

    <script>
      (function() {
        var toggle = document.getElementById('mobileMenuToggle');
        if (toggle) {
          toggle.addEventListener('click', function() {
            var open = !document.body.classList.contains('menu-open');
            document.body.classList.toggle('menu-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
          });
        }

        var page = location.pathname.split('/').pop().replace('.php', '').replace('.html', '') || 'contact';
        fetch('api/track-visit.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'page=' + encodeURIComponent(page)
        }).catch(function() {});
      })();
    </script>

<div class="beta-backdrop" id="betaBackdrop">
  <div class="beta-popup" id="betaPopup">

    <!-- CLOSE -->
    <button class="beta-close" id="betaClose" aria-label="Close">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>

    <!-- DEFAULT STATE -->
    <div id="betaDefault">

      <!-- ICON + BADGE -->
      <div class="beta-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 924.99 924.99" width="32" height="32">
          <rect width="924.99" height="924.99" rx="160" fill="#020608"/>
          <path fill="#fff" d="M462.5,29.1C223.14,29.1,29.09,223.13,29.09,462.49s194.04,433.4,433.41,433.4,433.4-194.04,433.4-433.4S701.85,29.1,462.5,29.1ZM396.31,77.91c119.98-18.73,242.41,17.48,330.88,97.19.61.97.89,2.6.3,3.59-.52.86-14.82,8.69-17.55,10.64-66.73,47.6-86.98,143.28-38.26,210.05,26.92,36.89,76.07,63.03,87.3,109.49,21.68,89.7-79.17,162.38-161.71,116.2-65.77-36.81-62.88-113.82-98.69-170.64-39.1-62.05-128.89-110.83-202.42-120.84-65.04-8.85-136.38,4.88-193.7,35.32-8.94,4.75-17.67,11.81-25.96,16.12-1.17.61-1.84,1.27-3.41.91C101.31,228.39,233.34,103.34,396.31,77.91ZM766.38,712.49c-42.6,51.02-103.4,92.93-166.99,115.77-72.56,26.07-151.51,30.37-227.09,14.04l.54-3.69c12.76-23.05,29.02-45.59,41.26-68.76,11.02-20.85,10.09-42.73-11.49-56.68-40.28-26.01-88.01-46.32-128.88-72.06-45.54-19.86-81.75,39.73-39.25,67.97,26.92,17.89,60.26,31.49,87.8,49.03l2.55,4.15-30.94,52.47c-33.41-14.67-65.65-35.41-93-59.08-63.91-55.34-115.64-141.93-126.7-225.04-.44-3.32-1.67-9.89-.86-12.77.97-3.48,14.34-18.69,17.66-22.26,80.64-86.45,217.11-90.89,308.89-17.77,49.8,39.68,52.57,78.1,73.37,132.34,42.89,111.84,163.49,157.84,274.78,105.21l28.06-16.41c.81.8-8.46,12.07-9.71,13.56ZM843.9,520.41c-10.05-65.88-41.93-89.65-82.83-137.27-30.99-36.08-51.56-79.23-12.11-119.52,6.64-6.78,26.39-19.85,35.79-20.66,1.72-.15,2.46,1.48,3.36,2.5,3.17,3.58,7.69,11.7,10.42,16.15,47.35,77.21,63.52,171.78,47.27,260.2-1.53,1.42-1.71-.13-1.9-1.41Z"/>
        </svg>
      </div>
      <div class="beta-tag">
        <span class="beta-tag-dot"></span>
        Beta Version
      </div>

      <!-- TITLE -->
      <div class="beta-title">Welcome to SG Production</div>

      <!-- DESC -->
      <p class="beta-desc">
        You're one of the first to experience our new music download platform. We're still ironing out the details and you may encounter <strong>glitches or incomplete features</strong> along the way.
      </p>

      <!-- WHAT TO EXPECT -->
      <ul class="beta-list">
        <li>
          <span class="li-icon">⚡</span>
          Some pages or features may not work perfectly yet
        </li>
        <li>
          <span class="li-icon">🎵</span>
          Music library is being uploaded — more tracks coming soon
        </li>
        <li>
          <span class="li-icon">📱</span>
          Mobile experience is being continuously improved
        </li>
      </ul>

      <div class="beta-divider"></div>

      <!-- SUGGESTION FORM -->
      <div class="beta-form">
        <div class="beta-form-label">Share your feedback</div>
        <input type="hidden" name="_subject" value="SG Production Beta Feedback">
        <textarea class="beta-textarea" id="betaFeedback" placeholder="Found a bug? Have a suggestion? Tell us anything..."></textarea>
      </div>

      <!-- ACTIONS -->
      <div class="beta-actions">
        <button class="btn-primary" id="betaSubmit">
          Send Feedback
        </button>
        <button class="btn-secondary" id="betaDismiss">
          Got it
        </button>
      </div>

      <div class="beta-divider"></div>

      <div class="beta-footer">
        This popup will only show once. You can always reach us via the Contact page.
      </div>

    </div>

    <!-- SUCCESS STATE -->
    <div class="beta-success" id="betaSuccess">
      <div class="beta-success-icon">🙏</div>
      <h3>Thank you for your feedback!</h3>
      <p>Your suggestion helps us improve SG Production.<br>We'll take a look and get back to you soon.</p>
      <button class="btn-primary" id="betaSuccessClose" style="margin-top:8px;max-width:160px">
        Close
      </button>
    </div>

  </div>
</div>

<script>
  (function initBetaFeedbackPopup() {
    if (window.sgBetaPopupInit) return;
    window.sgBetaPopupInit = true;

    const backdrop = document.getElementById('betaBackdrop');
    const closeBtn = document.getElementById('betaClose');
    const dismissBtn = document.getElementById('betaDismiss');
    const submitBtn = document.getElementById('betaSubmit');
    const successClose = document.getElementById('betaSuccessClose');
    const defaultState = document.getElementById('betaDefault');
    const successState = document.getElementById('betaSuccess');

    if (!backdrop || !closeBtn || !dismissBtn || !submitBtn || !successClose || !defaultState || !successState) return;

    if (localStorage.getItem('sg_beta_dismissed') === '1') {
      backdrop.style.display = 'none';
      return;
    }

    function closePopup() {
      backdrop.style.opacity = '0';
      backdrop.style.transition = 'opacity .2s ease';
      setTimeout(() => backdrop.style.display = 'none', 200);
      localStorage.setItem('sg_beta_dismissed', '1');
    }

    closeBtn.addEventListener('click', closePopup);
    dismissBtn.addEventListener('click', closePopup);

    submitBtn.addEventListener('click', async function() {
      const text = document.getElementById('betaFeedback')
        .value.trim();

      if (!text) {
        const ta = document.getElementById('betaFeedback');
        ta.focus();
        ta.style.borderColor = 'rgba(255,69,58,0.5)';
        setTimeout(() => {
          ta.style.borderColor = 'rgba(255,255,255,0.1)';
        }, 2000);
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
      submitBtn.style.opacity = '0.7';

      try {
        const response = await fetch(
          'https://formspree.io/f/xkoealzj', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            message: text,
            _subject: 'SG Production Beta Feedback'
          })
        });

        if (response.ok) {
          defaultState.style.display = 'none';
          successState.style.display = 'flex';
          successState.style.animation =
            'pop-in .3s cubic-bezier(0.34,1.56,0.64,1) both';
        } else {
          throw new Error('Failed');
        }
      } catch (error) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Feedback';
        submitBtn.style.opacity = '1';
        alert('Something went wrong. Please try again.');
      }
    });

    successClose.addEventListener('click', closePopup);

    backdrop.addEventListener('click', function(e) {
      if (e.target === backdrop) closePopup();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && backdrop.style.display !== 'none') closePopup();
    });

    setTimeout(function() {
      const dismissed = localStorage.getItem(
        'sg_beta_dismissed'
      );
      if (!dismissed) {
        backdrop.style.display = 'flex';
      }
    }, 1500);
  })();
</script>

  </body>
</html>
