<?php
/**
 * 提交答案API接口
 * 用于处理用户提交的题目答案，判断正确性并保存答题记录
 * 同时更新用户的章节学习进度
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

// 获取请求参数
$questionId = $_POST['question_id'] ?? null; // 题目ID
$userAnswerJson = $_POST['user_answer'] ?? '[]'; // 用户答案，JSON格式字符串

// 参数验证：检查是否缺少题目ID
if (!$questionId) {
    // 返回400错误（参数错误）
    jsonResponse(['success' => false, 'message' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();
// 获取当前登录用户ID
$userId = $_SESSION['user_id'];

// 获取题目信息
$stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
$stmt->execute([$questionId]);
$question = $stmt->fetch();

// 检查题目是否存在
if (!$question) {
    // 返回404错误（未找到）
    jsonResponse(['success' => false, 'message' => '题目不存在'], 404);
}

// 解析答案：将JSON格式转换为PHP数组
$userAnswer = json_decode($userAnswerJson, true); // 用户答案
$correctAnswer = json_decode($question['answer'], true); // 正确答案

// 判断答案正误（根据不同题型采用不同判断逻辑）
$isCorrect = false;

if ($question['type'] === 'essay') {
    // 论述题不自动判断正确与否，直接标记为正确（用于统计）
    $isCorrect = true;
} elseif (empty($userAnswer)) {
    // 单选、多选、填空没有选择答案默认为错
    $isCorrect = false;
} elseif ($question['type'] === 'multiple') {
    // 多选题：需要对答案进行排序后再比较（确保顺序不影响结果）
    sort($userAnswer);
    sort($correctAnswer);
    $isCorrect = $userAnswer === $correctAnswer;
} else {
    // 单选题和填空题：检查用户答案是否在正确答案数组中
    $isCorrect = in_array($userAnswer[0], $correctAnswer);
}

// 保存答题记录到user_answers表
$stmt = $pdo->prepare("INSERT INTO user_answers (user_id, question_id, is_correct, user_answer) VALUES (?, ?, ?, ?)");
$stmt->execute([$userId, $questionId, $isCorrect ? 1 : 0, $userAnswerJson]);

// 更新用户的章节学习进度
$chapterId = $question['chapter_id']; // 获取当前题目所属章节ID

// 检查用户是否已有该章节的进度记录
$stmt = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ? AND chapter_id = ?");
$stmt->execute([$userId, $chapterId]);
$progress = $stmt->fetch();

if ($progress) {
    // 如果已有进度记录，更新总答题数和正确答题数
    $stmt = $pdo->prepare("UPDATE user_progress SET total_count = total_count + 1, correct_count = correct_count + ? WHERE user_id = ? AND chapter_id = ?");
    $stmt->execute([$isCorrect ? 1 : 0, $userId, $chapterId]);
} else {
    // 如果没有进度记录，创建新的进度记录
    $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, chapter_id, total_count, correct_count) VALUES (?, ?, 1, ?)");
    $stmt->execute([$userId, $chapterId, $isCorrect ? 1 : 0]);
}

// 返回成功响应，包含答案正确性、正确答案和解析
jsonResponse([
    'success' => true,
    'is_correct' => $isCorrect,          // 是否答对
    'correct_answer' => $correctAnswer,  // 正确答案
    'analysis' => $question['analysis'] ?? '' // 题目解析
]);