<?php
/**
 * 数据库配置文件
 */

// 数据库连接配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'grade_system');

// 系统配置
define('SITE_NAME', '成绩分析');
define('SITE_URL', '');
define('ADMIN_EMAIL', 'admin@example.com');

// 会话配置
define('SESSION_PREFIX', 'grade_system_');
define('SESSION_LIFETIME', 3600); // 1小时

// 默认设置
define('DEFAULT_PASSWORD', '123456'); // 默认密码

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai'); 