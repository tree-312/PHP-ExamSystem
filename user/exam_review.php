<?php
require_once '../config.php';
checkUser();

$page_title = '组卷结果';
$pdo = getDB();

$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if (!$examId) {
    header('Location: ' . BASE_PATH . 'user/home.php');
    exit;
}

// 获取组卷信息
$stmt = $pdo->prepare("SELECT * FROM user_exams WHERE id = ? AND user_id = ? AND status = 'completed'");
$stmt->execute([$examId, $_SESSION['user_id']]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: ' . BASE_PATH . 'user/home.php');
    exit;
}

// 获取所有题目（乱序显示）
$stmt = $pdo->prepare("SELECT eq.*, q.*, qb.name as bank_name, c.chapter_name 
                       FROM exam_questions eq 
                       LEFT JOIN questions q ON q.id = eq.question_id 
                       LEFT JOIN question_banks qb ON qb.id = q.bank_id 
                       LEFT JOIN chapters c ON c.id = q.chapter_id 
                       WHERE eq.exam_id = ? 
                       ORDER BY RAND()");
$stmt->execute([$examId]);
$questions = $stmt->fetchAll();

// 获取每个题目的选项
foreach ($questions as &$q) {
    $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
    $stmt->execute([$q['question_id']]);
    $q['options'] = $stmt->fetchAll();
    $q['correct_answer'] = json_decode($q['answer'], true);
    $q['user_answer'] = $q['user_answer'] ? json_decode($q['user_answer'], true) : null;
}
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?></h5>
                <span class="badge bg-success">已完成</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <h3 class="text-primary mb-1"><?php echo count($questions); ?></h3>
                    <p class="text-muted mb-0">总题数</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-success mb-1">
                        <?php 
                        $correctCount = 0;
                        foreach ($questions as $q) {
                            if ($q['is_correct']) $correctCount++;
                        }
                        echo $correctCount;
                        ?>
                    </h3>
                    <p class="text-muted mb-0">正确题数</p>
                </div>
                <div class="col-md-4">
                    <h3 class="text-info mb-1">
                        <?php echo round($correctCount / count($questions) * 100, 1); ?>%
                    </h3>
                    <p class="text-muted mb-0">正确率</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 所有题目列表 -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">题目详情</h5>
        </div>
        <div class="card-body">
            <?php foreach ($questions as $index => $q): ?>
                <div class="card mb-4 question-review-item">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="question-type-badge type-<?php echo $q['type']; ?>">
                                    <?php
                                    $typeNames = ['single' => '单选题', 'multiple' => '多选题', 'fill' => '填空题', 'essay' => '论述题'];
                                    echo $typeNames[$q['type']] ?? $q['type'];
                                    ?>
                                </span>
                                <span class="badge bg-<?php echo $q['is_correct'] ? 'success' : 'danger'; ?> ms-2">
                                    <?php echo $q['is_correct'] ? '✓ 正确' : '✗ 错误'; ?>
                                </span>
                            </div>
                            <span class="text-muted">#<?php echo $index + 1; ?></span>
                        </div>
                        
                        <h6 class="mb-3"><?php echo htmlspecialchars($q['title']); ?></h6>
                        
                        <?php if (!empty($q['options'])): ?>
                            <div class="mb-3">
                                <?php 
                                $userAnswer = $q['user_answer'] ?? [];
                                $correctAnswer = $q['correct_answer'] ?? [];
                                foreach ($q['options'] as $opt): 
                                    $isSelected = in_array($opt['option_label'], $userAnswer);
                                    $isCorrect = in_array($opt['option_label'], $correctAnswer);
                                    $isWrong = $isSelected && !$isCorrect;
                                ?>
                                    <div class="option-item mb-2 <?php echo $isSelected ? 'selected' : ''; ?> <?php echo $isCorrect ? 'correct' : ''; ?> <?php echo $isWrong ? 'wrong' : ''; ?>" style="pointer-events: none;">
                                        <span class="option-label"><?php echo htmlspecialchars($opt['option_label']); ?></span>
                                        <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <p><strong>您的答案：</strong>
                                    <?php echo ($q['user_answer'] && count($q['user_answer']) > 0) ? htmlspecialchars($q['user_answer'][0]) : '未作答'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-<?php echo $q['is_correct'] ? 'success' : 'danger'; ?>">
                            <?php if ($q['type'] === 'essay'): ?>
                                <h6><i class="bi bi-info-circle"></i> 参考答案</h6>
                            <?php else: ?>
                                <h6><i class="bi bi-<?php echo $q['is_correct'] ? 'check-circle' : 'x-circle'; ?>"></i> 
                                    <?php echo $q['is_correct'] ? '回答正确' : '回答错误'; ?>
                                </h6>
                            <?php endif; ?>
                            
                            <?php if ($q['user_answer']): ?>
                                <p class="mb-1"><strong>您的答案：</strong>
                                    <?php echo is_array($q['user_answer']) ? implode(', ', $q['user_answer']) : $q['user_answer']; ?>
                                </p>
                            <?php endif; ?>
                            
                            <p class="mb-1"><strong>正确答案：</strong>
                                <?php echo is_array($q['correct_answer']) ? implode(', ', $q['correct_answer']) : $q['correct_answer']; ?>
                            </p>
                            
                            <?php if ($q['analysis']): ?>
                                <div class="analysis-box mt-2">
                                    <strong>解析：</strong><?php echo htmlspecialchars($q['analysis']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="text-center mt-4 mb-4">
        <a href="<?php echo BASE_PATH; ?>user/home.php" class="btn btn-primary btn-lg">
            <i class="bi bi-house"></i> 返回首页
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

