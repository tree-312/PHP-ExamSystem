<?php
/**
 * 章节管理页面
 * 提供章节的添加、编辑、删除和列表展示功能
 */

// 引入配置文件，加载系统常量和通用函数
require_once '../config.php';

// 检查管理员权限，确保只有管理员可以访问此页面
checkAdmin();

// 设置页面标题
$page_title = '章节管理';

// 获取数据库连接
$pdo = getDB();

// 处理删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // 获取要删除的章节ID
    $id = $_GET['delete'];
    // 准备并执行删除操作
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$id]);
    // 重定向到章节列表页面并显示成功消息
    header('Location: chapters.php?msg=删除成功');
    exit;
}

// 处理添加/编辑操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $id = $_POST['id'] ?? null; // 编辑时的章节ID，添加时为null
    $bank_id = $_POST['bank_id'] ?? null; // 所属题库ID
    $chapter_name = trim($_POST['chapter_name'] ?? ''); // 章节名称
    $sort_order = intval($_POST['sort_order'] ?? 0); // 排序顺序

    // 验证必填字段
    if (empty($chapter_name) || empty($bank_id)) {
        $error = '请填写完整信息';
    } else {
        if ($id) {
            // 更新章节信息
            $stmt = $pdo->prepare("UPDATE chapters SET bank_id = ?, chapter_name = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$bank_id, $chapter_name, $sort_order, $id]);
            header('Location: chapters.php?msg=更新成功');
        } else {
            // 添加新章节
            $stmt = $pdo->prepare("INSERT INTO chapters (bank_id, chapter_name, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$bank_id, $chapter_name, $sort_order]);
            header('Location: chapters.php?msg=添加成功');
        }
        exit;
    }
}

// 获取要编辑的章节信息
$editChapter = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editChapter = $stmt->fetch();
}

// 获取题库列表（用于章节所属题库的选择）
$banks = $pdo->query("SELECT * FROM question_banks ORDER BY name")->fetchAll();

// 获取章节列表（包含所属题库名称和题目数量统计）
$chapters = $pdo->query("SELECT c.*, qb.name as bank_name, COUNT(q.id) as question_count 
                         FROM chapters c 
                         LEFT JOIN question_banks qb ON qb.id = c.bank_id 
                         LEFT JOIN questions q ON q.chapter_id = c.id 
                         GROUP BY c.id 
                         ORDER BY qb.name, c.sort_order, c.id")->fetchAll();
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/sidebar.php'; ?> <!-- 引入管理员侧边栏 -->

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-list-ul"></i> 章节管理</h2>
        <div class="d-flex gap-2">
            <!-- 添加章节按钮 -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#chapterModal">
                <i class="bi bi-plus-circle"></i> 添加章节
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

    <!-- 章节列表 -->
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>所属题库</th>
                    <th>章节名称</th>
                    <th>排序</th>
                    <th>题目数</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($chapters as $chapter): ?>
                    <tr>
                        <td><?php echo $chapter['id']; ?></td>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($chapter['bank_name']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($chapter['chapter_name']); ?></strong></td>
                        <td><?php echo $chapter['sort_order']; ?></td>
                        <td><span class="badge bg-success"><?php echo $chapter['question_count']; ?></span></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($chapter['created_at'])); ?></td>
                        <td>
                            <!-- 编辑按钮 -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editChapter(<?php echo htmlspecialchars(json_encode($chapter)); ?>)">
                                <i class="bi bi-pencil"></i> 编辑
                            </button>
                            <!-- 删除按钮 -->
                            <a href="?delete=<?php echo $chapter['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定删除吗？这将删除所有相关题目！')">
                                <i class="bi bi-trash"></i> 删除
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 章节编辑模态框 -->
<div class="modal fade" id="chapterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $editChapter ? '编辑章节' : '添加章节'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($editChapter): ?>
                        <input type="hidden" name="id" value="<?php echo $editChapter['id']; ?>">
                    <?php endif; ?>
                    <!-- 所属题库选择 -->
                    <div class="mb-3">
                        <label class="form-label">所属题库</label>
                        <select class="form-select" name="bank_id" required>
                            <option value="">请选择题库</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" <?php echo ($editChapter && $editChapter['bank_id'] == $bank['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- 章节名称输入框 -->
                    <div class="mb-3">
                        <label class="form-label">章节名称</label>
                        <input type="text" class="form-control" name="chapter_name" value="<?php echo $editChapter ? htmlspecialchars($editChapter['chapter_name']) : ''; ?>" required>
                    </div>
                    <!-- 排序顺序输入框 -->
                    <div class="mb-3">
                        <label class="form-label">排序顺序</label>
                        <input type="number" class="form-control" name="sort_order" value="<?php echo $editChapter ? $editChapter['sort_order'] : '0'; ?>" min="0">
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
    // 将题库列表传递给JavaScript，用于编辑时的题库选择
    const banks = <?php echo json_encode($banks, JSON_UNESCAPED_UNICODE); ?>;

    /**
     * 编辑章节函数
     * 填充编辑表单并显示模态框
     * @param {Object} chapter - 章节对象
     */
    function editChapter(chapter) {
        // 移除已存在的ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 获取表单
        const form = document.querySelector('#chapterModal form');
        // 创建新的ID隐藏字段
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = chapter.id;
        // 在表单头部插入ID隐藏字段
        form.insertBefore(idInput, form.firstChild);

        // 填充表单数据
        document.querySelector('select[name="bank_id"]').value = chapter.bank_id || '';
        document.querySelector('input[name="chapter_name"]').value = chapter.chapter_name || '';
        document.querySelector('input[name="sort_order"]').value = chapter.sort_order || '0';

        // 更新模态框标题
        document.querySelector('#chapterModal .modal-title').textContent = '编辑章节';

        // 显示模态框
        const modal = new bootstrap.Modal(document.getElementById('chapterModal'));
        modal.show();
    }

    // 添加章节时重置表单
    document.getElementById('chapterModal').addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        // 重置表单
        form.reset();
        // 移除ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 更新模态框标题
        document.querySelector('#chapterModal .modal-title').textContent = '添加章节';
    });

    // 编辑时填充表单（URL参数方式）
    <?php if ($editChapter): ?>
    document.addEventListener('DOMContentLoaded', function() {
        editChapter(<?php echo json_encode($editChapter, JSON_UNESCAPED_UNICODE); ?>);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?> <!-- 引入页面底部 -->
