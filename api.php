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
// 🔽 修改部分开始：加载外部配置文件
// ==========================================

// 检查配置文件是否存在，防止报错
$configFile = __DIR__ . '/config.php'; 
if (!file_exists($configFile)) {
    echo json_encode(["error" => "系统错误：找不到配置文件 config.php"]);
    exit;
}

// 引入配置文件并获取数组
$dbConfig = require $configFile;

// 使用配置文件中的值建立连接
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];

// ==========================================
// 🔼 修改部分结束
// ==========================================

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // ⚠️ 生产环境建议不要直接输出 $e->getMessage()，容易暴露数据库结构
    echo json_encode(["error" => "数据库连接失败"]);
    // 调试时可以打开下面这行查看具体错误：
    // echo json_encode(["error" => "数据库连接失败: " . $e->getMessage()]);
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

// --- 核心功能函数 (以下完全不需要改动) ---

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