<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// اطلاعات اتصال به دیتابیس (برای Render بعداً عوض میشه)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'barber_db';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ساخت جدول
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id SERIAL PRIMARY KEY,
        code VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        service VARCHAR(200) NOT NULL,
        requestedBarber VARCHAR(100) NOT NULL,
        requestedDate VARCHAR(20) NOT NULL,
        requestedTime VARCHAR(10) NOT NULL,
        finalBarber VARCHAR(100) DEFAULT NULL,
        finalDate VARCHAR(20) DEFAULT NULL,
        finalTime VARCHAR(10) DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
} catch(PDOException $e) {
    echo json_encode(['error' => 'خطا در اتصال به دیتابیس: ' . $e->getMessage()]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add') {
    $code = generateUniqueCode($pdo);
    $stmt = $pdo->prepare("INSERT INTO appointments (code, name, phone, service, requestedBarber, requestedDate, requestedTime, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$code, $input['name'], $input['phone'], $input['service'], 
                    $input['requestedBarber'], $input['requestedDate'], $input['requestedTime']]);
    echo json_encode(['success' => true, 'code' => $code]);
    
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'pending') {
    $stmt = $pdo->query("SELECT * FROM appointments WHERE status = 'pending' ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'approved') {
    $stmt = $pdo->query("SELECT * FROM appointments WHERE status = 'approved' ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'track' && isset($_GET['code'])) {
    $code = $_GET['code'];
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE code = ?");
    $stmt->execute([$code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($result ? $result : ['error' => 'کد معتبر نیست']);
    
} elseif ($method === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'approve') {
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'approved', finalBarber = ?, finalDate = ?, finalTime = ? WHERE id = ?");
    $stmt->execute([$input['finalBarber'], $input['finalDate'], $input['finalTime'], $input['id']]);
    echo json_encode(['success' => true]);
    
} elseif ($method === 'DELETE' && isset($_GET['action']) && $_GET['action'] === 'reject') {
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$input['id']]);
    echo json_encode(['success' => true]);
    
} else {
    echo json_encode(['error' => 'درخواست نامعتبر']);
}

function generateUniqueCode($pdo) {
    do {
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn();
    } while($exists > 0);
    return $code;
}
?>