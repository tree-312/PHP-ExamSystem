<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: ' . BASE_PATH . 'admin/dashboard.php');
            } else {
                header('Location: ' . BASE_PATH . 'user/home.php');
            }
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}

// 如果已登录，重定向
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_PATH . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_PATH . 'user/home.php');
    }
    exit;
}
?>
<?php include 'includes/header.php'; ?>
<div class="login-container">
    <div class="login-card">
        <div class="text-center mb-4">
            <img src="<?php echo BASE_PATH; ?>png/黑龙江科技大学题字.png" alt="黑龙江科技大学" class="img-fluid mb-2" style="max-height: 80px;">
            <h3 class="mb-0">大学生刷题系统</h3>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> 登录
                </button>
            </div>
            <div class="text-center mt-3">
                <div class="mb-3">
                    <img src="<?php echo BASE_PATH; ?>png/黑龙江科技大学校徽.png" alt="黑龙江科技大学校徽" class="img-fluid" style="max-height: 60px;">
                </div>
                <a href="<?php echo BASE_PATH; ?>register.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-person-plus"></i> 注册新账号
                </a>
                <div class="mt-2">
                    <small class="text-muted">黑龙江科技大学-信息管理与信息系统</small>
                </div>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

