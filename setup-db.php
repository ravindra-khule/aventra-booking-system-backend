<?php
/**
 * Database Setup
 * Run once: php setup-db.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'aventra_db';

try {
    // Create connection without selecting database
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $db";
    if (!$conn->query($sql)) {
        die("❌ Error creating database: " . $conn->error);
    }
    echo "✅ Database '$db' created/exists\n";
    
    // Select database
    $conn->select_db($db);
    
    // Create tours table
    $sql = "CREATE TABLE IF NOT EXISTS tours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        short_description TEXT NOT NULL,
        description LONGTEXT NOT NULL,
        status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
        image_url VARCHAR(512),
        price DECIMAL(10, 2) NOT NULL,
        deposit_price DECIMAL(10, 2) DEFAULT 0,
        currency VARCHAR(3) DEFAULT 'USD',
        duration_days INT NOT NULL,
        difficulty ENUM('easy', 'moderate', 'hard') DEFAULT 'moderate',
        location VARCHAR(255) NOT NULL,
        country VARCHAR(255) NOT NULL,
        region VARCHAR(255),
        max_capacity INT NOT NULL,
        available_spots INT NOT NULL,
        next_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating tours table: " . $conn->error);
    }
    echo "✅ Tours table created/exists\n";
    
    // Insert sample data if table is empty
    $checkSql = "SELECT COUNT(*) as count FROM tours";
    $result = $conn->query($checkSql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $samples = [
            "('Mountain Adventure', 'mountain-adventure', 'Exciting mountain trek', 'Experience the beautiful Swiss Alps with professional guides...', 'active', 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?q=80&w=800', 1500.00, 300.00, 'USD', 7, 'hard', 'Swiss Alps', 'Switzerland', 10, 5, '2026-05-15')",
            "('Tropical Beach Tour', 'tropical-beach', 'Relax on beautiful beaches', 'Paradise awaits you in the Maldives...', 'active', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=800', 899.00, 200.00, 'USD', 5, 'easy', 'Maldives', 'Maldives', 15, 8, '2026-05-20')",
            "('Desert Safari', 'desert-safari', 'Adventure in the sand dunes', 'Explore the vast desert landscape...', 'active', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?q=80&w=800', 599.00, 150.00, 'USD', 3, 'moderate', 'Sahara', 'Morocco', 12, 3, '2026-05-10')",
            "('Bestig Kilimanjaro', 'bestig-kilimanjaro', '8 dagar på berget. Vandra genom fyra klimatzoner till toppen av Afrika.', 'Vandra genom fyra klimatzoner på vår unika rutt till toppen av Afrika, Uhuru Peak 5895 m höjd. En expedition utöver det vanliga med erfarna guider.', 'active', 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?q=80&w=800', 45900.00, 5000.00, 'SEK', 10, 'hard', 'Kilimanjaro', 'Tanzania', 12, 4, '2027-01-24')",
            "('Vandra Inkaleden', 'vandra-inkaleden', '12 dagar. Vi vandrar på vår obefolkade rutt av Inkaleden.', 'Vi vandrar på vår obefolkade rutt av Inkaleden, utan trängsel, fram till slutmålet – Machu Picchu! En klassisk expedition.', 'active', 'https://images.unsplash.com/photo-1483389127117-b6a2102724ae?q=80&w=800', 45900.00, 4500.00, 'SEK', 12, 'hard', 'Machu Picchu', 'Peru', 12, 6, '2027-04-30')",
            "('Jämtlandsfjällen', 'jamtlandsfjallen', '5 dagar. Fjällvandring bland kala toppar och glittrande fjällsjöar.', 'Upptäck Jämtlandsfjällen med oss – en fjällvandring genom ett av Sveriges vackraste fjällområden. Vi vandrar på leder mellan fjällstationer och njuter av stillheten och den storslagna naturen.', 'active', 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=800', 12900.00, 1500.00, 'SEK', 5, 'moderate', 'Jämtland', 'Sweden', 12, 8, '2027-06-15')",
            "('Touch av Sarek', 'touch-av-sarek', '7 dagar. En smak av Sarek – vandring i Sveriges mest vilda nationalpark.', 'Upplev Sareks nationalpark, Europas sista vildmark. Vi vandrar genom sagolika dalar omgivna av höga toppar och glaciärer. En äkta vildmarksupplevelse med erfarna guider.', 'active', 'https://images.unsplash.com/photo-1519681393784-d120267933ba?q=80&w=800', 18900.00, 2000.00, 'SEK', 7, 'hard', 'Sarek National Park', 'Sweden', 10, 6, '2027-07-10')",
            "('Everest Base Camp', 'everest-base-camp', '14 dagar. Vandra den unika Gokyo-rutten fram till Everest BC.', 'Vandra den unika Gokyo-rutten fram till Everest BC. Från Base Camp tar vi helikopter tillbaka ned. En expedition med svensk guide.', 'active', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?q=80&w=800', 45900.00, 4500.00, 'SEK', 14, 'hard', 'Everest Region', 'Nepal', 10, 3, '2027-03-23')"
        ];
        
        $insertSql = "INSERT INTO tours (title, slug, short_description, description, status, image_url, price, deposit_price, currency, duration_days, difficulty, location, country, max_capacity, available_spots, next_date) VALUES ";
        $insertSql .= implode(", ", $samples);
        
        if ($conn->query($insertSql)) {
            echo "✅ Sample tour data inserted\n";
        } else {
            echo "⚠️  Sample data insert failed: " . $conn->error . "\n";
        }
    }
    
    $conn->close();
    echo "\n✅ Database setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>
