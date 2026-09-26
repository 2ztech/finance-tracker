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

$secureCookie = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $secureCookie,
]);
session_start();

// Auto-load core classes
spl_autoload_register(function ($class_name) {

    $file = __DIR__ . '/../src/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Capture PHP warnings/errors and uncaught exceptions into the app log.
set_error_handler(function ($no, $str, $file, $line) {
    AppLog::warn('php_error', ['msg' => $str, 'file' => $file, 'line' => $line]);
    return false;
});
set_exception_handler(function ($e) {
    AppLog::error('uncaught_exception', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
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

if ($route === 'settings/export') {
    Auth::requireLogin();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="finance_transactions_' . date('Y-m-d_Hi') . '.csv"');
    $db = Database::getConnection();
    $stmt = $db->query("SELECT t.date, t.type, t.amount, c.name as category_name, t.description, a.name as account_name FROM transactions t LEFT JOIN categories c ON t.category_id = c.id LEFT JOIN accounts a ON a.id = t.account_id ORDER BY t.date DESC");
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Type', 'Amount', 'Category', 'Description', 'Account']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['date'], $row['type'], $row['amount'], $row['category_name'], $row['description'], $row['account_name']]);
    }
    fclose($output);
    exit;
}

if ($route === 'settings/backup') {
    Auth::requireLogin();
    $tmp = sys_get_temp_dir() . '/finance_backup_' . bin2hex(random_bytes(6)) . '.db';
    if (Backup::createConsistentCopy($tmp)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="finance_' . date('Y-m-d_Hi') . '.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
    http_response_code(500);
    die('Could not create a database backup.');
}

if ($route === 'settings/logs') {
    Auth::requireLogin();
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="expenzz-logs_' . date('Y-m-d_Hi') . '.log"');
    echo AppLog::tail(3000);
    exit;
}

if ($route === 'settings/restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    if (!isset($_FILES['db_file']) || $_FILES['db_file']['error'] !== 0) {
        header('Location: /settings?msg=restore_error');
        exit;
    }

    $original = (string) $_FILES['db_file']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['db', 'sqlite'], true)) {
        header('Location: /settings?msg=restore_error');
        exit;
    }

    $tmp = sys_get_temp_dir() . '/finance_restore_' . bin2hex(random_bytes(6)) . '.db';
    if (!move_uploaded_file($_FILES['db_file']['tmp_name'], $tmp)) {
        header('Location: /settings?msg=restore_error');
        exit;
    }

    $check = Backup::validateSqliteFile($tmp);
    if (!$check['ok']) {
        @unlink($tmp);
        AppLog::warn('restore_rejected', ['error' => $check['error']]);
        header('Location: /settings?msg=restore_invalid');
        exit;
    }

    // Snapshot the current DB before replacing it (keeps newest 3).
    Backup::backupCurrent(3);
    AppLog::info('restore_started', ['original' => $original]);

    $dest = Backup::dbPath();
    if (@rename($tmp, $dest)) {
        header('Location: /settings?msg=restore_success');
        exit;
    }

    @unlink($tmp);
    header('Location: /settings?msg=restore_error');
    exit;
}

if ($route === 'settings/duplicates/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $ids = $_POST['ids'] ?? [];
    $deleted = 0;
    if (is_array($ids) && !empty($ids)) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $stmt->execute([$id])) {
                $deleted += $stmt->rowCount();
            }
        }
    }
    header('Location: /settings/duplicates?deleted=' . $deleted);
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
                $stmtInsert = $db->prepare("INSERT INTO transactions (category_id, amount, type, description, date, account_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtCheck = $db->prepare("SELECT 1 FROM transactions WHERE DATE(date) = DATE(?) AND ABS(amount - ?) < 0.01 AND type = ? AND TRIM(LOWER(description)) = TRIM(LOWER(?)) LIMIT 1");
                $stmtAcc = $db->prepare("SELECT id FROM accounts WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1");
                $defaultAccount = Account::defaultId();

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 5) {
                        continue;
                    }
                    $date = trim($data[0] ?? '');
                    $type = trim($data[1] ?? '');
                    $amount = round((float)($data[2] ?? 0), 2);
                    $categoryName = trim($data[3] ?? '');
                    $description = trim($data[4] ?? '');
                    $accountName = trim($data[5] ?? '');

                    if ($date === '' || $amount <= 0 || !in_array($type, ['income', 'expense'], true)) {
                        continue;
                    }

                    $accId = $defaultAccount;
                    if ($accountName !== '') {
                        $stmtAcc->execute([$accountName]);
                        $found = $stmtAcc->fetchColumn();
                        if ($found) {
                            $accId = (int) $found;
                        }
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

                    $stmtInsert->execute([$catId, $amount, $type, $description, $date, $accId]);
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


if ($route === 'accounts/activate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    Account::setActive((int) ($_POST['account_id'] ?? 0));
    $back = $_POST['return'] ?? '/dashboard';
    header('Location: ' . (str_starts_with($back, '/') ? $back : '/dashboard'));
    exit;
}

if ($route === 'accounts/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $iconUpload = IconUpload::fromUpload($_FILES['icon_file'] ?? null);
    } catch (InvalidArgumentException $e) {
        header('Location: /accounts?msg=icon_upload_invalid');
        exit;
    }
    $kind = in_array($_POST['kind'] ?? '', ['savings', 'credit', 'paylater'], true) ? $_POST['kind'] : 'savings';
    $data = [
        'name'           => trim($_POST['name'] ?? ''),
        'kind'           => $kind,
        'color_hex'      => $_POST['color_hex'] ?? '#4f6ef7',
        'icon_data'      => $iconUpload['data'] ?? null,
        'icon_mime'      => $iconUpload['mime'] ?? 'image/png',
        'clear_icon'     => isset($_POST['clear_icon']) ? 1 : 0,
        'bnpl_mode'      => in_array($_POST['bnpl_mode'] ?? '', ['cycle', 'per_purchase'], true) ? $_POST['bnpl_mode'] : null,
        'statement_day'  => ($_POST['statement_day'] ?? '') !== '' ? (int) $_POST['statement_day'] : null,
        'due_day'        => ($_POST['due_day'] ?? '') !== '' ? (int) $_POST['due_day'] : null,
        'first_due_offset' => (int) ($_POST['first_due_offset'] ?? 1),
        'allow_partial'  => isset($_POST['allow_partial']) ? 1 : 0,
        'no_interest_months' => ($_POST['no_interest_months'] ?? '') !== '' ? (int) $_POST['no_interest_months'] : null,
        'credit_limit'   => ($_POST['credit_limit'] ?? '') !== '' ? (float) $_POST['credit_limit'] : null,
        'opening_balance'=> (float) ($_POST['opening_balance'] ?? 0),
        'start_month'    => ($_POST['start_month'] ?? '') !== '' ? $_POST['start_month'] : null,
        'archived'       => isset($_POST['archived']) ? 1 : 0,
        'is_primary'     => ($kind === 'savings' && isset($_POST['is_primary'])) ? 1 : 0,
        'sort_order'     => (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($data['name'] === '') {
        header('Location: /accounts?msg=name_required');
        exit;
    }
    if ($kind !== 'savings') {
        $data['opening_balance'] = (float) ($_POST['opening_balance'] ?? 0);
        $data['start_month'] = null;
    }
    if ($id > 0) {
        Account::update($id, $data);
        AppLog::info('account_updated', ['id' => $id, 'name' => $data['name'], 'kind' => $kind]);
        header('Location: /accounts?msg=updated');
    } else {
        $newId = Account::create($data);
        if ($newId > 0 && Account::activeId() === null) {
            Account::setActive($newId);
        }
        AppLog::info('account_created', ['id' => $newId, 'name' => $data['name'], 'kind' => $kind]);
        header('Location: /accounts?msg=created');
    }
    exit;
}

if ($route === 'accounts/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $id = (int) ($_POST['id'] ?? 0);
    $db = Database::getConnection();
    $txCount = 0;
    if ($id > 0) {
        $s = $db->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
        $s->execute([$id]);
        $txCount = (int) $s->fetchColumn();
    }
    if ($id > 0 && $txCount === 0) {
        Account::delete($id);
        header('Location: /accounts?msg=deleted');
    } else {
        header('Location: /accounts?msg=blocked');
    }
    exit;
}

if ($route === 'accounts/reassign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $from = (int) ($_POST['from_account_id'] ?? 0);
    $to = (int) ($_POST['to_account_id'] ?? 0);
    $catId = (int) ($_POST['category_id'] ?? 0);
    if ($from > 0 && $to > 0 && $from !== $to) {
        $db = Database::getConnection();
        $sql = "UPDATE transactions SET account_id = ? WHERE account_id = ?";
        $params = [$to, $from];
        if ($catId > 0) {
            $sql .= " AND category_id = ?";
            $params[] = $catId;
        }
        $db->prepare($sql)->execute($params);
        // Move matching commitments too when no category filter narrows it.
        if ($catId === 0) {
            $db->prepare("UPDATE commitments SET account_id = ? WHERE account_id = ?")->execute([$to, $from]);
            $db->prepare("UPDATE quick_templates SET account_id = ? WHERE account_id = ?")->execute([$to, $from]);
        }
    }
    header('Location: /accounts?msg=reassigned');
    exit;
}

if ($route === 'transfers/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {    Auth::requireLogin();
    $from = (int) ($_POST['from_account_id'] ?? 0);
    $to = (int) ($_POST['to_account_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $desc = trim($_POST['description'] ?? '');
    $kind = ($_POST['transfer_kind'] ?? 'internal') === 'bill_payment' ? 'bill_payment' : 'internal';
    $return = $_POST['return_month'] ?? date('Y-m');
    if ($from > 0 && $to > 0 && $from !== $to && $amount > 0) {
        Transfer::create($from, $to, $amount, $date, $desc, $kind);
    }
    header('Location: /transactions?month=' . urlencode($return));
    exit;
}

if ($route === 'transfers/delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    Transfer::delete((int) ($_POST['id'] ?? 0));
    header('Location: /transactions?month=' . urlencode($_POST['return_month'] ?? date('Y-m')));
    exit;
}

if ($route === 'bills/pay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $accountId = (int) ($_POST['account_id'] ?? 0);
    $dueDate = $_POST['due_date'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $from = (int) ($_POST['from_account_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    if ($accountId > 0 && $dueDate !== '' && $amount > 0) {
        $applied = Bill::payDue($accountId, $dueDate, $amount, $from, $date);
        AppLog::info('bill_paid', ['account_id' => $accountId, 'due_date' => $dueDate, 'requested' => $amount, 'applied' => $applied, 'from' => $from]);
    }
    header('Location: /bills?msg=paid');
    exit;
}

if ($route === 'plans/settle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $from = (int) ($_POST['from_account_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    if ($planId > 0) {
        $settled = PaylaterPlan::settle($planId, $from, $date);
        AppLog::info('plan_settled', ['plan_id' => $planId, 'amount' => $settled, 'from' => $from]);
    }
    header('Location: /bills?msg=settled');
    exit;
}

if ($route === 'plans/refund' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    if ($planId > 0 && $amount > 0) {
        $refunded = PaylaterPlan::refund($planId, $amount, $date);
        AppLog::info('plan_refunded', ['plan_id' => $planId, 'amount' => $refunded]);
    }
    header('Location: /bills?msg=refunded');
    exit;
}

if ($route === 'quick-template/add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireLogin();
    $type = $_POST['type'] ?? 'expense';
    $desc = trim($_POST['description'] ?? '');
    $catId = (int) ($_POST['category_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $accountId = (int) ($_POST['account_id'] ?? 0);
    if ($desc !== '' && in_array($type, ['income', 'expense'], true)) {
        QuickTemplate::create($type, $desc, $catId > 0 ? $catId : null, $amount, $accountId > 0 ? $accountId : null);
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
    'settings/duplicates' => 'duplicates.php',
    'accounts' => 'accounts.php',
    'bills' => 'bills.php',
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
