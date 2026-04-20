<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Redefinição de Senha</title>
</head>
<body>
    <h2>Olá, {{ $customer->name ?? $customer->email }}</h2>
    <p>Recebemos uma solicitação para redefinir sua senha.</p>
    <p>Para redefinir sua senha, clique no botão abaixo ou copie o link e cole no seu navegador:</p>
    <p>
        <a href="{{ url('/password/reset?email=' . urlencode($customer->email) . '&token=' . urlencode($token)) }}" style="display:inline-block;padding:10px 20px;background:#6366f1;color:#fff;text-decoration:none;border-radius:5px;">Redefinir Senha</a>
    </p>
    <p>Ou copie e cole este link no navegador:</p>
    <p>{{ url('/password/reset?email=' . urlencode($customer->email) . '&token=' . urlencode($token)) }}</p>
    <p>Se você não solicitou a redefinição, ignore este e-mail.</p>
    <p>Atenciosamente,<br>Equipe Yoop</p>
</body>
</html>
