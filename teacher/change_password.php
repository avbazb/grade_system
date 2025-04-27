<?php
/**
 * 成绩分析系统 - 教师修改密码
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireTeacher();

// 获取当前用户信息
$userId = $_SESSION['user_id'];
$userInfo = getCurrentUser();

// 处理表单提交
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // 验证数据
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = '请填写所有必填字段';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = '新密码与确认密码不匹配';
    } elseif (strlen($newPassword) < 6) {
        $errorMessage = '新密码长度必须至少为6个字符';
    } else {
        // 验证当前密码
        $user = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $errorMessage = '当前密码不正确';
        } else {
            // 更新密码
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
            
            if ($result) {
                $successMessage = '密码修改成功';
            } else {
                $errorMessage = '密码修改失败，请重试';
            }
        }
    }
}

// 页面标题
$pageTitle = '修改密码';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-key"></i> 修改密码</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success"><?php echo $successMessage; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">当前密码</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">新密码</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">密码长度至少为6个字符</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">确认新密码</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">修改密码</button>
                        <a href="index.php" class="btn btn-secondary">返回</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 包含页脚
include '../components/teacher_footer.php';
?> 