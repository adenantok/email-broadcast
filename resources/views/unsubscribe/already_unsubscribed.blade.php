@extends('unsubscribe/app')

@section('content')
<div class="text-center mt-5">
    <h3>Sudah Tidak Berlangganan</h3>
    <p>{{ $recipient->email }} sebelumnya sudah berhenti menerima email.</p>
</div>
@endsection