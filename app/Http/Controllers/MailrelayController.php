<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailRelayController extends Controller
{
    /**
     * Terima POST dari Google Apps Script dan kirim email via SMTP
     * Endpoint: POST /api/mail-relay
     * (Dipakai aplikasi lain — jangan diubah.)
     */
    public function send(Request $request)
    {
        // ── Validasi secret key ──
        $secret = $request->header('X-Relay-Secret');
        if ($secret !== env('GAS_RELAY_SECRET')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        // ── Validasi input ──
        $to      = trim($request->input('to', ''));
        $toName  = trim($request->input('to_name', ''));
        $subject = trim($request->input('subject', ''));
        $html    = trim($request->input('html', ''));

        if (!$to || !$subject || !$html) {
            return response()->json(['ok' => false, 'message' => 'Parameter to, subject, html wajib diisi.'], 422);
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'Format email tidak valid.'], 422);
        }

        // ── Kirim via PHPMailer ──
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'mail.aliftama.id';
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_BROADCAST_USER');
            $mail->Password   = env('MAIL_BROADCAST_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 30;
            $mail->SMTPDebug  = 0;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(env('MAIL_BROADCAST_USER'), 'AlifNET');
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;

            $mail->send();

            return response()->json(['ok' => true, 'message' => 'Email berhasil dikirim.']);

        } catch (Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Gagal kirim email: ' . $mail->ErrorInfo], 500);
        }
    }

    /**
     * Terima POST dari Google Apps Script (Aplikasi Email Marketing - AlifNET)
     * dan kirim email broadcast via SMTP.
     * Endpoint: POST /api/mail-relay/marketing
     *
     * Body JSON yang diterima dari GAS:
     *   to         (wajib)  - alamat email tujuan
     *   to_name    (opsional)
     *   subject    (wajib)
     *   html       (wajib)  - isi email berupa HTML
     *   cc         (opsional) - satu alamat email, atau beberapa dipisah koma
     *   from_name  (opsional) - nama pengirim, default "AlifNET Marketing"
     *
     * Header wajib: X-Relay-Secret harus sama dengan env MARKETING_RELAY_SECRET.
     * Secret ini SENGAJA dibuat terpisah dari GAS_RELAY_SECRET supaya
     * aplikasi ini tidak berbagi kredensial dengan aplikasi lain yang
     * memakai endpoint /mail-relay.
     */
    public function sendMarketing(Request $request)
    {
        // ── Validasi secret key ──
        $secret = $request->header('X-Relay-Secret');
        if ($secret !== env('MARKETING_RELAY_SECRET')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        // ── Validasi input ──
        $to       = trim($request->input('to', ''));
        $toName   = trim($request->input('to_name', ''));
        $subject  = trim($request->input('subject', ''));
        $html     = trim($request->input('html', ''));
        $ccRaw    = trim($request->input('cc', ''));
        $fromName = trim($request->input('from_name', '')) ?: 'AlifNET Marketing';

        if (!$to || !$subject || !$html) {
            return response()->json(['ok' => false, 'message' => 'Parameter to, subject, html wajib diisi.'], 422);
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'Format email tidak valid.'], 422);
        }

        // cc boleh kosong, satu email, atau beberapa dipisah koma.
        $ccList = [];
        if ($ccRaw !== '') {
            foreach (explode(',', $ccRaw) as $cc) {
                $cc = trim($cc);
                if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $ccList[] = $cc;
                }
            }
        }

        // ── Kirim via PHPMailer ──
        // NB: memakai akun SMTP yang sama dengan endpoint /mail-relay
        // (MAIL_BROADCAST_USER / MAIL_BROADCAST_PASS). Kalau nanti mau
        // pakai akun SMTP terpisah khusus marketing, tinggal ganti ke
        // env MAIL_MARKETING_USER / MAIL_MARKETING_PASS.
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'mail.aliftama.id';
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_BROADCAST_USER');
            $mail->Password   = env('MAIL_BROADCAST_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 30;
            $mail->SMTPDebug  = 0;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(env('MAIL_BROADCAST_USER'), $fromName);
            $mail->addAddress($to, $toName);
            foreach ($ccList as $cc) {
                $mail->addCC($cc);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;

            $mail->send();

            return response()->json(['ok' => true, 'message' => 'Email berhasil dikirim.']);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Gagal kirim email: ' . $e->getMessage()], 500);
        }
    }
}