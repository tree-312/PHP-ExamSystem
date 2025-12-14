<?php
/**
 * 题库管理页面
 * 提供题库的添加、编辑、删除和列表展示功能
 */

// 引入配置文件，加载系统常量和通用函数
require_once '../config.php';

// 检查管理员权限，确保只有管理员可以访问此页面
checkAdmin();

// 设置页面标题
$page_title = '题库管理';

// 获取数据库连接
$pdo = getDB();

// 处理删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // 获取要删除的题库ID
    $id = $_GET['delete'];
    // 准备并执行删除操作
    $stmt = $pdo->prepare("DELETE FROM question_banks WHERE id = ?");
    $stmt->execute([$id]);
    // 重定向到题库列表页面并显示成功消息
    header('Location: banks.php?msg=删除成功');
    exit;
}

// 处理添加/编辑操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $id = $_POST['id'] ?? null; // 编辑时的题库ID，添加时为null
    $name = trim($_POST['name'] ?? ''); // 题库名称
    $description = trim($_POST['description'] ?? ''); // 题库描述

    // 验证题库名称
    if (empty($name)) {
        $error = '题库名称不能为空';
    } else {
        if ($id) {
            // 更新题库信息
            $stmt = $pdo->prepare("UPDATE question_banks SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
            header('Location: banks.php?msg=更新成功');
        } else {
            // 添加新题库
            $stmt = $pdo->prepare("INSERT INTO question_banks (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $description]);
            header('Location: banks.php?msg=添加成功');
        }
        exit;
    }
}

// 获取要编辑的题库信息
$editBank = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM question_banks WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editBank = $stmt->fetch();
}

// 获取题库列表（包含章节数和题目数统计）
$banks = $pdo->query("SELECT qb.*,
           (SELECT COUNT(id) FROM chapters WHERE bank_id = qb.id) as chapter_count,
           (SELECT COUNT(id) FROM questions WHERE bank_id = qb.id) as question_count
    FROM question_banks qb 
    ORDER BY qb.created_at DESC")->fetchAll();
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/sidebar.php'; ?> <!-- 引入管理员侧边栏 -->

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-folder"></i> 题库管理</h2>
        <div class="d-flex gap-2">
            <!-- 添加题库按钮 -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankModal">
                <i class="bi bi-plus-circle"></i> 添加题库
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

    <!-- 题库列表 -->
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>题库名称</th>
                    <th>描述</th>
                    <th>章节数</th>
                    <th>题目数</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($banks as $bank): ?>
                    <tr>
                        <td><?php echo $bank['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($bank['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($bank['description'] ?: '-'); ?></td>
                        <td><span class="badge bg-info"><?php echo $bank['chapter_count']; ?></span></td>
                        <td><span class="badge bg-success"><?php echo $bank['question_count']; ?></span></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($bank['created_at'])); ?></td>
                        <td>
                            <!-- 编辑按钮 -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBank(<?php echo htmlspecialchars(json_encode($bank)); ?>">
                                <i class="bi bi-pencil"></i> 编辑
                            </button>
                            <!-- 删除按钮 -->
                            <a href="?delete=<?php echo $bank['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定删除吗？这将删除所有相关章节和题目！')">
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

<!-- 题库编辑模态框 -->
<div class="modal fade" id="bankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $editBank ? '编辑题库' : '添加题库'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($editBank): ?>
                        <input type="hidden" name="id" value="<?php echo $editBank['id']; ?>">
                    <?php endif; ?>
                    <!-- 题库名称输入框 -->
                    <div class="mb-3">
                        <label class="form-label">题库名称</label>
                        <input type="text" class="form-control" name="name" value="<?php echo $editBank ? htmlspecialchars($editBank['name']) : ''; ?>", required>
                    </div>
                    <!-- 题库描述输入框 -->
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo $editBank ? htmlspecialchars($editBank['description']) : ''; ?></textarea>
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
    /**
     * 编辑题库函数
     * 填充编辑表单并显示模态框
     * @param {Object} bank - 题库对象
     */
    function editBank(bank) {
        // 移除已存在的ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 获取表单
        const form = document.querySelector('#bankModal form');
        // 创建新的ID隐藏字段
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = bank.id;
        // 在表单头部插入ID隐藏字段
        form.insertBefore(idInput, form.firstChild);

        // 填充表单数据
        document.querySelector('input[name="name"]').value = bank.name || '';
        document.querySelector('textarea[name="description"]').value = bank.description || '';

        // 更新模态框标题
        document.querySelector('#bankModal .modal-title').textContent = '编辑题库';

        // 显示模态框
        const modal = new bootstrap.Modal(document.getElementById('bankModal'));
        modal.show();
    }

    // 添加题库时重置表单
    document.getElementById('bankModal').addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        // 重置表单
        form.reset();
        // 移除ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 更新模态框标题
        document.querySelector('#bankModal .modal-title').textContent = '添加题库';
    });

    // 编辑时填充表单（URL参数方式）
    <?php if ($editBank): ?>
    document.addEventListener('DOMContentLoaded', function() {
        editBank(<?php echo json_encode($editBank, JSON_UNESCAPED_UNICODE); ?>);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?> <!-- 引入页面底部 -->

