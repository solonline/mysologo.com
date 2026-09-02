@extends('emails.layouts.base')

@section('title', 'Teste de email')

@section('content')
    <h2>Teste de email</h2>
    <p>Se recebeste esta mensagem, as credenciais de email da dashboard estão a funcionar.</p>
    <div class="info-block">
        Destino: {{ $to }}<br>
        Host SMTP: {{ $host !== '' ? $host : 'não configurado' }}
    </div>
@endsection
