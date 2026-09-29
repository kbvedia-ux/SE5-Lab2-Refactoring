<?php

$sessionPath = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = "127.0.0.1";
$user = "root";
$pass = "admin123";
$dbname = "bh_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

function dbColumnExists(string $table, string $column): bool
{
    global $conn, $dbname;

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('sss', $dbname, $table, $column);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count > 0;
}

function ensureApplicationSchema(): void
{
    global $conn;

    $userColumns = [
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL AFTER email",
        'role' => "ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'landlord' AFTER password",
        'account_status' => "ALTER TABLE users ADD COLUMN account_status VARCHAR(30) NOT NULL DEFAULT 'Pending' AFTER role",
        'reviewed_at' => "ALTER TABLE users ADD COLUMN reviewed_at DATETIME NULL AFTER account_status",
        'review_notes' => "ALTER TABLE users ADD COLUMN review_notes TEXT NULL AFTER reviewed_at",
    ];

    $houseColumns = [
        'application_type' => "ALTER TABLE boarding_houses ADD COLUMN application_type VARCHAR(30) NOT NULL DEFAULT 'New' AFTER status",
        'description' => "ALTER TABLE boarding_houses ADD COLUMN description TEXT NULL AFTER contact_number",
        'room_limit' => "ALTER TABLE boarding_houses ADD COLUMN room_limit INT NOT NULL DEFAULT 0 AFTER total_rooms",
        'reviewed_at' => "ALTER TABLE boarding_houses ADD COLUMN reviewed_at DATETIME NULL AFTER accreditation_expiry",
        'review_notes' => "ALTER TABLE boarding_houses ADD COLUMN review_notes TEXT NULL AFTER reviewed_at",
    ];

    $roomColumns = [
        'remarks' => "ALTER TABLE rooms ADD COLUMN remarks TEXT NULL AFTER safety_status",
    ];

    $documentColumns = [
        'original_file_name' => "ALTER TABLE accreditation_documents ADD COLUMN original_file_name VARCHAR(255) NULL AFTER file_name",
        'requested_room_limit' => "ALTER TABLE accreditation_documents ADD COLUMN requested_room_limit INT NULL AFTER document_type",
        'approved_room_limit' => "ALTER TABLE accreditation_documents ADD COLUMN approved_room_limit INT NULL AFTER requested_room_limit",
        'review_notes' => "ALTER TABLE accreditation_documents ADD COLUMN review_notes TEXT NULL AFTER status",
        'reviewed_at' => "ALTER TABLE accreditation_documents ADD COLUMN reviewed_at DATETIME NULL AFTER review_notes",
    ];

    $paymentColumns = [
        'tenant_id' => "ALTER TABLE payments ADD COLUMN tenant_id INT NULL AFTER id",
        'rent_due' => "ALTER TABLE payments ADD COLUMN rent_due DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER tenant_name",
        'amount_paid' => "ALTER TABLE payments ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER rent_due",
        'balance' => "ALTER TABLE payments ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER amount_paid",
        'billing_month' => "ALTER TABLE payments ADD COLUMN billing_month DATE NULL AFTER balance",
        'notes' => "ALTER TABLE payments ADD COLUMN notes TEXT NULL AFTER status",
    ];

    $banlistColumns = [
        'landlord_id' => "ALTER TABLE banlist ADD COLUMN landlord_id INT NULL AFTER id",
        'status' => "ALTER TABLE banlist ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Published' AFTER date_reported",
    ];

    $allColumns = [
        'users'                  => $userColumns,
        'boarding_houses'        => $houseColumns,
        'rooms'                  => $roomColumns,
        'accreditation_documents' => $documentColumns,
        'payments'               => $paymentColumns,
        'banlist'                => $banlistColumns,
    ];

    foreach ($allColumns as $table => $columns) {
        foreach ($columns as $column => $sql) {
            if (!dbColumnExists($table, $column)) {
                $conn->query($sql);
            }
        }
    }
    $conn->query(
        "CREATE TABLE IF NOT EXISTS tenants (
            id INT NOT NULL AUTO_INCREMENT,
            landlord_id INT NOT NULL,
            room_id INT NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            course VARCHAR(150) NULL,
            email VARCHAR(120) NULL,
            phone VARCHAR(50) NULL,
            move_in_date DATE NOT NULL,
            monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'GCash',
            status VARCHAR(30) NOT NULL DEFAULT 'Active',
            moved_out_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY landlord_id (landlord_id),
            KEY room_id (room_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query("ALTER TABLE payments MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
    $conn->query(
        "UPDATE boarding_houses bh
         LEFT JOIN (
             SELECT boarding_house_id, COUNT(*) AS room_count
             FROM rooms
             GROUP BY boarding_house_id
         ) room_totals ON room_totals.boarding_house_id = bh.id
         SET bh.room_limit = GREATEST(COALESCE(room_totals.room_count, 0), COALESCE(bh.total_rooms, 0))
         WHERE bh.status = 'Accredited' AND bh.room_limit = 0"
    );
}

ensureApplicationSchema();

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your session expired. Please refresh the page and try again.');
    }
}

function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function pullFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function currentLandlord(): ?array
{
    global $conn;

    $userId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($userId < 1) {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, full_name, email, phone, account_status, created_at FROM users WHERE id = ? AND role = ?');
    $role = 'landlord';
    $stmt->bind_param('is', $userId, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $user;
}

function requireLandlord(): void
{
    $user = currentLandlord();
    if (!$user || strcasecmp($user['account_status'], 'Approved') !== 0) {
        unset($_SESSION['landlord_id']);
        setFlash('Please sign in with an approved landlord account.', 'error');
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (!currentAdmin()) {
        unset($_SESSION['admin_id'], $_SESSION['admin_authenticated'], $_SESSION['admin_name']);
        header('Location: admin_login.php');
        exit;
    }
}

function currentAdmin(): ?array
{
    global $conn;

    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId < 1) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT id, full_name, email, phone, role, account_status
         FROM users
         WHERE id = ? AND role IN ('admin', 'officer', 'ua_admin')
         LIMIT 1"
    );
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $admin;
}

function getBoardingHouses(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($landlordId < 1) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT bh.*,
                COUNT(r.id) AS room_count,
                COALESCE(SUM(r.status = 'Vacant'), 0) AS vacant_count,
                COALESCE(SUM(r.status = 'Occupied'), 0) AS tenant_count
         FROM boarding_houses bh
         LEFT JOIN rooms r ON r.boarding_house_id = bh.id
         WHERE bh.landlord_id = ?
         GROUP BY bh.id
         ORDER BY bh.created_at DESC"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'address' => $row['address'],
            'status' => $row['status'],
            'rooms' => (int) $row['room_count'],
            'vacant' => (int) $row['vacant_count'],
            'tenants' => (int) $row['tenant_count'],
            'expiry' => $row['accreditation_expiry'] ? date('M d, Y', strtotime($row['accreditation_expiry'])) : '—',
            'approved' => $row['date_approved'] ? date('M d, Y', strtotime($row['date_approved'])) : '—',
            'contact' => $row['contact_number'] ?: '—',
            'description' => $row['description'] ?: '',
            'application_type' => $row['application_type'],
            'room_limit' => (int) $row['room_limit'],
            'review_notes' => $row['review_notes'] ?: '',
        ];
    }, $rows);
}

function getRooms(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($landlordId < 1) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT r.*, bh.name AS house
         FROM rooms r
         INNER JOIN boarding_houses bh ON bh.id = r.boarding_house_id
         WHERE bh.landlord_id = ? AND bh.status = 'Accredited'
         ORDER BY bh.name, r.room_number"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'boarding_house_id' => (int) $row['boarding_house_id'],
            'number' => $row['room_number'],
            'house' => $row['house'],
            'status' => $row['status'],
            'occupant' => $row['occupant'] ?: '—',
            'rent' => number_format((float) $row['monthly_rent'], 2),
            'since' => date('M Y', strtotime($row['created_at'])),
            'size' => $row['room_size'] ?: '—',
            'windows' => (string) $row['windows'],
            'fire_alarm' => $row['fire_alarm'],
            'emergency_exit' => $row['emergency_exit'],
            'own_cr' => $row['own_cr'],
            'safety' => $row['safety_status'],
            'remarks' => $row['remarks'] ?: '',
        ];
    }, $rows);
}

function getDocuments(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($landlordId < 1) {
        return [];
    }

    $stmt = $conn->prepare(
        'SELECT d.*, bh.name AS house
         FROM accreditation_documents d
         INNER JOIN boarding_houses bh ON bh.id = d.boarding_house_id
         WHERE bh.landlord_id = ?
         ORDER BY d.created_at DESC'
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['document_name'],
            'file' => $row['original_file_name'] ?: ($row['file_name'] ?: '—'),
            'stored_file' => $row['file_name'] ?: '',
            'house' => $row['house'],
            'type' => $row['document_type'],
            'requested_room_limit' => $row['requested_room_limit'] !== null ? (int) $row['requested_room_limit'] : null,
            'approved_room_limit' => $row['approved_room_limit'] !== null ? (int) $row['approved_room_limit'] : null,
            'uploaded' => $row['uploaded_date'] ? date('M d, Y', strtotime($row['uploaded_date'])) : '—',
            'expiry' => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '—',
            'status' => $row['status'],
            'review_notes' => $row['review_notes'] ?: '',
        ];
    }, $rows);
}

function getPayments(): array
{
    return [
        ['tenant' => 'Juan Dela Cruz', 'room' => '101', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '4,500', 'balance' => '-', 'date' => 'Jun 01, 2025', 'method' => 'GCash', 'status' => 'Paid'],
        ['tenant' => 'Ana Reyes', 'room' => '102', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '4,500', 'balance' => '-', 'date' => 'Jun 02, 2025', 'method' => 'Bank Transfer', 'status' => 'Paid'],
        ['tenant' => 'Lorna Villanueva', 'room' => '301', 'house' => 'Mariposa Lodge', 'month' => 'June 2025', 'due' => '5,000', 'paid' => '5,000', 'balance' => '-', 'date' => 'Jun 03, 2025', 'method' => 'Cash', 'status' => 'Paid'],
        ['tenant' => 'Maria Reyes', 'room' => '201', 'house' => 'Dela Cruz Lodge', 'month' => 'June 2025', 'due' => '3,800', 'paid' => '3,800', 'balance' => '-', 'date' => 'Jun 01, 2025', 'method' => 'GCash', 'status' => 'Paid'],
        ['tenant' => 'Pedro Ramos', 'room' => '202', 'house' => 'Dela Cruz Lodge', 'month' => 'June 2025', 'due' => '3,800', 'paid' => '2,000', 'balance' => '1,800', 'date' => 'Jun 02, 2025', 'method' => 'Bank Transfer', 'status' => 'Partial'],
        ['tenant' => 'Pedro Ramos', 'room' => '302', 'house' => 'Mariposa Lodge', 'month' => 'June 2025', 'due' => '5,000', 'paid' => '0', 'balance' => '5,000', 'date' => 'Not yet paid', 'method' => '-', 'status' => 'Pending'],
        ['tenant' => '-', 'room' => '103', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '0', 'balance' => '4,500', 'date' => 'Not yet paid', 'method' => '-', 'status' => 'Overdue'],
        ['tenant' => '-', 'room' => '104', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '0', 'balance' => '4,500', 'date' => 'Not yet paid', 'method' => '-', 'status' => 'Overdue'],
        ['tenant' => 'Alfredo Gomez', 'room' => '105', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '4,500', 'balance' => '-', 'date' => 'Jun 01, 2025', 'method' => 'Cash', 'status' => 'Paid'],
        ['tenant' => '-', 'room' => '203', 'house' => 'Dela Cruz Lodge', 'month' => 'June 2025', 'due' => '3,800', 'paid' => '0', 'balance' => '3,800', 'date' => 'Not yet paid', 'method' => '-', 'status' => 'Overdue'],
        ['tenant' => '-', 'room' => '303', 'house' => 'Mariposa Lodge', 'month' => 'June 2025', 'due' => '5,000', 'paid' => '0', 'balance' => '5,000', 'date' => 'Not yet paid', 'method' => '-', 'status' => 'Overdue'],
        ['tenant' => 'Elena Garcia', 'room' => '106', 'house' => 'Sunshine Boarding House', 'month' => 'June 2025', 'due' => '4,500', 'paid' => '2,500', 'balance' => '2,000', 'date' => 'Jun 04, 2025', 'method' => 'GCash', 'status' => 'Partial'],
    ];
}

function getTenants(): array
{
    return [
        ['name' => 'Jdji Nmf', 'course' => 'Baco Comca - 3rd', 'email' => 'zzzz', 'phone' => 'hood', 'room' => '102', 'house' => 'Sunshine Boarding House', 'move_in' => '2025-06-17', 'rent' => '4,500', 'status' => 'Active'],
        ['name' => 'Juan Dela Cruz', 'course' => 'BS Computer Science - 3rd Year', 'email' => 'juan.delacruz@ua.edu.ph', 'phone' => '0917-555-1001', 'room' => '101', 'house' => 'Sunshine Boarding House', 'move_in' => 'Jan 15, 2025', 'rent' => '4,500', 'status' => 'Active'],
        ['name' => 'Maria Santos', 'course' => 'BS Nursing - 3rd Year', 'email' => 'maria.santos@ua.edu.ph', 'phone' => '0917-555-1002', 'room' => '102', 'house' => 'Sunshine Boarding House', 'move_in' => 'Jan 20, 2025', 'rent' => '4,500', 'status' => 'Active'],
        ['name' => 'Pedro Ramos', 'course' => 'BS Electrical Engineering - 2nd Year', 'email' => 'pedro.ramos@ua.edu.ph', 'phone' => '0917-555-1003', 'room' => '202', 'house' => 'Dela Cruz Lodge', 'move_in' => 'Feb 01, 2025', 'rent' => '4,000', 'status' => 'Active'],
        ['name' => 'Ana Garcia', 'course' => 'BS Education - 3rd Year', 'email' => 'ana.garcia@ua.edu.ph', 'phone' => '0917-555-1004', 'room' => '103', 'house' => 'Sunshine Boarding House', 'move_in' => 'Jan 10, 2025', 'rent' => '4,500', 'status' => 'Active'],
        ['name' => 'Carlos Mendoza', 'course' => 'BS Mechanical Engineering - 2nd Year', 'email' => 'carlos.mendoza@ua.edu.ph', 'phone' => '0917-555-1005', 'room' => '201', 'house' => 'Dela Cruz Lodge', 'move_in' => 'Feb 03, 2025', 'rent' => '4,000', 'status' => 'Active'],
        ['name' => 'Sofia Reyes', 'course' => 'BS Accountancy - 4th Year', 'email' => 'sofia.reyes@ua.edu.ph', 'phone' => '0917-555-1006', 'room' => '301', 'house' => 'Mariposa Lodge', 'move_in' => 'Mar 01, 2025', 'rent' => '5,000', 'status' => 'Active'],
        ['name' => 'Miguel Torres', 'course' => 'BS Civil Engineering - 4th Year', 'email' => 'miguel.torres@ua.edu.ph', 'phone' => '0917-555-1007', 'room' => '302', 'house' => 'Mariposa Lodge', 'move_in' => 'Mar 10, 2025', 'rent' => '5,000', 'status' => 'Moving Out'],
        ['name' => 'Isabel Cruz', 'course' => 'BS Psychology - 2nd Year', 'email' => 'isabel.cruz@ua.edu.ph', 'phone' => '0917-555-1008', 'room' => '104', 'house' => 'Sunshine Boarding House', 'move_in' => 'Jan 22, 2025', 'rent' => '4,500', 'status' => 'On Hold'],
    ];
}

function getBanlist(): array
{
    return [
        ['name' => 'Mark Villanueva', 'severity' => 'High', 'reason' => 'Property Damage', 'details' => 'Caused significant damage to room furniture and walls.', 'house' => 'Dela Cruz Lodge'],
        ['name' => 'Kristine Abad', 'severity' => 'High', 'reason' => 'Non-Payment', 'details' => 'Left without paying 3 months of rent.', 'house' => 'Sunshine Boarding House'],
        ['name' => 'Ryan Tolentino', 'severity' => 'Medium', 'reason' => 'Company Refusal', 'details' => 'Repeatedly disturbed other tenants.', 'house' => 'Mariposa Lodge'],
        ['name' => 'Sheila Magno', 'severity' => 'Low', 'reason' => 'Unauthorized Guests', 'details' => 'Frequently brought unauthorized guests overnight.', 'house' => 'Dawn Valley Boarding'],
        ['name' => 'Dennis Ocampo', 'severity' => 'High', 'reason' => 'Theft', 'details' => 'Reported for stealing personal belongings from other tenants.', 'house' => 'Laguna Boarding House'],
    ];
}

function getDatabasePayments(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($landlordId < 1) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT p.*, r.room_number, bh.name AS house
         FROM payments p
         INNER JOIN rooms r ON r.id = p.room_id
         INNER JOIN boarding_houses bh ON bh.id = r.boarding_house_id
         WHERE bh.landlord_id = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'tenant' => $row['tenant_name'] ?: '—',
            'room' => $row['room_number'],
            'house' => $row['house'],
            'month' => $row['billing_month'] ? date('F Y', strtotime($row['billing_month'])) : date('F Y', strtotime($row['created_at'])),
            'due' => number_format((float) $row['rent_due'], 2),
            'due_value' => (float) $row['rent_due'],
            'paid' => number_format((float) $row['amount_paid'], 2),
            'paid_value' => (float) $row['amount_paid'],
            'balance' => number_format((float) $row['balance'], 2),
            'balance_value' => (float) $row['balance'],
            'date' => $row['payment_date'] ? date('M d, Y', strtotime($row['payment_date'])) : 'Not yet paid',
            'method' => $row['method'] ?: '—',
            'status' => $row['status'],
            'notes' => $row['notes'] ?: '',
        ];
    }, $rows);
}

function getDatabaseTenants(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    if ($landlordId < 1) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT t.*, r.room_number, r.boarding_house_id, bh.name AS house
         FROM tenants t
         INNER JOIN rooms r ON r.id = t.room_id
         INNER JOIN boarding_houses bh ON bh.id = r.boarding_house_id
         WHERE t.landlord_id = ?
         ORDER BY t.created_at DESC"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'room_id' => (int) $row['room_id'],
            'boarding_house_id' => (int) $row['boarding_house_id'],
            'name' => $row['full_name'],
            'course' => $row['course'] ?: '—',
            'email' => $row['email'] ?: '—',
            'phone' => $row['phone'] ?: '—',
            'room' => $row['room_number'],
            'house' => $row['house'],
            'move_in' => date('M d, Y', strtotime($row['move_in_date'])),
            'move_in_value' => $row['move_in_date'],
            'rent' => number_format((float) $row['monthly_rent'], 2),
            'rent_value' => (float) $row['monthly_rent'],
            'payment_method' => $row['payment_method'],
            'status' => $row['status'],
        ];
    }, $rows);
}

function getDatabaseBanlist(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT b.*, u.full_name AS reporter_name
         FROM banlist b
         LEFT JOIN users u ON u.id = b.landlord_id
         WHERE b.status = 'Published'
         ORDER BY b.created_at DESC"
    );
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'name' => $row['tenant_name'],
            'age' => $row['age'] !== null ? (int) $row['age'] : null,
            'severity' => $row['severity'],
            'reason' => $row['reason'],
            'details' => $row['description'] ?: '—',
            'house' => $row['boarding_house'] ?: '—',
            'reported_by' => $row['reporter_name'] ?: ($row['reported_by'] ?: 'Landlord'),
            'date_reported' => $row['date_reported'] ? date('M d, Y', strtotime($row['date_reported'])) : date('M d, Y', strtotime($row['created_at'])),
        ];
    }, $rows);
}

function getAdminPendingDocuments(?string $category = null, ?string $status = null): array
{
    global $conn;

    $where = '1 = 1';
    if (in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
        $safeStatus = $conn->real_escape_string($status);
        $where .= " AND d.status = '$safeStatus'";
    }
    if ($category === 'accreditation') {
        $where .= " AND d.document_type = 'Accreditation'";
    } elseif ($category === 'documents') {
        $where .= " AND d.document_type = 'Legal'";
    } elseif ($category === 'safety') {
        $where .= " AND d.document_type IN ('Safety', 'Health')";
    }

    $result = $conn->query(
        "SELECT d.*, bh.name AS house, bh.address, u.full_name AS landlord,
                u.email AS landlord_email, u.phone AS landlord_phone
         FROM accreditation_documents d
         INNER JOIN boarding_houses bh ON bh.id = d.boarding_house_id
         INNER JOIN users u ON u.id = bh.landlord_id
         WHERE $where
         ORDER BY d.created_at ASC"
    );

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPendingLandlordRegistrations(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT u.id, u.full_name, u.email, u.phone, u.created_at,
                bh.id AS house_id, bh.name AS house, bh.address
         FROM users u
         LEFT JOIN boarding_houses bh ON bh.landlord_id = u.id
         WHERE u.role = 'landlord' AND u.account_status = 'Pending'
         ORDER BY u.created_at ASC"
    );

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAdminDocumentReviewHistory(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT d.id, d.document_name, d.document_type, d.requested_room_limit, d.approved_room_limit, d.status, d.review_notes,
                d.reviewed_at, d.original_file_name, d.file_name,
                bh.id AS house_id, bh.name AS house,
                u.full_name AS landlord, u.email
         FROM accreditation_documents d
         INNER JOIN boarding_houses bh ON bh.id = d.boarding_house_id
         INNER JOIN users u ON u.id = bh.landlord_id
         WHERE d.status IN ('Approved', 'Rejected')
         ORDER BY d.reviewed_at DESC, d.created_at DESC"
    );

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAdminRegistrationReviewHistory(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT u.id, u.full_name, u.email, u.phone, u.account_status AS status,
                u.reviewed_at, u.review_notes,
                MIN(bh.id) AS house_id, MIN(bh.name) AS house, MIN(bh.address) AS address
         FROM users u
         LEFT JOIN boarding_houses bh ON bh.landlord_id = u.id
         WHERE u.role = 'landlord'
           AND u.account_status IN ('Approved', 'Rejected')
           AND u.reviewed_at IS NOT NULL
         GROUP BY u.id
         ORDER BY u.reviewed_at DESC"
    );

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAdminDocumentCounts(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT
            COALESCE(SUM(status = 'Pending' AND document_type = 'Accreditation'), 0) AS accreditation,
            COALESCE(SUM(status = 'Pending' AND document_type = 'Legal'), 0) AS documents,
            COALESCE(SUM(status = 'Pending' AND document_type IN ('Safety', 'Health')), 0) AS safety
         FROM accreditation_documents"
    );

    $row = $result ? $result->fetch_assoc() : [];
    return [
        'accreditation' => (int) ($row['accreditation'] ?? 0),
        'documents' => (int) ($row['documents'] ?? 0),
        'safety' => (int) ($row['safety'] ?? 0),
    ];
}

function getHouseDocumentsForAdmin(int $houseId): array
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT id, document_name, original_file_name, file_name, document_type,
                uploaded_date, expiry_date, status
         FROM accreditation_documents
         WHERE boarding_house_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param('i', $houseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getNotifications(): array
{
    return [
        ['icon' => 'circle-check', 'text' => 'Your accreditation for Sunshine Boarding House has been approved!', 'time' => '10 minutes ago', 'tag' => 'Approved', 'color' => 'green'],
        ['icon' => 'door-open', 'text' => 'Room 104 at Sunshine BH is now vacant. You can now accept new tenants.', 'time' => '2 hours ago', 'tag' => 'Room Update', 'color' => 'green'],
        ['icon' => 'file-lines', 'text' => 'Reminder: Fire safety certificate for Sunshine BH expires in 50 days.', 'time' => 'Yesterday', 'tag' => 'Reminder', 'color' => 'yellow'],
        ['icon' => 'comment-dots', 'text' => 'Admin sent you a message about your pending accreditation documents.', 'time' => '2 days ago', 'tag' => 'Message', 'color' => 'blue'],
        ['icon' => 'triangle-exclamation', 'text' => 'URGENT: Mariposa Lodge accreditation expires in 25 days.', 'time' => '4 days ago', 'tag' => 'Urgent', 'color' => 'red'],
        ['icon' => 'peso-sign', 'text' => 'Payment of &#8369;4,500 received from Juan Dela Cruz for Room 101.', 'time' => '4 days ago', 'tag' => 'Payment', 'color' => 'green'],
    ];
}

function getHouseNames(): array
{
    return array_column(getBoardingHouses(), 'name');
}

function getAccreditedBoardingHouses(): array
{
    return array_values(array_filter(
        getBoardingHouses(),
        static fn(array $house): bool => $house['status'] === 'Accredited'
    ));
}

function getDashboardStats(): array
{
    global $conn;

    $landlordId = (int) ($_SESSION['landlord_id'] ?? 0);
    $stats = [
        'houses' => 0,
        'rooms' => 0,
        'vacant' => 0,
        'occupied' => 0,
        'occupancy_rate' => 0,
        'pending_documents' => 0,
        'monthly_income' => 0.0,
        'active_tenants' => 0,
    ];

    if ($landlordId < 1) {
        return $stats;
    }

    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT bh.id) AS houses,
                COUNT(r.id) AS rooms,
                COALESCE(SUM(r.status = 'Vacant'), 0) AS vacant,
                COALESCE(SUM(r.status = 'Occupied'), 0) AS occupied
         FROM boarding_houses bh
         LEFT JOIN rooms r ON r.boarding_house_id = bh.id
         WHERE bh.landlord_id = ? AND bh.status = 'Accredited'"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS active_tenants,
                COALESCE(SUM(monthly_rent), 0) AS monthly_income
         FROM tenants
         WHERE landlord_id = ? AND status = 'Active'"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $tenantRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS pending_documents
         FROM accreditation_documents d
         INNER JOIN boarding_houses bh ON bh.id = d.boarding_house_id
         WHERE bh.landlord_id = ? AND d.status = 'Pending'"
    );
    $stmt->bind_param('i', $landlordId);
    $stmt->execute();
    $documentRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stats['houses'] = (int) ($row['houses'] ?? 0);
    $stats['rooms'] = (int) ($row['rooms'] ?? 0);
    $stats['vacant'] = (int) ($row['vacant'] ?? 0);
    $stats['occupied'] = (int) ($row['occupied'] ?? 0);
    $stats['active_tenants'] = (int) ($tenantRow['active_tenants'] ?? 0);
    $stats['monthly_income'] = (float) ($tenantRow['monthly_income'] ?? 0);
    $stats['occupancy_rate'] = $stats['rooms'] > 0
        ? (int) round(($stats['occupied'] / $stats['rooms']) * 100)
        : 0;
    $stats['pending_documents'] = (int) ($documentRow['pending_documents'] ?? 0);

    return $stats;
}

function getAdminStats(): array
{
    global $conn;

    $pending = 0;
    $approved = (int) ($conn->query("SELECT COUNT(*) AS total FROM boarding_houses WHERE status = 'Accredited' AND DATE(reviewed_at) = CURDATE()")->fetch_assoc()['total'] ?? 0);
    $rejected = (int) ($conn->query("SELECT COUNT(*) AS total FROM boarding_houses WHERE status = 'Rejected' AND DATE(reviewed_at) = CURDATE()")->fetch_assoc()['total'] ?? 0);
    $expiring = (int) ($conn->query("SELECT COUNT(*) AS total FROM boarding_houses WHERE status = 'Accredited' AND accreditation_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0);

    return [
        ['label' => 'Pending Reviews', 'value' => (string) $pending, 'icon' => 'fa-file-lines', 'tone' => 'orange', 'note' => 'View all'],
        ['label' => 'Approved Today', 'value' => (string) $approved, 'icon' => 'fa-check', 'tone' => 'green', 'note' => 'Today'],
        ['label' => 'Rejected Today', 'value' => (string) $rejected, 'icon' => 'fa-circle-xmark', 'tone' => 'red', 'note' => 'Today'],
        ['label' => 'Expiring Soon', 'value' => (string) $expiring, 'icon' => 'fa-fire', 'tone' => 'amber', 'note' => 'Next 30 days'],
    ];
}

function getAdminApplications(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT bh.id, bh.landlord_id, bh.name AS house, bh.address, bh.contact_number,
                bh.description, bh.application_type, bh.created_at,
                u.full_name AS landlord, u.email, u.phone,
                COUNT(DISTINCT r.id) AS rooms,
                COALESCE(ROUND(AVG(CASE
                    WHEN r.fire_alarm = 'Yes' AND r.emergency_exit = 'Yes' THEN 100
                    WHEN r.fire_alarm = 'Yes' OR r.emergency_exit = 'Yes' THEN 50
                    ELSE 0 END)), 0) AS score,
                GROUP_CONCAT(DISTINCT d.document_name ORDER BY d.document_name SEPARATOR '||') AS documents
         FROM boarding_houses bh
         INNER JOIN users u ON u.id = bh.landlord_id
         LEFT JOIN rooms r ON r.boarding_house_id = bh.id
         LEFT JOIN accreditation_documents d ON d.boarding_house_id = bh.id
         WHERE bh.status = 'Pending'
           AND u.account_status = 'Approved'
         GROUP BY bh.id
         ORDER BY bh.created_at ASC"
    );

    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    return array_map(static function (array $row): array {
        $rooms = (int) $row['rooms'];
        return [
            'id' => (int) $row['id'],
            'landlord_id' => (int) $row['landlord_id'],
            'house' => $row['house'],
            'landlord' => $row['landlord'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?: '—',
            'type' => $row['application_type'],
            'address' => $row['address'],
            'contact' => $row['contact_number'] ?: '—',
            'rooms' => $rooms,
            'capacity' => $rooms * 2,
            'submitted' => date('M d, Y', strtotime($row['created_at'])),
            'score' => (int) $row['score'],
            'note' => $row['description'] ?: 'Landlord registration or boarding-house accreditation request.',
            'docs' => $row['documents'] ? explode('||', $row['documents']) : [],
        ];
    }, $rows);
}

function getAdminReviewHistory(): array
{
    global $conn;

    $result = $conn->query(
        "SELECT bh.id, bh.name AS house, bh.address, bh.contact_number, bh.application_type,
                bh.status, bh.reviewed_at, bh.review_notes, bh.date_approved,
                u.full_name AS landlord, u.email, u.phone,
                COUNT(r.id) AS rooms
         FROM boarding_houses bh
         INNER JOIN users u ON u.id = bh.landlord_id
         LEFT JOIN rooms r ON r.boarding_house_id = bh.id
         WHERE bh.status IN ('Pending', 'Accredited', 'Rejected')
         GROUP BY bh.id
         ORDER BY bh.reviewed_at DESC, bh.created_at DESC"
    );

    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    return array_map(static function (array $row): array {
        $approved = $row['status'] === 'Accredited';
        $decision = match ($row['status']) {
            'Accredited' => 'Approved',
            'Rejected' => 'Rejected',
            default => 'Pending',
        };
        return [
            'id' => (int) $row['id'],
            'house' => $row['house'],
            'landlord' => $row['landlord'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?: '—',
            'address' => $row['address'],
            'contact' => $row['contact_number'] ?: '—',
            'rooms' => (int) $row['rooms'],
            'type' => 'New',
            'decision' => $decision,
            'date' => $row['reviewed_at'] ? date('M d, Y', strtotime($row['reviewed_at'])) : 'Not reviewed',
            'notes' => $row['review_notes'] ?: 'No review notes.',
        ];
    }, $rows);
}

function getAdminConversations(): array
{
    return [
        ['name' => 'Juan Dela Cruz', 'initials' => 'JDC', 'preview' => 'Yes, I already uploaded the sanitary permit.', 'time' => '10:30 AM', 'unread' => 2, 'online' => true],
        ['name' => 'Pedro Mariposa', 'initials' => 'PM', 'preview' => 'When can we expect the inspection results?', 'time' => 'Yesterday', 'unread' => 0, 'online' => false],
        ['name' => 'Elena Garcia', 'initials' => 'EG', 'preview' => 'I will submit the missing documents today.', 'time' => 'Jun 12', 'unread' => 1, 'online' => false],
    ];
}

?>
