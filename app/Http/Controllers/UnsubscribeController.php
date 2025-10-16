<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRecipient;
use App\Models\BroadcastUnsubscribeLog;
use Illuminate\Http\Request;

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
}
