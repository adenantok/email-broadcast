<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Imports\BroadcastRecipientsImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BroadcastController extends Controller
{
    public function index()
    {
        // Ambil data penerima (pagination)
        $recipients = DB::table('broadcast_recipients')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('broadcast', compact('recipients'));
    }

    /**
     * Import file Excel ke database
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        $filePath = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $headerSkipped = false;
        $count = 0;

        foreach ($rows as $row) {
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            $namaPerusahaan = trim($row[0] ?? '');
            $pic = trim($row[1] ?? '');
            $email = trim($row[2] ?? '');

            // Validasi email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Cek apakah email sudah ada
            $existing = DB::table('broadcast_recipients')->where('email', $email)->first();

            if ($existing) {
                // Update data lama (tanpa ubah id)
                DB::table('broadcast_recipients')
                    ->where('email', $email)
                    ->update([
                        'nama_perusahaan' => $namaPerusahaan,
                        'pic' => $pic,
                        'is_subscribed' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert data baru
                DB::table('broadcast_recipients')->insert([
                    'id' => Str::uuid()->toString(),
                    'nama_perusahaan' => $namaPerusahaan,
                    'pic' => $pic,
                    'email' => $email,
                    'is_subscribed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count++;
        }

        return back()->with('success', "$count penerima berhasil diimpor.");
    }

    /**
     * Kirim broadcast email (redirect ke halaman streaming)
     */
    public function send(Request $request)
    {
        return redirect()->route('broadcast.send.stream');
    }

    /**
     * Stream real-time progress pengiriman email
     */
    public function sendStream(Request $request)
    {
        // Set header untuk Server-Sent Events
        return response()->stream(function () {
            // Disable output buffering
            if (ob_get_level()) ob_end_clean();

            $recipients = DB::table('broadcast_recipients')
                ->where('is_subscribed', true)
                ->get();

            $total = $recipients->count();
            $success = 0;
            $failed = 0;

            // Kirim total ke frontend
            echo "data: " . json_encode([
                'type' => 'init',
                'total' => $total
            ]) . "\n\n";
            flush();

            if ($total === 0) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => 'Tidak ada penerima yang aktif'
                ]) . "\n\n";
                flush();
                return;
            }

            $mail = new PHPMailer(true);

            try {
                // Konfigurasi SMTP
                $mail->isSMTP();
                $mail->Host       = 'mail.aliftama.id';
                $mail->SMTPAuth   = true;
                $mail->Username   = env('MAIL_BROADCAST_USER', 'adnan@aliftama.id');
                $mail->Password   = env('MAIL_BROADCAST_PASS', 'Wqsawqwsa1');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPKeepAlive = true;

                $mail->setFrom('adnan@aliftama.id', 'AlifNET Marketing');
                $mail->addReplyTo('aktsa@aliftama.id', 'AlifNET Marketing');
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = 'Broadcast Produk dan Layanan AlifNET';

                foreach ($recipients as $index => $recipient) {
                    $email = trim($recipient->email);

                    if (empty($email)) {
                        $failed++;
                        continue;
                    }

                    // Validasi format email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->logSendResult($recipient->id, 'invalid_format', 'Format email tidak valid');

                        echo "data: " . json_encode([
                            'type' => 'progress',
                            'current' => $index + 1,
                            'total' => $total,
                            'status' => 'error',
                            'email' => $email,
                            'message' => 'Format email tidak valid'
                        ]) . "\n\n";
                        flush();

                        $failed++;
                        continue;
                    }

                    // Validasi domain
                    $domain = substr(strrchr($email, "@"), 1);
                    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                        $this->logSendResult($recipient->id, 'invalid_domain', 'Domain tidak valid');

                        echo "data: " . json_encode([
                            'type' => 'progress',
                            'current' => $index + 1,
                            'total' => $total,
                            'status' => 'error',
                            'email' => $email,
                            'message' => 'Domain tidak valid'
                        ]) . "\n\n";
                        flush();

                        $failed++;
                        continue;
                    }

                    try {
                        $mail->clearAddresses();
                        $mail->clearCustomHeaders();
                        $mail->addAddress($email);

                        $view = view('emails.broadcast', [
                            'email' => $email,
                            'recipient' => $recipient,
                            'unsubscribeLink' => url("/unsubscribe?email=" . urlencode($email))
                        ])->render();

                        $mail->Body = $view;

                        $unsubscribeLink = url("/unsubscribe?email=" . urlencode($email));
                        $mail->addCustomHeader('List-Unsubscribe', "<mailto:adnan@aliftama.id>, <$unsubscribeLink>");

                        $mail->send();

                        // Log ke DB
                        $this->logSendResult($recipient->id, 'success', 'Email terkirim');
                        DB::table('broadcast_recipients')->where('id', $recipient->id)->update([
                            'last_sent_at' => now(),
                            'sent_count' => DB::raw('sent_count + 1')
                        ]);

                        // Kirim progress ke frontend
                        echo "data: " . json_encode([
                            'type' => 'progress',
                            'current' => $index + 1,
                            'total' => $total,
                            'status' => 'success',
                            'email' => $email,
                            'message' => 'Email terkirim'
                        ]) . "\n\n";
                        flush();

                        $success++;
                    } catch (Exception $e) {
                        $this->logSendResult($recipient->id, 'failed', $mail->ErrorInfo);

                        echo "data: " . json_encode([
                            'type' => 'progress',
                            'current' => $index + 1,
                            'total' => $total,
                            'status' => 'error',
                            'email' => $email,
                            'message' => $mail->ErrorInfo
                        ]) . "\n\n";
                        flush();

                        $failed++;
                    }
                }

                $mail->smtpClose();

                // Kirim hasil akhir
                echo "data: " . json_encode([
                    'type' => 'complete',
                    'success' => $success,
                    'failed' => $failed,
                    'total' => $total
                ]) . "\n\n";
                flush();
            } catch (Exception $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => 'Gagal koneksi SMTP: ' . $e->getMessage()
                ]) . "\n\n";
                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Simpan hasil pengiriman ke tabel broadcast_logs
     */
    private function logSendResult(string $recipientId, string $status, string $message)
    {
        DB::table('broadcast_logs')->insert([
            'id' => Str::uuid()->toString(),
            'recipient_id' => $recipientId,
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Halaman log pengiriman
     */
    public function logs()
    {
        $logs = DB::table('broadcast_logs')
            ->join('broadcast_recipients', 'broadcast_recipients.id', '=', 'broadcast_logs.recipient_id')
            ->select('broadcast_logs.*', 'broadcast_recipients.email')
            ->orderBy('broadcast_logs.created_at', 'desc')
            ->paginate(20);

        return view('broadcast_logs', compact('logs'));
    }
}
