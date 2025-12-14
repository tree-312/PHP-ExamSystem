<?php
require_once '../config.php';
checkUser();

$page_title = '统计图表';
$pdo = getDB();
$userId = $_SESSION['user_id'];

// 章节正确率数据
$chapterStats = $pdo->prepare("
    SELECT c.chapter_name, 
           COALESCE(up.correct_count, 0) as correct_count,
           COALESCE(up.total_count, 0) as total_count,
           CASE 
               WHEN COALESCE(up.total_count, 0) > 0 
               THEN ROUND(up.correct_count * 100.0 / up.total_count, 1)
               ELSE 0 
           END as correct_rate
    FROM chapters c
    LEFT JOIN user_progress up ON up.chapter_id = c.id AND up.user_id = ?
    WHERE up.total_count > 0
    ORDER BY up.updated_at DESC
    LIMIT 20
");
$chapterStats->execute([$userId]);
$chapterStats = $chapterStats->fetchAll();



// 累计答题量（按日期统计）
$answerStats = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM user_answers
    WHERE user_id = ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
");
$answerStats->execute([$userId]);
$answerStats = $answerStats->fetchAll();

// 题目类型分布
$questionTypeStats = $pdo->prepare("
    SELECT q.type, COUNT(*) as count
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    WHERE ua.user_id = ?
    GROUP BY q.type
");
$questionTypeStats->execute([$userId]);
$questionTypeStats = $questionTypeStats->fetchAll();

// 每日学习时长统计（按小时）
$dailyDurationStats = $pdo->prepare("
    SELECT 
        DATE(created_at) as date, 
        HOUR(created_at) as hour, 
        COUNT(*) as count
    FROM user_answers
    WHERE user_id = ?
    GROUP BY date, hour
    ORDER BY date DESC, hour
    LIMIT 48
");
$dailyDurationStats->execute([$userId]);
$dailyDurationStats = $dailyDurationStats->fetchAll();

// 总体统计
$totalStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_answers,
        SUM(is_correct) as correct_answers,
        COUNT(DISTINCT question_id) as unique_questions,
        COUNT(DISTINCT DATE(created_at)) as study_days
    FROM user_answers
    WHERE user_id = ?
");
$totalStats->execute([$userId]);
$totalStats = $totalStats->fetch();
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container">
    <!-- 总体统计 -->
    <div class="row mb-4 stats-container">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo $totalStats['total_answers']; ?></h3>
                    <p class="text-muted mb-0">总答题数</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo $totalStats['correct_answers']; ?></h3>
                    <p class="text-muted mb-0">正确题数</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-info"><?php echo $totalStats['unique_questions']; ?></h3>
                    <p class="text-muted mb-0">已做题数</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?php echo $totalStats['study_days']; ?></h3>
                    <p class="text-muted mb-0">学习天数</p>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* 移动端统计卡片简化样式 */
        @media (max-width: 768px) {
            .stats-container .card {
                margin-bottom: 8px;
                padding: 0;
            }

            .stats-container .card-body {
                padding: 12px 8px;
            }

            .stats-container h3 {
                font-size: 18px;
                margin: 0 0 4px 0;
            }

            .stats-container p {
                font-size: 12px;
                margin: 0;
            }

            /* 减小图表高度 */
            .chart-container {
                padding: 16px;
                margin-bottom: 16px;
            }

            #chapterRateChart,
            #answerCountChart,
            #questionTypeChart,
            #dailyDurationChart {
                height: 250px !important;
            }
        }
    </style>

    <!-- 图表 -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">章节正确率</div>
                <div id="chapterRateChart" style="height: 350px;"></div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">累计答题量</div>
                <div id="answerCountChart" style="height: 350px;"></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">题目类型分布</div>
                <div id="questionTypeChart" style="height: 350px;"></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="chart-container">
                <div class="chart-title">每日学习时长</div>
                <div id="dailyDurationChart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // 章节正确率折线图
    const chapterRateChart = echarts.init(document.getElementById('chapterRateChart'));
    const chapterData = <?php echo json_encode($chapterStats, JSON_UNESCAPED_UNICODE); ?>;
    chapterRateChart.setOption({
        tooltip: {
            trigger: 'axis',
            formatter: function(params) {
                const data = params[0];
                return `${data.name}<br/>正确率: ${data.value}%<br/>正确: ${data.data.correct}/${data.data.total}`;
            }
        },
        xAxis: {
            type: 'category',
            data: chapterData.map(item => item.chapter_name),
            axisLabel: { rotate: 45 }
        },
        yAxis: {
            type: 'value',
            max: 100,
            axisLabel: { formatter: '{value}%' }
        },
        series: [{
            data: chapterData.map(item => ({
                value: parseFloat(item.correct_rate),
                correct: item.correct_count,
                total: item.total_count
            })),
            type: 'line',
            smooth: true,
            itemStyle: { color: '#4A90E2' },
            areaStyle: { color: 'rgba(74, 144, 226, 0.1)' }
        }]
    });



    // 累计答题量柱状图
    const answerCountChart = echarts.init(document.getElementById('answerCountChart'));
    const answerData = <?php echo json_encode($answerStats, JSON_UNESCAPED_UNICODE); ?>;
    answerCountChart.setOption({
        tooltip: {
            trigger: 'axis'
        },
        xAxis: {
            type: 'category',
            data: answerData.map(item => item.date).reverse()
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            data: answerData.map(item => parseInt(item.count)).reverse(),
            type: 'bar',
            itemStyle: { color: '#4A90E2' }
        }]
    });

    // 题目类型分布饼图
    const questionTypeChart = echarts.init(document.getElementById('questionTypeChart'));
    const questionTypeData = <?php echo json_encode($questionTypeStats, JSON_UNESCAPED_UNICODE); ?>;
    // 类型映射
    const typeMap = {
        'single': '单选题',
        'multiple': '多选题',
        'fill': '填空题',
        'essay': '简答题'
    };
    if (questionTypeData.length > 0) {
        questionTypeChart.setOption({
            tooltip: {
                trigger: 'item'
            },
            series: [{
                type: 'pie',
                radius: '60%',
                data: questionTypeData.map(item => ({
                    value: parseInt(item.count),
                    name: typeMap[item.type] || item.type
                })),
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                }
            }]
        });
    } else {
        questionTypeChart.setOption({
            graphic: {
                type: 'text',
                left: 'center',
                top: 'middle',
                style: {
                    text: '暂无数据',
                    fontSize: 16,
                    fill: '#999'
                }
            }
        });
    }

    // 每日学习时长热力图
    const dailyDurationChart = echarts.init(document.getElementById('dailyDurationChart'));
    const dailyDurationData = <?php echo json_encode($dailyDurationStats, JSON_UNESCAPED_UNICODE); ?>;

    // 处理数据
    const processedData = dailyDurationData.map(item => [item.date, item.hour, item.count]);

    dailyDurationChart.setOption({
        tooltip: {
            position: 'top',
            formatter: function(params) {
                return `${params.value[0]} ${params.value[1]}时<br/>答题数: ${params.value[2]}`;
            }
        },
        xAxis: {
            type: 'category',
            data: [...new Set(dailyDurationData.map(item => item.date))].reverse(),
            axisLabel: { rotate: 45 }
        },
        yAxis: {
            type: 'category',
            data: Array.from({length: 24}, (_, i) => i + '时'),
            splitArea: {
                show: true
            }
        },
        visualMap: {
            min: 0,
            max: Math.max(...dailyDurationData.map(item => item.count)) || 10,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: '15%'
        },
        series: [{
            name: '学习时长',
            type: 'heatmap',
            data: processedData,
            label: {
                show: true,
                formatter: '{c}'
            },
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            }
        }]
    });

    window.addEventListener('resize', function() {
        chapterRateChart.resize();
        answerCountChart.resize();
        questionTypeChart.resize();
        dailyDurationChart.resize();
    });
</script>

<?php include '../includes/footer.php'; ?>

