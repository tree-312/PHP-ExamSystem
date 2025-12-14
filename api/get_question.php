<?php
/**
 * 获取题目API接口
 * 用于在刷题页面中获取题目信息，支持顺序模式和随机模式
 * 包含题目基本信息、选项、用户答题记录等
 */

// 引入配置文件（包含数据库连接、工具函数等）
require_once '../config.php';
// 检查用户登录状态（未登录将重定向到登录页）
checkLogin();

// 获取请求参数
$bankId = $_GET['bank_id'] ?? null;           // 题库ID
$chapterId = $_GET['chapter_id'] ?? null;     // 章节ID
$randomMode = isset($_GET['random']) && $_GET['random'] == '1'; // 是否随机模式
$currentId = isset($_GET['current_id']) ? intval($_GET['current_id']) : null; // 当前题目ID（顺序模式下使用）

// 参数验证：检查是否缺少必要参数
if (!$bankId || !$chapterId) {
    // 返回400错误（参数错误）和错误信息
    jsonResponse(['success' => false, 'message' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();
// 获取当前登录用户ID
$userId = $_SESSION['user_id'];

// 获取已答题目ID（乱序模式下排除已答题目）
$answeredIds = [];
if ($randomMode) {
    // 查询当前用户已回答的所有题目ID
    $stmt = $pdo->prepare("SELECT DISTINCT question_id FROM user_answers WHERE user_id = ?");
    $stmt->execute([$userId]);
    // 提取question_id列的值到数组
    $answeredIds = array_column($stmt->fetchAll(), 'question_id');
}

// 构建查询条件
$whereClause = "WHERE q.bank_id = ? AND q.chapter_id = ?";
$params = [$bankId, $chapterId];

// 顺序模式下，如果提供了当前题目ID，获取下一道题目
if (!$randomMode && $currentId) {
    $whereClause .= " AND q.id > ?";
    $params[] = $currentId;
}

// 乱序模式下，排除已答题目
if ($randomMode && !empty($answeredIds)) {
    // 生成占位符字符串（如: ?,?,?）
    $placeholders = implode(',', array_fill(0, count($answeredIds), '?'));
    $whereClause .= " AND q.id NOT IN ($placeholders)";
    // 合并参数数组
    $params = array_merge($params, $answeredIds);
}

// 设置排序方式：随机模式使用RAND()，顺序模式按ID排序
$orderBy = $randomMode ? "ORDER BY RAND()" : "ORDER BY q.id";
// 构建完整的SQL查询
$sql = "SELECT q.* FROM questions q $whereClause $orderBy LIMIT 1";

// 执行查询
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
// 获取题目信息
$question = $stmt->fetch();

// 如果没有找到题目
if (!$question) {
    jsonResponse(['success' => false, 'message' => '没有更多题目']);
}

// 获取该题目的选项（按排序顺序）
$stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
$stmt->execute([$question['id']]);
// 将选项添加到题目数组中
$question['options'] = $stmt->fetchAll();

// 检查用户是否已经回答过该题目
$stmt = $pdo->prepare("SELECT * FROM user_answers WHERE user_id = ? AND question_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId, $question['id']]);
$userAnswer = $stmt->fetch();

if ($userAnswer) {
    // 如果用户已回答，添加用户答案信息
    $question['user_answer'] = json_decode($userAnswer['user_answer'], true); // 用户答案（JSON解析为数组）
    $question['is_correct'] = $userAnswer['is_correct']; // 是否答对（0：错误，1：正确）
    $question['answered'] = true; // 标记为已回答
} else {
    // 如果用户未回答，标记为未回答
    $question['answered'] = false;
}

// 返回成功响应，包含题目完整信息
jsonResponse(['success' => true, 'question' => $question]);

