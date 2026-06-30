#!/bin/bash
# 等待 MySQL 完全启动
echo "Waiting for MySQL to start..."
while ! mysqladmin ping -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" --silent; do
    sleep 2
done

echo "MySQL is up! Importing database..."
# 导入你的 SQL 文件初始化数据库
mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < dump-test_db-202606301837.sql

echo "Starting PHP Server..."
# 启动 PHP 内置服务器，监听 Railway 分配的端口
exec php -S 0.0.0.0:$PORT