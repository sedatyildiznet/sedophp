<?php

declare(strict_types=1);

$capture = $argv[1] ?? sys_get_temp_dir() . '/sedophp-smtp.eml';
$server = stream_socket_server('tcp://127.0.0.1:2525', $errorNumber, $errorMessage);
if (!is_resource($server)) {
    fwrite(STDERR, "SMTP test server failed: {$errorMessage} ({$errorNumber})\n");
    exit(1);
}
$client = stream_socket_accept($server, 20);
if (!is_resource($client)) { exit(1); }
fwrite($client, "220 localhost SedoPHP test SMTP\r\n");
$data = false;
$message = '';
while (($line = fgets($client)) !== false) {
    $command = rtrim($line, "\r\n");
    if ($data) {
        if ($command === '.') {
            file_put_contents($capture, $message);
            fwrite($client, "250 queued\r\n");
            $data = false;
        } else {
            $message .= $line;
        }
        continue;
    }
    if (str_starts_with($command, 'EHLO ')) { fwrite($client, "250 localhost\r\n"); continue; }
    if (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) { fwrite($client, "250 ok\r\n"); continue; }
    if ($command === 'DATA') { fwrite($client, "354 end with dot\r\n"); $data = true; continue; }
    if ($command === 'QUIT') { fwrite($client, "221 bye\r\n"); break; }
    fwrite($client, "500 unsupported\r\n");
}
fclose($client);
fclose($server);
