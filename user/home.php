<?php
/**
 * 用户首页 - 选择题库和章节
 * 用户登录后可以在这里选择要学习的题库和章节，查看学习进度
 */

// 引入配置文件，加载系统常量和通用函数
require_once '../config.php';

// 检查用户权限，确保只有普通用户可以访问此页面
checkUser();

// 设置页面标题
$page_title = '首页 - 选择题库和章节';

// 获取数据库连接
$pdo = getDB();

// 获取题库列表
$banks = $pdo->query("SELECT * FROM question_banks ORDER BY name")->fetchAll();

// 获取章节列表（根据选择的题库）
$chapters = [];
if (isset($_GET['bank_id']) && $_GET['bank_id']) {
    // 验证并获取题库ID
    $bankId = intval($_GET['bank_id']);
    // 准备并执行SQL查询，获取指定题库的所有章节
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE bank_id = ? ORDER BY sort_order, chapter_name");
    $stmt->execute([$bankId]);
    $chapters = $stmt->fetchAll();
}

// 获取用户学习进度
$userProgress = [];
$chapterTotalQuestions = [];
if ($chapters) {
    // 提取所有章节ID
    $chapterIds = array_column($chapters, 'id');
    // 创建占位符字符串
    $placeholders = implode(',', array_fill(0, count($chapterIds), '?'));
    
    // 获取用户在这些章节上的学习进度
    $stmt = $pdo->prepare("SELECT chapter_id, correct_count, total_count FROM user_progress WHERE user_id = ? AND chapter_id IN ($placeholders)");
    $stmt->execute(array_merge([$_SESSION['user_id']], $chapterIds));
    foreach ($stmt->fetchAll() as $progress) {
        $userProgress[$progress['chapter_id']] = $progress;
    }
    
    // 获取每个章节的总题目数
    $stmt = $pdo->prepare("SELECT chapter_id, COUNT(*) as total FROM questions WHERE chapter_id IN ($placeholders) GROUP BY chapter_id");
    $stmt->execute($chapterIds);
    foreach ($stmt->fetchAll() as $total) {
        $chapterTotalQuestions[$total['chapter_id']] = $total['total'];
    }
}
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/navbar.php'; ?> <!-- 引入用户导航栏 -->

<div class="container">
    <!-- 组卷功能入口 -->
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
        <div class="card-body text-white text-center py-4">
            <h4 class="mb-3"><i class="bi bi-file-earmark-text"></i> 组卷练习</h4>
            <p class="mb-3">从题库中随机抽取题目进行综合测试</p>
            <a href="<?php echo BASE_PATH; ?>user/create_exam.php" class="btn btn-light btn-lg">
                <i class="bi bi-plus-circle"></i> 创建组卷
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-folder"></i> 选择题库</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <select class="form-select mb-3" name="bank_id" onchange="this.form.submit()">
                            <option value="">请选择题库</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" <?php echo (isset($_GET['bank_id']) && $_GET['bank_id'] == $bank['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php if (isset($_GET['bank_id']) && $_GET['bank_id']): ?>
                        <?php
                        // 查找选中的题库信息
                        $selectedBank = array_filter($banks, function($b) { return $b['id'] == $_GET['bank_id']; });
                        $selectedBank = reset($selectedBank);
                        ?>
                        <div class="mt-3">
                            <h6><?php echo htmlspecialchars($selectedBank['name']); ?></h6>
                            <p class="text-muted small"><?php echo htmlspecialchars($selectedBank['description'] ?: '暂无描述'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php if (empty($chapters)): ?>
                <!-- 未选择题库时显示的提示信息 -->
                <div class="card">
                    <div class="card-body text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">请先选择题库</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- 显示章节列表 -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-ul"></i> 选择章节</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($chapters as $chapter): ?>
                                <?php
                                // 获取当前章节的学习进度
                                $progress = $userProgress[$chapter['id']] ?? null;
                                // 计算正确率
                                $progressRate = $progress && $progress['total_count'] > 0
                                    ? round($progress['correct_count'] / $progress['total_count'] * 100, 1)
                                    : 0;
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6><?php echo htmlspecialchars($chapter['chapter_name']); ?></h6>
                                            <?php 
                                    $totalQuestions = $chapterTotalQuestions[$chapter['id']] ?? 0;
                                    $answeredCount = $progress['total_count'] ?? 0;
                                    $progressRate = $totalQuestions > 0 ? round($answeredCount / $totalQuestions * 100, 1) : 0;
                                ?>
                                <?php if ($totalQuestions > 0): ?>
                                    <!-- 显示学习进度 -->
                                    <div class="mb-2">
                                        <small class="text-muted">进度：<?php echo $answeredCount; ?>/<?php echo $totalQuestions; ?></small>
                                        <div class="progress mt-1" style="height: 6px;">
                                            <div class="progress-bar" style="width: <?php echo $progressRate; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                            <!-- 开始刷题按钮 -->
                                            <a href="<?php echo BASE_PATH; ?>user/quiz.php?bank_id=<?php echo $chapter['bank_id']; ?>&chapter_id=<?php echo $chapter['id']; ?>" class="btn btn-primary btn-sm w-100">
                                                <i class="bi bi-play-circle"></i> 开始刷题
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> <!-- 引入页面底部 -->