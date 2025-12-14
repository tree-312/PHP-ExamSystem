<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度应在3-20个字符之间';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6个字符';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        $pdo = getDB();
        
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '用户名已存在';
        } else {
            // 创建用户
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
            $stmt->execute([$username, hashPassword($password)]);
            $success = '注册成功！正在跳转到登录页面...';
            
            // 3秒后跳转到登录页
            header("refresh:2;url=login.php");
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
        <h2><i class="bi bi-person-plus"></i> 用户注册</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="username" name="username" required autofocus 
                       minlength="3" maxlength="20" placeholder="3-20个字符">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <input type="password" class="form-control" id="password" name="password" required 
                       minlength="6" placeholder="至少6个字符">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">确认密码</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                       minlength="6" placeholder="请再次输入密码">
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-person-plus"></i> 注册
                </button>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted">
                    已有账号？<a href="<?php echo BASE_PATH; ?>login.php" class="text-primary">立即登录</a>
                </small>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

