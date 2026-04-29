@extends('emails.layouts.base')

@section('title', 'Bem-vindo ao Yoop!')

@section('content')
    <h1>Bem-vindooooo, {{ $customer->name }}!</h1>
    <p>Sua conta foi criada com sucesso.<br>Estamos felizes em ter você conosco.<br>Para começar a usar todos os recursos, valide seu e-mail clicando no botão abaixo:</p>
    <div style="text-align:center;">
        <a href="{{ $validationUrl }}" class="button">Validar meu e-mail</a>
    </div>
    <p style="margin-top:24px;">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
    <div class="info-block">{{ $validationUrl }}</div>
    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">Se você não criou esta conta, ignore este e-mail.</p>
@endsection
