<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Yoop')</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f7fafc; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #e2e8f0; padding: 32px 28px; }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo img { max-width: 120px; }
        h1, h2 { color: #3730a3; font-weight: 600; margin-bottom: 18px; }
        p { color: #4a5568; font-size: 1.08rem; line-height: 1.6; }
        .button {
            display: inline-block;
            margin: 32px 0 18px 0;
            padding: 14px 32px;
            background: #3730a3;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1.08rem;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .footer { margin-top: 32px; color: #64748b; font-size: 0.97rem; text-align: center; }
        .info-block { background: #f1f5f9; padding: 12px 10px; border-radius: 5px; word-break: break-all; font-size: 0.97rem; margin-bottom: 24px; }
        hr { border: none; border-top: 1px solid #e2e8f0; margin: 32px 0 18px 0; }
    </style>
    @yield('head')
</head>
<body>
    <div class="container">
        <div class="logo">
            @hasSection('logo')
                @yield('logo')
            @else
                <img src="{{ url('/yoop.jpeg') }}" alt="Yoop Logo">
            @endif
        </div>
        @yield('content')
        <hr>
        <div class="footer">
            <strong>{{ $infoSystem['company_name'] ?? 'Yoop' }}</strong><br>
            {{ $infoSystem['email'] ?? '' }}<br>
            {{ $infoSystem['phone'] ?? '' }}<br>
            {{ $infoSystem['address'] ?? '' }}<br>
            <span style="color:#a0aec0; font-size:0.95rem;">Esta mensagem foi enviada automaticamente. Por favor, não responda este e-mail.<br>&copy; {{ date('Y') }} Yoop. Todos os direitos reservados.</span>
        </div>
    </div>
</body>
</html>
