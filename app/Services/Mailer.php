<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Settings;

/**
 * Envoi de courriels (SMTP via PHPMailer si disponible, sinon mail()).
 * Tout echec est silencieux : les notifications internes restent prioritaires.
 */
class Mailer
{
    public function isEnabled(): bool
    {
        return Settings::bool('email_notifications', (bool) Config::get('mail.enabled', false))
            && (string) (Settings::get('smtp_host') ?: Config::get('mail.host', '')) !== '';
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $host   = (string) (Settings::get('smtp_host') ?: Config::get('mail.host', ''));
        $port   = (int) (Settings::get('smtp_port') ?: Config::get('mail.port', 587));
        $user   = (string) (Settings::get('smtp_user') ?: Config::get('mail.username', ''));
        $pass   = (string) (Settings::get('smtp_pass') ?: Config::get('mail.password', ''));
        $secure = (string) (Settings::get('smtp_secure') ?: Config::get('mail.secure', 'tls'));
        $from   = (string) (Settings::get('smtp_from') ?: Config::get('mail.from', ''));
        $fromNom = (string) (Settings::get('smtp_from_nom') ?: Config::get('mail.from_name', 'Litiges'));

        if ($from === '') {
            $from = $to;
        }

        // --- PHPMailer si installe via Composer ---
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && $host !== '') {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->Port       = $port;
                $mail->SMTPAuth   = $user !== '';
                $mail->Username   = $user;
                $mail->Password   = $pass;
                if ($secure === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($secure === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($from, $fromNom);
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
                $mail->send();
                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        // --- Repli sur la fonction mail() ---
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->encodeHeader($fromNom) . ' <' . $from . '>',
            ];
            return @mail($to, $this->encodeHeader($subject), $htmlBody, implode("\r\n", $headers));
        } catch (\Throwable) {
            return false;
        }
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public function gabarit(string $titre, string $contenuHtml): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"></head>'
            . '<body style="font-family:Arial,sans-serif;background:#f4f6f9;padding:24px;color:#1f2933">'
            . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e3e8ef">'
            . '<div style="background:#12467b;color:#fff;padding:16px 22px;font-size:18px;font-weight:bold">' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="padding:22px;font-size:14px;line-height:1.6">' . $contenuHtml . '</div>'
            . '<div style="padding:14px 22px;background:#f7f9fc;color:#7b8794;font-size:12px">Message automatique - Gestion des litiges</div>'
            . '</div></body></html>';
    }
}
