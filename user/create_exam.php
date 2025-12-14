<?php
require_once '../config.php';
checkUser();

$page_title = '创建组卷';
$pdo = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bankId = $_POST['bank_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $questionCount = intval($_POST['question_count'] ?? 0);
    
    if (empty($bankId) || empty($title) || $questionCount <= 0) {
        $error = '请填写完整信息';
    } else {
        // 检查题库是否有足够的题目
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE bank_id = ?");
        $stmt->execute([$bankId]);
        $total = $stmt->fetchColumn();
        
        if ($total < $questionCount) {
            $error = "该题库只有 {$total} 道题目，无法组卷 {$questionCount} 道题";
        } else {
            $pdo->beginTransaction();
            try {
                // 创建组卷
                $stmt = $pdo->prepare("INSERT INTO user_exams (user_id, bank_id, title, question_count) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $bankId, $title, $questionCount]);
                $examId = $pdo->lastInsertId();
                
                // 随机抽取题目（乱序）
                $stmt = $pdo->prepare("SELECT id FROM questions WHERE bank_id = ? ORDER BY RAND() LIMIT ?");
                $stmt->execute([$bankId, $questionCount]);
                $questionIds = array_column($stmt->fetchAll(), 'id');
                
                // 再次打乱顺序
                shuffle($questionIds);
                
                // 插入组卷题目
                $stmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
                foreach ($questionIds as $order => $questionId) {
                    $stmt->execute([$examId, $questionId, $order + 1]);
                }
                
                $pdo->commit();
                header('Location: ' . BASE_PATH . 'user/exam.php?exam_id=' . $examId . '&index=0');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = '创建组卷失败：' . $e->getMessage();
            }
        }
    }
}

// 获取题库列表
$banks = $pdo->query("SELECT qb.*, COUNT(q.id) as question_count 
                      FROM question_banks qb 
                      LEFT JOIN questions q ON q.bank_id = qb.id 
                      GROUP BY qb.id 
                      HAVING question_count > 0 
                      ORDER BY qb.name")->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-file-earmark-plus"></i> 创建组卷</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">选择题库</label>
                    <select class="form-select" name="bank_id" id="bankSelect" required onchange="updateQuestionCount()">
                        <option value="">请选择题库</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>" data-count="<?php echo $bank['question_count']; ?>">
                                <?php echo htmlspecialchars($bank['name']); ?> (<?php echo $bank['question_count']; ?>题)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">组卷标题</label>
                    <input type="text" class="form-control" name="title" placeholder="例如：Java基础综合测试" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">题目数量</label>
                    <input type="number" class="form-control" name="question_count" id="questionCount" 
                           min="1" max="100" value="10" required>
                    <small class="text-muted" id="maxCountHint">最多可组卷 <span id="maxCount">0</span> 题</small>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> 创建组卷
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateQuestionCount() {
    const select = document.getElementById('bankSelect');
    const selectedOption = select.options[select.selectedIndex];
    const maxCount = selectedOption ? parseInt(selectedOption.getAttribute('data-count')) : 0;
    
    document.getElementById('maxCount').textContent = maxCount;
    document.getElementById('questionCount').max = maxCount;
    
    if (maxCount > 0 && parseInt(document.getElementById('questionCount').value) > maxCount) {
        document.getElementById('questionCount').value = maxCount;
    }
}
</script>

<?php include '../includes/footer.php'; ?>

