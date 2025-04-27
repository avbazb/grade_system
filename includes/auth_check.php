<?php
/**
 * 身份验证检查文件
 * 
 * 检查用户是否已登录，如果没有登录则重定向到登录页面
 */

// 确保启用会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查会话是否存在
if (isset($_SESSION) && !empty($_SESSION)) {
    // 输出会话调试信息
    /*
    echo "<pre>auth_check.php 中的会话信息：\n";
    echo "session_id: " . session_id() . "\n";
    print_r($_SESSION);
    echo "</pre>";
    */
} else {
    // 会话为空，重定向到登录页面
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// 检查用户是否已登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // 用户未登录，重定向到登录页面
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// 检查会话是否过期（可选：设置2小时过期）
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    // 如果上次活动超过2小时，销毁会话
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/index.php?expired=1');
    exit;
}

// 更新最后活动时间
$_SESSION['last_activity'] = time();
?>