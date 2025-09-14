<?php
// public/index.php

// 1. SETUP AWAL APLIKASI
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
session_start();

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// 2. KONFIGURASI DEPENDENCY
$container->set('db', function () {
    $host = 'localhost'; $dbname = 'afika_music'; $user = 'root'; $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) { die("Koneksi database gagal: " . $e->getMessage()); }
});
$container->set('view', function () {
    return Twig::create(__DIR__ . '/../templates', ['cache' => false]);
});
$app->add(TwigMiddleware::createFromContainer($app));

// 3. MIDDLEWARE
$authMiddleware = function (Request $request, $handler) use ($app) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        $response = $app->getResponseFactory()->createResponse();
        return $response->withHeader('Location', '/afika-music/public/admin/login')->withStatus(302);
    }
    return $handler->handle($request);
};

// 4. RUTE APLIKASI
$app->group('/afika-music/public', function ($group) use ($authMiddleware) {

    // Rute Publik
    $group->get('/home', function (Request $request, Response $response) {
        $db = $this->get('db');
        $featured_services = $db->query("SELECT * FROM services WHERE is_active = 1 AND is_featured = 1 LIMIT 3")->fetchAll();
        $gallery_images = $db->query("SELECT * FROM google_drive_files WHERE is_public = 1 AND is_featured = 1 AND mime_type LIKE 'image/%' LIMIT 4")->fetchAll();
        return $this->get('view')->render($response, 'home.html.twig', [
            'page_title' => 'Selamat Datang',
            'featured_services' => $featured_services,
            'gallery_images' => $gallery_images
        ]);
    });
    $group->get('/layanan', function (Request $request, Response $response) {
        $db = $this->get('db');
        $services = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY price ASC")->fetchAll();
        return $this->get('view')->render($response, 'services.html.twig', ['page_title' => 'Layanan & Harga', 'services' => $services]);
    });
    $group->get('/booking', function (Request $request, Response $response) {
        $db = $this->get('db');
        $services = $db->query("SELECT id, name, price FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        $settings_raw = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
        $settings = [];
        foreach ($settings_raw as $row) { $settings[$row['key']] = $row['value']; }
        return $this->get('view')->render($response, 'booking.html.twig', ['page_title' => 'Booking & Kontak', 'services' => $services, 'settings' => $settings]);
    });
    $group->post('/booking', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $db = $this->get('db');
        $stmt_service = $db->prepare("SELECT name, price FROM services WHERE id = ?");
        $stmt_service->execute([$data['service_id']]);
        $service = $stmt_service->fetch();
        $price = $service['price'] ?? 0;
        $service_name = $service['name'] ?? 'Layanan tidak ditemukan';
        $sql = "INSERT INTO bookings (name, phone, service_id, booking_date, address, message, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt_booking = $db->prepare($sql);
        $stmt_booking->execute([$data['name'], $data['phone'], $data['service_id'], $data['booking_date'], $data['address'], $data['message'], $price]);
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'email.gmail.anda@gmail.com';
            $mail->Password   = 'sandi_16_karakter_dari_google';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->setFrom('email.gmail.anda@gmail.com', 'Notifikasi Website Afika');
            $mail->addAddress('email.admin.penerima@gmail.com', 'Admin Afika Music');
            $mail->isHTML(true);
            $mail->Subject = 'Booking Baru Masuk: ' . htmlspecialchars($data['name']);
            $mail->Body    = "<h2>Ada booking baru!</h2><p><strong>Nama:</strong> " . htmlspecialchars($data['name']) . "</p><p><strong>No. HP:</strong> " . htmlspecialchars($data['phone']) . "</p><p><strong>Layanan:</strong> " . htmlspecialchars($service_name) . "</p><p><strong>Tanggal Acara:</strong> " . htmlspecialchars($data['booking_date']) . "</p><p><strong>Alamat:</strong><br>" . nl2br(htmlspecialchars($data['address'])) . "</p><p><strong>Pesan:</strong><br>" . nl2br(htmlspecialchars($data['message'])) . "</p><p>Silakan cek panel admin untuk konfirmasi pesanan.</p>";
            $mail->send();
        } catch (Exception $e) {
            // error_log("Mailer Error: {$mail->ErrorInfo}");
        }
        $response->getBody()->write('<h1>Terima Kasih!</h1><p>Permintaan booking Anda telah kami terima. Tim kami akan segera menghubungi Anda untuk konfirmasi.</p><a href="/afika-music/public/home">Kembali ke Beranda</a>');
        return $response;
    });
   $group->get('/galeri[/]', function (Request $request, Response $response)  {
        $db = $this->get('db');
        $files = $db->query("SELECT * FROM google_drive_files WHERE is_public = 1 ORDER BY created_at DESC")->fetchAll();
        return $this->get('view')->render($response, 'galeri.html.twig', ['page_title' => 'Galeri Media', 'files' => $files]);
    });
    $group->get('/jadwal', function (Request $request, Response $response) {
        $db = $this->get('db');
        $stmt = $db->query("SELECT booking_date FROM bookings WHERE status = 'confirmed'");
        $confirmed_bookings = $stmt->fetchAll();
        $booked_dates = [];
        foreach ($confirmed_bookings as $booking) {
            $date = new DateTime($booking['booking_date']);
            $booked_dates[$date->format('j-n-Y')] = true;
        }
        date_default_timezone_set('Asia/Jakarta');
        $today = new DateTime();
        $current_month = $today->format('n');
        $current_year = $today->format('Y');
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
        $first_day_timestamp = strtotime("1-".$current_month."-".$current_year);
        $first_day_of_week = date('N', $first_day_timestamp);
        $first_day_offset = $first_day_of_week - 1;
        $month_names = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
        return $this->get('view')->render($response, 'jadwal.html.twig', [
            'page_title' => 'Cek Jadwal', 'booked_dates' => $booked_dates, 'days_in_month' => $days_in_month,
            'current_month' => $current_month, 'current_year' => $current_year,
            'first_day_offset' => $first_day_offset, 'current_month_name' => $month_names[(int)$current_month]
        ]);
    });

    // Rute Admin Tidak Dilindungi
    $group->get('/admin/login', function (Request $request, Response $response) {
        $params = $request->getQueryParams();
        $error = $params['error'] ?? null;
        return $this->get('view')->render($response, 'admin/login.html.twig', ['error' => $error]);
    });
    $group->post('/admin/login', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $db = $this->get('db');
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$data['username']]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($data['password'], $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            return $response->withHeader('Location', '/afika-music/public/admin/panel')->withStatus(302);
        } else {
            return $response->withHeader('Location', '/afika-music/public/admin/login?error=Username atau password salah')->withStatus(302);
        }
    });
    $group->get('/admin/logout', function (Request $request, Response $response) {
        session_destroy();
        return $response->withHeader('Location', '/afika-music/public/admin/login')->withStatus(302);
    });

    // Rute Admin Dilindungi
    $group->group('/admin', function ($adminGroup) {
        $adminGroup->get('/panel', function (Request $request, Response $response) {
            return $this->get('view')->render($response, 'admin/panel.html.twig', ['page_title' => 'Dashboard']);
        });
        $adminGroup->get('/services', function (Request $request, Response $response) {
            $db = $this->get('db');
            $services = $db->query("SELECT id, name, price, is_active FROM services ORDER BY name ASC")->fetchAll();
            return $this->get('view')->render($response, 'admin/services.html.twig', ['page_title' => 'Kelola Layanan', 'services' => $services]);
        });
        $adminGroup->get('/services/new', function (Request $request, Response $response) {
            return $this->get('view')->render($response, 'admin/service_form.html.twig', ['page_title' => 'Tambah Layanan Baru', 'service' => []]);
        });
        $adminGroup->post('/services/new', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
            $db = $this->get('db');
            $sql = "INSERT INTO services (name, slug, price, description, notes, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$data['name'], $slug, $data['price'], $data['description'], $data['notes'], $data['is_active']]);
            return $response->withHeader('Location', '/afika-music/public/admin/services')->withStatus(302);
        });
        $adminGroup->get('/services/edit/{id}', function (Request $request, Response $response, $args) {
            $db = $this->get('db');
            $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$args['id']]);
            $service = $stmt->fetch();
            return $this->get('view')->render($response, 'admin/service_form.html.twig', ['page_title' => 'Edit Layanan: ' . ($service['name'] ?? ''), 'service' => $service]);
        });
        $adminGroup->post('/services/edit/{id}', function (Request $request, Response $response, $args) {
            $data = $request->getParsedBody();
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
            $db = $this->get('db');
            $sql = "UPDATE services SET name=?, slug=?, price=?, description=?, notes=?, is_active=? WHERE id=?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$data['name'], $slug, $data['price'], $data['description'], $data['notes'], $data['is_active'], $args['id']]);
            return $response->withHeader('Location', '/afika-music/public/admin/services')->withStatus(302);
        });
        $adminGroup->post('/services/delete/{id}', function (Request $request, Response $response, $args) {
            $db = $this->get('db');
            $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$args['id']]);
            return $response->withHeader('Location', '/afika-music/public/admin/services')->withStatus(302);
        });
        $adminGroup->get('/bookings', function (Request $request, Response $response) {
            $db = $this->get('db');
            $sql = "SELECT b.*, s.name as service_name FROM bookings b LEFT JOIN services s ON b.service_id = s.id ORDER BY b.created_at DESC";
            $bookings = $db->query($sql)->fetchAll();
            return $this->get('view')->render($response, 'admin/bookings.html.twig', ['page_title' => 'Kelola Pesanan', 'bookings' => $bookings]);
        });
        $adminGroup->post('/bookings/update-status/{id}', function (Request $request, Response $response, $args) {
            $data = $request->getParsedBody();
            $status = $data['status'];
            $booking_id = $args['id'];
            $db = $this->get('db');
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $booking_id]);
            return $response->withHeader('Location', '/afika-music/public/admin/bookings')->withStatus(302);
        });
        $adminGroup->get('/settings', function (Request $request, Response $response) {
            $db = $this->get('db');
            $stmt = $db->query("SELECT `key`, `value` FROM settings");
            $settings_raw = $stmt->fetchAll();
            $settings = [];
            foreach ($settings_raw as $row) { $settings[$row['key']] = $row; }
            return $this->get('view')->render($response, 'admin/settings.html.twig', ['page_title' => 'Pengaturan Website', 'settings' => $settings]);
        });
        $adminGroup->post('/settings', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            $db = $this->get('db');
            $sql = "INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
            $stmt = $db->prepare($sql);
            foreach ($data as $key => $value) { $stmt->execute([$key, $value]); }
            return $response->withHeader('Location', '/afika-music/public/admin/settings')->withStatus(302);
        });
    })->add($authMiddleware);

});

// 5. JALANKAN APLIKASI
$app->run();