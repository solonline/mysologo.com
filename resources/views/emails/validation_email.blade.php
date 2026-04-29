@extends('emails.layouts.base')

@section('title', 'Confirmação do seu endereço de e-mail')

@section('content')
    <h1>Olá, {{ $customer->name }}!</h1>
    <p>Para ativar sua conta, valide seu e-mail clicando no botão abaixo:</p>
    @isset($validationUrl)
        <div style="text-align:center;">
            <a href="{{ $validationUrl }}" class="button">Validar meu e-mail</a>
        </div>
    @endisset
    <p style="margin-top:24px;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
    @isset($validationUrl)
        <div class="info-block">{{ $validationUrl }}</div>
    @endisset
    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">Se você não criou esta conta, ignore este e-mail.</p>
@endsection
