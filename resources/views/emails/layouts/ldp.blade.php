<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? 'MAXIMIZE LDP' }}</title>
    <style>
        body {
            background-color: #f5f7fa;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            padding: 20px;
            margin: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 30px;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 20px;
        }

        .header img {
            max-height: 60px;
        }

        .header h1 {
            color: #3498db;
            margin: 10px 0 0 0;
            font-size: 24px;
        }

        .content {
            padding: 20px 0;
        }

        .content p {
            line-height: 1.6;
            margin: 15px 0;
        }

        .button {
            display: inline-block;
            background-color: #3498db;
            color: #ffffff !important;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            {{-- Logo --}}
            <img src="{{ asset('images/logo-ldp.png') }}" alt="MAXIMIZE LDP">
            <h1>MAXIMIZE LDP</h1>
        </div>

        <div class="content">
            {{-- Titre personnalisé --}}
            <h2 style="color:#2c3e50;">{{ $title ?? 'Notification importante' }}</h2>

            {{-- Message principal --}}
            <p>{{ $message ?? 'Contenu de l’email non fourni.' }}</p>

            {{-- Bouton (optionnel) --}}
            @isset($actionUrl)
                <a href="{{ $actionUrl }}" class="button">
                    {{ $actionText ?? 'Voir les détails' }}
                </a>
            @endisset
        </div>

        <div class="footer">
            © {{ now()->year }} MAXIMIZE LDP. Tous droits réservés.
        </div>
    </div>
</body>
</html>
