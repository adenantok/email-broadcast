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
}