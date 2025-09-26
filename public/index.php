<?php
// public/index.php

// 1. SETUP AWAL APLIKASI
use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
session_start();

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Set dynamic base path for subfolder deployments (e.g., /afikamusic-website-V2/public)
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$app->setBasePath($basePath);

// 2. KONFIGURASI DEPENDENCY
$container->set('db', function () {
    $host = 'localhost'; $dbname = 'afika_music'; $user = 'root'; $pass = '';
    try {
        // Attempt normal connection
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Auto-provision database if it's missing (SQLSTATE 1049)
        $message = $e->getMessage();
        $code = (int)($e->errorInfo[1] ?? 0);
        if ($code === 1049 || stripos($message, 'Unknown database') !== false) {
            try {
                $rootPdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
                $rootPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $rootPdo = null;
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $provisionEx) {
                die("Koneksi database gagal (provision): " . $provisionEx->getMessage());
            }
        } else {
            die("Koneksi database gagal: " . $message);
        }
    }

    // Ensure minimal schema exists so the app can run on fresh installs
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS services (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            service_id INT UNSIGNED NULL,
            booking_date DATE NOT NULL,
            address TEXT NULL,
            message TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS google_drive_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NULL,
            mime_type VARCHAR(100) NULL,
            web_view_link VARCHAR(500) NULL,
            thumbnail_link VARCHAR(500) NULL,
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS schedule_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            client_name VARCHAR(255) NULL,
            location VARCHAR(255) NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
            notes TEXT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_schedule_unique (start_date, client_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure latitude & longitude columns exist for older installs
        $pdo->exec("ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL AFTER notes");
        $pdo->exec("ALTER TABLE schedule_events ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL AFTER latitude");
    } catch (PDOException $schemaEx) {
        // If schema creation fails, surface the reason for easier setup
        die("Inisialisasi schema gagal: " . $schemaEx->getMessage());
    }

    return $pdo;
});
$container->set('view', function () use ($basePath) {
    $twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);
    $twig->getEnvironment()->addGlobal('base_path', $basePath);
    $twig->getEnvironment()->addGlobal('session', $_SESSION ?? []);
    return $twig;
});
$app->add(TwigMiddleware::createFromContainer($app));
$app->add(function (Request $request, $handler) {
    $twig = $this->get('view');
    $twig->getEnvironment()->addGlobal('current_path', $request->getUri()->getPath());
    return $handler->handle($request);
});

// 3. MIDDLEWARE
$authMiddleware = function (Request $request, $handler) use ($app) {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        $response = $app->getResponseFactory()->createResponse();
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/login')->withStatus(302);
    }
    return $handler->handle($request);
};

// 4. RUTE APLIKASI
$app->group('', function ($group) use ($authMiddleware) {

    // Root redirect to /home for convenience
    $group->get('[/]', function (Request $request, Response $response) {
		$base = RouteContext::fromRequest($request)->getBasePath();
		return $response->withHeader('Location', $base . '/home')->withStatus(302);
    });

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
		$base = RouteContext::fromRequest($request)->getBasePath();
        $response->getBody()->write('<h1>Terima Kasih!</h1><p>Permintaan booking Anda telah kami terima. Tim kami akan segera menghubungi Anda untuk konfirmasi.</p><a href="' . htmlspecialchars($base . '/home') . '">Kembali ke Beranda</a>');
        return $response;
    });
   $group->get('/galeri[/]', function (Request $request, Response $response)  {
        $db = $this->get('db');
        $files = $db->query("SELECT * FROM google_drive_files WHERE is_public = 1 ORDER BY created_at DESC")->fetchAll();
        return $this->get('view')->render($response, 'galeri.html.twig', ['page_title' => 'Galeri Media', 'files' => $files]);
    });
    $group->get('/jadwal', function (Request $request, Response $response) {
        $db = $this->get('db');
        $stmt = $db->query("SELECT title, client_name, location, start_date, end_date, status, notes, latitude, longitude FROM schedule_events ORDER BY start_date ASC");
        $events = $stmt->fetchAll();
        $grouped = [];
        setlocale(LC_TIME, 'id_ID.UTF-8');
        foreach ($events as $event) {
            $monthLabel = strftime('%B %Y', strtotime($event['start_date']));
            $grouped[$monthLabel][] = $event;
        }
        return $this->get('view')->render($response, 'jadwal.html.twig', [
            'page_title' => 'Cek Jadwal',
            'events' => $events,
            'grouped_events' => $grouped
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
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/panel')->withStatus(302);
        } else {
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/login?error=Username atau password salah')->withStatus(302);
        }
    });
    $group->get('/admin/logout', function (Request $request, Response $response) {
        session_destroy();
		$base = RouteContext::fromRequest($request)->getBasePath();
		return $response->withHeader('Location', $base . '/admin/login')->withStatus(302);
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
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/services')->withStatus(302);
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
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/services')->withStatus(302);
        });
        $adminGroup->post('/services/delete/{id}', function (Request $request, Response $response, $args) {
            $db = $this->get('db');
            $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$args['id']]);
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/services')->withStatus(302);
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

            $stmtBooking = $db->prepare("SELECT name, booking_date, address FROM bookings WHERE id = ?");
            $stmtBooking->execute([$booking_id]);
            $booking = $stmtBooking->fetch();

            if ($booking) {
                if ($status === 'confirmed') {
                    $stmtSchedule = $db->prepare("INSERT INTO schedule_events (title, client_name, location, start_date, end_date, status, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), client_name=VALUES(client_name), location=VALUES(location), start_date=VALUES(start_date), end_date=VALUES(end_date), status=VALUES(status), latitude=VALUES(latitude), longitude=VALUES(longitude)");
                    $stmtSchedule->execute([
                        'Event ' . $booking['name'],
                        $booking['name'],
                        $booking['address'],
                        $booking['booking_date'],
                        $booking['booking_date'],
                        'scheduled',
                        null,
                        null
                    ]);
                } else {
                    $stmtDelete = $db->prepare("DELETE FROM schedule_events WHERE start_date = ? AND client_name = ?");
                    $stmtDelete->execute([$booking['booking_date'], $booking['name']]);
                }
            }
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/bookings')->withStatus(302);
        });
        $adminGroup->get('/schedule', function (Request $request, Response $response) {
            $db = $this->get('db');
            $events = $db->query("SELECT * FROM schedule_events ORDER BY start_date DESC")->fetchAll();
            return $this->get('view')->render($response, 'admin/schedule.html.twig', [
                'page_title' => 'Kelola Jadwal',
                'events' => $events
            ]);
        });
        $adminGroup->post('/schedule/new', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            $db = $this->get('db');
            $stmt = $db->prepare("INSERT INTO schedule_events (title, client_name, location, start_date, end_date, status, notes, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['title'],
                $data['client_name'],
                $data['location'],
                $data['start_date'],
                $data['end_date'],
                $data['status'],
                $data['notes'],
                $data['latitude'] ?? null,
                $data['longitude'] ?? null
            ]);
		$base = RouteContext::fromRequest($request)->getBasePath();
		return $response->withHeader('Location', $base . '/admin/schedule')->withStatus(302);
        });
        $adminGroup->post('/schedule/delete/{id}', function (Request $request, Response $response, $args) {
            $db = $this->get('db');
            $stmt = $db->prepare("DELETE FROM schedule_events WHERE id = ?");
            $stmt->execute([$args['id']]);
		$base = RouteContext::fromRequest($request)->getBasePath();
		return $response->withHeader('Location', $base . '/admin/schedule')->withStatus(302);
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
			$base = RouteContext::fromRequest($request)->getBasePath();
			return $response->withHeader('Location', $base . '/admin/settings')->withStatus(302);
        });
    })->add($authMiddleware);

});

// 5. JALANKAN APLIKASI
$app->run();