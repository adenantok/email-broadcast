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
use App\Models\BroadcastGroup;
use Illuminate\Support\Facades\Validator;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Log;

class BroadcastController extends Controller
{
    public function index(Request $request)
    {
        // Subquery untuk last_sent_at dan sent_count
        $logsSub = DB::table('broadcast_logs')
            ->select(
                'recipient_id',
                DB::raw('MAX(sent_at) as last_sent_at'),
                DB::raw('COUNT(CASE WHEN status = "success" THEN 1 END) as sent_count')
            )
            ->groupBy('recipient_id');

        $query = DB::table('broadcast_recipients')
            ->select(
                'broadcast_recipients.*',
                'broadcast_groups.id as group_id',
                'broadcast_groups.name as group_name',
                'logs.last_sent_at',
                'logs.sent_count'
            )
            ->leftJoin('broadcast_group_recipient', 'broadcast_recipients.id', '=', 'broadcast_group_recipient.recipient_id')
            ->leftJoin('broadcast_groups', 'broadcast_group_recipient.group_id', '=', 'broadcast_groups.id')
            ->leftJoinSub($logsSub, 'logs', function ($join) {
                $join->on('broadcast_recipients.id', '=', 'logs.recipient_id');
            });

        // 🔍 Filter berdasarkan grup
        if ($groupId = $request->get('group')) {
            $query->where('broadcast_group_recipient.group_id', $groupId);
        }

        // 🔍 Filter pencarian
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_perusahaan', 'like', "%$search%")
                    ->orWhere('pic', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        $recipients = $query->orderBy('nama_perusahaan', 'asc')
            ->paginate(10)
            ->appends($request->only(['search', 'group']));

        // ✅ Template email aktif
        $templates = Schema::hasTable('email_templates')
            ? EmailTemplate::where('is_active', true)->get()
            : [];

        // ✅ Data grup
        $groups = BroadcastGroup::withCount('recipients')->orderBy('name')->get();

        return Inertia::render('Broadcast/Index', [
            'recipients' => $recipients,
            'templates' => $templates,
            'groups' => $groups,
            'filters' => [
                'search' => $request->get('search', ''),
                'group' => $request->get('group', ''),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Batch update status recipients
     */
    private function batchUpdateRecipients(array $ids, string $status)
    {
        if (empty($ids)) {
            return;
        }

        try {
            DB::table('broadcast_recipients')
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_at' => now()
                ]);
        } catch (\Exception $e) {
            Log::error("Error batch update recipients: " . $e->getMessage());
        }
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $groupId = $request->input('group_id'); // ambil group_id dari request

        $filePath = $request->file('file')->getRealPath();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
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
                // Update data lama
                DB::table('broadcast_recipients')
                    ->where('email', $email)
                    ->update([
                        'nama_perusahaan' => $namaPerusahaan,
                        'pic' => $pic,
                        'is_subscribed' => true,
                        'updated_at' => now(),
                    ]);

                $recipientId = $existing->id;
            } else {
                // Insert data baru
                $recipientId = Str::uuid()->toString();

                DB::table('broadcast_recipients')->insert([
                    'id' => $recipientId,
                    'nama_perusahaan' => $namaPerusahaan,
                    'pic' => $pic,
                    'email' => $email,
                    'is_subscribed' => true,
                    'status' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Jika group dipilih → tautkan ke tabel pivot
            if ($groupId) {
                DB::table('broadcast_group_recipient')->updateOrInsert(
                    [
                        'group_id' => $groupId,
                        'recipient_id' => $recipientId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            $count++;
        }

        return back()->with('success', "$count penerima berhasil diimpor" . ($groupId ? " dan ditambahkan ke grup." : "."));
    }

    public function send(Request $request)
    {
        return redirect()->route('broadcast.send.stream');
    }

    public function sendStream(Request $request)
    {
        return response()->stream(function () use ($request) {
            if (ob_get_level())
                ob_end_clean();

            set_time_limit(3600);
            ini_set('max_execution_time', 3600);

            // ✅ Ambil group_id dari query parameter (ignore timestamp param)
            $groupId = $request->query('group');
            $groupName = null;

            // ✅ Query recipients dengan filter group
            $query = DB::table('broadcast_recipients')
                ->where('broadcast_recipients.is_subscribed', true);

            // ✅ JOIN jika ada group yang dipilih
            if ($groupId) {
                $query->join('broadcast_group_recipient', 'broadcast_recipients.id', '=', 'broadcast_group_recipient.recipient_id')
                    ->where('broadcast_group_recipient.group_id', $groupId)
                    ->select('broadcast_recipients.*'); // Pastikan hanya select kolom dari broadcast_recipients

                // Ambil nama group untuk ditampilkan di log
                $group = DB::table('broadcast_groups')->where('id', $groupId)->first();
                $groupName = $group ? $group->name : null;
            }

            $recipients = $query->get();

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

            // ✅ Kirim info awal termasuk nama group
            echo "data: " . json_encode([
                'type' => 'init',
                'total' => $total,
                'template' => $template ? $template->name : null,
                'group' => $groupName // Tambahkan info group
            ]) . "\n\n";
            flush();

            if ($total === 0) {
                $message = $groupId
                    ? "Tidak ada penerima aktif di grup \"$groupName\""
                    : 'Tidak ada penerima yang aktif';

                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $message
                ]) . "\n\n";
                flush();
                return;
            }

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'mail.aliftama.id';
                $mail->SMTPAuth = true;
                $mail->Username = env('MAIL_BROADCAST_USER');
                $mail->Password = env('MAIL_BROADCAST_PASS');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
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

                    // ✅ Kirim keepalive setiap 10 email untuk prevent timeout
                    if (($index + 1) % 10 === 0) {
                        echo ": keepalive " . date('H:i:s') . "\n\n";
                        flush();
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

                // ✅ LOG: Debug untuk memastikan sampai sini
                Log::info("Broadcast selesai. Success: $success, Failed: $failed");

                // ✅ CRITICAL: Multiple flush untuk memastikan data terkirim
                echo "data: " . json_encode([
                    'type' => 'complete',
                    'success' => $success,
                    'failed' => $failed,
                    'total' => $total
                ]) . "\n\n";

                Log::info("Event complete dikirim");

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                Log::info("Flush pertama selesai");

                // ✅ Kirim heartbeat untuk memaksa buffer flush
                echo ": heartbeat\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                Log::info("Heartbeat dikirim");

                // ✅ Delay lebih lama untuk koneksi cepat
                usleep(1000000); // 1 detik

                Log::info("Selesai usleep, stream akan berakhir");
            } catch (Exception $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => 'Gagal koneksi SMTP: ' . $e->getMessage()
                ]) . "\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // ✅ Delay untuk event error juga
                usleep(1000000); // 1 detik
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
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

    // Store new group
    public function addGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:broadcast_groups,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Gagal membuat grup: ' . $validator->errors()->first());
        }

        try {
            $group = BroadcastGroup::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return redirect()->back()->with('success', "Grup '{$group->name}' berhasil dibuat!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat grup: ' . $e->getMessage());
        }
    }

    // Update group
    public function updateGroup(Request $request, $id)
    {
        $group = BroadcastGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:broadcast_groups,name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Gagal update grup: ' . $validator->errors()->first());
        }

        try {
            $group->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return redirect()->back()->with('success', "Grup '{$group->name}' berhasil diupdate!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal update grup: ' . $e->getMessage());
        }
    }

    // Delete group
    public function deleteGroup($id)
    {
        try {
            $group = BroadcastGroup::findOrFail($id);
            $name = $group->name;

            // Pivot table will auto-delete due to cascade
            $group->delete();

            return redirect()->back()->with('success', "Grup '{$name}' berhasil dihapus!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus grup: ' . $e->getMessage());
        }
    }

    // Store new recipient manually
    public function addRecipient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_perusahaan' => 'required|string|max:255',
            'pic' => 'nullable|string|max:255',
            'email' => 'required|email|unique:broadcast_recipients,email',
            'group_id' => 'nullable|exists:broadcast_groups,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Gagal menambah penerima: ' . $validator->errors()->first());
        }

        try {
            $recipient = BroadcastRecipient::create([
                'nama_perusahaan' => $request->nama_perusahaan,
                'pic' => $request->pic,
                'email' => $request->email,
                'status' => 'active',
            ]);

            // Attach to group if specified
            if ($request->group_id) {
                $recipient->groups()->syncWithoutDetaching([$request->group_id]);
            }

            return redirect()->back()->with('success', "Penerima '{$recipient->email}' berhasil ditambahkan!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menambah penerima: ' . $e->getMessage());
        }
    }
}
