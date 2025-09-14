<?php
// create_admin.php (Versi Sederhana)

require __DIR__ . '/vendor/autoload.php';

// Langsung buat koneksi PDO tanpa container
$host = 'localhost';
$dbname = 'afika_music';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}


// --- Ganti ini ---
$username = 'admin';
$password = 'PasswordSangatKuat123!';
// -----------------

// Buat hash password yang aman
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $passwordHash]);
    echo "Admin user '{$username}' berhasil dibuat!";
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        echo "Error: Username '{$username}' sudah ada.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}