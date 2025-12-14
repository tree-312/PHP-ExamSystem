<?php
/**
 * 批量导入管理页面
 * 功能：支持从CSV和JSON两种格式批量导入题目数据
 * 特点：
 * 1. 自动创建不存在的题库和章节
 * 2. 支持单选、多选、填空、论述四种题型
 * 3. 提供详细的错误提示和验证机制
 * 4. 使用事务管理确保数据一致性
 */

// 引入配置文件（数据库连接、函数定义等）
require '../config.php';
// 检查管理员权限（未登录或非管理员将被重定向）
checkAdmin();

// 定义导入结果和错误变量
$result = null;
$error = null;
$totalSuccessCount = 0;
$totalErrorCount = 0;
$allErrors = [];

// 处理导入请求（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取导入数据和格式
    $data = $_POST['data'] ?? '';
    $importType = $_POST['import_type'] ?? 'csv';

    try {
        // 验证输入数据是否为空
        if (empty(trim($data))) {
            throw new Exception('导入数据不能为空，请粘贴CSV或JSON格式的数据');
        }

        // 连接数据库
        $pdo = connectDB();

        // 根据导入格式选择不同的处理逻辑
        if ($importType === 'csv') {
            // 处理CSV格式导入
            $pdo->beginTransaction();

            // 使用str_getcsv解析CSV数据（支持双引号包裹的字段）
            $rows = array_map('str_getcsv', explode("\n", trim($data)));
            if (empty($rows) || count($rows) < 2) {
                throw new Exception('CSV格式错误：至少需要包含表头和一条数据');
            }

            // 验证表头
            $expectedHeader = ['bank', 'chapter', 'title', 'type', 'options', 'answer', 'analysis'];
            $actualHeader = array_map('trim', $rows[0]);
            if ($actualHeader !== $expectedHeader) {
                throw new Exception('CSV表头格式错误，必须为：bank,chapter,title,type,options,answer,analysis');
            }

            // 定义支持的题型
            $validTypes = ['single', 'multiple', 'fill', 'essay'];

            // 遍历数据行（从第二行开始，跳过表头）
            for ($lineNum = 1; $lineNum < count($rows); $lineNum++) {
                $row = $rows[$lineNum];
                if (count($row) < 7) {
                    $allErrors[] = "第" . ($lineNum + 1) . "行：字段数量不足（至少需要7个字段）";
                    continue;
                }

                // 提取字段值
                $bankName = trim($row[0]);
                $chapterName = trim($row[1]);
                $title = trim($row[2]);
                $type = trim($row[3]);
                $optionsStr = trim($row[4]);
                $answerStr = trim($row[5]);
                $analysis = trim($row[6]);

                // 核心字段验证
                if (empty($bankName) || empty($chapterName) || empty($title) || empty($type)) {
                    $allErrors[] = "第" . ($lineNum + 1) . "行：缺少必填字段（bank、chapter、title、type均为必填）";
                    continue;
                }

                // 题型验证
                if (!in_array($type, $validTypes)) {
                    $allErrors[] = "第" . ($lineNum + 1) . "行：题型错误（仅支持single/multiple/fill/essay）";
                    continue;
                }

                // 单选/多选题的特殊验证
                if (in_array($type, ['single', 'multiple'])) {
                    if (empty($answerStr)) {
                        $allErrors[] = "第" . ($lineNum + 1) . "行：单选/多选题必须包含答案";
                        continue;
                    }
                }

                // 处理题库（不存在则创建）
                $stmt = $pdo->prepare("SELECT id FROM question_banks WHERE name = ?");
                $stmt->execute([$bankName]);
                $bank = $stmt->fetch();
                if (!$bank) {
                    $stmt = $pdo->prepare("INSERT INTO question_banks (name) VALUES (?)");
                    $stmt->execute([$bankName]);
                    $bankId = $pdo->lastInsertId();
                } else {
                    $bankId = $bank['id'];
                }

                // 处理章节（不存在则创建）
                $stmt = $pdo->prepare("SELECT id FROM chapters WHERE bank_id = ? AND chapter_name = ?");
                $stmt->execute([$bankId, $chapterName]);
                $chapter = $stmt->fetch();
                if (!$chapter) {
                    $stmt = $pdo->prepare("INSERT INTO chapters (bank_id, chapter_name) VALUES (?, ?)");
                    $stmt->execute([$bankId, $chapterName]);
                    $chapterId = $pdo->lastInsertId();
                } else {
                    $chapterId = $chapter['id'];
                }

                // 处理答案
                if ($type === 'multiple') {
                    $answers = array_map('trim', explode(',', $answerStr));
                    foreach ($answers as $ans) {
                        if (!preg_match('/^[A-Z]$/', $ans)) {
                            $totalErrorCount++;
                            $allErrors[] = "第" . ($lineNum + 2) . "行：多选答案必须为单个大写字母（如A,C）";
                            continue 2;
                        }
                    }
                    $answerJson = json_encode($answers, JSON_UNESCAPED_UNICODE);
                } else {
                    $answerJson = json_encode([trim($answerStr)], JSON_UNESCAPED_UNICODE);
                }

                // 插入题目
                $stmt = $pdo->prepare("INSERT INTO questions (bank_id, chapter_id, title, type, answer, analysis) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$bankId, $chapterId, $title, $type, $answerJson, $analysis]);
                $questionId = $pdo->lastInsertId();

                // 处理选项（仅针对单选/多选题）
                if (in_array($type, ['single', 'multiple']) && !empty($optionsStr)) {
                    $options = explode('|', $optionsStr);
                    foreach ($options as $index => $optionText) {
                        $optionText = trim($optionText);
                        if (!empty($optionText)) {
                            $label = chr(65 + $index); // 自动生成A,B,C...标签
                            $stmt = $pdo->prepare("INSERT INTO options (question_id, option_label, option_text, sort_order) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$questionId, $label, $optionText, $index]);
                        }
                    }
                }

                $totalSuccessCount++;
            }

            // 提交CSV导入事务
            $pdo->commit();
        } else {
            // ========== JSON格式导入处理 ==========
            $pdo->beginTransaction();

            // 步骤1：清洗JSON数据（移除注释、多余空格）
            $cleanData = preg_replace('/\/\/.*$/m', '', $data); // 移除单行注释
            $cleanData = preg_replace('/\/\*.*?\*\//s', '', $cleanData); // 移除多行注释
            $cleanData = trim($cleanData);

            // 步骤2：解析JSON（支持数组和单个对象）
            $jsonData = json_decode($cleanData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON格式错误：' . json_last_error_msg() . '（请检查语法，如逗号、引号是否闭合）');
            }

            // 步骤3：统一处理（无论是单个对象还是数组）
            $bankList = is_array($jsonData) && isset($jsonData[0]['bank']) ? $jsonData : [$jsonData];

            // 步骤4：遍历每个题库
            foreach ($bankList as $bankIndex => $bankItem) {
                // 校验题库级字段
                $bankName = $bankItem['bank'] ?? '';
                if (empty($bankName)) {
                    $allErrors[] = "题库" . ($bankIndex + 1) . "：缺少必填字段bank（题库名称）";
                    continue;
                }
                if (!isset($bankItem['questions']) || !is_array($bankItem['questions'])) {
                    $allErrors[] = "题库" . ($bankIndex + 1) . "（{$bankName}）：必须包含questions数组（题目列表）";
                    continue;
                }

                // 获取或创建题库
                $stmt = $pdo->prepare("SELECT id FROM question_banks WHERE name = ?");
                $stmt->execute([$bankName]);
                $bank = $stmt->fetch();
                if (!$bank) {
                    $stmt = $pdo->prepare("INSERT INTO question_banks (name) VALUES (?)");
                    $stmt->execute([$bankName]);
                    $bankId = $pdo->lastInsertId();
                } else {
                    $bankId = $bank['id'];
                }

                // 遍历当前题库的题目
                $questions = $bankItem['questions'];
                foreach ($questions as $qIndex => $q) {
                    $questionNum = "题库" . ($bankIndex + 1) . " - 题目" . ($qIndex + 1);

                    // 基础字段获取
                    $chapterName = $q['chapter'] ?? '';
                    $title = $q['title'] ?? '';
                    $type = $q['type'] ?? 'single';
                    $options = $q['options'] ?? [];
                    $answer = $q['answer'] ?? [];
                    $analysis = $q['analysis'] ?? '';

                    // 核心字段校验
                    if (empty($chapterName)) {
                        $totalErrorCount++;
                        $allErrors[] = "{$questionNum}：缺少必填字段chapter（章节名称）";
                        continue;
                    }
                    if (empty($title)) {
                        $totalErrorCount++;
                        $allErrors[] = "{$questionNum}：缺少必填字段title（题目内容）";
                        continue;
                    }

                    $validTypes = ['single', 'multiple', 'fill', 'essay'];
                    if (!in_array($type, $validTypes)) {
                        $totalErrorCount++;
                        $allErrors[] = "{$questionNum}：题型错误（仅支持single/multiple/fill/essay）";
                        continue;
                    }

                    // 单选/多选题校验
                    if (in_array($type, ['single', 'multiple'])) {
                        if (!is_array($options) || empty($options)) {
                            $totalErrorCount++;
                            $allErrors[] = "{$questionNum}：单选/多选题必须包含非空的options数组（选项列表）";
                            continue;
                        }
                        if (!is_array($answer) || empty($answer)) {
                            $totalErrorCount++;
                            $allErrors[] = "{$questionNum}：单选/多选题必须包含非空的answer数组（如[\"A\"]或[\"A\",\"C\"]）";
                            continue;
                        }
                        // 校验答案格式
                        foreach ($answer as $ans) {
                            if (!preg_match('/^[A-Z]$/', $ans)) {
                                $totalErrorCount++;
                                $allErrors[] = "{$questionNum}：答案必须为单个大写字母（如A、B），当前错误值：{$ans}";
                                continue 2;
                            }
                        }
                        // 校验选项是否为空字符串
                        foreach ($options as $optIndex => $optText) {
                            if (trim($optText) === '') {
                                $totalErrorCount++;
                                $allErrors[] = "{$questionNum}：选项" . chr(65 + $optIndex) . "不能为空字符串";
                                continue 2;
                            }
                        }
                    }

                    // 获取或创建章节
                    $stmt = $pdo->prepare("SELECT id FROM chapters WHERE bank_id = ? AND chapter_name = ?");
                    $stmt->execute([$bankId, $chapterName]);
                    $chapter = $stmt->fetch();
                    if (!$chapter) {
                        $stmt = $pdo->prepare("INSERT INTO chapters (bank_id, chapter_name) VALUES (?, ?)");
                        $stmt->execute([$bankId, $chapterName]);
                        $chapterId = $pdo->lastInsertId();
                    } else {
                        $chapterId = $chapter['id'];
                    }

                    // 处理答案
                    if ($type === 'multiple') {
                        $answerJson = json_encode($answer, JSON_UNESCAPED_UNICODE);
                    } else {
                        $answerJson = json_encode($answer ? [$answer[0] ?? ''] : [''], JSON_UNESCAPED_UNICODE);
                    }

                    // 插入题目（防SQL注入，所有参数都用预处理）
                    $stmt = $pdo->prepare("INSERT INTO questions (bank_id, chapter_id, title, type, answer, analysis) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$bankId, $chapterId, $title, $type, $answerJson, $analysis]);
                    $questionId = $pdo->lastInsertId();

                    // 处理选项
                    if (in_array($type, ['single', 'multiple']) && is_array($options)) {
                        foreach ($options as $index => $optionText) {
                            $optionText = trim($optionText);
                            if (!empty($optionText)) {
                                $label = chr(65 + $index); // 自动生成A,B,C...标签
                                $stmt = $pdo->prepare("INSERT INTO options (question_id, option_label, option_text, sort_order) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$questionId, $label, $optionText, $index]);
                            }
                        }
                    }

                    $totalSuccessCount++;
                }
            }

            // 提交JSON导入事务
            $pdo->commit();
        }

        // 组装导入结果
        $result = [
            'success' => true,
            'successCount' => $totalSuccessCount,
            'errorCount' => $totalErrorCount,
            'errors' => $allErrors
        ];

    } catch (PDOException $e) {
        // 数据库操作失败，回滚事务
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = '数据库操作失败：' . $e->getMessage() . '（SQL状态码：' . $e->getCode() . '）';
        // 记录详细错误日志（建议实际项目中写入日志文件）
        error_log("导入失败 - " . date('Y-m-d H:i:s') . " - " . $e->getMessage() . " - SQL: " . $e->getTraceAsString());
    } catch (Exception $e) {
        // 其他异常处理，回滚事务
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = '导入失败：' . $e->getMessage();
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-4"><i class="bi bi-upload"></i> 批量导入</h2>
            <button class="btn btn-outline-primary d-md-none" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert alert-<?php echo $result['errorCount'] > 0 ? 'warning' : 'success'; ?>">
                <h5>导入完成！</h5>
                <p>成功导入：<strong><?php echo $result['successCount']; ?></strong> 条</p>
                <?php if ($result['errorCount'] > 0): ?>
                    <p>失败：<strong><?php echo $result['errorCount']; ?></strong> 条</p>
                    <?php if (!empty($result['errors'])): ?>
                        <ul class="mb-0">
                            <?php foreach ($result['errors'] as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5>导入格式说明 <span class="text-danger">（重要：必看）</span></h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="formatTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#csvTab">CSV格式</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#jsonTab">JSON格式</button>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="csvTab">
                        <p><strong>CSV格式要求：</strong></p>
                        <ol>
                            <li>第一行为固定表头：<code>bank,chapter,title,type,options,answer,analysis</code>（顺序不可改）</li>
                            <li>选项之间用 <code>|</code> 分隔，<strong>无需手动填写A/B/C标签</strong>（系统自动生成）</li>
                            <li>多选题答案用逗号分隔，且必须用英文双引号包裹（如：<code>"A,C"</code>）</li>
                            <li>题型仅支持：<code>single</code>（单选）、<code>multiple</code>（多选）、<code>fill</code>（填空）、<code>essay</code>（论述）</li>
                            <li>字段内容包含逗号时，必须用英文双引号包裹（如答案<code>"A,C"</code>）</li>
                            <li>填空/论述题的options字段可留空，answer字段直接填写文本</li>
                        </ol>
                        <div class="alert alert-info">
                            <strong>错误示例（需避免）：</strong><br>
                            错误：<code>数据挖掘,支持向量机,什么是SVM?,single,"A|支持向量机|B|神经网络",A,SVM是监督学习算法</code><br>
                            正确：<code>数据挖掘,支持向量机,什么是SVM?,single,支持向量机|神经网络|决策树|K-means,A,SVM是一种监督学习算法</code>
                        </div>
                        <pre class="bg-light p-3 rounded"><code>bank,chapter,title,type,options,answer,analysis
数据挖掘,支持向量机,什么是SVM?,single,支持向量机|神经网络|决策树|K-means,A,SVM是一种监督学习算法
ERP理论与应用,ERP概述,ERP的主要特点？,multiple,集成性|实时性|模块化|复杂性,"A,C",ERP具有集成性和模块化特点
Python基础,变量,Python变量命名规则？,fill,,只能以字母/下划线开头,变量命名核心规则
Java基础,异常,简述try-catch执行顺序,essay,,,try块执行→异常则执行catch</code></pre>
                    </div>
                    <div class="tab-pane fade" id="jsonTab">
                        <p><strong>JSON格式核心要求：</strong></p>
                        <ol>
                            <li>支持两种格式：
                                <ul>
                                    <li>单题库：根节点为对象，包含<code>bank</code>和<code>questions</code>数组</li>
                                    <li>多题库：根节点为数组，每个元素是包含<code>bank</code>和<code>questions</code>的对象</li>
                                </ul>
                            </li>
                            <li>每个题目必须包含：<code>chapter</code>（章节名称）、<code>title</code>（题目内容）、<code>type</code>（题型）</li>
                            <li>题型取值：<code>single</code>（单选）、<code>multiple</code>（多选）、<code>fill</code>（填空）、<code>essay</code>（论述）</li>
                            <li>单选/多选题必填：<code>options</code>（选项数组，如["选项1","选项2"]）、<code>answer</code>（答案数组，如["A"]或["A","C"]）</li>
                            <li>填空/论述题：<code>options</code>可留空数组，<code>answer</code>数组填文本（填空）或留空（论述）</li>
                            <li>答案格式：单选/多选必须是大写字母数组（如["A"]），填空/论述为文本数组（如["TPS（每秒事务数）"]）</li>
                            <li>所有字段使用英文引号，语法需严格符合JSON规范（逗号、括号闭合）</li>
                            <li>JSON中<strong>不支持注释</strong>（// 或 /* */），导入前请移除注释</li>
                        </ol>
                        <div class="alert alert-warning">
                            <strong>常见错误提醒：</strong><br>
                            1. 答案不能填中文/数字（如["是"]），必须是大写字母（如["A"]）；<br>
                            2. options数组不能为空（单选/多选），且元素需为非空字符串；<br>
                            3. 避免末尾逗号、引号未闭合等语法错误；<br>
                            4. 移除JSON中的所有注释（// 或 /* */）；<br>
                            5. 多题库导入时，根节点必须是数组格式。
                        </div>
                        <h6 class="mt-3">多题库JSON示例：</h6>
                        <pre class="bg-light p-3 rounded"><code>[
  {
    "bank": "软件测试题库",
    "questions": [
      {
        "chapter": "功能测试",
        "type": "single",
        "title": "以下哪项不是黑盒测试方法？",
        "options": ["等价类划分", "边界值分析", "语句覆盖", "场景法"],
        "answer": ["C"],
        "analysis": "语句覆盖属于白盒测试的逻辑覆盖方法"
      }
    ]
  },
  {
    "bank": "编程语言题库",
    "questions": [
      {
        "chapter": "Python基础",
        "type": "single",
        "title": "Python中，定义函数的关键字是？",
        "options": ["func", "def", "function", "define"],
        "answer": ["B"],
        "analysis": "def是Python定义函数的关键字"
      }
    ]
  }
]</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>导入数据</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">导入格式</label>
                        <select class="form-select" name="import_type" id="importType" onchange="updatePlaceholder()">
                            <option value="csv">CSV格式</option>
                            <option value="json">JSON格式</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">导入数据</label>
                        <textarea class="form-control" name="data" id="importData" rows="15" required placeholder="请粘贴CSV或JSON数据..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> 开始导入
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePlaceholder() {
            const type = document.getElementById('importType').value;
            const textarea = document.getElementById('importData');
            if (type === 'csv') {
                textarea.placeholder = '请粘贴CSV数据，第一行为表头：bank,chapter,title,type,options,answer,analysis\n提示：多选答案需用双引号包裹（如"A,C"），选项无需加A/B/C标签';
            } else {
                textarea.placeholder = '请粘贴JSON数据，格式要求：\n1. 支持单题库（对象）或多题库（数组）格式\n2. 根节点包含bank（题库名）和questions（题目数组）\n3. 单选/多选题需填options（选项数组）和answer（字母数组）\n4. 移除所有// 或 /* */注释\n5. 参考上方格式说明示例';
            }
        }
        // 初始化placeholder
        updatePlaceholder();
    </script>

<?php include '../includes/footer.php'; ?>