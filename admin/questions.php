<?php
require_once '../config.php';
checkAdmin();

$page_title = '题目管理';
$pdo = getDB();

// 处理删除
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: questions.php?msg=删除成功');
    exit;
}

// 处理添加/编辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $bank_id = $_POST['bank_id'] ?? null;
    $chapter_id = $_POST['chapter_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'single';
    $answer = $_POST['answer'] ?? '';
    $analysis = trim($_POST['analysis'] ?? '');

    // 处理答案（多选转为数组，其他题型转为数组格式）
    if ($type === 'multiple') {
        // 多选题答案处理
        $answerArray = is_array($answer) ? $answer : explode(',', $answer);
        $answerJson = json_encode($answerArray, JSON_UNESCAPED_UNICODE);
    } else {
        // 其他题型答案处理
        $answerJson = json_encode([$answer], JSON_UNESCAPED_UNICODE);
    }

    if (empty($title) || empty($bank_id) || empty($chapter_id)) {
        $error = '请填写完整信息';
    } else {
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE questions SET bank_id = ?, chapter_id = ?, title = ?, type = ?, answer = ?, analysis = ? WHERE id = ?");
                $stmt->execute([$bank_id, $chapter_id, $title, $type, $answerJson, $analysis, $id]);

                // 删除旧选项（如果是选择题）
                $pdo->prepare("DELETE FROM options WHERE question_id = ?")->execute([$id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO questions (bank_id, chapter_id, title, type, answer, analysis) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$bank_id, $chapter_id, $title, $type, $answerJson, $analysis]);
                $id = $pdo->lastInsertId();
            }

            // 添加选项（仅针对选择题型）
            if (in_array($type, ['single', 'multiple'])) {
                $options = $_POST['options'] ?? [];
                $optionLabels = $_POST['option_labels'] ?? [];
                foreach ($options as $index => $optionText) {
                    if (!empty($optionText)) {
                        $label = $optionLabels[$index] ?? chr(65 + $index); // 默认选项标签：A, B, C, D...
                        $stmt = $pdo->prepare("INSERT INTO options (question_id, option_label, option_text, sort_order) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$id, $label, $optionText, $index]);
                    }
                }
            }

            // 提交事务
            $pdo->commit();
            header('Location: questions.php?msg=' . ($id && isset($_POST['id']) ? '更新成功' : '添加成功'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

// 获取编辑题目
$editQuestion = null;
$editOptions = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editQuestion = $stmt->fetch();
    if ($editQuestion) {
        $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
        $stmt->execute([$_GET['edit']]);
        $editOptions = $stmt->fetchAll();
        // 解析答案为数组格式
        $editQuestion['answer'] = json_decode($editQuestion['answer'], true);
    }
}

// 获取题库和章节
$banks = $pdo->query("SELECT * FROM question_banks ORDER BY name")->fetchAll();
$chapters = [];
if (isset($_GET['bank_id']) || ($editQuestion && $editQuestion['bank_id'])) {
    $bankId = $_GET['bank_id'] ?? $editQuestion['bank_id'];
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE bank_id = ? ORDER BY sort_order, chapter_name");
    $stmt->execute([$bankId]);
    $chapters = $stmt->fetchAll();
}

// 获取题目列表
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if (isset($_GET['bank_id']) && $_GET['bank_id']) {
    $where[] = "q.bank_id = ?";
    $params[] = $_GET['bank_id'];
}
if (isset($_GET['chapter_id']) && $_GET['chapter_id']) {
    $where[] = "q.chapter_id = ?";
    $params[] = $_GET['chapter_id'];
}
if (isset($_GET['type']) && $_GET['type']) {
    $where[] = "q.type = ?";
    $params[] = $_GET['type'];
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$total = $pdo->prepare("SELECT COUNT(*) FROM questions q $whereClause");
$total->execute($params);
$totalCount = $total->fetchColumn();

$questions = $pdo->prepare("SELECT q.*, qb.name as bank_name, c.chapter_name 
                             FROM questions q 
                             LEFT JOIN question_banks qb ON qb.id = q.bank_id 
                             LEFT JOIN chapters c ON c.id = q.chapter_id 
                             $whereClause 
                             ORDER BY q.id DESC 
                             LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$questions->execute($params);
$questions = $questions->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-question-circle"></i> 题目管理</h2>
        <div class="d-flex gap-2">
            <!-- 添加题目按钮 -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#questionModal">
                <i class="bi bi-plus-circle"></i> 添加题目
            </button>
            <!-- 移动端菜单按钮 -->
            <button class="btn btn-outline-primary d-md-none" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- 筛选区域 -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">选择题库</label>
                    <select class="form-select" name="bank_id" id="filterBankId" onchange="loadChaptersForFilter(this.value)">
                        <option value="">全部题库</option>
                        <?php foreach ($banks as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>" <?php echo (isset($_GET['bank_id']) && $_GET['bank_id'] == $bank['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">选择章节</label>
                    <select class="form-select" name="chapter_id" id="filterChapterId">
                        <option value="">全部章节</option>
                        <?php foreach ($chapters as $chapter): ?>
                            <option value="<?php echo $chapter['id']; ?>" <?php echo (isset($_GET['chapter_id']) && $_GET['chapter_id'] == $chapter['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">选择题型</label>
                    <select class="form-select" name="type">
                        <option value="">全部题型</option>
                        <option value="single" <?php echo (isset($_GET['type']) && $_GET['type'] === 'single') ? 'selected' : ''; ?>>单选题</option>
                        <option value="multiple" <?php echo (isset($_GET['type']) && $_GET['type'] === 'multiple') ? 'selected' : ''; ?>>多选题</option>
                        <option value="fill" <?php echo (isset($_GET['type']) && $_GET['type'] === 'fill') ? 'selected' : ''; ?>>填空题</option>
                        <option value="essay" <?php echo (isset($_GET['type']) && $_GET['type'] === 'essay') ? 'selected' : ''; ?>>论述题</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-outline-primary w-100">筛选</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 题目列表 -->
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>题库</th>
                    <th>章节</th>
                    <th>题目</th>
                    <th>题型</th>
                    <th>答案</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q): ?>
                    <?php
                    // 获取题目选项
                    $stmt = $pdo->prepare("SELECT * FROM options WHERE question_id = ? ORDER BY sort_order");
                    $stmt->execute([$q['id']]);
                    $qOptions = $stmt->fetchAll();
                    $q['options'] = $qOptions;
                    ?>
                    <tr>
                        <td><?php echo $q['id']; ?></td>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($q['bank_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($q['chapter_name']); ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php
                            $title = $q['title'];
                            // 截断显示过长的题干
                            if (function_exists('mb_substr')) {
                                echo htmlspecialchars(mb_substr($title, 0, 50));
                            } else {
                                echo htmlspecialchars(substr($title, 0, 50));
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            // 题型名称映射
                            $typeNames = ['single' => '单选', 'multiple' => '多选', 'fill' => '填空', 'essay' => '论述'];
                            echo $typeNames[$q['type']] ?? $q['type'];
                            ?>
                        </td>
                        <td>
                            <?php
                            // 显示答案
                            $answer = json_decode($q['answer'], true);
                            echo is_array($answer) ? implode(', ', $answer) : $answer;
                            ?>
                        </td>
                        <td>
                            <!-- 编辑按钮 -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q, JSON_UNESCAPED_UNICODE)); ?>)">
                                <i class="bi bi-pencil"></i> 编辑
                            </button>
                            <!-- 删除按钮 -->
                            <a href="?delete=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定删除吗？')">
                                <i class="bi bi-trash"></i> 删除
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <?php if ($totalCount > $perPage): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php
                        $totalPages = ceil($totalCount / $perPage);
                        for ($i = 1; $i <= $totalPages; $i++):
                            $query = $_GET;
                            $query['page'] = $i;
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query($query); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 题目编辑模态框 -->
<div class="modal fade" id="questionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $editQuestion ? '编辑题目' : '添加题目'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="questionForm">
                <div class="modal-body">
                    <?php if ($editQuestion): ?>
                        <input type="hidden" name="id" value="<?php echo $editQuestion['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">所属题库</label>
                            <select class="form-select" name="bank_id" id="bankSelect" required onchange="loadChapters(this.value)">
                                <option value="">请选择题库</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?php echo $bank['id']; ?>" <?php echo ($editQuestion && $editQuestion['bank_id'] == $bank['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bank['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">所属章节</label>
                            <select class="form-select" name="chapter_id" id="chapterSelect" required>
                                <option value="">请先选择题库</option>
                                <?php foreach ($chapters as $chapter): ?>
                                    <option value="<?php echo $chapter['id']; ?>" <?php echo ($editQuestion && $editQuestion['chapter_id'] == $chapter['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">题型</label>
                        <select class="form-select" name="type" id="questionType" required onchange="toggleOptions()">
                            <option value="single" <?php echo ($editQuestion && $editQuestion['type'] === 'single') ? 'selected' : ''; ?>>单选题</option>
                            <option value="multiple" <?php echo ($editQuestion && $editQuestion['type'] === 'multiple') ? 'selected' : ''; ?>>多选题</option>
                            <option value="fill" <?php echo ($editQuestion && $editQuestion['type'] === 'fill') ? 'selected' : ''; ?>>填空题</option>
                            <option value="essay" <?php echo ($editQuestion && $editQuestion['type'] === 'essay') ? 'selected' : ''; ?>>论述题</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">题干</label>
                        <textarea class="form-control" name="title" rows="3" required><?php echo $editQuestion ? htmlspecialchars($editQuestion['title']) : ''; ?></textarea>
                    </div>
                    <div id="optionsContainer" style="display: none;">
                        <label class="form-label">选项</label>
                        <div id="optionsList">
                            <?php if ($editQuestion && in_array($editQuestion['type'], ['single', 'multiple'])): ?>
                                <?php foreach ($editOptions as $index => $opt): ?>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text"><?php echo htmlspecialchars($opt['option_label']); ?></span>
                                        <input type="hidden" name="option_labels[]" value="<?php echo htmlspecialchars($opt['option_label']); ?>">
                                        <input type="text" class="form-control" name="options[]" value="<?php echo htmlspecialchars($opt['option_text']); ?>" required>
                                        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">A</span>
                                    <input type="hidden" name="option_labels[]" value="A">
                                    <input type="text" class="form-control" name="options[]" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text">B</span>
                                    <input type="hidden" name="option_labels[]" value="B">
                                    <input type="text" class="form-control" name="options[]" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOption()">
                            <i class="bi bi-plus"></i> 添加选项
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">答案</label>
                        <div id="answerContainer">
                            <?php if ($editQuestion && $editQuestion['type'] === 'multiple'): ?>
                                <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $label): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="answer[]" value="<?php echo $label; ?>"
                                            <?php echo (in_array($label, $editQuestion['answer'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo $label; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif ($editQuestion && $editQuestion['type'] === 'single'): ?>
                                <select class="form-select" name="answer">
                                    <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $label): ?>
                                        <option value="<?php echo $label; ?>" <?php echo ($editQuestion['answer'][0] === $label) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" name="answer" value="<?php echo $editQuestion ? htmlspecialchars(implode(', ', $editQuestion['answer'])) : ''; ?>" required>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">解析</label>
                        <textarea class="form-control" name="analysis" rows="3"><?php echo $editQuestion ? htmlspecialchars($editQuestion['analysis']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- 取消按钮 -->
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <!-- 保存按钮 -->
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let optionIndex = <?php echo $editQuestion && $editOptions ? count($editOptions) : 2; ?>;

    function loadChapters(bankId) {
        if (!bankId) {
            document.getElementById('chapterSelect').innerHTML = '<option value="">请先选择题库</option>';
            return Promise.resolve();
        }
        const basePath = '<?php echo BASE_PATH; ?>';
        return fetch(`${basePath}api/get_chapters.php?bank_id=${bankId}`)
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('chapterSelect');
                select.innerHTML = '<option value="">请选择章节</option>';
                data.forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter.id;
                    option.textContent = chapter.chapter_name;
                    select.appendChild(option);
                });
            });
    }

    function toggleOptions() {
        const type = document.getElementById('questionType').value;
        const container = document.getElementById('optionsContainer');
        const answerContainer = document.getElementById('answerContainer');

        if (type === 'single' || type === 'multiple') {
            container.style.display = 'block';
            if (type === 'single') {
                answerContainer.innerHTML = '<select class="form-select" name="answer"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option><option value="F">F</option></select>';
            } else {
                let html = '';
                ['A', 'B', 'C', 'D', 'E', 'F'].forEach(label => {
                    html += `<div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="answer[]" value="${label}">
                    <label class="form-check-label">${label}</label>
                </div>`;
                });
                answerContainer.innerHTML = html;
            }
        } else {
            container.style.display = 'none';
            answerContainer.innerHTML = '<input type="text" class="form-control" name="answer" required>';
        }
    }

    function addOption() {
        const labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        const label = labels[optionIndex] || String.fromCharCode(65 + optionIndex);
        const div = document.createElement('div');
        div.className = 'input-group mb-2';
        div.innerHTML = `
        <span class="input-group-text">${label}</span>
        <input type="hidden" name="option_labels[]" value="${label}">
        <input type="text" class="form-control" name="options[]" required>
        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
    `;
        document.getElementById('optionsList').appendChild(div);
        optionIndex++;
    }

    function removeOption(btn) {
        if (document.getElementById('optionsList').children.length > 1) {
            btn.parentElement.remove();
        } else {
            alert('至少保留一个选项');
        }
    }

    function loadChaptersForFilter(bankId) {
        if (!bankId) {
            document.getElementById('filterChapterId').innerHTML = '<option value="">全部章节</option>';
            return;
        }
        const basePath = '<?php echo BASE_PATH; ?>';
        fetch(`${basePath}api/get_chapters.php?bank_id=${bankId}`)
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('filterChapterId');
                select.innerHTML = '<option value="">全部章节</option>';
                data.forEach(chapter => {
                    const option = document.createElement('option');
                    option.value = chapter.id;
                    option.textContent = chapter.chapter_name;
                    select.appendChild(option);
                });
            });
    }

    function editQuestion(question) {
        // 填充表单
        document.querySelector('input[name="id"]')?.remove();
        const form = document.querySelector('#questionModal form');
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = question.id;
        form.insertBefore(idInput, form.firstChild);

        // 填充基本信息
        document.querySelector('select[name="bank_id"]').value = question.bank_id || '';
        if (question.bank_id) {
            loadChapters(question.bank_id).then(() => {
                document.querySelector('select[name="chapter_id"]').value = question.chapter_id || '';
            });
        }
        // 填充题型、题干和解析
        document.querySelector('select[name="type"]').value = question.type || 'single';
        document.querySelector('textarea[name="title"]').value = question.title || '';
        document.querySelector('textarea[name="analysis"]').value = question.analysis || '';

        // 切换题型显示
        toggleOptions();

        // 填充选项
        if (question.options && question.options.length > 0) {
            const optionsList = document.getElementById('optionsList');
            optionsList.innerHTML = '';
            question.options.forEach((opt, index) => {
                const div = document.createElement('div');
                div.className = 'input-group mb-2';
                div.innerHTML = `
                <span class="input-group-text">${opt.option_label}</span>
                <input type="hidden" name="option_labels[]" value="${opt.option_label}">
                <input type="text" class="form-control" name="options[]" value="${opt.option_text}" required>
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
            `;
                optionsList.appendChild(div);
            });
            optionIndex = question.options.length;
        }

        // 填充答案
        const answer = JSON.parse(question.answer || '[]');
        const answerContainer = document.getElementById('answerContainer');
        if (question.type === 'single') {
            answerContainer.innerHTML = '<select class="form-select" name="answer"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option><option value="E">E</option><option value="F">F</option></select>';
            if (answer.length > 0) {
                document.querySelector('select[name="answer"]').value = answer[0];
            }
        } else if (question.type === 'multiple') {
            let html = '';
            ['A', 'B', 'C', 'D', 'E', 'F'].forEach(label => {
                const checked = answer.includes(label) ? 'checked' : '';
                html += `<div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="answer[]" value="${label}" ${checked}>
                <label class="form-check-label">${label}</label>
            </div>`;
            });
            answerContainer.innerHTML = html;
        } else {
            answerContainer.innerHTML = '<input type="text" class="form-control" name="answer" value="' + (answer.length > 0 ? answer[0] : '') + '" required>';
        }

        // 更新标题
        document.querySelector('#questionModal .modal-title').textContent = '编辑题目';

        // 显示模态框
        const modal = new bootstrap.Modal(document.getElementById('questionModal'));
        modal.show();
    }

    // 添加题目时重置表单
    document.getElementById('questionModal').addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        form.reset();
        document.querySelector('input[name="id"]')?.remove();
        document.querySelector('#questionModal .modal-title').textContent = '添加题目';
        document.getElementById('optionsList').innerHTML = `
        <div class="input-group mb-2">
            <span class="input-group-text">A</span>
            <input type="hidden" name="option_labels[]" value="A">
            <input type="text" class="form-control" name="options[]" required>
            <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
        </div>
        <div class="input-group mb-2">
            <span class="input-group-text">B</span>
            <input type="hidden" name="option_labels[]" value="B">
            <input type="text" class="form-control" name="options[]" required>
            <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">删除</button>
        </div>
    `;
        optionIndex = 2;
    });

    // 编辑时填充表单（URL参数方式）
    <?php if ($editQuestion): ?>
    document.addEventListener('DOMContentLoaded', function() {
        editQuestion(<?php echo json_encode(array_merge($editQuestion, ['options' => $editOptions]), JSON_UNESCAPED_UNICODE); ?>);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>


