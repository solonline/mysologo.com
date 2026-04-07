<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Yoop!</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7fafc; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #e2e8f0; padding: 32px; }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo img { max-width: 120px; }
        h1 { color: #2d3748; font-size: 2rem; margin-bottom: 12px; }
        p { color: #4a5568; font-size: 1.1rem; line-height: 1.6; }
        .button {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 32px;
            background: #3182ce;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
        }
        .footer { margin-top: 32px; color: #a0aec0; font-size: 0.95rem; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="https://dummyimage.com/120x40/3182ce/fff&text=YOOP" alt="Yoop Logo">
        </div>
        <h1>Bem-vindo, {{ $customer->name }}!</h1>
        <p>
            Sua conta foi criada com sucesso.<br>
            Para ativar sua conta, valide seu e-mail clicando no botão abaixo:
        </p>
        @isset($validationUrl)
            <a href="{{ $validationUrl }}" class="button">Validar meu e-mail</a>
        @else
            <p style="color:#e53e3e;">Link de validação não disponível.</p>
        @endisset
        <div class="footer">
            Se você não criou esta conta, ignore este e-mail.<br>
            &copy; {{ date('Y') }} Yoop. Todos os direitos reservados.
        </div>
    </div>
</body>
</html>
