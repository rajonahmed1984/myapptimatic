<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 | Page Not Found</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }
        .wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 640px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.08);
        }
        .code {
            display: inline-block;
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #475569;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 6px 10px;
        }
        h1 {
            margin: 12px 0 8px;
            font-size: 32px;
            line-height: 1.2;
        }
        p {
            margin: 0 0 18px;
            color: #475569;
            font-size: 15px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            text-decoration: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            background: #ffffff;
        }
        .btn-primary {
            border-color: #0f766e;
            background: #0f766e;
            color: #ffffff;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <span class="code">Error 404</span>
        <h1>Page Not Found</h1>
        <p>The page you requested does not exist or has been moved.</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Go Home</a>
            <a class="btn" href="{{ url()->previous() }}">Go Back</a>
        </div>
    </div>
</div>
</body>
</html>
