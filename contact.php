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
    $to = 'support@sgproductionindia.music';
    $mailSubject = '[SG Production Contact] ' . $subject;
    $mailBody = "New message from SG Production contact page\n\n"
      . "Full Name: {$name}\n"
      . "Email Address: {$email}\n"
      . "Subject: {$subject}\n\n"
      . "Message:\n{$message}\n";

    $headers = [
      'From: SG Production Website <support@sgproductionindia.music>',
      'Reply-To: ' . $email,
      'Content-Type: text/plain; charset=UTF-8',
    ];

    if (mail($to, $mailSubject, $mailBody, implode("\r\n", $headers))) {
      $success = 'Message sent successfully. We will get back to you soon.';
      $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    } else {
      $error = 'Something went wrong. Please try again.';
    }
  } else {
    $error = 'Please fix the highlighted fields and try again.';
  }
}

if (file_exists(__DIR__ . '/header.php')) {
  include __DIR__ . '/header.php';
}
?>

<style>
.contact-hero{padding:56px 48px 48px;border-bottom:1px solid #222}
.contact-hero .breadcrumb{font-size:12px;color:#666;margin-bottom:16px;display:flex;align-items:center;gap:6px}
.contact-hero .breadcrumb a{color:#666;text-decoration:none}
.contact-hero .breadcrumb a:hover{color:#aaa}
.contact-hero h1{font-size:32px;font-weight:800;letter-spacing:-.8px;margin:0 0 10px;color:#fff}
.contact-hero p{font-size:14px;color:#aaa;margin:0;max-width:620px;line-height:1.7}
.contact-section{padding:48px 48px;border-bottom:1px solid #222}
.contact-section:last-of-type{border-bottom:none}
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
@media(max-width:700px){.contact-hero{padding:32px 20px}.contact-section{padding:32px 20px}}
</style>

<main class="contact-page">
  <section class="contact-hero">
    <div class="breadcrumb"><a href="/">Home</a><span>/</span><span>Contact</span></div>
    <h1>Contact Us</h1>
    <p>Have a question, licensing inquiry, or DMCA request? We'd love to hear from you.</p>
  </section>

  <section class="contact-section" aria-label="Contact options">
    <div class="contact-cards">
      <article class="contact-card">
        <div class="contact-card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h6"></path></svg>
        </div>
        <div class="contact-card-title">Licensing Inquiry</div>
        <div class="contact-card-text">Want to use our music commercially? Get in touch for pricing and licensing options.</div>
        <a href="mailto:support@sgproductionindia.music?subject=Licensing%20Inquiry" class="contact-card-link">
          Send Inquiry ↗
        </a>
      </article>

      <article class="contact-card">
        <div class="contact-card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        </div>
        <div class="contact-card-title">DMCA &amp; Copyright</div>
        <div class="contact-card-text">Copyright owner with a takedown request? We take all valid requests seriously and respond promptly.</div>
        <a href="mailto:support@sgproductionindia.music?subject=DMCA%20Copyright%20Takedown%20Request" class="contact-card-link">
          Submit Request ↗
        </a>
      </article>

      <article class="contact-card">
        <div class="contact-card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path></svg>
        </div>
        <div class="contact-card-title">General Questions</div>
        <div class="contact-card-text">Any other questions about SG Production, our music, or the website?</div>
        <a href="mailto:support@sgproductionindia.music?subject=General%20Question" class="contact-card-link">
          Get in Touch ↗
        </a>
      </article>
    </div>
  </section>

  <section class="contact-section" id="contact-form" aria-labelledby="contact-form-title">
    <h2 id="contact-form-title">Send Message</h2>

    <?php if ($success): ?>
      <div class="form-success" role="status"><strong><?php echo sg_contact_e($success); ?></strong></div>
    <?php elseif ($error): ?>
      <div class="form-alert" role="alert"><strong><?php echo sg_contact_e($error); ?></strong></div>
    <?php endif; ?>

    <form class="contact-form" method="post" action="/contact" novalidate>
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

  <section class="contact-section" aria-labelledby="other-ways-title">
    <h2 id="other-ways-title">Other Ways to Reach Us</h2>
    <div class="contact-info-list">
      <a class="contact-info-row" href="https://www.youtube.com/@sgproductionindia" target="_blank" rel="noopener">
        <span class="contact-info-left">
          <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-2C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 2A29.94 29.94 0 0 0 1 12a29.94 29.94 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 2C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-2A29.94 29.94 0 0 0 23 12a29.94 29.94 0 0 0-.46-5.58z"></path><path d="m10 15 5-3-5-3z"></path></svg></span>
          <span><span class="contact-info-label">YouTube Channel</span><span class="contact-info-value">youtube.com/@sgproductionindia</span></span>
        </span>
      </a>
      <a class="contact-info-row" href="https://www.instagram.com/sgproduction.music" target="_blank" rel="noopener">
        <span class="contact-info-left">
          <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path></svg></span>
          <span><span class="contact-info-label">Instagram</span><span class="contact-info-value">@sgproduction.music</span></span>
        </span>
      </a>
      <a class="contact-info-row" href="mailto:support@sgproductionindia.music">
        <span class="contact-info-left">
          <span class="contact-info-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path><path d="m22 6-10 7L2 6"></path></svg></span>
          <span><span class="contact-info-label">Email</span><span class="contact-info-value">support@sgproductionindia.music</span></span>
        </span>
      </a>
    </div>
    <div class="contact-notice"><strong>Response time:</strong> We typically respond within 24-48 hours. For urgent DMCA requests, please mention URGENT in your subject line.</div>
  </section>
</main>

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

<script src="page-search.js?v=20260528-page-search" defer></script>

<?php
if (file_exists(__DIR__ . '/footer.php')) {
  include __DIR__ . '/footer.php';
}
?>
