<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function enviarEmail(string $destino, string $assunto, string $corpoHtml): bool {
    $cfg = require __DIR__ . '/../config/email.php';

    $mail = new PHPMailer(true);
    try {
        // ---------- SMTP ----------
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['usuario'];
        $mail->Password   = $cfg['senha'];
        $mail->Port       = $cfg['port'];

        // 🔐 Tipo de criptografia
        if (($cfg['seguranca'] ?? 'tls') === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // ---------- Debug (opcional) ----------
        if (!empty($cfg['debug'])) {
            $mail->SMTPDebug   = $cfg['debug'];
            $mail->Debugoutput = fn($str, $level) => error_log("[SMTP] $str");
        }

        // ---------- Charset ----------
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // ---------- Remetente / destinatário ----------
        $mail->setFrom($cfg['from'], $cfg['nome']);
        $mail->addReplyTo($cfg['reply'], $cfg['nome']);
        $mail->addAddress($destino);

        // ---------- Conteúdo ----------
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHtml;
        $mail->AltBody = strip_tags($corpoHtml);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('[MAILER] Falha: ' . ($mail->ErrorInfo ?? $e->getMessage()));
        return false;
    }
}