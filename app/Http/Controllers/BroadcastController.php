<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
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
    public function index(Request $request)
    {
        $query = DB::table('broadcast_recipients');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%$search%")
                    ->orWhere('pic', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $recipients = $query->orderBy('nama_perusahaan', 'asc')
            ->paginate(10)->appends($request->only('search'));

        $templates = Schema::hasTable('email_templates')
            ? EmailTemplate::where('is_active', true)->get()
            : [];

        return Inertia::render('Broadcast/Index', [
            'recipients' => $recipients,
            'templates' => $templates,
            'filters' => [
                'search' => $request->get('search', ''),
            ],
            // ✅ TAMBAHAN: Flash message
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
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
                    'status' => '',
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

            set_time_limit(3600);
            ini_set('max_execution_time', 3600);

            $recipients = DB::table('broadcast_recipients')
                ->where('is_subscribed', true)
                ->get();

            $total = $recipients->count();
            $success = 0;
            $failed = 0;

            // ✅ Batch updates - simpan ID untuk update nanti
            $successIds = [];
            $failedIds = [];
            $batchSize = 50; // Update setiap 50 email

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

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'mail.aliftama.id';
                $mail->SMTPAuth   = true;
                $mail->Username   = env('MAIL_BROADCAST_USER');
                $mail->Password   = env('MAIL_BROADCAST_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPKeepAlive = true;

                $mail->Timeout = 30;
                $mail->SMTPDebug = 0;

                // ✅ FIX: Email pengirim dan nama yang muncul di inbox
                $mail->setFrom('sales@aliftama.id', 'Hayyi Birrulwalidaini Ihsan');

                // ✅ FIX: Reply-To ke email Adnan
                // $mail->addReplyTo('hayyi@aliftama.id', 'Hayyi Birrulwalidaini Ihsan');

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $template ? $template->subject : 'Broadcast Produk dan Layanan AlifNET';


                foreach ($recipients as $index => $recipient) {
                    $email = trim($recipient->email);

                    if (empty($email)) {
                        $failed++;
                        $failedIds[] = $recipient->id;
                        continue;
                    }

                    static $dnsCache = [];

                    // Validasi format email
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $this->logSendResult($recipient->id, 'invalid_format', 'Format email tidak valid');
                        $failedIds[] = $recipient->id;

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

                        // ✅ Batch update setiap $batchSize
                        if (count($failedIds) >= $batchSize) {
                            $this->batchUpdateRecipients($failedIds, 'failed');
                            $failedIds = []; // Reset
                        }

                        continue;
                    }

                    // Validasi domain
                    $domain = substr(strrchr($email, "@"), 1);

                    if (!isset($dnsCache[$domain])) {
                        $dnsCache[$domain] = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
                    }

                    if (!$dnsCache[$domain]) {
                        $this->logSendResult($recipient->id, 'invalid_domain', 'Domain tidak valid');
                        $failedIds[] = $recipient->id;

                        echo "data: " . json_encode([
                            'type' => 'progress',
                            'current' => $index + 1,
                            'total' => $total,
                            'status' => 'error',
                            'message' => 'Domain tidak valid',
                            'email' => $email,
                        ]) . "\n\n";
                        flush();

                        $failed++;

                        if (count($failedIds) >= $batchSize) {
                            $this->batchUpdateRecipients($failedIds, 'failed');
                            $failedIds = [];
                        }

                        continue;
                    }

                    try {
                        $mail->clearAddresses();
                        $mail->clearCustomHeaders();
                        $mail->addAddress($email);

                        if ($template && file_exists(base_path('resources/views/' . $template->file_path))) {
                            $mail->Body = view(str_replace(['/', '.blade.php'], ['.', ''], $template->file_path), [
                                'email' => $email,
                                'recipient' => $recipient,
                                'unsubscribeLink' => url("/unsubscribe/{$recipient->id}")
                            ])->render();
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

                        // ✅ Simpan ID untuk batch update
                        $successIds[] = $recipient->id;
                        $this->logSendResult($recipient->id, 'success', 'Email terkirim');

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

                        // ✅ Batch update recipients setiap $batchSize email berhasil
                        if (count($successIds) >= $batchSize) {
                            $this->batchUpdateRecipients($successIds, 'sent');
                            $successIds = []; // Reset array
                        }

                        // Reconnect setiap 100 email
                        if (($index + 1) % 100 === 0) {
                            $mail->smtpClose();
                            sleep(2);
                        }
                    } catch (Exception $e) {
                        $this->logSendResult($recipient->id, 'failed', $mail->ErrorInfo);
                        $failedIds[] = $recipient->id;

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

                        if (count($failedIds) >= $batchSize) {
                            $this->batchUpdateRecipients($failedIds, 'failed');
                            $failedIds = [];
                        }

                        // Reconnect jika koneksi putus
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

                // ✅ Update sisa recipients yang belum ter-update (sisa batch)
                if (count($successIds) > 0) {
                    $this->batchUpdateRecipients($successIds, 'sent');
                }
                if (count($failedIds) > 0) {
                    $this->batchUpdateRecipients($failedIds, 'failed');
                }

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
            'Connection' => 'keep-alive',
        ]);
    }

    private function batchUpdateRecipients(array $recipientIds, string $status)
    {
        if (empty($recipientIds)) {
            return;
        }

        try {
            $updateData = [
                'status' => $status,
                'updated_at' => now(),
            ];

            // Tambahkan last_sent_at dan increment sent_count hanya untuk status 'sent'
            if ($status === 'sent') {
                $updateData['last_sent_at'] = now();

                // ✅ Bulk update dengan 1 query saja
                DB::table('broadcast_recipients')
                    ->whereIn('id', $recipientIds)
                    ->update([
                        'status' => $status,
                        'last_sent_at' => now(),
                        'sent_count' => DB::raw('sent_count + 1'),
                        'updated_at' => now(),
                    ]);
            } else {
                // Untuk status failed/invalid, update tanpa increment
                DB::table('broadcast_recipients')
                    ->whereIn('id', $recipientIds)
                    ->update($updateData);
            }

            Logger()->info("Batch updated {count} recipients with status: {status}", [
                'count' => count($recipientIds),
                'status' => $status
            ]);
        } catch (\Exception $e) {
            Logger()->error('Batch update recipients failed: ' . $e->getMessage());
        }
    }

    private function logSendResult($recipientId, $status, $message = null)
    {
        try {
            DB::table('broadcast_logs')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'recipient_id' => $recipientId,
                'status' => $status,
                'message' => $message,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Logger()->error('Failed to log broadcast result: ' . $e->getMessage());
        }
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

    public function updateRecipient(Request $request, $id)
    {
        $request->validate([
            'nama_perusahaan' => 'required|string|max:255',
            'pic' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:broadcast_recipients,email,' . $id . ',id',
        ]);

        $recipient = DB::table('broadcast_recipients')->where('id', $id)->first();

        if (!$recipient) {
            return back()->with('error', 'Data tidak ditemukan');
        }

        // Cek apakah email berubah
        $emailChanged = $recipient->email !== $request->email;

        // Data yang akan diupdate
        $updateData = [
            'nama_perusahaan' => $request->nama_perusahaan,
            'pic' => $request->pic,
            'email' => $request->email,
            'updated_at' => now(),
        ];

        // Jika email berubah, set status jadi 'updated'
        if ($emailChanged) {
            $updateData['status'] = 'updated';
        }

        DB::table('broadcast_recipients')
            ->where('id', $id)
            ->update($updateData);

        return back()->with('success', 'Data berhasil diupdate' . ($emailChanged ? ' (Status berubah menjadi Updated)' : ''));
    }

    // BONUS: Method untuk delete recipient
    public function deleteRecipient($id)
    {
        $deleted = DB::table('broadcast_recipients')->where('id', $id)->delete();

        if ($deleted) {
            return back()->with('success', 'Data berhasil dihapus');
        }

        return back()->with('error', 'Data tidak ditemukan');
    }
}
