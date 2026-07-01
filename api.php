<?php
// ==========================================
// 1. 设置响应头 (保持不变)
// ==========================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 处理浏览器预检请求 (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==========================================
// 2. PHP驱动自检
// ==========================================
if (!extension_loaded('pdo_mysql')) {
    header('Content-Type: application/json');
    echo json_encode([
        "error" => "PHP扩展缺失",
        "message" => "pdo_mysql 扩展未安装或未启用",
        "available_pdo_drivers" => PDO::getAvailableDrivers(),
        "loaded_extensions" => extension_loaded('pdo') ? "pdo已加载" : "pdo未加载",
        "hint" => "请检查 Dockerfile 中是否安装了 pdo_mysql 扩展"
    ]);
    exit;
}

// ==========================================
// 3. 核心修改：环境变量读取 + 强制调试输出
// ==========================================

// 尝试多种方式获取变量
$host = getenv('MYSQLHOST') ?: ($_ENV['MYSQLHOST'] ?? $_SERVER['MYSQLHOST'] ?? '');
$user = getenv('MYSQLUSER') ?: ($_ENV['MYSQLUSER'] ?? $_SERVER['MYSQLUSER'] ?? '');
$pass = getenv('MYSQLPASSWORD') ?: ($_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? '');
$db   = getenv('MYSQLDATABASE') ?: ($_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? '');
$port = getenv('MYSQLPORT') ?: ($_ENV['MYSQLPORT'] ?? $_SERVER['MYSQLPORT'] ?? '3306');

// if (!$host || !$user || !$db) {
//     header('Content-Type: application/json');
//     echo json_encode([
//         "error" => "环境变量缺失",
//         "debug_info" => [
//             "host" => $host ? "已获取(长度:" . strlen($host) . ")" : "空",
//             "user" => $user ? "已获取(长度:" . strlen($user) . ")" : "空",
//             "db"   => $db ? "已获取(长度:" . strlen($db) . ")" : "空",
//             "port" => $port
//         ],
//         "hint" => "请检查 Railway Variables 面板中的变量名是否完全一致（注意大小写）"
//     ]);
//     exit;
// }

// 构建 DSN
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 捕获连接错误
    echo json_encode([
        "error" => "数据库连接失败",
        "message" => $e->getMessage(), // 这里会显示具体的拒绝原因
        "dsn_hint" => "Host: $host, DB: $db"
    ]);
    exit;
}

// ==========================================
// 3. 业务逻辑处理
// ==========================================
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        getList($pdo);
        break;
    case 'add':
        addTask($pdo);
        break;
    case 'update':
        updateTask($pdo);
        break;
    case 'delete':
        deleteTask($pdo);
        break;
    default:
        echo json_encode(["error" => "无效的操作类型"]);
}

// --- 功能函数 ---

function getList($pdo) {
    try {
        // 假设你的表叫 tasks，状态字段叫 is_completed
        $stmt = $pdo->query("SELECT id, title, is_completed as status FROM tasks ORDER BY id DESC");
        $tasks = $stmt->fetchAll();
        echo json_encode($tasks);
    } catch (PDOException $e) {
        echo json_encode(["error" => "查询失败: " . $e->getMessage()]);
    }
}

function addTask($pdo) {
    // 获取 POST 原始数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['title'])) {
        echo json_encode(["error" => "标题不能为空"]);
        return;
    }

    $title = trim($data['title']);
    $sql = "INSERT INTO tasks (title, is_completed) VALUES (:title, 0)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute(['title' => $title]);
        echo json_encode(["message" => "添加成功", "id" => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "写入数据库失败: " . $e->getMessage()]);
    }
}

function updateTask($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;

    if ($id === null || $status === null) {
        echo json_encode(["error" => "参数缺失"]);
        return;
    }

    $sql = "UPDATE tasks SET is_completed = :status WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => $status, 'id' => $id]);
    echo json_encode(["message" => "更新成功"]);
}

function deleteTask($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        echo json_encode(["error" => "ID缺失"]);
        return;
    }

    $sql = "DELETE FROM tasks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    echo json_encode(["message" => "删除成功"]);
}
?>