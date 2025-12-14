<?php
/**
 * 提交组卷API接口
 * 用于处理用户提交的组卷考试，包含批量评分、状态更新和结果统计
 * 使用事务确保数据一致性
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

// 获取请求参数：组卷ID
$examId = isset($_POST['exam_id']) ? $_POST['exam_id'] : null;

// 参数验证：检查是否缺少组卷ID
if (!$examId) {
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

// 开始事务（确保数据操作的原子性）
$pdo->beginTransaction();
try {
    // 获取组卷中的所有题目（包含正确答案和题型）
    $stmt = $pdo->prepare("SELECT eq.*, q.answer, q.type 
                           FROM exam_questions eq 
                           LEFT JOIN questions q ON q.id = eq.question_id 
                           WHERE eq.exam_id = ? 
                           ORDER BY eq.question_order");
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll();

    // 统计答对题目数量
    $correctCount = 0;

    // 遍历所有题目进行评分
    foreach ($questions as $q) {
        // 解析用户答案和正确答案
        $userAnswer = $q['user_answer'] ? json_decode($q['user_answer'], true) : [];
        $correctAnswer = json_decode($q['answer'], true);

        // 判断答案正误（根据不同题型采用不同判断逻辑）
        $isCorrect = false;
        if ($q['type'] === 'essay') {
            // 论述题不自动判断正确与否，直接标记为正确
            $isCorrect = true;
        } elseif (empty($userAnswer)) {
            // 单选、多选、填空没有选择答案默认为错
            $isCorrect = false;
        } elseif ($q['type'] === 'multiple') {
            // 多选题：需要对答案进行排序后再比较
            sort($userAnswer);
            sort($correctAnswer);
            $isCorrect = $userAnswer === $correctAnswer;
        } else {
            // 单选题和填空题：检查用户答案是否在正确答案数组中
            $isCorrect = in_array($userAnswer[0], $correctAnswer);
        }

        // 更新答对题目计数
        if ($isCorrect) $correctCount++;

        // 更新题目评分结果
        $stmt = $pdo->prepare("UPDATE exam_questions SET is_correct = ? WHERE id = ?");
        $stmt->execute([$isCorrect ? 1 : 0, $q['id']]);
    }

    // 更新组卷状态为已完成，并记录提交时间
    $stmt = $pdo->prepare("UPDATE user_exams SET status = 'completed', submitted_at = NOW() WHERE id = ?");
    $stmt->execute([$examId]);

    // 提交事务
    $pdo->commit();

    // 返回成功响应，包含评分结果
    jsonResponse([
        'success' => true,
        'message' => '试卷提交成功',
        'correct_count' => $correctCount, // 答对题目数量
        'total_count' => count($questions) // 总题目数量
    ]);
} catch (Exception $e) {
    // 发生异常时回滚事务
    $pdo->rollBack();
    // 返回错误信息
    jsonResponse(['success' => false, 'message' => '提交失败：' . $e->getMessage()]);
}