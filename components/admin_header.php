<?php
/**
 * 成绩分析系统 - 管理员页头
 */

// 检查是否已定义页面标题，如果没有，使用默认标题
if (!isset($pageTitle)) {
    $pageTitle = '管理员后台';
}

// 检查用户是否已登录并具有管理员权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // 如果未登录或非管理员，则重定向到登录页
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// 获取当前用户信息
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS 文件 -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <!-- 字体图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    
    <!-- SheetJS (Excel) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.17.0/dist/xlsx.full.min.js"></script>
    
    <style>
        /* 管理员面板特定样式 */
        .admin-navbar {
            background: linear-gradient(90deg, var(--primary-color), #0056b3);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .admin-navbar .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .admin-navbar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 15px 15px;
            transition: var(--transition);
        }
        
        .admin-navbar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .admin-navbar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .admin-sidebar {
            background-color: white;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .admin-sidebar h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaeaea;
            font-size: 18px;
            color: var(--dark-color);
        }
        
        .admin-sidebar .nav-link {
            color: var(--dark-color);
            padding: 8px 0;
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .admin-sidebar .nav-link:hover {
            color: var(--primary-color);
            background-color: rgba(0, 122, 255, 0.05);
            padding-left: 5px;
        }
        
        .admin-sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(0, 122, 255, 0.1);
            font-weight: 500;
            padding-left: 5px;
        }
        
        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .user-dropdown .dropdown-menu {
            right: 0;
            left: auto;
            min-width: 200px;
        }
        
        .user-dropdown .user-info {
            padding: 10px 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .user-dropdown .user-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .user-dropdown .user-role {
            font-size: 12px;
            color: var(--gray-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 3px;
            padding: 3px 5px;
            border-radius: 50%;
            background-color: var(--danger-color);
            color: white;
            font-size: 10px;
        }
        
        /* 响应式导航栏折叠样式 */
        .navbar-toggler {
            display: none;
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px 10px;
            outline: none;
        }
        
        .navbar-collapse {
            display: flex;
            flex-basis: 100%;
        }
        
        @media (max-width: 768px) {
            .admin-navbar .nav-link {
                padding: 10px;
            }
            
            .navbar-toggler {
                display: block;
            }
            
            .navbar-collapse {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
                background: linear-gradient(90deg, var(--primary-color), #0056b3);
                flex-direction: column;
                width: 100%;
                z-index: 1000;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .navbar-collapse.show {
                display: flex;
            }
            
            .navbar-nav {
                flex-direction: column;
                width: 100%;
            }
            
            .navbar-nav .nav-item {
                width: 100%;
            }
            
            .navbar-nav .nav-link {
                text-align: left;
                padding: 12px 15px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            
            .dropdown-menu {
                position: static !important;
                width: 100%;
                background-color: rgba(0,0,0,0.1);
                border: none;
                border-radius: 0;
            }
            
            .dropdown-item {
                color: white !important;
                padding: 10px 20px;
            }
            
            .dropdown-divider {
                border-color: rgba(255,255,255,0.1);
            }
            
            .navbar-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar admin-navbar">
        <div class="container">
            <div class="navbar-container">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/admin/index.php">
                    <i class="fas fa-chart-line mr-2"></i> <?php echo SITE_NAME; ?>
                </a>
                
                <button class="navbar-toggler" type="button" id="navbarToggler">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/index.php">
                            <i class="fas fa-tachometer-alt"></i> 控制台
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'exam') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/exams.php">
                            <i class="fas fa-file-alt"></i> 考试管理
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/student_manage.php">
                            <i class="fas fa-user-graduate"></i> 学生管理
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'teacher') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/teacher_manage.php">
                            <i class="fas fa-chalkboard-teacher"></i> 教师管理
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'class') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/class_manage.php">
                            <i class="fas fa-users"></i> 班级管理
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'data_visualization') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/data_visualization.php">
                            <i class="fas fa-chart-bar"></i> 数据分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'ai_analysis') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/ai_analysis.php">
                            <i class="fas fa-robot"></i> AI分析
                        </a>
                    </li>
                    
                    <li class="nav-item user-dropdown navbar-dropdown">
                        <a class="nav-link" href="javascript:void(0);">
                            <i class="fas fa-user-circle"></i>
                            <?php echo $currentUser['name']; ?>
                        </a>
                        <div class="dropdown-menu">
                            <div class="user-info">
                                <div class="user-name"><?php echo $currentUser['name']; ?></div>
                                <div class="user-role">管理员</div>
                            </div>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/profile.php">
                                <i class="fas fa-user-cog mr-2"></i> 个人设置
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> 退出登录
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- 添加导航栏折叠控制的JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.getElementById('navbarToggler');
            const collapse = document.getElementById('navbarCollapse');
            const dropdowns = document.querySelectorAll('.navbar-dropdown');
            
            // 汉堡菜单点击事件
            toggler.addEventListener('click', function() {
                collapse.classList.toggle('show');
            });
            
            // 用户下拉菜单点击事件
            dropdowns.forEach(function(dropdown) {
                const link = dropdown.querySelector('.nav-link');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                link.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        menu.classList.toggle('show');
                    }
                });
            });
            
            // 点击导航链接后关闭菜单
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768 && !this.parentElement.classList.contains('navbar-dropdown')) {
                        collapse.classList.remove('show');
                    }
                });
            });
            
            // 点击页面其他地方关闭菜单
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    let clickedInside = toggler.contains(e.target);
                    dropdowns.forEach(function(dropdown) {
                        if (dropdown.contains(e.target)) {
                            clickedInside = true;
                        }
                    });
                    
                    if (!clickedInside && collapse.classList.contains('show')) {
                        collapse.classList.remove('show');
                    }
                }
            });
            
            // 窗口大小改变时处理
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    collapse.classList.remove('show');
                    dropdowns.forEach(function(dropdown) {
                        const menu = dropdown.querySelector('.dropdown-menu');
                        menu.classList.remove('show');
                    });
                }
            });
        });
    </script>
    <!-- 主内容区开始 --> 