<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DiskarTech subscription</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #111827; font: 16px/1.6 system-ui, sans-serif; }
        main { max-width: 420px; margin: 24px; padding: 32px; background: white; border: 1px solid #e2e8f0; border-radius: 24px; }
        h1 { font-size: 24px; line-height: 1.3; } p { color: #64748b; }
    </style>
</head>
<body>
    <main>
        <strong>DiskarTech</strong>
        <h1>{{ $cancelled ? 'Checkout closed' : 'Check your payment in the app' }}</h1>
        <p>Close this browser and return to DiskarTech. Select Check payment on the subscription screen to confirm your payment status.</p>
        <p>This page does not activate a subscription. Your payment is confirmed securely with PayMongo.</p>
    </main>
</body>
</html>
