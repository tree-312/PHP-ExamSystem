<?php
// 数据库配置 - 系统数据库连接参数
// 定义数据库主机地址
define('DB_HOST', 'localhost');
// 定义数据库名称
define('DB_NAME', 'exam_system');
// 定义数据库用户名
define('DB_USER', 'root');
// 定义数据库密码
define('DB_PASS', '你自己的数据库密码');
// 定义数据库字符集
define('DB_CHARSET', 'utf8mb4');

// 基础路径配置（相对于网站根目录）
// 获取项目根目录的绝对路径，并将反斜杠替换为正斜杠以确保跨平台兼容性
$rootPath = str_replace('\\', '/', dirname(__FILE__));
// 获取服务器文档根目录的绝对路径
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
// 计算项目根目录相对于文档根目录的路径
$basePath = str_replace($docRoot, '', $rootPath);
$basePath = str_replace('\\', '/', $basePath);
// 确保基础路径以斜杠开头和结尾
if ($basePath && $basePath !== '/') {
    $basePath = '/' . trim($basePath, '/') . '/';
} else {
    $basePath = '/';
}
// 定义基础路径常量，用于在整个项目中引用资源和链接
define('BASE_PATH', $basePath);

// 会话配置 - 增强会话安全性
// 确保会话cookie只能通过HTTP访问，防止XSS攻击
ini_set('session.cookie_httponly', 1);
// 确保只使用cookie来存储会话ID，防止会话固定攻击
ini_set('session.use_only_cookies', 1);
// 启动PHP会话
session_start();

// 时区设置 - 设置系统默认时区为上海
date_default_timezone_set('Asia/Shanghai');

/**
 * 数据库连接函数
 * 使用单例模式创建并返回PDO数据库连接对象
 * 
 * @return PDO 数据库连接对象
 */
function getDB() {
    // 静态变量，确保只创建一次连接
    static $pdo = null;
    // 如果连接不存在，则创建新连接
    if ($pdo === null) {
        try {
            // 构建数据源名称(DSN)
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            // 创建PDO连接，设置错误模式、默认获取模式和禁用预处理语句模拟
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 抛出异常处理错误
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // 默认以关联数组形式返回结果
                PDO::ATTR_EMULATE_PREPARES => false // 禁用预处理语句模拟，提高安全性
            ]);
        } catch (PDOException $e) {
            // 如果连接失败，输出错误信息并终止程序
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    // 返回数据库连接对象
    return $pdo;
}

/**
 * 检查用户登录状态
 * 如果用户未登录，重定向到登录页面
 */
function checkLogin() {
    // 检查会话中是否存在用户ID
    if (!isset($_SESSION['user_id'])) {
        // 重定向到登录页面
        header('Location: login.php');
        // 终止后续代码执行
        exit;
    }
}

/**
 * 检查管理员权限
 * 首先检查登录状态，然后检查用户角色是否为管理员
 */
function checkAdmin() {
    // 先检查登录状态
    checkLogin();
    // 检查用户角色是否为管理员
    if ($_SESSION['role'] !== 'admin') {
        // 如果不是管理员，重定向到用户首页
        header('Location: ' . BASE_PATH . 'user/home.php');
        // 终止后续代码执行
        exit;
    }
}

/**
 * 检查普通用户权限
 * 首先检查登录状态，然后检查用户角色是否为普通用户
 */
function checkUser() {
    // 先检查登录状态
    checkLogin();
    // 检查用户角色是否为普通用户
    if ($_SESSION['role'] !== 'user') {
        // 如果不是普通用户，重定向到管理员首页
        header('Location: ' . BASE_PATH . 'admin/dashboard.php');
        // 终止后续代码执行
        exit;
    }
}

/**
 * 返回JSON格式响应
 * 用于API接口返回标准化的JSON数据
 * 
 * @param mixed $data 要返回的数据
 * @param int $status HTTP状态码，默认200
 */
function jsonResponse($data, $status = 200) {
    // 设置HTTP响应状态码
    http_response_code($status);
    // 设置响应头为JSON格式
    header('Content-Type: application/json; charset=utf-8');
    // 将数据编码为JSON并输出，确保中文字符不被转义
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    // 终止后续代码执行
    exit;
}

/**
 * 验证密码
 * 使用PHP内置的password_verify函数验证密码是否与哈希值匹配
 * 
 * @param string $password 原始密码
 * @param string $hash 密码哈希值
 * @return bool 密码是否匹配
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 加密密码
 * 使用PHP内置的password_hash函数生成安全的密码哈希值
 * 
 * @param string $password 原始密码
 * @return string 密码哈希值
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}