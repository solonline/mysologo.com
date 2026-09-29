<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovativo de transferência</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.5;
            background: #ffffff;
        }

        @if (!empty($preview))
        html { background: #d7dee8; }
        body {
            background: #d7dee8;
            padding: 32px 12px 48px;
        }
        @endif

        .page {
            width: 210mm;
            @if (!empty($preview))
            min-height: 297mm;
            margin: 0 auto;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.16);
            @else
            height: 297mm;
            @endif
            background: #ffffff;
            position: relative;
        }

        .brand-bar {
            height: 6px;
            background: #3730a3;
            width: 100%;
        }

        .content {
            padding: 40px 52px 0;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .header td {
            vertical-align: middle;
            padding: 0;
        }

        .logo {
            height: 46px;
            width: auto;
            display: block;
        }

        .header-right {
            text-align: right;
        }

        .eyebrow {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #818cf8;
            margin-bottom: 6px;
        }

        .title {
            font-size: 21px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .divider {
            height: 1px;
            background: #e2e8f0;
            border: 0;
            margin: 24px 0 0;
        }

        .hero {
            text-align: center;
            padding: 36px 12px 30px;
        }

        .hero-status {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #3730a3;
            margin-bottom: 14px;
        }

        .hero-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .hero-amount {
            font-size: 46px;
            font-weight: 700;
            color: #3730a3;
            letter-spacing: -1.2px;
            line-height: 1;
        }

        .hero-currency {
            font-size: 18px;
            font-weight: 600;
            color: #64748b;
            margin-left: 6px;
            letter-spacing: 0;
        }

        .flow {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 34px;
        }

        .flow td {
            vertical-align: top;
            padding: 0;
        }

        .party {
            width: 44%;
        }

        .party-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .party-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .arrow-cell {
            width: 12%;
            text-align: center;
            vertical-align: middle !important;
            padding-top: 18px !important;
            color: #c7d2fe;
            font-size: 18px;
            font-weight: 700;
        }

        .section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #94a3b8;
            margin: 0 0 8px;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #e2e8f0;
        }

        .details tr td {
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .details .label {
            width: 38%;
            font-size: 12px;
            color: #64748b;
        }

        .details .value {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-align: right;
        }

        .details .value-mono {
            font-family: DejaVu Sans Mono, Courier, monospace;
            font-weight: 500;
            color: #475569;
            font-size: 12px;
        }

        .footer {
            position: absolute;
            left: 52px;
            right: 52px;
            bottom: 30px;
        }

        .footer-rule {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 0 0 12px;
        }

        .footer-legal {
            font-size: 8.5px;
            line-height: 1.5;
            color: #94a3b8;
            margin: 0 0 8px;
        }

        .footer-links {
            font-size: 10px;
            color: #64748b;
        }

        .footer-links a {
            color: #3730a3;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/yoop-logo.png');
    if (! is_file($logoPath)) {
        $logoPath = public_path('yoop.jpeg');
    }
    $ext = str_ends_with(strtolower((string) $logoPath), '.png') ? 'png' : 'jpeg';
    $logoSrc = is_file($logoPath)
        ? 'data:image/'.$ext.';base64,'.base64_encode((string) file_get_contents($logoPath))
        : '';
@endphp

<div class="page">
    <div class="brand-bar"></div>

    <div class="content">
        <table class="header">
            <tr>
                <td style="width: 40%;">
                    @if ($logoSrc !== '')
                        <img class="logo" src="{{ $logoSrc }}" alt="Yoop">
                    @endif
                </td>
                <td class="header-right">
                    <div class="eyebrow">Transfer Confirmation</div>
                    <div class="title">Comprovativo de<br>transferência</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="hero">
            <div class="hero-status">● Concluída</div>
            <div class="hero-label">Montante transferido</div>
            <div class="hero-amount">
                {{ $data->amount }}<span class="hero-currency">{{ $data->currency }}</span>
            </div>
        </div>

        <table class="flow">
            <tr>
                <td class="party">
                    <div class="party-label">De</div>
                    <div class="party-name">{{ $data->fromCustomerUsername }}</div>
                </td>
                <td class="arrow-cell">→</td>
                <td class="party">
                    <div class="party-label">Para</div>
                    <div class="party-name">{{ $data->toCustomerUsername }}</div>
                </td>
            </tr>
        </table>

        <div class="section-title">Detalhes da operação</div>
        <table class="details">
            <tr>
                <td class="label">Data</td>
                <td class="value">{{ $data->date }}</td>
            </tr>
            <tr>
                <td class="label">Referência</td>
                <td class="value value-mono">#{{ $data->movementId }}</td>
            </tr>
            <tr>
                <td class="label">Tipo</td>
                <td class="value">Transferência</td>
            </tr>
            @if (filled($data->description))
            <tr>
                <td class="label">Descrição</td>
                <td class="value">{{ $data->description }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Estado</td>
                <td class="value" style="color:#3730a3;">Concluída</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <hr class="footer-rule">
        <p class="footer-legal">
            Yoop é o nome fantasia da Yoop Payments Platform Limited, uma Instituição de Pagamentos Digital,
            incorporada em Inglaterra (UK) com o número de registro 16632962 e sede registrada no endereço
            71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, Reino Unido.
        </p>
        <div class="footer-links">
            <a href="https://www.yooppayments.eu">www.yooppayments.eu</a>
            &nbsp;·&nbsp;
            Precisa de ajuda? Visite <a href="https://yooppayments.eu/help">yooppayments.eu/help</a>
        </div>
    </div>
</div>
</body>
</html>
