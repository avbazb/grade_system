<?php
/**
 * 成绩分析系统 - 教师页头
 */

// 检查是否已定义页面标题，如果没有，使用默认标题
if (!isset($pageTitle)) {
    $pageTitle = '教师系统';
}

// 检查用户是否已登录并具有教师权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    // 如果未登录或非教师，则重定向到登录页
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取教师信息
$teacherId = null;
$teacherInfo = null;
if (isset($_SESSION['user_id'])) {
    $teacherInfo = getTeacherInfo($_SESSION['user_id']);
    if ($teacherInfo) {
        $teacherId = $teacherInfo['id'];
    }
}

// 获取教师角色信息（普通教师、班主任、年级主任）
$teacherRole = "";
if ($teacherInfo) {
    if (!empty($teacherInfo['is_grade_director']) && $teacherInfo['is_grade_director'] == 1) {
        $teacherRole = "年级主任";
    } elseif (!empty($teacherInfo['class_id'])) {
        $teacherRole = "班主任";
    } else {
        $teacherRole = "任课教师";
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
        /* 教师面板特定样式 */
        .teacher-navbar {
            background: linear-gradient(90deg, #34c759, #30b94d);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .teacher-navbar .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .teacher-navbar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 15px 15px;
            transition: var(--transition);
        }
        
        .teacher-navbar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .teacher-navbar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .teacher-sidebar {
            background-color: white;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .teacher-sidebar h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaeaea;
            font-size: 18px;
            color: var(--dark-color);
        }
        
        .teacher-sidebar .nav-link {
            color: var(--dark-color);
            padding: 8px 0;
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .teacher-sidebar .nav-link:hover {
            color: var(--success-color);
            background-color: rgba(52, 199, 89, 0.05);
            padding-left: 5px;
        }
        
        .teacher-sidebar .nav-link.active {
            color: var(--success-color);
            background-color: rgba(52, 199, 89, 0.1);
            font-weight: 500;
            padding-left: 5px;
        }
        
        .teacher-sidebar .nav-link i {
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
        
        .teacher-info {
            padding: 15px;
            background-color: rgba(52, 199, 89, 0.05);
            border-radius: var(--border-radius);
            margin-top: 15px;
        }
        
        .teacher-info p {
            margin-bottom: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 20px;
            margin-left: 8px;
            color: white;
            font-weight: 500;
        }
        
        .badge-grade-director {
            background-color: #ff9500;
        }
        
        .badge-class-teacher {
            background-color: #5856d6;
        }
        
        .badge-teacher {
            background-color: #34c759;
        }
        
        /* 添加下拉菜单样式 */
        .navbar-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 1000;
            display: none;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0;
            font-size: 1rem;
            color: #212529;
            text-align: left;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.25rem;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            text-decoration: none;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }
        
        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }
        
        /* 图表样式 */
        .chart-container {
            height: 400px;
            position: relative;
            margin: 0 auto;
            width: 100%;
        }
        
        .chart-card {
            margin-bottom: 2rem;
        }
        
        /* 小尺寸屏幕上的图表高度调整 */
        @media (max-width: 768px) {
            .chart-container {
                height: 350px;
            }
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
            .teacher-navbar .nav-link {
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
                background: linear-gradient(90deg, #34c759, #30b94d);
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
            
            .role-badge {
                margin-left: 0;
                display: inline-block;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar teacher-navbar">
        <div class="container">
            <div class="navbar-container">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/teacher/index.php">
                    <i class="fas fa-chalkboard-teacher mr-2"></i> <?php echo SITE_NAME; ?>
                </a>
                
                <button class="navbar-toggler" type="button" id="navbarToggler">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/index.php">
                            <i class="fas fa-home"></i> 首页
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'class_scores.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/class_scores.php">
                            <i class="fas fa-users"></i> 班级成绩
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student_analysis.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/student_analysis.php">
                            <i class="fas fa-user-graduate"></i> 学生分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'score_analysis.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/score_analysis.php">
                            <i class="fas fa-chart-bar"></i> 成绩分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'ai_analysis.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/ai_analysis.php">
                            <i class="fas fa-robot"></i> AI分析
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'upload_scores.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/teacher/upload_scores.php">
                            <i class="fas fa-upload"></i> 上传成绩
                        </a>
                    </li>
                    
                    <li class="nav-item user-dropdown navbar-dropdown">
                        <a class="nav-link" href="javascript:void(0);">
                            <i class="fas fa-user-circle"></i>
                            <?php echo $currentUser['name']; ?>
                            <?php if ($teacherRole): ?>
                                <span class="role-badge badge-<?php echo $teacherRole == '年级主任' ? 'grade-director' : ($teacherRole == '班主任' ? 'class-teacher' : 'teacher'); ?>">
                                    <?php echo $teacherRole; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu">
                            <div class="user-info">
                                <div class="user-name"><?php echo $currentUser['name']; ?></div>
                                <div class="user-role">教师<?php echo $teacherRole ? " - " . $teacherRole : ""; ?></div>
                            </div>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/teacher/profile.php">
                                <i class="fas fa-user-cog mr-2"></i> 个人信息
                            </a>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/teacher/change_password.php">
                                <i class="fas fa-key mr-2"></i> 修改密码
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