@extends('emails.layouts.base')

@section('title', $title)

@section('content')
    <h1>Olá{{ $customer?->name ? ', '.$customer->name : '' }}!</h1>

    <p style="font-size:1.1rem;"><strong>{{ $title }}</strong></p>

    @if(filled($body))
        <p style="font-size:1.05rem;">{{ $body }}</p>
    @endif

    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">
        Este é um e-mail automático da Yoop.
    </p>
@endsection
