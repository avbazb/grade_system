<?php
/**
 * 成绩分析系统 - 学生反馈页面
 * 
 * 允许学生提交对系统的反馈和建议
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '反馈建议';

// 包含学生页面头部
include_once '../components/student_header.php';

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取表单数据
    $feedbackType = isset($_POST['feedback_type']) ? trim($_POST['feedback_type']) : '';
    $feedbackContent = isset($_POST['feedback_content']) ? trim($_POST['feedback_content']) : '';
    
    // 验证表单数据
    if (empty($feedbackType) || empty($feedbackContent)) {
        $message = '请填写所有必填字段';
        $messageType = 'danger';
    } elseif (strlen($feedbackContent) < 10) {
        $message = '反馈内容太短，请详细描述您的问题或建议';
        $messageType = 'danger';
    } else {
        // 在实际项目中，这里应该将反馈保存到数据库
        // 这里我们简单模拟一下成功提交的效果
        $message = '感谢您的反馈！我们会认真考虑您的意见和建议。';
        $messageType = 'success';
        
        // 重置表单
        $feedbackType = '';
        $feedbackContent = '';
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>反馈类别</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="#bug-report">
                            <i class="fas fa-bug"></i> 问题报告
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#feature-request">
                            <i class="fas fa-lightbulb"></i> 功能建议
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#improvement">
                            <i class="fas fa-tools"></i> 改进意见
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#other">
                            <i class="fas fa-comment-dots"></i> 其他反馈
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
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/help.php">
                            <i class="fas fa-question-circle"></i> 帮助中心
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
                        <i class="fas fa-comment-alt text-primary mr-2"></i>
                        反馈与建议
                    </h2>
                    <p class="card-text">
                        我们非常重视您的意见和建议。如果您在使用系统过程中遇到问题，或有任何改进意见，请在此提交。
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
            
            <!-- 反馈表单 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-paper-plane mr-2"></i>
                        提交反馈
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" id="feedback-form">
                        <div class="form-group">
                            <label for="feedback_type">反馈类型 <span class="text-danger">*</span></label>
                            <select class="form-control" id="feedback_type" name="feedback_type" required>
                                <option value="" selected disabled>请选择反馈类型</option>
                                <option value="bug" id="bug-report" <?php echo isset($feedbackType) && $feedbackType === 'bug' ? 'selected' : ''; ?>>问题报告</option>
                                <option value="feature" id="feature-request" <?php echo isset($feedbackType) && $feedbackType === 'feature' ? 'selected' : ''; ?>>功能建议</option>
                                <option value="improvement" id="improvement" <?php echo isset($feedbackType) && $feedbackType === 'improvement' ? 'selected' : ''; ?>>改进意见</option>
                                <option value="other" id="other" <?php echo isset($feedbackType) && $feedbackType === 'other' ? 'selected' : ''; ?>>其他反馈</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="feedback_content">反馈内容 <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="feedback_content" name="feedback_content" rows="6" placeholder="请详细描述您的问题或建议..." required><?php echo isset($feedbackContent) ? $feedbackContent : ''; ?></textarea>
                            <small class="form-text text-muted">请尽可能详细地描述，这将帮助我们更好地理解和解决问题。</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="attachment">附件（可选）</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="attachment" name="attachment">
                                <label class="custom-file-label" for="attachment">选择文件</label>
                            </div>
                            <small class="form-text text-muted">您可以上传截图或其他相关文件（最大2MB）。</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="contact_permission" name="contact_permission">
                                <label class="custom-control-label" for="contact_permission">允许管理员通过系统消息联系我获取更多信息</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane mr-1"></i> 提交反馈
                            </button>
                            <button type="reset" class="btn btn-outline-secondary ml-2">
                                <i class="fas fa-redo mr-1"></i> 重置
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 反馈指南 -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle mr-2"></i>
                        反馈指南
                    </h5>
                </div>
                <div class="card-body">
                    <h6 class="text-info">如何提交有效的反馈？</h6>
                    <p>
                        为了帮助我们更好地理解和解决您的问题，请在提交反馈时遵循以下建议：
                    </p>
                    
                    <div class="card-deck mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-bug text-danger mr-2"></i>问题报告</h6>
                                <ul class="card-text pl-3 mb-0">
                                    <li>详细描述问题发生的步骤</li>
                                    <li>说明您期望的结果和实际结果</li>
                                    <li>提供错误信息或截图</li>
                                </ul>
                            </div>
                        </div>
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title"><i class="fas fa-lightbulb text-warning mr-2"></i>功能建议</h6>
                                <ul class="card-text pl-3 mb-0">
                                    <li>描述您希望添加的新功能</li>
                                    <li>说明此功能将如何改善您的使用体验</li>
                                    <li>提供可能的实现方案（如有）</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>注意：</strong> 我们会认真对待每一条反馈，但可能无法回复所有反馈。如果您的建议被采纳，您可能会在未来的系统更新中看到相关改进。
                    </div>
                </div>
            </div>
            
            <div class="text-center mb-4">
                <a href="<?php echo SITE_URL; ?>/student/help.php" class="btn btn-outline-primary">
                    <i class="fas fa-question-circle mr-1"></i> 查看帮助文档
                </a>
                <a href="<?php echo SITE_URL; ?>/student/index.php" class="btn btn-outline-secondary ml-2">
                    <i class="fas fa-home mr-1"></i> 返回首页
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 自定义文件输入
    document.querySelector('.custom-file-input').addEventListener('change', function(e) {
        var fileName = this.files[0].name;
        var nextSibling = this.nextElementSibling;
        nextSibling.innerText = fileName;
    });
    
    // 表单验证
    document.getElementById('feedback-form').addEventListener('submit', function(event) {
        const feedbackType = document.getElementById('feedback_type').value;
        const feedbackContent = document.getElementById('feedback_content').value;
        
        if (!feedbackType || !feedbackContent) {
            event.preventDefault();
            alert('请填写所有必填字段');
        } else if (feedbackContent.length < 10) {
            event.preventDefault();
            alert('反馈内容太短，请详细描述您的问题或建议');
        }
    });
    
    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            // 如果是侧边栏链接，则设置对应的选择项
            if (this.getAttribute('href').indexOf('#bug-report') === 0) {
                document.getElementById('feedback_type').value = 'bug';
            } else if (this.getAttribute('href').indexOf('#feature-request') === 0) {
                document.getElementById('feedback_type').value = 'feature';
            } else if (this.getAttribute('href').indexOf('#improvement') === 0) {
                document.getElementById('feedback_type').value = 'improvement';
            } else if (this.getAttribute('href').indexOf('#other') === 0) {
                document.getElementById('feedback_type').value = 'other';
            }
            
            // 滚动到表单
            document.querySelector('#feedback-form').scrollIntoView({
                behavior: 'smooth'
            });
            
            // 聚焦到反馈内容
            setTimeout(function() {
                document.getElementById('feedback_content').focus();
            }, 500);
        });
    });
});
</script>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 