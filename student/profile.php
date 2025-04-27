<?php
/**
 * 成绩分析系统 - 学生个人资料页面
 * 
 * 本页面允许学生查看个人信息和修改密码
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '个人资料';

// 包含学生页面头部
include_once '../components/student_header.php';

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $currentPassword = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    // 验证表单数据
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = '所有字段都必须填写';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = '新密码和确认密码不匹配';
        $messageType = 'danger';
    } elseif (strlen($newPassword) < 6) {
        $message = '新密码长度不能少于6个字符';
        $messageType = 'danger';
    } else {
        // 验证当前密码
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($currentPassword, $user['password'])) {
            // 更新密码
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                $message = '密码修改成功';
                $messageType = 'success';
            } else {
                $message = '密码修改失败，请稍后再试';
                $messageType = 'danger';
            }
        } else {
            $message = '当前密码不正确';
            $messageType = 'danger';
        }
    }
}

// 获取学生详细信息
$student = array();
if ($studentId) {
    $sql = "SELECT s.*, c.name as class_name, c.grade 
            FROM students s 
            INNER JOIN classes c ON s.class_id = c.id 
            WHERE s.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>个人资料</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#profile">
                            <i class="fas fa-user"></i> 基本信息
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#change-password">
                            <i class="fas fa-key"></i> 修改密码
                        </a>
                    </li>
                </ul>
                
                <h3 class="mt-4">快速导航</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/index.php">
                            <i class="fas fa-tachometer-alt"></i> 返回首页
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/all_scores.php">
                            <i class="fas fa-list-ol"></i> 成绩详情
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/analysis.php">
                            <i class="fas fa-chart-pie"></i> 成绩分析
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- 主内容区 -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">
                        <i class="fas fa-user-circle text-primary mr-2"></i>
                        个人资料
                    </h2>
                    <p class="card-text">
                        在此页面，您可以查看个人信息，并且修改登录密码。
                    </p>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> mr-2"></i>
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- 基本信息 -->
            <div id="profile" class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user mr-2"></i>
                        基本信息
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($student)): ?>
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th style="width: 150px;">姓名</th>
                                        <td><?php echo $student['name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>学号</th>
                                        <td><?php echo $student['student_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>年级</th>
                                        <td><?php echo $student['grade']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>班级</th>
                                        <td><?php echo $student['class_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>账号</th>
                                        <td><?php echo $currentUser['username']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> 如需修改个人基本信息，请联系班主任或系统管理员。
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="student-avatar mb-3">
                                <i class="fas fa-user-graduate fa-6x text-primary"></i>
                            </div>
                            <div class="student-role">
                                <span class="badge badge-primary p-2">学生</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> 无法获取学生信息，请联系管理员。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 修改密码 -->
            <div id="change-password" class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-key mr-2"></i>
                        修改密码
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="#change-password" id="password-form">
                        <div class="form-group">
                            <label for="current_password">当前密码</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">新密码</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">密码长度不能少于6个字符</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">确认新密码</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save mr-1"></i> 保存修改
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 表单验证
    const passwordForm = document.getElementById('password-form');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    passwordForm.addEventListener('submit', function(event) {
        if (newPassword.value !== confirmPassword.value) {
            event.preventDefault();
            alert('新密码和确认密码不匹配');
        } else if (newPassword.value.length < 6) {
            event.preventDefault();
            alert('新密码长度不能少于6个字符');
        }
    });
    
    // 关闭警告框
    document.querySelectorAll('.alert .close').forEach(function(button) {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
    
    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
});
</script>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 