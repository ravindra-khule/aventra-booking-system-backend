<?php
/**
 * Index.php - Public folder entry point
 * This is just a welcome page
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Aventra API</title>
    <style>
        body { font-family: Arial; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .endpoint { background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #ff1b00; }
        code { background: #e0e0e0; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 Aventra Booking API</h1>
        <p>Pure PHP Backend - Works on Hostinger</p>
        
        <h2>Available Endpoints:</h2>
        
        <div class="endpoint">
            <strong>GET /api/tours.php</strong><br>
            Fetch all active tours for the homepage<br>
            <code>curl http://localhost:8000/api/tours.php</code>
        </div>
        
        <h2>Setup Instructions:</h2>
        <ol>
            <li>Run <code>php setup-db.php</code> to create database</li>
            <li>Start server: <code>php -S localhost:8000</code></li>
            <li>Test API: <code>http://localhost:8000/api/tours.php</code></li>
        </ol>
    </div>
</body>
</html>
