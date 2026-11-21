<?php
/**
 * CASM - email notices
 *
 * Every in-app notification (new assignment, new material, quiz/exam scheduled,
 * meeting, grade, reminder, doubt reply ...) is also sent to the person's email,
 * just like Google Classroom.
 *
 * - Works with any SMTP server (Gmail, Outlook, your school's mail server).
 * - No extra library needed. Settings are in config/config.php.
 * - Emails are collected while the page runs and sent AFTER the page has
 *   responded, over ONE connection, so the lecturer never waits for them.
 * - If email is not configured, nothing is sent and nothing breaks.
 */

/** Is email set up in config.php? */
function mail_enabled(): bool
{
    return defined('MAIL_ENABLED') && MAIL_ENABLED
        && defined('MAIL_HOST') && MAIL_HOST !== ''
        && defined('MAIL_USER') && MAIL_USER !== ''
        && defined('MAIL_PASS') && MAIL_PASS !== '';
}

/** Full web address of this CASM install, e.g. http://localhost/casm */
function mail_app_url(): string
{
    if (defined('APP_URL') && APP_URL !== '') return rtrim(APP_URL, '/');
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host . BASE_URL;
}

/** Called by notify(): queue one email (sent later, in a batch). */
function mail_queue(string $toEmail, string $toName, string $title, string $message, ?string $link): void
{
    static $registered = false;
    if (!mail_enabled()) return;

    // skip fake / reserved addresses (the demo users use @casm.test) so they never bounce
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return;
    if (preg_match('/\.(test|example|invalid|localhost)$/i', $toEmail)) return;

    $GLOBALS['__mail_queue'][] = [
        'email'   => $toEmail,
        'name'    => $toName,
        'subject' => $title,
        'message' => $message,
        'link'    => $link ? mail_app_url() . '/' . ltrim($link, '/') : mail_app_url() . '/',
    ];

    if (!$registered) {
        $registered = true;
        register_shutdown_function('mail_flush');
    }
}

/** Sends everything in the queue (runs automatically when the page ends). */
function mail_flush(): void
{
    $queue = $GLOBALS['__mail_queue'] ?? [];
    $GLOBALS['__mail_queue'] = [];
    if (!$queue || !mail_enabled()) return;

    try {
        ignore_user_abort(true);
        @set_time_limit(180);

        // release the session lock so the next page the user opens is not held up
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

        // let the browser finish loading the page first
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (!headers_sent()) header('Connection: close');
            while (ob_get_level() > 0) @ob_end_flush();
            @flush();
        }

        $messages = [];
        foreach ($queue as $q) $messages[] = ['to' => $q['email'], 'raw' => mail_build($q)];
        $errors = mail_smtp_send($messages);
        foreach ($errors as $err) mail_log($err);
    } catch (Throwable $t) {
        mail_log('Email error: ' . $t->getMessage());
    }
}

/** Errors are written to uploads/email_errors.log (not publicly reachable). */
function mail_log(string $line): void
{
    @file_put_contents(UPLOAD_DIR . '/email_errors.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

/* ------------------------------------------------------------
   Build the email (HTML + plain text)
   ------------------------------------------------------------ */
function mail_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function mail_encode_header(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function mail_build(array $q): string
{
    $app   = defined('APP_NAME') ? APP_NAME : 'CASM';
    $fromE = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : MAIL_USER;
    $fromN = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : $app;
    $first = function_exists('first_name') ? first_name($q['name']) : $q['name'];

    $title = $q['subject'];
    $msg   = $q['message'];
    $link  = $q['link'];

    $text = "Hi $first,\n\n$title\n$msg\n\nOpen in $app: $link\n\n-- \nYou are receiving this because you have an account on $app.";

    $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0">'
        . '<tr><td style="background:#2563eb;padding:18px 24px;color:#ffffff;font-size:20px;font-weight:bold">' . mail_h($app) . '</td></tr>'
        . '<tr><td style="padding:26px 24px">'
        . '<p style="margin:0 0 14px;font-size:15px">Hi ' . mail_h($first) . ',</p>'
        . '<h2 style="margin:0 0 10px;font-size:19px;color:#0f172a">' . mail_h($title) . '</h2>'
        . '<p style="margin:0 0 22px;font-size:15px;line-height:1.55;color:#475569">' . nl2br(mail_h($msg)) . '</p>'
        . '<a href="' . mail_h($link) . '" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:12px;font-size:15px">Open in ' . mail_h($app) . '</a>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px">You are receiving this because you have an account on ' . mail_h($app) . '.</td></tr>'
        . '</table></td></tr></table></body></html>';

    $boundary = 'casm_' . bin2hex(random_bytes(8));
    $domain   = substr(strrchr($fromE, '@') ?: '@localhost', 1);

    $head  = 'Date: ' . date('r') . "\r\n";
    $head .= 'From: ' . mail_encode_header($fromN) . ' <' . $fromE . ">\r\n";
    $head .= 'To: ' . mail_encode_header($q['name']) . ' <' . $q['email'] . ">\r\n";
    $head .= 'Subject: ' . mail_encode_header('[' . $app . '] ' . $title) . "\r\n";
    $head .= 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . ">\r\n";
    $head .= "MIME-Version: 1.0\r\n";
    $head .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";

    $body  = '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text), 76, "\r\n");
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html), 76, "\r\n");
    $body .= '--' . $boundary . "--\r\n";

    return $head . "\r\n" . $body;
}

/* ------------------------------------------------------------
   Tiny SMTP client (one connection for the whole batch)
   ------------------------------------------------------------ */
function mail_smtp_read($fp): array
{
    $code = 0; $text = '';
    while (($line = fgets($fp, 515)) !== false) {
        $text .= $line;
        $code = (int) substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] !== '-') break;   // last line of the reply
    }
    return [$code, trim($text)];
}

function mail_smtp_cmd($fp, string $cmd, array $okCodes): array
{
    fwrite($fp, $cmd . "\r\n");
    [$code, $text] = mail_smtp_read($fp);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP said: ' . $text);
    }
    return [$code, $text];
}

/** @return string[] list of error messages (empty = everything sent) */
function mail_smtp_send(array $messages): array
{
    $errors = [];
    $port   = defined('MAIL_PORT') ? (int) MAIL_PORT : 587;
    $secure = defined('MAIL_SECURE') ? strtolower((string) MAIL_SECURE) : 'tls';
    $verify = defined('MAIL_VERIFY_SSL') ? (bool) MAIL_VERIFY_SSL : false;
    $fromE  = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : MAIL_USER;

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => $verify,
        'verify_peer_name'  => $verify,
        'allow_self_signed' => !$verify,
    ]]);

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return ["Could not connect to $remote ($errstr)"];
    stream_set_timeout($fp, 20);

    try {
        [$c, $t] = mail_smtp_read($fp);
        if ($c !== 220) throw new RuntimeException('SMTP greeting: ' . $t);

        $me = $_SERVER['SERVER_NAME'] ?? 'localhost';
        mail_smtp_cmd($fp, 'EHLO ' . $me, [250]);

        if ($secure === 'tls') {
            mail_smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start TLS encryption');
            }
            mail_smtp_cmd($fp, 'EHLO ' . $me, [250]);
        }

        mail_smtp_cmd($fp, 'AUTH LOGIN', [334]);
        mail_smtp_cmd($fp, base64_encode(MAIL_USER), [334]);
        mail_smtp_cmd($fp, base64_encode(MAIL_PASS), [235]);

        foreach ($messages as $m) {
            try {
                mail_smtp_cmd($fp, 'MAIL FROM:<' . $fromE . '>', [250]);
                mail_smtp_cmd($fp, 'RCPT TO:<' . $m['to'] . '>', [250, 251]);
                mail_smtp_cmd($fp, 'DATA', [354]);
                // dot-stuffing, then end of data
                $raw = preg_replace('/^\./m', '..', $m['raw']);
                fwrite($fp, $raw . "\r\n.\r\n");
                [$c, $t] = mail_smtp_read($fp);
                if ($c !== 250) throw new RuntimeException('SMTP said: ' . $t);
            } catch (Throwable $t) {
                $errors[] = 'Failed to send to ' . $m['to'] . ' - ' . $t->getMessage();
                @fwrite($fp, "RSET\r\n");
                mail_smtp_read($fp);
            }
        }
        @fwrite($fp, "QUIT\r\n");
    } catch (Throwable $t) {
        $errors[] = 'Email error: ' . $t->getMessage();
    }
    @fclose($fp);
    return $errors;
}
