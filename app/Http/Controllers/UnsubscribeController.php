<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRecipient;
use App\Models\BroadcastUnsubscribeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class UnsubscribeController extends Controller
{
    public function show($id)
    {
        $recipient = BroadcastRecipient::findOrFail($id);

        if (!$recipient->is_subscribed) {
            return view('unsubscribe.already_unsubscribed', compact('recipient'));
        }

        return view('unsubscribe.confirm', compact('recipient'));
    }

    public function confirm(Request $request, $id)
    {
        $recipient = BroadcastRecipient::findOrFail($id);

        if (!$recipient->is_subscribed) {
            return redirect()->route('unsubscribe.show', $id)
                ->with('info', 'Anda sudah tidak berlangganan.');
        }

        // Update status penerima
        $recipient->update([
            'is_subscribed' => false,
            'unsubscribed_at' => now(),
        ]);

        // Simpan log
        BroadcastUnsubscribeLog::create([
            'recipient_id' => $recipient->id,
            'reason' => 'user_clicked_link',
            'unsubscribed_at' => now(),
        ]);

        return view('unsubscribe.success', compact('recipient'));
    }
    public function unsubscribe_logs()
    {
        $logs = DB::table('broadcast_unsubscribe_logs')
            ->join('broadcast_recipients', 'broadcast_unsubscribe_logs.recipient_id', '=', 'broadcast_recipients.id')
            ->select(
                'broadcast_recipients.email',
                'broadcast_recipients.nama_perusahaan',
                'broadcast_unsubscribe_logs.reason',
                'broadcast_unsubscribe_logs.unsubscribed_at'
            )
            ->orderBy('broadcast_unsubscribe_logs.unsubscribed_at', 'desc')
            ->paginate(10);

        return view('unsubscribe_logs', compact('logs'));
    }
}
