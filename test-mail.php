<?php
if (!isset($_GET['key']) || $_GET['key'] !== 'sgdebug2026') {
  die('Forbidden');
}

header('Content-Type: text/plain');

$host     = getenv('SMTP_HOST') ?: 'smtp.office365.com';
$port     = (int)(getenv('SMTP_PORT') ?: 587);
$username = getenv('SMTP_USERNAME') ?: '';
$password = getenv('SMTP_PASSWORD') ?: '';
$from     = getenv('SMTP_FROM') ?: '';
$to       = getenv('CONTACT_TO') ?: '';

echo "Host: $host\n";
echo "Port: $port\n";
echo "Username: $username\n";

function smtp_read($sock) {
  $data = '';
  while ($line = fgets($sock, 515)) {
    $data .= $line;
    if (isset($line[3]) && $line[3] === ' ') break;
  }
  return [(int)substr($data, 0, 3), trim($data)];
}

// Step 1: Plain TCP connect (no SSL for STARTTLS)
$socket = @fsockopen($host, $port, $errno, $errstr, 30);
if (!$socket) {
  echo "FAILED to connect: $errstr ($errno)\n";
  exit;
}
stream_set_timeout($socket, 30);
echo "Connected!\n";

// Step 2: Read greeting
[$code, $resp] = smtp_read($socket);
echo "Greeting: $code\n";

// Step 3: EHLO
fwrite($socket, "EHLO sgproduction.music\r\n");
[$code, $resp] = smtp_read($socket);
echo "EHLO 1: $code\n";

// Step 4: STARTTLS
fwrite($socket, "STARTTLS\r\n");
[$code, $resp] = smtp_read($socket);
echo "STARTTLS: $code\n";
if ($code !== 220) {
  echo "STARTTLS failed: $resp\n";
  fclose($socket);
  exit;
}

// Step 5: Enable TLS encryption on socket
$result = stream_socket_enable_crypto(
  $socket, true,
  STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
);
echo "TLS: " . ($result ? "OK" : "FAILED") . "\n";

// Step 6: EHLO again after TLS (REQUIRED)
fwrite($socket, "EHLO sgproduction.music\r\n");
[$code, $resp] = smtp_read($socket);
echo "EHLO 2: $code\n";

// Step 7: AUTH LOGIN
fwrite($socket, "AUTH LOGIN\r\n");
[$code, $resp] = smtp_read($socket);
echo "AUTH: $code\n";

// Step 8: Username
fwrite($socket, base64_encode($username) . "\r\n");
[$code, $resp] = smtp_read($socket);
echo "Username: $code\n";

// Step 9: Password
fwrite($socket, base64_encode($password) . "\r\n");
[$code, $resp] = smtp_read($socket);
echo "Password: $code - $resp\n";

if ($code === 235) {
  echo "\nAUTHENTICATION SUCCESS!\n";
  echo "Sending test email...\n";

  fwrite($socket, "MAIL FROM:<$from>\r\n");
  [$code] = smtp_read($socket);
  echo "MAIL FROM: $code\n";

  fwrite($socket, "RCPT TO:<$to>\r\n");
  [$code] = smtp_read($socket);
  echo "RCPT TO: $code\n";

  fwrite($socket, "DATA\r\n");
  [$code] = smtp_read($socket);
  echo "DATA: $code\n";

  $msg = "Date: " . date('r') . "\r\n"
    . "From: <$from>\r\n"
    . "To: <$to>\r\n"
    . "Subject: SG Production Test Email\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "\r\n"
    . "This is a test email from SG Production website.\r\n"
    . "\r\n.\r\n";

  fwrite($socket, $msg);
  [$code] = smtp_read($socket);
  echo "SENT: $code\n";

  if ($code === 250) {
    echo "\nTEST EMAIL SENT SUCCESSFULLY!\n";
    echo "Check your inbox at $to\n";
  }
} else {
  echo "\nAUTHENTICATION FAILED\n";
  echo "Check username and password\n";
}

fwrite($socket, "QUIT\r\n");
fclose($socket);
