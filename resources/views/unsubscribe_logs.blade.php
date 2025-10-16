@extends('layout/app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">🚫 Log Unsubscribe</h2>

    @if ($logs->count() > 0)
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th width="5%">No</th>
                    <th>Email</th>
                    <th>Nama Perusahaan</th>
                    <th>Alasan</th>
                    <th>Waktu Unsubscribe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $index => $log)
                <tr>
                    <td>{{ $logs->firstItem() + $index }}</td>
                    <td>{{ $log->email }}</td>
                    <td>{{ $log->nama_perusahaan ?? '-' }}</td>
                    <td>
                        @if ($log->reason === 'user_clicked_link')
                        <span class="badge bg-primary">Klik Link</span>
                        @elseif ($log->reason === 'manual_admin')
                        <span class="badge bg-warning text-dark">Manual Admin</span>
                        @else
                        <span class="badge bg-secondary">{{ $log->reason ?? '-' }}</span>
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($log->unsubscribed_at)->format('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
    @else
    <div class="alert alert-info text-center">
        Belum ada log unsubscribe.
    </div>
    @endif
</div>
@endsection