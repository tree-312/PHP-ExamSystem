<?php
require_once '../config.php';
checkUser();

$page_title = '题库预览';
$pdo = getDB();

// 获取题库列表
$banks = $pdo->query("SELECT * FROM question_banks ORDER BY name")->fetchAll();

// 获取题目列表
$bankId = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : 0;
$chapterId = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;

$questions = [];
if ($bankId) {
    $where = ["q.bank_id = ?"];
    $params = [$bankId];
    
    if ($chapterId) {
        $where[] = "q.chapter_id = ?";
        $params[] = $chapterId;
    }
    
    $sql = "SELECT q.*, qb.name as bank_name, c.chapter_name 
            FROM questions q 
            LEFT JOIN question_banks qb ON qb.id = q.bank_id 
            LEFT JOIN chapters c ON c.id = q.chapter_id 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY c.sort_order, q.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();
    
    // 获取选项
    foreach ($questions as &$q) {
        $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
        $stmt->execute([$q['id']]);
        $q['options'] = $stmt->fetchAll();
        $q['answer'] = json_decode($q['answer'], true);
    }
}

// 获取章节列表
$chapters = [];
if ($bankId) {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE bank_id = ? ORDER BY sort_order, chapter_name");
    $stmt->execute([$bankId]);
    $chapters = $stmt->fetchAll();
}
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">选择题库</label>
                    <select class="form-select" name="bank_id" onchange="this.form.submit()">
                        <option value="">全部题库</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>" <?php echo ($bankId == $bank['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">选择章节</label>
                    <select class="form-select" name="chapter_id" onchange="this.form.submit()">
                        <option value="">全部章节</option>
                        <?php foreach ($chapters as $chapter): ?>
                            <option value="<?php echo $chapter['id']; ?>" <?php echo ($chapterId == $chapter['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">筛选</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">请选择题库和章节进行预览</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">题目预览（共 <?php echo count($questions); ?> 题）</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="toggleAllAnswers()" id="toggleAllBtn">
                    <i class="bi bi-eye" id="toggleAllIcon"></i> 
                    <span id="toggleAllText">显示全部答案</span>
                </button>
            </div>
            <div class="card-body">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="card mb-3 question-item">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($q['bank_name']); ?></span>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($q['chapter_name']); ?></span>
                                    <?php
                                    $typeNames = ['single' => '单选', 'multiple' => '多选', 'fill' => '填空', 'essay' => '论述'];
                                    echo '<span class="badge bg-secondary">' . ($typeNames[$q['type']] ?? $q['type']) . '</span>';
                                    ?>
                                </div>
                                <span class="text-muted">#<?php echo $index + 1; ?></span>
                            </div>
                            <h6 class="mb-3"><?php echo htmlspecialchars($q['title']); ?></h6>
                            
                            <?php if (!empty($q['options'])): ?>
                                <div class="mb-3">
                                    <?php foreach ($q['options'] as $opt): ?>
                                        <div class="mb-2">
                                            <strong><?php echo htmlspecialchars($opt['option_label']); ?>.</strong> 
                                            <?php echo htmlspecialchars($opt['option_text']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-success mb-0 answer-box" style="display: none;">
                                <strong>答案：</strong><?php echo is_array($q['answer']) ? implode(', ', $q['answer']) : $q['answer']; ?>
                                <?php if ($q['analysis']): ?>
                                    <div class="mt-2"><strong>解析：</strong><?php echo htmlspecialchars($q['analysis']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
let allAnswersVisible = false;

function toggleAllAnswers() {
    const answerBoxes = document.querySelectorAll('.answer-box');
    const toggleIcon = document.getElementById('toggleAllIcon');
    const toggleText = document.getElementById('toggleAllText');
    
    allAnswersVisible = !allAnswersVisible;
    
    answerBoxes.forEach(box => {
        box.style.display = allAnswersVisible ? 'block' : 'none';
    });
    
    if (allAnswersVisible) {
        toggleIcon.className = 'bi bi-eye-slash';
        toggleText.textContent = '隐藏全部答案';
    } else {
        toggleIcon.className = 'bi bi-eye';
        toggleText.textContent = '显示全部答案';
    }
}
</script>

<?php include '../includes/footer.php'; ?>

