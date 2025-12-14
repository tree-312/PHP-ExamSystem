<?php
// 引入配置文件
require_once '../config.php';
// 检查用户登录状态
checkLogin();

// 获取请求参数：题库ID
$bankId = $_GET['bank_id'] ?? null;

// 参数验证
if (!$bankId) {
    // 如果缺少题库ID，返回400错误（参数错误）
    jsonResponse(['error' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();

// 准备SQL查询：根据题库ID获取章节列表，按排序顺序和章节名称排序
$stmt = $pdo->prepare("SELECT id, chapter_name FROM chapters WHERE bank_id = ? ORDER BY sort_order, chapter_name");
// 执行查询
$stmt->execute([$bankId]);
// 获取所有章节记录
$chapters = $stmt->fetchAll();

// 返回JSON格式的章节列表数据
jsonResponse($chapters);
