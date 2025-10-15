@extends('layout/app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">📬 Log Pengiriman Broadcast</h2>

    @if ($logs->count() > 0)
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th width="5%">No</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Pesan</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $index => $log)
                <tr>
                    <td>{{ $logs->firstItem() + $index }}</td>
                    <td>{{ $log->email }}</td>
                    <td>
                        @if ($log->status === 'success')
                        <span class="badge bg-success">Berhasil</span>
                        @elseif ($log->status === 'invalid')
                        <span class="badge bg-warning text-dark">Email Tidak Valid</span>
                        @elseif ($log->status === 'domain_invalid')
                        <span class="badge bg-danger">Domain Tidak Valid</span>
                        @else
                        <span class="badge bg-secondary">{{ $log->status }}</span>
                        @endif
                    </td>
                    <td>{{ $log->message ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
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
        Belum ada log pengiriman email.
    </div>
    @endif
</div>
@endsection