<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject ?? '' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1a1a2e; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 32px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background-color: #0f3460; padding: 24px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .content { padding: 32px; font-size: 15px; line-height: 1.6; color: #333333; }
        .content h1, .content h2, .content h3 { color: #0f3460; margin-top: 0; }
        .content h1 { font-size: 20px; }
        .content h2 { font-size: 17px; }
        .content h3 { font-size: 15px; }
        .content p { margin: 0 0 16px; }
        .content a { color: #e94560; }
        .content table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .content table td, .content table th { border: 1px solid #e5e7eb; padding: 8px 12px; text-align: left; font-size: 14px; }
        .content table th { background-color: #f8fafc; color: #0f3460; }
        .content img { max-width: 100%; height: auto; }
        .footer { padding: 16px 32px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #8a8f9e; }
        .footer p { margin: 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>{{ config('app.name') }}</h1>
            </div>
            <div class="content">
                {!! $bodyHtml !!}
            </div>
            <div class="footer">
                <p>{{ config('app.name') }} &middot; {{ now()->format('d/m/Y') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
