<?php
// REMOVE THIS FILE AFTER TESTING

function smtp_read($sock) {
  $data = '';
  while ($line = fgets($sock, 515)) {
    $data .= $line;
    if (substr($line, 3, 1) === ' ') break;
  }
  return [(int)substr($data, 0, 3), $data];
}

$host = getenv('SMTP_HOST') ?: 'smtpout.secureserver.net';
$port = (int)(getenv('SMTP_PORT') ?: 465);
$username = getenv('SMTP_USERNAME') ?: '';
$password = getenv('SMTP_PASSWORD') ?: '';
$from = getenv('SMTP_FROM') ?: '';
$to = getenv('CONTACT_TO') ?: '';

echo "Testing SMTP Connection\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Username: $username\n";
echo "From: $from\n";
echo "To: $to\n\n";

$context = stream_context_create([
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ]
]);

echo "Connecting to ssl://$host:$port ...\n";

$socket = @stream_socket_client(
  'ssl://' . $host . ':' . $port,
  $errno, $errstr, 30,
  STREAM_CLIENT_CONNECT,
  $context
);

if (!$socket) {
  echo "CONNECTION FAILED: $errstr ($errno)\n";
  exit;
}

echo "Connected!\n";
stream_set_timeout($socket, 30);

[$code, $response] = smtp_read($socket);
echo "Greeting: $code - $response\n";

fwrite($socket, "EHLO sgproduction.music\r\n");
[$code, $response] = smtp_read($socket);
echo "EHLO: $code - $response\n";

fwrite($socket, "AUTH LOGIN\r\n");
[$code, $response] = smtp_read($socket);
echo "AUTH: $code - $response\n";

fwrite($socket, base64_encode($username) . "\r\n");
[$code, $response] = smtp_read($socket);
echo "Username: $code - $response\n";

fwrite($socket, base64_encode($password) . "\r\n");
[$code, $response] = smtp_read($socket);
echo "Password: $code - $response\n";

fwrite($socket, "QUIT\r\n");
fclose($socket);
