<?php
/**
 * 成绩分析系统 - 首页（登录页面）
 */

// 引入配置文件
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// 处理登录请求
$error = '';
$success = '';

// 如果已经登录，直接跳转到对应的首页
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            redirect(SITE_URL . '/admin/index.php');
            break;
        case 'teacher':
            redirect(SITE_URL . '/teacher/index.php');
            break;
        case 'student':
            redirect(SITE_URL . '/student/index.php');
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码';
    } else {
        // 根据用户名查询用户（不限制角色）
        $user = fetchOne("SELECT * FROM users WHERE username = '" . sanitize($username) . "'");
        
        if ($user && passwordVerify($password, $user['password'])) {
            // 登录成功，设置会话
            loginUser($user);
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 登录</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- 添加Font Awesome图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: url('2eea46ecb5f25567a11af329da005f85.jpg') no-repeat center center fixed;
            background-size: cover;
            background-color: #333; /* 图片加载失败时的备用背景色 */
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 35px;
            background-color: rgba(255, 255, 255, 0.92);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.6s;
            backdrop-filter: blur(5px); /* 玻璃效果，现代浏览器支持 */
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .login-logo h1 {
            font-size: 30px;
            color: var(--primary-color);
            margin: 0;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .login-form-group {
            margin-bottom: 22px;
        }
        
        .login-form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        
        .login-form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            background-color: rgba(255, 255, 255, 0.9);
        }
        
        .login-form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.25);
            outline: none;
            background-color: #fff;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--primary-color);
            color: var(--white-color);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 122, 255, 0.2);
        }
        
        .login-btn:hover {
            background-color: #0062cc;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 122, 255, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 122, 255, 0.2);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: var(--gray-color);
        }
        
        .login-message {
            margin-bottom: 20px;
            border-radius: 6px;
            padding: 12px;
        }
        
        .alert-error {
            background-color: rgba(255, 76, 81, 0.1);
            border-left: 4px solid #ff4c51;
            color: #d32f2f;
        }
        
        .alert-success {
            background-color: rgba(76, 217, 100, 0.1);
            border-left: 4px solid #4cd964;
            color: #28a745;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* 响应式调整 */
        @media (max-width: 480px) {
            .login-container {
                max-width: 90%;
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h1><?php echo SITE_NAME; ?></h1>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="login-message alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="login-message alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="post">
            <div class="login-form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" placeholder="请输入用户名" required>
            </div>
            
            <div class="login-form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <button type="submit" name="login" class="login-btn">登录</button>
        </form>
        
        <div class="login-footer">
            <!-- 页脚内容已删除 -->
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 添加表单提交前的验证
            document.querySelector('.login-form').addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('请填写用户名和密码');
                }
            });
        });
    </script>
</body>
</html> 