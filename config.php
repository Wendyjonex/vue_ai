<?php
// config.php

// 【关键修改】判断当前是否在 Railway 环境运行
// Railway 会自动注入 MYSQLHOST 这个变量，如果存在，说明是在云端
if (getenv('MYSQLHOST')) {
    
    // --- ☁️ 生产环境 (Railway) ---
    // 直接从 Railway 的后台设置里读取，完全不依赖文件，所以密码不会暴露
    return [
        'host'    => getenv('MYSQLHOST'),
        'port'    => getenv('MYSQLPORT') ?: '3306',
        'db'      => getenv('MYSQLDATABASE'),
        'user'    => getenv('MYSQLUSER'),
        'pass'    => getenv('MYSQLPASSWORD'), // 这里的值来自 Railway 后台，不是文件
        'charset' => 'utf8mb4'
    ];

} else {

    // --- 💻 本地开发环境 (localhost) ---
    // 只有在你自己电脑上跑的时候，才会读取下面的假密码/本地密码
    // 这个分支的代码即使被上传到 GitHub 也没关系，因为云端走的是上面那个 if
    return [
        'host'    => 'localhost',
        'port'    => '3306',
        'db'      => 'test_db', 
        'user'    => 'root',
        'pass'    => '', // 这里写你本地的密码，或者留空
        'charset' => 'utf8mb4'
    ];
}
?>