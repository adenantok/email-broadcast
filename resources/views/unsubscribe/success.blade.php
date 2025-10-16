@extends('unsubscribe/app')

@section('content')
<div class="text-center mt-5">
    <h3>Anda Telah Berhenti Berlangganan</h3>
    <p>{{ $recipient->email }} tidak akan lagi menerima email broadcast.</p>
</div>
@endsection