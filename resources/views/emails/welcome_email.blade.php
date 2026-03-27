// ...existing code...
        .logo img { max-width: 120px; }
        h1 { color: #2d3748; font-size: 2rem; margin-bottom: 12px; }
        p { color: #4a5568; font-size: 1.1rem; line-height: 1.6; }
        .button {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 32px;
            background: #38a169;
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
            <!-- Substitua pelo logo real -->
            <img src="https://dummyimage.com/120x40/38a169/fff&text=YOOP" alt="Yoop Logo">
        </div>
        <h1>Bem-vindo, {{ $customer->name }}!</h1>
        <p>
            Sua conta foi criada com sucesso.<br>
            Estamos felizes em ter você conosco.<br>
            Para começar a usar todos os recursos, valide seu e-mail clicando no botão abaixo:
        </p>
            <a href="{{ $validationUrl }}" class="button">Validar meu e-mail</a>
        <div class="footer">
            Se você não criou esta conta, ignore este e-mail.<br>
            &copy; {{ date('Y') }} Yoop. Todos os direitos reservados.
        </div>
    </div>
</body>
</html>
