@extends('unsubscribe/app')

@section('content')
<div class="text-center mt-5">
    <h3>Berhenti Berlangganan</h3>
    <p>Apakah Anda yakin ingin berhenti menerima email dari kami?</p>
    <form action="{{ route('unsubscribe.confirm', $recipient->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-danger">Ya, Unsubscribe</button>
    </form>
</div>
@endsection