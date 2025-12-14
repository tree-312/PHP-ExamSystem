<?php
// 初始化管理员账户脚本
// 运行此脚本可以重置管理员密码为 admin

require_once 'config.php';

$username = 'admin';
$password = 'admin';

$pdo = getDB();

// 检查管理员是否存在
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin'");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if ($admin) {
    // 更新密码
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([hashPassword($password), $admin['id']]);
    echo "管理员密码已重置为: admin\n";
} else {
    // 创建管理员
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$username, hashPassword($password)]);
    echo "管理员账户已创建: admin / admin\n";
}

echo "初始化完成！\n";

