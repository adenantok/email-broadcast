<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use App\Models\EmailTemplate; // Tambahkan ini
use Illuminate\Support\Facades\Schema;

class BroadcastController extends Controller
{
    public function index()
    {
        // Ambil data penerima (pagination)
        $recipients = DB::table('broadcast_recipients')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // ✅ TAMBAHAN: Cek apakah tabel email_templates ada
        $templates = [];
        if (Schema::hasTable('email_templates')) {
            $templates = EmailTemplate::where('is_active', true)->get();
        }

        return view('broadcast', compact('recipients', 'templates'));
    }

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

    public function send(Request $request)
    {
        return redirect()->route('broadcast.send.stream');
    }

    public function sendStream(Request $request)
    {
        return response()->stream(function () {
            if (ob_get_level()) ob_end_clean();

            // ✅ TAMBAHAN: Set timeout lebih lama
            set_time_limit(3600); // 1 jam
            ini_set('max_execution_time', 3600);

            $recipients = DB::table('broadcast_recipients')
                ->where('is_subscribed', true)
                ->get();

            $total = $recipients->count();
            $success = 0;
            $failed = 0;

            $template = null;
            $templateId = session('selected_template_id');
            if ($templateId && Schema::hasTable('email_templates')) {
                $template = \App\Models\EmailTemplate::find($templateId);
            }

            echo "data: " . json_encode([
                'type' => 'init',
                'total' => $total,
                'template' => $template ? $template->name : null
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

            // ✅ OPTIMASI: Buat 1 koneksi SMTP untuk semua email
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'mail.aliftama.id';
                $mail->SMTPAuth   = true;
                $mail->Username   = env('MAIL_BROADCAST_USER');
                $mail->Password   = env('MAIL_BROADCAST_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPKeepAlive = true; // ← PENTING: Koneksi tetap hidup

                // ✅ TAMBAHAN: Set SMTP timeout
                $mail->Timeout = 30;
                $mail->SMTPDebug = 0;

                $mail->setFrom('adnan@aliftama.id', 'AlifNET Marketing');
                $mail->addReplyTo('aktsa@aliftama.id', 'AlifNET Marketing');
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $template ? $template->subject : 'Broadcast Produk dan Layanan AlifNET';

                foreach ($recipients as $index => $recipient) {
                    $email = trim($recipient->email);

                    if (empty($email)) {
                        $failed++;
                        continue;
                    }

                    // ✅ OPTIMASI: Cache DNS lookup untuk domain yang sama
                    static $dnsCache = [];

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

                    // ✅ OPTIMASI: DNS lookup dengan cache
                    $domain = substr(strrchr($email, "@"), 1);

                    if (!isset($dnsCache[$domain])) {
                        $dnsCache[$domain] = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
                    }

                    if (!$dnsCache[$domain]) {
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

                        if ($template && file_exists(base_path('resources/views/' . $template->file_path))) {
                            $html = file_get_contents(base_path('resources/views/' . $template->file_path));
                            $html = str_replace('%%EMAIL%%', $email, $html);
                            $mail->Body = $html;
                        } else {
                            $view = view('emails.broadcast', [
                                'email' => $email,
                                'recipient' => $recipient,
                                'unsubscribeLink' => url("/unsubscribe/{$recipient->id}")
                            ])->render();
                            $mail->Body = $view;
                        }

                        $unsubscribeLink = url("/unsubscribe/{$recipient->id}");
                        $mail->addCustomHeader('List-Unsubscribe', "<mailto:adnan@aliftama.id>, <$unsubscribeLink>");

                        $mail->send();

                        $this->logSendResult($recipient->id, 'success', 'Email terkirim');
                        DB::table('broadcast_recipients')->where('id', $recipient->id)->update([
                            'last_sent_at' => now(),
                            'sent_count' => DB::raw('sent_count + 1')
                        ]);

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

                        // ✅ TAMBAHAN: Reconnect setiap 100 email untuk mencegah timeout
                        if (($index + 1) % 100 === 0) {
                            $mail->smtpClose();
                            sleep(2); // Jeda 2 detik
                            // Koneksi akan otomatis dibuka lagi di send() berikutnya
                        }
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

                        // ✅ TAMBAHAN: Reconnect jika koneksi putus
                        if (strpos($mail->ErrorInfo, 'SMTP Error') !== false) {
                            try {
                                $mail->smtpClose();
                                sleep(2);
                            } catch (\Exception $e) {
                                // Ignore
                            }
                        }
                    }
                }

                $mail->smtpClose();

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
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive', // ✅ TAMBAHAN
        ]);
    }

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

    public function logs()
    {
        $logs = DB::table('broadcast_logs')
            ->join('broadcast_recipients', 'broadcast_recipients.id', '=', 'broadcast_logs.recipient_id')
            ->select('broadcast_logs.*', 'broadcast_recipients.email')
            ->orderBy('broadcast_logs.created_at', 'desc')
            ->paginate(20);

        return view('broadcast_logs', compact('logs'));
    }

    // ✅ METHOD BARU (tidak mengganggu yang lama)
    public function setTemplate(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id'
        ]);

        session(['selected_template_id' => $request->template_id]);

        return response()->json(['success' => true]);
    }

    public function preview($id)
    {
        $template = EmailTemplate::findOrFail($id);

        if (file_exists(base_path('resources/views/' . $template->file_path))) {
            $html = file_get_contents(base_path('resources/views/' . $template->file_path));
            $html = str_replace('%%EMAIL%%', 'contoh@email.com', $html);
            return response($html)->header('Content-Type', 'text/html');
        }

        abort(404, 'Template file tidak ditemukan');
    }
}
