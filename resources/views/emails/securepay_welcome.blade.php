@extends('emails.layouts.base')

@section('title', 'Bem-vindo à SecurePay')

@section('logo')
    <img src="{{ $message->embed(resource_path('images/securepay-email.png')) }}" alt="SecurePay" width="260" style="display:inline-block;width:260px;max-width:100%;height:auto;background:#ffffff;">
@endsection

@section('content')
    <h1>Bem-vindo à SecurePay{{ $customer?->name ? ', '.$customer->name : '' }}!</h1>

    <p>A conta <strong>{{ $merchant->name }}</strong> já está pronta. Pode começar a receber pagamentos com os serviços da SecurePay.</p>

    <p>Com a SecurePay consegue:</p>
    <p>
        aceitar pagamentos online de forma segura;<br>
        partilhar uma página de pagamento com os seus clientes;<br>
        receber um webhook quando um pagamento for concluído.
    </p>

    <div class="info-block">
        <strong>Conta</strong><br>
        {{ $merchant->name }}<br>
        Modo: {{ $merchant->mode === 'live' ? 'produção' : 'teste' }}
    </div>

    <p>A chave de API foi mostrada apenas no momento do registo. Guarde-a num local seguro.</p>

    <div style="text-align:center;">
        <a href="{{ $docsUrl }}" class="button">Ver a documentação</a>
    </div>
@endsection
