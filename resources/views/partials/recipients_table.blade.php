@forelse($recipients as $index => $recipient)
<tr>
    <td>{{ $recipients->firstItem() + $index }}</td>
    <td>{{ $recipient->nama_perusahaan }}</td>
    <td>{{ $recipient->pic }}</td>
    <td>{{ $recipient->email }}</td>
    <td class="text-center">
        @if($recipient->is_subscribed)
        <span class="badge bg-success">✓ Aktif</span>
        @else
        <span class="badge bg-secondary">✗ Tidak Aktif</span>
        @endif
    </td>
    <td>{{ $recipient->last_sent_at ? \Carbon\Carbon::parse($recipient->last_sent_at)->format('d/m/Y H:i') : '-' }}</td>
    <td>{{ $recipient->unsubscribed_at ? \Carbon\Carbon::parse($recipient->unsubscribed_at)->format('d/m/Y H:i') : '-' }}</td>
    <td class="text-center">{{ $recipient->send_count ?? 0 }}</td>
</tr>
@empty
<tr>
    <td colspan="8" class="text-center text-muted">Tidak ada data ditemukan</td>
</tr>
@endforelse