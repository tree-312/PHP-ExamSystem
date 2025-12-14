<?php
/**
 * 获取上一题API接口
 * 用于在刷题页面中获取当前题目之前的题目信息
 * 包含题目基本信息、选项、用户答题记录等
 */

// 引入配置文件（包含数据库连接、工具函数等）
require_once '../config.php';
// 检查用户登录状态（未登录将重定向到登录页）
checkLogin();

// 获取请求参数
$bankId = $_GET['bank_id'] ?? null;           // 题库ID
$chapterId = $_GET['chapter_id'] ?? null;     // 章节ID
$currentQuestionId = $_GET['current_question_id'] ?? null; // 当前题目ID

// 参数验证：检查是否缺少必要参数
if (!$bankId || !$chapterId || !$currentQuestionId) {
    // 返回400错误（参数错误）和错误信息
    jsonResponse(['success' => false, 'message' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();
// 获取当前登录用户ID
$userId = $_SESSION['user_id'];

// 获取当前题目之前的题目（按ID倒序，取最接近的一个）
$stmt = $pdo->prepare("SELECT q.* FROM questions q 
                       WHERE q.bank_id = ? AND q.chapter_id = ? AND q.id < ? 
                       ORDER BY q.id DESC LIMIT 1");
$stmt->execute([$bankId, $chapterId, $currentQuestionId]);
$question = $stmt->fetch();

// 检查是否存在上一题
if (!$question) {
    // 返回没有上一题的提示
    jsonResponse(['success' => false, 'message' => '没有上一题']);
}

// 获取题目的选项（按排序顺序）
$stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
$stmt->execute([$question['id']]);
$question['options'] = $stmt->fetchAll();

// 检查用户是否已回答该题
$stmt = $pdo->prepare("SELECT * FROM user_answers WHERE user_id = ? AND question_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId, $question['id']]);
$userAnswer = $stmt->fetch();

// 如果用户已回答，添加答题记录信息
if ($userAnswer) {
    $question['user_answer'] = json_decode($userAnswer['user_answer'], true); // 用户答案（JSON解析为数组）
    $question['is_correct'] = $userAnswer['is_correct']; // 是否答对（0：错误，1：正确）
    $question['answered'] = true; // 标记为已回答
} else {
    $question['answered'] = false; // 标记为未回答
}

// 返回JSON格式的成功响应和题目数据
jsonResponse(['success' => true, 'question' => $question]);

