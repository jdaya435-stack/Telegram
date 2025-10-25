
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot - Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .status {
            padding: 15px;
            background: #4CAF50;
            color: white;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Telegram Bot Status</h1>
        
        <div class="status">
            ✅ Bot is Running
        </div>

        <div class="info">
            <h3>ℹ️ Information</h3>
            <p>This is a Telegram webhook endpoint. You don't access it directly in a browser.</p>
            <p><strong>Webhook URL:</strong> <code><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/webhook.php'; ?></code></p>
        </div>

        <div class="info">
            <h3>📱 How to Use</h3>
            <ol>
                <li>Open Telegram and find your bot</li>
                <li>Send <code>/start</code> to get your ID</li>
                <li>Send <code>/bin 411111</code> to look up BIN info</li>
                <li>Send <code>/cmds</code> to see all commands</li>
            </ol>
        </div>

        <div class="info">
            <h3>🔧 Bot Commands</h3>
            <ul>
                <li><code>/start</code> - Get your Telegram ID</li>
                <li><code>/bin [6-digits]</code> - BIN lookup</li>
                <li><code>/chk [amount] [card]</code> - Card check (USD)</li>
                <li><code>/inr [amount] [card]</code> - Card check (INR)</li>
                <li><code>/sk [key]</code> - Stripe key validation</li>
            </ul>
        </div>
    </div>
</body>
</html>
