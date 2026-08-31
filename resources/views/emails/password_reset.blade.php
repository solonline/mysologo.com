@extends('emails.layouts.base')

@section('title', 'Redefinição de Senha')

@section('content')
    <h2>Olá, {{ $customer->name ?? $customer->email }}</h2>
    <p>Recebemos uma solicitação para redefinir a senha da sua conta na <strong>{{ $infoSystem['company_name'] ?? 'Yoop' }}</strong>.</p>
    <p>Para garantir a segurança da sua conta, clique no botão abaixo para criar uma nova senha. Se você não fez essa solicitação, pode ignorar este e-mail com segurança.</p>
    <div style="text-align:center;">
        <a href="{{ $resetUrl }}" class="button">Redefinir Senha</a>
    </div>
    <p style="margin-top:24px;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
    <div class="info-block">
        {{ $resetUrl }}
    </div>
@endsection
