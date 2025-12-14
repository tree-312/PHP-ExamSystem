<?php
require_once '../config.php';
checkUser();

$page_title = '搜索题目';
$pdo = getDB();

$keyword = $_GET['keyword'] ?? '';
$results = [];

if (!empty($keyword)) {
    $keyword = '%' . $keyword . '%';
    $stmt = $pdo->prepare("
        SELECT q.*, qb.name as bank_name, c.chapter_name 
        FROM questions q 
        LEFT JOIN question_banks qb ON qb.id = q.bank_id 
        LEFT JOIN chapters c ON c.id = q.chapter_id 
        WHERE q.title LIKE ? 
        ORDER BY q.id DESC 
        LIMIT 100
    ");
    $stmt->execute([$keyword]);
    $results = $stmt->fetchAll();
    
    // 获取每个题目的选项
    foreach ($results as &$q) {
        $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
        $stmt->execute([$q['id']]);
        $q['options'] = $stmt->fetchAll();
        $q['answer'] = json_decode($q['answer'], true);
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" name="keyword" value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>" placeholder="输入关键词搜索题目..." autofocus>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> 搜索
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($keyword) && empty($results)): ?>
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-search" style="font-size: 3rem;"></i>
                <p class="mt-3">未找到相关题目</p>
            </div>
        </div>
    <?php elseif (!empty($results)): ?>
        <div class="card">
            <div class="card-header">
                <h5>搜索结果（共 <?php echo count($results); ?> 条）</h5>
            </div>
            <div class="card-body">
                <?php foreach ($results as $q): ?>
                    <div class="card mb-3">
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
                            
                            <div class="alert alert-info mb-0">
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

<?php include '../includes/footer.php'; ?>

