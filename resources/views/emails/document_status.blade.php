@extends('emails.layouts.base')

@section('title', 'Atualização do status do seu documento')

@section('content')
    <h1>Olá, {{ $customer->name }}!</h1>
    @if($status === 'approved')
        <p style="font-size:1.1rem;">Temos o prazer de informar que seu documento foi <strong>aprovado</strong> com sucesso.<br>
        Agora você tem acesso completo a todos os recursos da plataforma Yoop.<br>
        Seja bem-vindo(a)!</p>
    @elseif($status === 'pending')
        <p style="font-size:1.1rem;">Recebemos seu documento e ele está atualmente <strong>em análise</strong> por nossa equipe.<br>
        Assim que houver uma atualização, entraremos em contato por este e-mail.<br>
        Agradecemos pela sua paciência.</p>
    @elseif($status === 'declined')
        <p style="font-size:1.1rem;">Após análise, identificamos que seu documento necessita de uma nova verificação.<br>
        Nossa equipe entrará em contato com você o mais breve possível para orientações sobre os próximos passos.<br>
        Caso tenha dúvidas, estamos à disposição para ajudar.</p>
    @else
        <p style="font-size:1.1rem;">O status do seu documento foi atualizado para: <strong>{{ ucfirst($status) }}</strong>.<br>
        Para mais informações, entre em contato com nosso suporte.</p>
    @endif
    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">Este é um e-mail automático. Se precisar de assistência, por favor, entre em contato com nosso suporte através dos canais oficiais.</p>
@endsection
