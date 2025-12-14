<nav class="sidebar">
    <div class="text-center text-white p-3 mb-3">
        <div class="mb-2">
            <img src="<?php echo BASE_PATH; ?>png/黑龙江科技大学校徽.png" alt="黑龙江科技大学校徽" class="img-fluid" style="max-height: 80px;">
        </div>
        <h5>管理员后台</h5>
        <small><?php echo htmlspecialchars($_SESSION['username']); ?></small>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/dashboard.php">
                <i class="bi bi-speedometer2"></i> 仪表盘
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/users.php">
                <i class="bi bi-people"></i> 用户管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'banks.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/banks.php">
                <i class="bi bi-folder"></i> 题库管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'chapters.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/chapters.php">
                <i class="bi bi-list-ul"></i> 章节管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'questions.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/questions.php">
                <i class="bi bi-question-circle"></i> 题目管理
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'import.php' ? 'active' : ''; ?>" href="<?php echo BASE_PATH; ?>admin/import.php">
                <i class="bi bi-upload"></i> 批量导入
            </a>
        </li>
        <li class="nav-item mt-3">
            <a class="nav-link" href="<?php echo BASE_PATH; ?>logout.php">
                <i class="bi bi-box-arrow-right"></i> 退出登录
            </a>
        </li>
    </ul>
</nav>

