<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Executed Successfully</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: radial-gradient(circle at 15% 15%, rgba(20, 184, 166, 0.2), transparent 55%),
                radial-gradient(circle at 85% 10%, rgba(59, 130, 246, 0.12), transparent 45%),
                linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            width: 80px;
            height: 80px;14b8a6 0%, #0d9488
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        .icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        
        h1 {
            color: #0f172a;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .message {
            color: #475569;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .timestamp {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .timestamp-label {
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        
        .timestamp-value {
            color: #0f172a;
            font-size: 18px;
            font-weight: 600;
            font-family: 'Monaco', 'Courier New', monospace;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer-text94a3b8
            color: #a0aec0;
            font-size: 13px;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 40px 25px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .message {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        
        <h1>Cron Job Executed</h1>
        
        <p class="message">
            {{ $message }}
        </p>
        
        <div class="timestamp">
            <div class="timestamp-label">Execution Time</div>
            <div class="timestamp-value">{{ $ran_at }}</div>
        </div>
        
        <div class="footer">
            <p class="footer-text">Automated cron task completed successfully</p>
        </div>
    </div>
</body>
</html>
