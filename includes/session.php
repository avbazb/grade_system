<?php
/**
 * 会话管理文件
 */

// 引入配置文件
require_once 'config.php';

// 设置会话参数（在session_start之前）
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);

// 设置会话名称
session_name(SESSION_PREFIX . 'sid');

// 启动会话
session_start();

// 检查会话是否过期
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
    // 会话已过期，清除会话
    session_unset();
    session_destroy();
    
    // 重定向到登录页
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

// 更新最后活动时间
$_SESSION['last_activity'] = time();

/**
 * 检查是否已登录，未登录则重定向
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // 保存当前URL
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // 重定向到登录页
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

/**
 * 检查是否有管理员权限，无权限则重定向
 */
function requireAdmin() {
    requireLogin();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        // 重定向到首页
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

/**
 * 检查是否有教师权限，无权限则重定向
 */
function requireTeacher() {
    requireLogin();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
        // 重定向到首页
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

/**
 * 检查是否有学生权限，无权限则重定向
 */
function requireStudent() {
    requireLogin();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        // 重定向到首页
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

/**
 * 登录用户
 * 
 * @param array $user 用户信息
 */
function loginUser($user) {
    // 设置会话变量
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    // 根据角色重定向
    switch ($user['role']) {
        case 'admin':
            header('Location: ' . SITE_URL . '/admin/index.php');
            break;
        case 'teacher':
            header('Location: ' . SITE_URL . '/teacher/index.php');
            break;
        case 'student':
            header('Location: ' . SITE_URL . '/student/index.php');
            break;
        default:
            header('Location: ' . SITE_URL . '/index.php');
    }
    exit();
}

/**
 * 注销用户
 */
function logoutUser() {
    // 清除会话
    session_unset();
    session_destroy();
    
    // 重定向到首页
    header('Location: ' . SITE_URL . '/index.php');
    exit();
} 