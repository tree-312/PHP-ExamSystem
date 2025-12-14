<?php
/**
 * 重置章节API接口
 * 用于重置用户在特定章节的学习进度和答题记录
 * 会删除该章节的所有答题记录和进度统计
 */

// 引入配置文件（包含数据库连接、工具函数等）
require_once '../config.php';
// 检查用户登录状态（未登录将重定向到登录页）
checkLogin();

// 验证请求方法：必须是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 返回405错误（方法不允许）
    jsonResponse(['success' => false, 'message' => '请求方法错误'], 405);
}

// 获取请求参数：章节ID
$chapterId = $_POST['chapter_id'] ?? null;

// 参数验证：检查是否缺少章节ID
if (!$chapterId) {
    // 返回400错误（参数错误）
    jsonResponse(['success' => false, 'message' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();
// 获取当前登录用户ID
$userId = $_SESSION['user_id'];

// 获取该章节的所有题目ID
$stmt = $pdo->prepare("SELECT id FROM questions WHERE chapter_id = ?");
$stmt->execute([$chapterId]);
$questionIds = array_column($stmt->fetchAll(), 'id');

// 检查章节是否有题目
if (empty($questionIds)) {
    jsonResponse(['success' => false, 'message' => '该章节没有题目']);
}

// 删除该章节的所有答题记录
// 生成占位符字符串（如: ?,?,?）
$placeholders = implode(',', array_fill(0, count($questionIds), '?'));
$stmt = $pdo->prepare("DELETE FROM user_answers WHERE user_id = ? AND question_id IN ($placeholders)");
$stmt->execute(array_merge([$userId], $questionIds));

// 删除该章节的学习进度记录
$stmt = $pdo->prepare("DELETE FROM user_progress WHERE user_id = ? AND chapter_id = ?");
$stmt->execute([$userId, $chapterId]);

// 返回成功响应
jsonResponse(['success' => true, 'message' => '重置成功']);
