@extends('layout/app')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">📢 Broadcast Email Manager</h2>

    {{-- Notifikasi hasil --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Real-time Progress Modal --}}
    <div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">📧 Mengirim Broadcast Email...</h5>
                </div>
                <div class="modal-body">
                    <!-- Progress Bar -->
                    <div class="mb-3">
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                role="progressbar" style="width: 0%">
                                <span id="progressText">0%</span>
                            </div>
                        </div>
                        <p class="text-center mt-2 mb-0">
                            <span id="progressCount">0</span> / <span id="progressTotal">0</span> email
                        </p>
                    </div>

                    <!-- Log Container -->
                    <div id="logContainer" class="border rounded p-3 bg-light" style="height: 400px; overflow-y: auto; font-family: monospace; font-size: 14px;">
                        <div id="logs"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="closeBtn" disabled>Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Upload Excel --}}
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">📂 Import Daftar Email</div>
        <div class="card-body">
            <form action="{{ route('broadcast.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">Pilih file Excel (.xlsx)</label>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                    <small class="text-muted">Kolom urutan: Nama Perusahaan | PIC | Email</small>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-upload"></i> Upload & Import
                </button>
            </form>
        </div>
    </div>

    {{-- Daftar Penerima --}}
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <span>📋 Daftar Penerima</span>
            <span class="badge bg-light text-dark">Total: {{ $recipients->total() }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Nama Perusahaan</th>
                            <th>PIC</th>
                            <th>Email</th>
                            <th style="width: 100px;" class="text-center">Subscribed</th>
                            <th style="width: 150px;">Terakhir Dikirim</th>
                            <th style="width: 100px;" class="text-center">Jumlah Kirim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recipients as $index => $r)
                        <tr>
                            <td>{{ $recipients->firstItem() + $index }}</td>
                            <td>{{ $r->nama_perusahaan ?? '-' }}</td>
                            <td>{{ $r->pic ?? '-' }}</td>
                            <td><small>{{ $r->email }}</small></td>
                            <td class="text-center">
                                @if ($r->is_subscribed)
                                <span class="badge bg-success">Ya</span>
                                @else
                                <span class="badge bg-danger">Tidak</span>
                                @endif
                            </td>
                            <td><small>{{ $r->last_sent_at ? \Carbon\Carbon::parse($r->last_sent_at)->diffForHumans() : '-' }}</small></td>
                            <td class="text-center">
                                <span class="badge bg-info">{{ $r->sent_count ?? 0 }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mb-0 mt-2">Belum ada data penerima.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination dengan info --}}
            @if($recipients->hasPages())
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    Menampilkan {{ $recipients->firstItem() }} - {{ $recipients->lastItem() }} dari {{ $recipients->total() }} data
                </div>
                <div>
                    {{ $recipients->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Tombol Kirim Broadcast --}}
    <div class="text-center mb-4">
        <button type="button" class="btn btn-lg btn-danger px-5" id="sendBroadcastBtn">
            <i class="bi bi-send-fill"></i> Kirim Broadcast Sekarang
        </button>
    </div>

    {{-- Tombol menuju log --}}
    <div class="text-center mb-5">
        <a href="{{ route('broadcast.logs') }}" class="btn btn-outline-secondary">
            <i class="bi bi-file-text"></i> Lihat Log Pengiriman
        </a>
    </div>

</div>

<script>
    document.getElementById('sendBroadcastBtn').addEventListener('click', function() {
        if (!confirm('Kirim broadcast ke semua penerima aktif?')) {
            return;
        }

        // Tampilkan modal
        const modal = new bootstrap.Modal(document.getElementById('progressModal'));
        modal.show();

        // Reset progress
        document.getElementById('progressBar').style.width = '0%';
        document.getElementById('progressText').textContent = '0%';
        document.getElementById('progressCount').textContent = '0';
        document.getElementById('progressTotal').textContent = '0';
        document.getElementById('logs').innerHTML = '';
        document.getElementById('closeBtn').disabled = true;

        // Buat EventSource untuk SSE
        const eventSource = new EventSource('{{ route("broadcast.send.stream") }}');

        eventSource.addEventListener('message', function(e) {
            const data = JSON.parse(e.data);
            const logsDiv = document.getElementById('logs');
            const logContainer = document.getElementById('logContainer');

            if (data.type === 'init') {
                document.getElementById('progressTotal').textContent = data.total;
                addLog('📊 Total penerima: ' + data.total, 'text-info');
            } else if (data.type === 'progress') {
                const percentage = Math.round((data.current / data.total) * 100);
                document.getElementById('progressBar').style.width = percentage + '%';
                document.getElementById('progressText').textContent = percentage + '%';
                document.getElementById('progressCount').textContent = data.current;

                let icon = '';
                let colorClass = '';

                if (data.status === 'success') {
                    icon = '✅';
                    colorClass = 'text-success';
                } else {
                    icon = '⚠️';
                    colorClass = 'text-danger';
                }

                addLog(`${icon} ${data.email} - ${data.message}`, colorClass);
            } else if (data.type === 'complete') {
                document.getElementById('progressBar').classList.remove('progress-bar-animated');
                document.getElementById('progressBar').classList.add('bg-success');
                addLog(`\n🎉 Selesai! ${data.success} berhasil, ${data.failed} gagal dari ${data.total} email`, 'text-primary fw-bold');
                document.getElementById('closeBtn').disabled = false;
                eventSource.close();
            } else if (data.type === 'error') {
                addLog('❌ Error: ' + data.message, 'text-danger fw-bold');
                document.getElementById('closeBtn').disabled = false;
                eventSource.close();
            }

            // Auto scroll ke bawah
            logContainer.scrollTop = logContainer.scrollHeight;
        });

        eventSource.addEventListener('error', function(e) {
            addLog('❌ Koneksi terputus', 'text-danger fw-bold');
            document.getElementById('closeBtn').disabled = false;
            eventSource.close();
        });

        function addLog(message, className = '') {
            const logsDiv = document.getElementById('logs');
            const logEntry = document.createElement('div');
            logEntry.className = className;
            logEntry.textContent = message;
            logsDiv.appendChild(logEntry);
        }

        // Event tombol tutup
        document.getElementById('closeBtn').addEventListener('click', function() {
            location.reload(); // Refresh halaman untuk update tabel
        });
    });
</script>
@endsection