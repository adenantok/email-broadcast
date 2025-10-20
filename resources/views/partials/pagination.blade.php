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