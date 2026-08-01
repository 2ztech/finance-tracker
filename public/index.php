<?php
// Dynamic timezone detection
$detectedTz = getenv('TZ') ?: ($_ENV['TZ'] ?? '');
if (empty($detectedTz)) {
    if (file_exists('/etc/timezone') && is_readable('/etc/timezone')) {
        $detectedTz = trim(file_get_contents('/etc/timezone'));
    }
}
if (empty($detectedTz) || !in_array($detectedTz, timezone_identifiers_list())) {
    $detectedTz = 'UTC';
}
date_default_timezone_set($detectedTz);

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

Csrf::validate();

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

if (Auth::isLoggedIn()) {
    Expense::processDueCommitments();
}

// Define custom routes inside `index.php`
if ($route === 'settings/account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $username = $_POST['username'] ?? '';
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    
    if (Auth::updateCredentials($_SESSION['user_id'], $username, $old, $new)) {
        header('Location: /settings?msg=account_success');
    } else {
        header('Location: /settings?msg=account_error');
    }
    exit;
}

if ($route === 'settings/ledger' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    require_once __DIR__ . '/../src/Settings.php';
    if (isset($_POST['tracking_start_month'])) {
        Settings::set('tracking_start_month', $_POST['tracking_start_month']);
    }
    if (isset($_POST['starting_balance'])) {
        Settings::set('starting_bank_balance', (string) round((float) $_POST['starting_balance'], 2));
    }
    header('Location: /settings?msg=settings_success');
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

if ($route === 'settings/restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    if (isset($_FILES['db_file']) && $_FILES['db_file']['error'] == 0) {
        $fileInfo = pathinfo($_FILES['db_file']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');
        if (in_array($ext, ['db', 'sqlite'])) {
            $dest = __DIR__ . '/../data/finance.db';
            if (move_uploaded_file($_FILES['db_file']['tmp_name'], $dest)) {
                header('Location: /settings?msg=restore_success');
                exit;
            }
        }
    }
    header('Location: /settings?msg=restore_error');
    exit;
}

if ($route === 'settings/clean-duplicates' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $db = Database::getConnection();
    
    $stmt = $db->prepare("
        DELETE FROM transactions WHERE id NOT IN (
            SELECT MIN(id) FROM transactions 
            GROUP BY DATE(date), amount, type, TRIM(LOWER(description)), category_id
        )
    ");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    header("Location: /settings?msg=clean_success&count={$deleted}");
    exit;
}

if ($route === 'settings/import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $db = Database::getConnection();
            $db->beginTransaction();
            try {
                fgetcsv($handle);
                $stmtCat = $db->prepare("SELECT id FROM categories WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) AND type = ? LIMIT 1");
                $stmtCreateCat = $db->prepare("INSERT INTO categories (name, type, color_hex) VALUES (?, ?, ?)");
                $stmtInsert = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date) VALUES (?, ?, ?, ?, ?)");
                $stmtCheck = $db->prepare("SELECT 1 FROM transactions WHERE DATE(date) = DATE(?) AND ABS(amount - ?) < 0.01 AND type = ? AND TRIM(LOWER(description)) = TRIM(LOWER(?)) LIMIT 1");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 5) {
                        continue;
                    }
                    $date = trim($data[0] ?? '');
                    $type = trim($data[1] ?? '');
                    $amount = round((float)($data[2] ?? 0), 2);
                    $categoryName = trim($data[3] ?? '');
                    $description = trim($data[4] ?? '');

                    if ($date === '' || $amount <= 0 || !in_array($type, ['income', 'expense'], true)) {
                        continue;
                    }

                    $stmtCheck->execute([$date, $amount, $type, $description]);
                    if ($stmtCheck->fetchColumn()) {
                        continue;
                    }

                    $catId = null;
                    if ($categoryName !== '') {
                        $stmtCat->execute([$categoryName, $type]);
                        $cat = $stmtCat->fetch();
                        if ($cat) {
                            $catId = $cat['id'];
                        } else {
                            $colorHex = '#' . substr(md5($categoryName . $type), 0, 6);
                            $stmtCreateCat->execute([$categoryName, $type, $colorHex]);
                            $catId = (int) $db->lastInsertId();
                        }
                    }

                    $stmtInsert->execute([$catId, $amount, $type, $description, $date]);
                }
                $db->commit();
                fclose($handle);
                header('Location: /settings?msg=import_success');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                fclose($handle);
                error_log('CSV import failed: ' . $e->getMessage());
            }
        }
    }
    header('Location: /settings?msg=import_error');
    exit;
}


if ($route === 'quick-template/add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $type = $_POST['type'] ?? 'expense';
    $desc = trim($_POST['description'] ?? '');
    $catId = (int) ($_POST['category_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($desc !== '' && in_array($type, ['income', 'expense'], true)) {
        QuickTemplate::create($type, $desc, $catId > 0 ? $catId : null, $amount);
    }
    header('Location: /transactions?month=' . urlencode($_POST['return_month'] ?? date('Y-m')));
    exit;
}

if ($route === 'quick-template/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    QuickTemplate::delete((int) ($_POST['id'] ?? 0));
    header('Location: /transactions?month=' . urlencode($_POST['return_month'] ?? date('Y-m')));
    exit;
}

if ($route === 'budget/set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $catId = (int) ($_POST['category_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($catId > 0 && $amount > 0) {
        Budget::set($catId, $amount);
    }
    header('Location: /dashboard');
    exit;
}

if ($route === 'budget/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    Budget::delete((int) ($_POST['category_id'] ?? 0));
    header('Location: /dashboard');
    exit;
}

// Map routes to templates
$routes = [
    '' => 'dashboard.php',
    'dashboard' => 'dashboard.php',
    'transactions' => 'transactions.php',
    'login' => 'login.php',
    'recurring' => 'recurring.php',
    'categories' => 'categories.php',
    'budgets' => 'budgets.php',
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
