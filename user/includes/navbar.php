<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_PATH; ?>user/home.php">
            <img src="<?php echo BASE_PATH; ?>png/黑龙江科技大学校徽.png" alt="黑龙江科技大学校徽" class="me-2" style="height: 45px;">
            刷题系统
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>user/home.php">
                        <i class="bi bi-house"></i> 首&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;页
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>user/search.php">
                        <i class="bi bi-search"></i> 搜索题目
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>user/preview.php">
                        <i class="bi bi-eye"></i> 题库预览
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>user/stats.php">
                        <i class="bi bi-bar-chart"></i> 统计图表
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_PATH; ?>user/create_exam.php">
                        <i class="bi bi-file-earmark-text"></i> 组卷练习
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <span class="navbar-text d-flex align-items-center gap-2 py-1 px-3 rounded-3 bg-primary-subtle text-white opacity-95 border border-white border-opacity-20">
                        <i class="bi bi-person-circle fs-6"></i>
                        <span class="fw-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </span>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link d-flex align-items-center gap-1 py-1 px-3 rounded-3 hover-bg-lighten transition-all" href="<?php echo BASE_PATH; ?>logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>退出</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    /* 移动端优化：导航栏展开后水平排列 */
    @media (max-width: 768px) {
        /* 重写Bootstrap默认的垂直堆叠样式 */
        .navbar-collapse {
            flex-basis: 100%;
            flex-grow: 1;
            align-items: flex-start;
        }

        /* 主导航菜单水平排列 */
        .navbar-collapse .navbar-nav.me-auto {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap !important;
            margin-bottom: 8px;
        }

        /* 调整菜单项间距 */
        .navbar-collapse .navbar-nav.me-auto .nav-item {
            margin-right: 8px;
            margin-bottom: 4px;
        }

        /* 调整菜单项样式 */
        .navbar-collapse .navbar-nav.me-auto .nav-link {
            padding: 6px 12px;
            border-radius: var(--border-radius-sm);
            background: rgba(255, 255, 255, 0.1);
        }

        /* 用户信息部分 */
        .navbar-collapse .navbar-nav:last-child {
            display: flex !important;
            flex-direction: row !important;
            align-items: center;
            width: 100%;
        }

        .navbar-collapse .navbar-nav:last-child .nav-item {
            margin-right: 12px;
        }
    }

    /* 按钮美化和悬停效果 */
    .hover-bg-lighten {
        transition: all 0.2s ease;
    }

    .hover-bg-lighten:hover {
        background: rgba(255, 255, 255, 0.15) !important;
        transform: translateY(-1px);
    }

    .transition-all {
        transition: all 0.2s ease;
    }
</style>

