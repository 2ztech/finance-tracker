<?php
session_start();

// Auto-load core classes
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../src/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Initialize the database and ensure tables exist
Database::getConnection();

// Basic Router
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($requestUri, '/');

// Handle logout explicitly
if ($route === 'logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// Global Auth guard
if (!Auth::isLoggedIn() && $route !== 'login') {
    header('Location: /login');
    exit;
}

// Define custom routes inside `index.php`
if ($route === 'settings/password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    
    if (Auth::updatePassword($_SESSION['user_id'], $old, $new)) {
        header('Location: /settings?msg=password_success');
    } else {
        header('Location: /settings?msg=password_error');
    }
    exit;
}

if ($route === 'settings/export') {
    Auth::requireLogin();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="finance_transactions_' . date('Y-m-d') . '.csv"');
    $db = Database::getConnection();
    $stmt = $db->query("SELECT t.date, t.type, t.amount, c.name as category_name, t.description FROM transactions t LEFT JOIN categories c ON t.category_id = c.id ORDER BY t.date DESC");
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Type', 'Amount', 'Category', 'Description']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['date'], $row['type'], $row['amount'], $row['category_name'], $row['description']]);
    }
    fclose($output);
    exit;
}

if ($route === 'settings/backup') {
    Auth::requireLogin();
    $file = __DIR__ . '/../data/finance.db';
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="finance.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

if ($route === 'settings/import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $db = Database::getConnection();
            $db->beginTransaction();
            try {
                // skip header
                fgetcsv($handle);
                $stmtCat = $db->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
                $stmtInsert = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 5) {
                        $date = $data[0];
                        $type = $data[1];
                        $amount = (float)$data[2];
                        $categoryName = $data[3];
                        $description = $data[4];

                        $stmtCat->execute([$categoryName]);
                        $cat = $stmtCat->fetch();
                        $catId = $cat ? $cat['id'] : null;

                        $stmtInsert->execute([$catId, $amount, $type, $description, $date]);
                    }
                }
                $db->commit();
                fclose($handle);
                header('Location: /settings?msg=import_success');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                fclose($handle);
            }
        }
    }
    header('Location: /settings?msg=import_error');
    exit;
}

// Map routes to templates
$routes = [
    '' => 'dashboard.php',
    'dashboard' => 'dashboard.php',
    'transactions' => 'transactions.php',
    'login' => 'login.php',
    'commitments' => 'commitments.php',
    'categories' => 'categories.php',
    'settings' => 'settings.php',
];

if (array_key_exists($route, $routes)) {
    $template = __DIR__ . '/../templates/' . $routes[$route];
    if (file_exists($template)) {
        require $template;
    } else {
        echo "Template " . htmlspecialchars($routes[$route]) . " not found. Wait for templates to be implemented.";
    }
} else {
    http_response_code(404);
    echo "404 Not Found";
}
