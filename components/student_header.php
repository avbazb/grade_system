<?php
/**
 * 成绩分析系统 - 学生页头
 */

// 检查是否已定义页面标题，如果没有，使用默认标题
if (!isset($pageTitle)) {
    $pageTitle = '学生系统';
}

// 检查用户是否已登录并具有学生权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    // 如果未登录或非学生，则重定向到登录页
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取学生信息
$studentId = null;
$studentInfo = null;
if (isset($_SESSION['user_id'])) {
    $studentInfo = getStudentInfo($_SESSION['user_id']);
    if ($studentInfo) {
        $studentId = $studentInfo['id'];
    }
}
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
    
    <style>
        /* 学生面板特定样式 */
        .student-navbar {
            background: linear-gradient(90deg, #5ac8fa, #0a84ff);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .student-navbar .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .student-navbar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 15px 15px;
            transition: var(--transition);
        }
        
        .student-navbar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .student-navbar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .student-sidebar {
            background-color: white;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .student-sidebar h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaeaea;
            font-size: 18px;
            color: var(--dark-color);
        }
        
        .student-sidebar .nav-link {
            color: var(--dark-color);
            padding: 8px 0;
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .student-sidebar .nav-link:hover {
            color: var(--primary-color);
            background-color: rgba(10, 132, 255, 0.05);
            padding-left: 5px;
        }
        
        .student-sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(10, 132, 255, 0.1);
            font-weight: 500;
            padding-left: 5px;
        }
        
        .student-sidebar .nav-link i {
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
        
        .student-info {
            padding: 15px;
            background-color: rgba(10, 132, 255, 0.05);
            border-radius: var(--border-radius);
            margin-top: 15px;
        }
        
        .student-info p {
            margin-bottom: 5px;
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
            .student-navbar .nav-link {
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
                background: linear-gradient(90deg, #5ac8fa, #0a84ff);
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
    <nav class="navbar student-navbar">
        <div class="container">
            <div class="navbar-container">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/student/index.php">
                    <i class="fas fa-user-graduate mr-2"></i> <?php echo SITE_NAME; ?>
                </a>
                
                <button class="navbar-toggler" type="button" id="navbarToggler">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/student/index.php">
                            <i class="fas fa-home"></i> 首页
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'all_scores.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/student/all_scores.php">
                            <i class="fas fa-list-alt"></i> 所有成绩
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'analysis.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/student/analysis.php">
                            <i class="fas fa-chart-line"></i> 成绩分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'ai_analysis.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/student/ai_analysis.php">
                            <i class="fas fa-robot"></i> AI分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'rankings.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/student/rankings.php">
                            <i class="fas fa-trophy"></i> 班级排名
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
                                <div class="user-role">学生</div>
                            </div>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/profile.php">
                                <i class="fas fa-user-cog mr-2"></i> 修改密码
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