<?php
/**
 * 管理员仪表盘
 * 显示系统统计数据和图表分析
 */

// 引入配置文件，加载系统常量和通用函数
require_once '../config.php';

// 检查管理员权限，确保只有管理员可以访问此页面
checkAdmin();

// 设置页面标题
$page_title = '管理员仪表盘';

// 获取数据库连接
$pdo = getDB();

// 统计数据获取
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(), // 普通用户总数
    'total_banks' => $pdo->query("SELECT COUNT(*) FROM question_banks")->fetchColumn(), // 题库总数
    'total_chapters' => $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn(), // 章节总数
    'total_questions' => $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn(), // 题目总数
    'total_answers' => $pdo->query("SELECT COUNT(*) FROM user_answers")->fetchColumn(), // 答题记录总数
];

// 用户注册日期统计（最近30天）
$userRegistrationStats = $pdo->query("SELECT 
        DATE(created_at) as date, 
        COUNT(*) as count
    FROM users
    WHERE role = 'user'
    GROUP BY date
    ORDER BY date DESC
    LIMIT 30")->fetchAll();

// 答题正确率分布（按用户分组）
$correctRateStats = $pdo->query("SELECT 
        CASE 
            WHEN correct_rate < 60 THEN '低于60%'
            WHEN correct_rate < 80 THEN '60%-80%'
            ELSE '80%以上'
        END as rate_range,
        COUNT(*) as count
    FROM (
        SELECT 
            (SUM(is_correct) * 100.0 / COUNT(*)) as correct_rate
        FROM user_answers
        GROUP BY user_id
    ) as user_rates
    GROUP BY rate_range")->fetchAll();

// 系统平均答题正确率
$avgCorrect = $pdo->query("SELECT AVG(is_correct) * 100 as avg_rate FROM user_answers")->fetchColumn() ?: 0;

// 题库答题分布统计
$bankStats = $pdo->query("SELECT qb.name, COUNT(ua.id) as answer_count
    FROM question_banks qb
    LEFT JOIN questions q ON q.bank_id = qb.id
    LEFT JOIN user_answers ua ON ua.question_id = q.id
    GROUP BY qb.id
    ORDER BY answer_count DESC
    LIMIT 10")->fetchAll();

// 章节题目分布统计
$chapterStats = $pdo->query("SELECT c.chapter_name, COUNT(q.id) as question_count
    FROM chapters c
    LEFT JOIN questions q ON q.chapter_id = c.id
    GROUP BY c.id
    ORDER BY question_count DESC
    LIMIT 10")->fetchAll();
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/sidebar.php'; ?> <!-- 引入管理员侧边栏 -->

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2"></i> 仪表盘</h2>
        <button class="btn btn-outline-primary d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- 统计卡片 -->
    <div class="row mb-4 admin-stats-container">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-muted">用户总数</h5>
                    <h2 class="text-primary"><?php echo $stats['total_users']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-muted">题库数量</h5>
                    <h2 class="text-primary"><?php echo $stats['total_banks']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-muted">题目总数</h5>
                    <h2 class="text-primary"><?php echo $stats['total_questions']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-muted">平均正确率</h5>
                    <h2 class="text-success"><?php echo number_format($avgCorrect, 1); ?>%</h2>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* 管理员仪表盘移动端统计卡片简化样式 */
        @media (max-width: 768px) {
            .admin-stats-container .card { margin-bottom: 8px; padding: 0; }
            .admin-stats-container .card-body { padding: 12px 8px; }
            .admin-stats-container h2 { font-size: 18px; margin: 0 0 4px 0; }
            .admin-stats-container h5 { font-size: 12px; margin: 0; }
            
            /* 减小图表高度 */
            .chart-container { padding: 16px; margin-bottom: 16px; }
            #bankChart, #chapterChart, #registrationChart, #correctRateChart { height: 250px !important; }
        }
    </style>

    <!-- 图表区域 -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">题库答题分布</div>
                <div id="bankChart" style="height: 350px;"></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">章节题目分布</div>
                <div id="chapterChart" style="height: 350px;"></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">用户注册日期统计</div>
                <div id="registrationChart" style="height: 350px;"></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">用户答题正确率分布</div>
                <div id="correctRateChart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // 题库答题分布图表（柱状图）
    const bankChart = echarts.init(document.getElementById('bankChart'));
    const bankData = <?php echo json_encode($bankStats, JSON_UNESCAPED_UNICODE); ?>;
    bankChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'category', data: bankData.map(item => item.name), axisLabel: { rotate: 45 } },
        yAxis: { type: 'value' },
        series: [{ data: bankData.map(item => parseInt(item.answer_count)), type: 'bar', itemStyle: { color: '#4A90E2' } }]
    });

    // 章节题目分布图表（饼图）
    const chapterChart = echarts.init(document.getElementById('chapterChart'));
    const chapterData = <?php echo json_encode($chapterStats, JSON_UNESCAPED_UNICODE); ?>;
    chapterChart.setOption({
        tooltip: { trigger: 'item' },
        series: [{
            type: 'pie', radius: '60%',
            data: chapterData.map(item => ({ value: parseInt(item.question_count), name: item.chapter_name })),
            emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
        }]
    });

    // 用户注册日期统计图表（柱状图）
    const registrationChart = echarts.init(document.getElementById('registrationChart'));
    const registrationData = <?php echo json_encode($userRegistrationStats, JSON_UNESCAPED_UNICODE); ?>;
    registrationChart.setOption({
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: registrationData.map(item => item.date).reverse(), axisLabel: { rotate: 45 } },
        yAxis: { type: 'value' },
        series: [{ data: registrationData.map(item => parseInt(item.count)).reverse(), type: 'bar', itemStyle: { color: '#50C878' } }]
    });

    // 用户答题正确率分布图表（饼图）
    const correctRateChart = echarts.init(document.getElementById('correctRateChart'));
    const correctRateData = <?php echo json_encode($correctRateStats, JSON_UNESCAPED_UNICODE); ?>;
    correctRateChart.setOption({
        tooltip: { trigger: 'item' },
        series: [{
            type: 'pie', radius: '60%',
            data: correctRateData.map(item => ({ value: parseInt(item.count), name: item.rate_range })),
            emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } }
        }]
    });

    // 窗口大小变化时重新渲染图表
    window.addEventListener('resize', function() {
        bankChart.resize();
        chapterChart.resize();
        registrationChart.resize();
        correctRateChart.resize();
    });
</script>

<?php include '../includes/footer.php'; ?> <!-- 引入页面底部 -->