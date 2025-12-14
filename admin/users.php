<?php
/**
 * 用户管理页面
 * 提供系统用户的添加、编辑、删除和列表展示功能，支持用户角色管理
 */

// 引入配置文件，加载系统常量和通用函数
require_once '../config.php';

// 检查管理员权限，确保只有管理员可以访问此页面
checkAdmin();

// 设置页面标题
$page_title = '用户管理';

// 获取数据库连接
$pdo = getDB();

// 处理删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // 获取要删除的用户ID
    $id = $_GET['delete'];
    // 防止用户删除自己
    if ($id != $_SESSION['user_id']) {
        // 准备并执行删除操作
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        // 重定向到用户列表页面并显示成功消息
        header('Location: users.php?msg=删除成功');
        exit;
    } else {
        // 重定向到用户列表页面并显示错误消息
        header('Location: users.php?error=不能删除自己');
        exit;
    }
}

// 处理添加/编辑操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $id = $_POST['id'] ?? null; // 编辑时的用户ID，添加时为null
    $username = trim($_POST['username'] ?? ''); // 用户名
    $password = $_POST['password'] ?? ''; // 密码
    $role = $_POST['role'] ?? 'user'; // 用户角色（user或admin）

    // 验证必填字段
    if (empty($username)) {
        $error = '用户名不能为空';
    } else {
        if ($id) {
            // 更新用户信息
            if (!empty($password)) {
                // 如果提供了新密码，则更新密码
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, hashPassword($password), $role, $id]);
            } else {
                // 如果未提供新密码，则不更新密码
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $role, $id]);
            }
            // 重定向到用户列表页面并显示成功消息
            header('Location: users.php?msg=更新成功');
        } else {
            // 添加新用户
            if (empty($password)) {
                // 新用户必须提供密码
                $error = '新用户密码不能为空';
            } else {
                // 准备并执行添加操作
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, hashPassword($password), $role]);
                // 重定向到用户列表页面并显示成功消息
                header('Location: users.php?msg=添加成功');
            }
        }
        exit;
    }
}

// 获取编辑用户信息
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    // 准备并执行查询，获取要编辑的用户信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// 获取用户列表（按创建时间倒序排序）
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/sidebar.php'; ?> <!-- 引入管理员侧边栏 -->

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> 用户管理</h2>
        <div class="d-flex gap-2">
            <!-- 添加用户按钮 -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="bi bi-plus-circle"></i> 添加用户
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
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- 用户列表 -->
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>角色</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <!-- 根据用户角色显示不同的标签颜色 -->
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo $user['role'] === 'admin' ? '管理员' : '用户'; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <!-- 编辑按钮 -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                <i class="bi bi-pencil"></i> 编辑
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <!-- 删除按钮（不能删除自己） -->
                                <a href="?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定删除吗？')">
                                    <i class="bi bi-trash"></i> 删除
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 用户编辑模态框 -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $editUser ? '编辑用户' : '添加用户'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">用户名</label>
                        <input type="text" class="form-control" name="username" value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密码<?php echo $editUser ? '（留空不修改）' : ''; ?></label>
                        <input type="password" class="form-control" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色</label>
                        <select class="form-select" name="role" required>
                            <option value="user" <?php echo ($editUser && $editUser['role'] === 'user') ? 'selected' : ''; ?>>用户</option>
                            <option value="admin" <?php echo ($editUser && $editUser['role'] === 'admin') ? 'selected' : ''; ?>>管理员</option>
                        </select>
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
     * 编辑用户函数
     * 填充编辑表单并显示模态框
     * @param {Object} user - 用户对象
     */
    function editUser(user) {
        // 移除已存在的ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 获取表单
        const form = document.querySelector('#userModal form');
        // 创建新的ID隐藏字段
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = user.id;
        // 在表单头部插入ID隐藏字段
        form.insertBefore(idInput, form.firstChild);

        // 填充表单数据
        document.querySelector('input[name="username"]').value = user.username;
        document.querySelector('input[name="password"]').value = '';
        document.querySelector('input[name="password"]').required = false;
        document.querySelector('select[name="role"]').value = user.role;

        // 更新模态框标题
        document.querySelector('#userModal .modal-title').textContent = '编辑用户';

        // 显示模态框
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    }

    // 添加用户时重置表单
    document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        // 重置表单
        form.reset();
        // 移除ID隐藏字段
        document.querySelector('input[name="id"]')?.remove();
        // 重置密码必填项
        document.querySelector('input[name="password"]').required = true;
        // 更新模态框标题
        document.querySelector('#userModal .modal-title').textContent = '添加用户';
    });

    // 编辑时填充表单（URL参数方式）
    <?php if ($editUser): ?>
    document.addEventListener('DOMContentLoaded', function() {
        editUser(<?php echo json_encode($editUser, JSON_UNESCAPED_UNICODE); ?>);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?> <!-- 引入页面底部 -->