<?php
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;

/** PHPMailer pre-configured from config.php. */
function mailer(): PHPMailer
{
    // Manual load (no Composer): the classes are loaded lazily,
    // only when we actually send mail.
    require_once __DIR__ . '/../mailer/Exception.php';
    require_once __DIR__ . '/../mailer/PHPMailer.php';
    require_once __DIR__ . '/../mailer/SMTP.php';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    return $mail;
}

function send_verification_email(string $email, string $token): void
{
    $link = BASE_URL . '/api/verify.php?token=' . $token;

    $mail = mailer();
    $mail->addAddress($email);
    $mail->Subject = 'Confirmă-ți contul';
    $mail->Body    = "Salut,\n\n"
        . "Activează-ți contul deschizând link-ul de mai jos (valabil 24h):\n\n$link\n\n"
        . "Dacă nu tu ai creat contul, ignoră acest email.";
    $mail->send();
}

function send_reset_email(string $email, string $token): void
{
    // The frontend page which then POSTs to /api/reset-password.php
    $link = APP_URL . '/reset-password.html?token=' . $token;

    $mail = mailer();
    $mail->addAddress($email);
    $mail->Subject = 'Resetează-ți parola';
    $mail->Body    = "Salut,\n\n"
        . "Resetează-ți parola deschizând link-ul de mai jos (valabil 1 oră):\n\n$link\n\n"
        . "Dacă nu tu ai cerut resetarea, ignoră acest email.";
    $mail->send();
}