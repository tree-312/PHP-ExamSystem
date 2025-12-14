<?php
/**
 * 系统入口文件
 * 根据用户的登录状态和角色进行不同的页面跳转
 */

// 引入配置文件，加载系统常量和通用函数
require_once 'config.php';

// 检查用户是否已登录（通过检查$_SESSION['user_id']是否存在）
if (isset($_SESSION['user_id'])) {
    // 已登录用户，根据角色进行跳转
    if ($_SESSION['role'] === 'admin') {
        // 管理员角色跳转到管理后台首页
        header('Location: ' . BASE_PATH . 'admin/dashboard.php');
    } else {
        // 普通用户角色跳转到用户首页
        header('Location: ' . BASE_PATH . 'user/home.php');
    }
    // 终止脚本执行，确保跳转生效
    exit;
} else {
    // 未登录用户，跳转到登录页面
    header('Location: ' . BASE_PATH . 'login.php');
    // 终止脚本执行，确保跳转生效
    exit;
}
