<?php
/**
 * 保存组卷答案API接口
 * 用于保存用户在组卷考试中的答案
 * 仅允许保存进行中的组卷答案
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
$examId = $_POST['exam_id'] ?? null;           // 组卷ID
$questionIndex = $_POST['question_index'] ?? null; // 题目索引（从0开始）
$userAnswerJson = $_POST['user_answer'] ?? '[]'; // 用户答案（JSON格式）

// 参数验证：检查是否缺少必要参数
if (!$examId || $questionIndex === null) {
    // 返回400错误（参数错误）
    jsonResponse(['success' => false, 'message' => '缺少参数'], 400);
}

// 获取数据库连接
$pdo = getDB();
// 获取当前登录用户ID
$userId = $_SESSION['user_id'];

// 验证组卷属于当前用户且状态为进行中
$stmt = $pdo->prepare("SELECT * FROM user_exams WHERE id = ? AND user_id = ? AND status = 'in_progress'");
$stmt->execute([$examId, $userId]);
$exam = $stmt->fetch();

// 检查组卷是否存在且处于进行中状态
if (!$exam) {
    jsonResponse(['success' => false, 'message' => '组卷不存在或已提交'], 404);
}

// 获取题目信息（包含正确答案和题型）
$stmt = $pdo->prepare("SELECT eq.*, q.answer, q.type 
                       FROM exam_questions eq 
                       LEFT JOIN questions q ON q.id = eq.question_id 
                       WHERE eq.exam_id = ? AND eq.question_order = ?");
// 注意：question_order是从1开始的，所以需要将索引+1
$stmt->execute([$examId, $questionIndex + 1]);
$examQuestion = $stmt->fetch();

// 检查题目是否存在
if (!$examQuestion) {
    jsonResponse(['success' => false, 'message' => '题目不存在'], 404);
}

// 更新用户答案到组卷题目关联表
$stmt = $pdo->prepare("UPDATE exam_questions SET user_answer = ? WHERE exam_id = ? AND question_order = ?");
$stmt->execute([$userAnswerJson, $examId, $questionIndex + 1]);

// 返回成功响应
jsonResponse(['success' => true, 'message' => '答案已保存']);
