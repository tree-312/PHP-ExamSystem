<?php
require_once '../config.php';
checkUser();

$page_title = '组卷练习';
$pdo = getDB();

$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$currentIndex = isset($_GET['index']) ? intval($_GET['index']) : 0;

// 获取组卷信息
$exam = null;
$questions = [];
if ($examId) {
    $stmt = $pdo->prepare("SELECT * FROM user_exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$examId, $_SESSION['user_id']]);
    $exam = $stmt->fetch();
    
    if ($exam) {
        // 获取组卷题目
        $stmt = $pdo->prepare("SELECT eq.*, q.*, qb.name as bank_name, c.chapter_name 
                               FROM exam_questions eq 
                               LEFT JOIN questions q ON q.id = eq.question_id 
                               LEFT JOIN question_banks qb ON qb.id = q.bank_id 
                               LEFT JOIN chapters c ON c.id = q.chapter_id 
                               WHERE eq.exam_id = ? 
                               ORDER BY eq.question_order");
        $stmt->execute([$examId]);
        $questions = $stmt->fetchAll();
        
        // 获取每个题目的选项
        foreach ($questions as &$q) {
            $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
            $stmt->execute([$q['question_id']]);
            $q['options'] = $stmt->fetchAll();
            // 从questions表获取正确答案
            $q['correct_answer'] = json_decode($q['answer'], true);
            // 从exam_questions表获取用户答案
            $q['user_answer'] = $q['user_answer'] ? json_decode($q['user_answer'], true) : null;
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container exam-container">
    <?php if (!$exam): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: var(--primary-color);"></i>
                <h4 class="mt-3">组卷功能暂未开始</h4>
                <p class="text-muted">请从首页选择组卷功能</p>
                <a href="<?php echo BASE_PATH; ?>user/home.php" class="btn btn-primary mt-3">返回首页</a>
            </div>
        </div>
    <?php elseif (empty($questions)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-exclamation-circle" style="font-size: 3rem; color: var(--warning-color);"></i>
                <h4 class="mt-3">组卷题目加载失败</h4>
                <a href="<?php echo BASE_PATH; ?>user/home.php" class="btn btn-primary mt-3">返回首页</a>
            </div>
        </div>
    <?php else: ?>
        <?php
        $currentQuestion = $questions[$currentIndex] ?? null;
        $totalQuestions = count($questions);
        $isCompleted = $exam['status'] === 'completed';
        ?>
        
        <?php if ($currentQuestion): ?>
            <!-- 进度条 -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0"><?php echo htmlspecialchars($exam['title']); ?></h5>
                        <span class="badge bg-<?php echo $isCompleted ? 'success' : 'primary'; ?>">
                            <?php echo $isCompleted ? '已完成' : '进行中'; ?>
                        </span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: <?php echo ($currentIndex + 1) / $totalQuestions * 100; ?>%"></div>
                    </div>
                    <div class="mt-2 text-muted small">
                        第 <?php echo $currentIndex + 1; ?> / <?php echo $totalQuestions; ?> 题
                    </div>
                </div>
            </div>

            <!-- 题目卡片 -->
            <div class="card question-card">
                <div class="card-body">
                    <div class="question-title">
                        <span class="question-type-badge type-<?php echo $currentQuestion['type']; ?>">
                            <?php
                            $typeNames = ['single' => '单选题', 'multiple' => '多选题', 'fill' => '填空题', 'essay' => '论述题'];
                            echo $typeNames[$currentQuestion['type']] ?? $currentQuestion['type'];
                            ?>
                        </span>
                        <?php echo htmlspecialchars($currentQuestion['title']); ?>
                    </div>

                    <div id="optionsArea" class="mt-4">
                        <?php if (!empty($currentQuestion['options'])): ?>
                            <?php 
                            $userAnswer = $currentQuestion['user_answer'] ?? [];
                            $correctAnswer = $currentQuestion['correct_answer'] ?? [];
                            foreach ($currentQuestion['options'] as $opt): 
                                $isSelected = in_array($opt['option_label'], $userAnswer);
                                $isCorrect = $isCompleted && in_array($opt['option_label'], $correctAnswer);
                                $isWrong = $isCompleted && $isSelected && !$isCorrect;
                            ?>
                                <div class="option-item <?php echo $isSelected ? 'selected' : ''; ?> <?php echo $isCorrect ? 'correct' : ''; ?> <?php echo $isWrong ? 'wrong' : ''; ?>" 
                                     onclick="selectOption(this, <?php echo $currentIndex; ?>)" 
                                     style="<?php echo $isCompleted ? 'pointer-events: none;' : ''; ?>">
                                    <input type="<?php echo $currentQuestion['type'] === 'multiple' ? 'checkbox' : 'radio'; ?>" 
                                           name="answer_<?php echo $currentIndex; ?>" 
                                           value="<?php echo htmlspecialchars($opt['option_label']); ?>" 
                                           style="display: none;"
                                           <?php echo $isSelected ? 'checked' : ''; ?>>
                                    <span class="option-label"><?php echo htmlspecialchars($opt['option_label']); ?></span>
                                    <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <textarea class="form-control" id="textAnswer_<?php echo $currentIndex; ?>" rows="4" 
                                      placeholder="请输入答案..." 
                                      <?php echo $isCompleted ? 'readonly' : ''; ?>><?php echo ($currentQuestion['user_answer'] && count($currentQuestion['user_answer']) > 0) ? htmlspecialchars($currentQuestion['user_answer'][0]) : ''; ?></textarea>
                            <?php if (!$isCompleted): ?>
                            <script>
                            // 文本输入框自动保存
                            (function() {
                                const textAnswer = document.getElementById('textAnswer_<?php echo $currentIndex; ?>');
                                if (textAnswer) {
                                    let saveTimer;
                                    textAnswer.addEventListener('input', function() {
                                        clearTimeout(saveTimer);
                                        saveTimer = setTimeout(() => {
                                            autoSaveAnswer(<?php echo $currentIndex; ?>);
                                        }, 1000);
                                    });
                                }
                            })();
                            </script>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 答案显示区域（仅提交后显示） -->
                    <?php if ($isCompleted): ?>
                        <div class="mt-4">
                            <?php
                            $correctAnswer = $currentQuestion['correct_answer'];
                            $isCorrect = $currentQuestion['is_correct'];
                            $userAnswer = $currentQuestion['user_answer'];
                            
                            // 如果是论述题，不显示正确/错误
                            if ($currentQuestion['type'] === 'essay'):
                            ?>
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle"></i> 参考答案</h6>
                                    <p class="mb-1"><strong>答案：</strong>
                                        <?php echo is_array($correctAnswer) ? implode(', ', $correctAnswer) : $correctAnswer; ?>
                                    </p>
                                    <?php if ($currentQuestion['analysis']): ?>
                                        <div class="analysis-box mt-2">
                                            <strong>解析：</strong><?php echo htmlspecialchars($currentQuestion['analysis']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-<?php echo $isCorrect ? 'success' : 'danger'; ?>">
                                    <h6><i class="bi bi-<?php echo $isCorrect ? 'check-circle' : 'x-circle'; ?>"></i> 
                                        <?php echo $isCorrect ? '回答正确' : '回答错误'; ?>
                                    </h6>
                                    <?php if ($userAnswer): ?>
                                        <p class="mb-1"><strong>您的答案：</strong>
                                            <?php echo is_array($userAnswer) ? implode(', ', $userAnswer) : $userAnswer; ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="mb-1"><strong>正确答案：</strong>
                                        <?php echo is_array($correctAnswer) ? implode(', ', $correctAnswer) : $correctAnswer; ?>
                                    </p>
                                    <?php if ($currentQuestion['analysis']): ?>
                                        <div class="analysis-box mt-2">
                                            <strong>解析：</strong><?php echo htmlspecialchars($currentQuestion['analysis']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- 按钮区域 -->
                    <?php if (!$isCompleted): ?>
                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <button class="btn btn-outline-secondary btn-lg" 
                                    onclick="prevQuestion()" 
                                    <?php echo $currentIndex === 0 ? 'disabled' : ''; ?>>
                                <i class="bi bi-arrow-left"></i> 上一题
                            </button>
                            
                            <button class="btn btn-outline-primary btn-lg" 
                                    onclick="nextQuestion()" 
                                    <?php echo $currentIndex >= $totalQuestions - 1 ? 'disabled' : ''; ?>>
                                下一题 <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>

                        <?php if ($currentIndex >= $totalQuestions - 1): ?>
                            <div class="mt-4 text-center">
                                <button class="btn btn-success btn-lg" onclick="submitExam()">
                                    <i class="bi bi-check-circle"></i> 提交试卷
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mt-4 text-center">
                            <a href="<?php echo BASE_PATH; ?>user/exam_review.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-list-check"></i> 查看全部题目
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
const examId = <?php echo $examId; ?>;
const currentIndex = <?php echo $currentIndex; ?>;
const totalQuestions = <?php echo count($questions); ?>;
const isCompleted = <?php echo $exam['status'] === 'completed' ? 'true' : 'false'; ?>;

function selectOption(element, index) {
    if (isCompleted) return;
    
    const input = element.querySelector('input[type="radio"], input[type="checkbox"]');
    if (!input) return;
    
    if (input.type === 'radio') {
        document.querySelectorAll(`input[name="answer_${index}"]`).forEach(inp => {
            const item = inp.closest('.option-item');
            if (item) {
                item.classList.remove('selected');
            }
        });
        element.classList.add('selected');
        input.checked = true;
    } else {
        element.classList.toggle('selected');
        input.checked = !input.checked;
    }
    
    // 自动保存答案
    autoSaveAnswer(index);
}

function autoSaveAnswer(index) {
    let userAnswer = [];
    
    const checkedInputs = document.querySelectorAll(`input[name="answer_${index}"]:checked`);
    if (checkedInputs.length > 0) {
        checkedInputs.forEach(input => userAnswer.push(input.value));
    } else {
        const textAnswer = document.getElementById(`textAnswer_${index}`);
        if (textAnswer && textAnswer.value.trim()) {
            userAnswer = [textAnswer.value.trim()];
        }
    }
    
    // 即使为空也保存（允许清空答案）
    const formData = new URLSearchParams({
        exam_id: examId,
        question_index: index,
        user_answer: JSON.stringify(userAnswer)
    });
    
    fetch(`${BASE_PATH}api/save_exam_answer.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        // 静默保存，不显示提示
    })
    .catch(err => {
        console.error('自动保存失败', err);
    });
}

// 文本输入框自动保存
document.addEventListener('DOMContentLoaded', function() {
    const textAnswer = document.getElementById(`textAnswer_${currentIndex}`);
    if (textAnswer && !isCompleted) {
        let saveTimer;
        textAnswer.addEventListener('input', function() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                autoSaveAnswer(currentIndex);
            }, 1000); // 1秒后自动保存
        });
    }
});

function prevQuestion() {
    // 先保存当前答案
    autoSaveAnswer(currentIndex);
    
    if (currentIndex > 0) {
        window.location.href = `${BASE_PATH}user/exam.php?exam_id=${examId}&index=${currentIndex - 1}`;
    }
}

function nextQuestion() {
    // 先保存当前答案
    autoSaveAnswer(currentIndex);
    
    if (currentIndex < totalQuestions - 1) {
        window.location.href = `${BASE_PATH}user/exam.php?exam_id=${examId}&index=${currentIndex + 1}`;
    }
}

function submitExam() {
    // 先保存当前答案
    autoSaveAnswer(currentIndex);
    
    if (!confirm('确定要提交试卷吗？提交后将无法修改答案。')) {
        return;
    }
    
    fetch(`${BASE_PATH}api/submit_exam.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ exam_id: examId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // 跳转到查看结果页面
            window.location.href = `${BASE_PATH}user/exam_review.php?exam_id=${examId}`;
        } else {
            showAlert(data.message || '提交失败', 'danger');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert('提交失败', 'danger');
    });
}
</script>

<?php include '../includes/footer.php'; ?>

