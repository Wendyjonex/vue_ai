<?php
// 1. 设置响应头 (保持不变)
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
// 🔽 核心修改：增强版环境变量读取逻辑
// ==========================================
// Railway 有时不将变量放入 $_SERVER，必须优先使用 getenv()
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT');

// 如果 getenv 读不到，再尝试从 $_ENV 或 $_SERVER 读取（作为备选）
if (!$host) $host = $_ENV['MYSQLHOST'] ?? $_SERVER['MYSQLHOST'] ?? '';
if (!$user) $user = $_ENV['MYSQLUSER'] ?? $_SERVER['MYSQLUSER'] ?? '';
if (!$pass) $pass = $_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? '';
if (!$db)   $db   = $_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? '';
if (!$port) $port = $_ENV['MYSQLPORT'] ?? $_SERVER['MYSQLPORT'] ?? '3306';

// 调试信息：如果连不上，暂时打开下面这行注释，查看代码到底读到了什么
// echo json_encode(["debug_host" => $host, "debug_user" => $user, "debug_db" => $db]); exit;

// 如果连主机都没读到，说明环境变量没配对
if (!$host || !$user) {
    echo json_encode(["error" => "系统错误：未找到数据库配置，请检查 Railway Variables 是否已部署"]);
    exit;
}

// 使用获取到的变量建立连接
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

// ==========================================
// 🔼 核心修改结束
// ==========================================

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 这里如果报错，通常是因为密码错或者地址错
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // 为了调试，这里暂时把具体错误打印出来（上线后建议关掉详细错误）
    echo json_encode(["error" => "数据库连接失败: " . $e->getMessage()]);
    exit;
}

// 4. 获取前端传来的操作类型 (保持不变)
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
        echo json_encode(["error" => "无效的操作"]);
}

function getList($pdo) {
    $stmt = $pdo->query("SELECT id, title, is_completed as status FROM tasks ORDER BY id DESC");
    $tasks = $stmt->fetchAll();
    echo json_encode($tasks);
}

function addTask($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['title'])) {
        echo json_encode(["error" => "标题不能为空"]);
        return;
    }

    $title = $data['title'];
    $sql = "INSERT INTO tasks (title, is_completed) VALUES (:title, 0)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute(['title' => $title]);
        echo json_encode(["message" => "添加成功", "id" => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "添加失败: " . $e->getMessage()]);
    }
}

function updateTask($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'];
    $status = $data['status'];

    $sql = "UPDATE tasks SET is_completed = :is_completed WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['is_completed' => $status, 'id' => $id]);

    echo json_encode(["message" => "更新成功"]);
}

function deleteTask($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'];

    $sql = "DELETE FROM tasks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);

    echo json_encode(["message" => "删除成功"]);
}
?>