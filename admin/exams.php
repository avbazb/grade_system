<?php
/**
 * 成绩分析系统 - 管理员考试管理页面
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 处理删除请求
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $examId = (int)$_GET['id'];
    
    // 开始事务
    $conn = getDBConnection();
    $conn->begin_transaction();
    
    try {
        // 先删除关联的考试科目
        $conn->query("DELETE FROM exam_subjects WHERE exam_id = $examId");
        
        // 删除考试下的成绩
        $conn->query("DELETE FROM scores WHERE exam_id = $examId");
        
        // 删除考试
        $conn->query("DELETE FROM exams WHERE id = $examId");
        
        // 提交事务
        $conn->commit();
        
        // 设置成功消息
        $success = '考试删除成功！';
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        
        // 设置错误消息
        $error = '考试删除失败：' . $e->getMessage();
    }
    
    // 重定向到考试列表页
    header('Location: exams.php');
    exit;
}

// 获取所有考试
$exams = fetchAll("SELECT * FROM exams ORDER BY exam_date DESC");

// 页面标题
$pageTitle = '考试管理';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1>考试管理</h1>
        </div>
        <div class="col-md-6 text-right">
            <a href="exams_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 新建考试
            </a>
        </div>
    </div>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">考试列表</h5>
                </div>
                <div class="col-auto">
                    <div class="search-box">
                        <input type="text" id="search-exams" class="search-input" placeholder="搜索考试...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                </div>
                <div class="col-auto">
                    <select id="filter-type" class="form-select">
                        <option value="">所有类型</option>
                        <option value="周测">周测</option>
                        <option value="月考">月考</option>
                        <option value="期中">期中</option>
                        <option value="期末">期末</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>考试名称</th>
                            <th>考试类型</th>
                            <th>考试日期</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="exam-list">
                        <?php if (count($exams) > 0): ?>
                            <?php foreach ($exams as $exam): ?>
                                <tr data-type="<?php echo $exam['type']; ?>">
                                    <td><?php echo $exam['id']; ?></td>
                                    <td><?php echo $exam['name']; ?></td>
                                    <td><span class="badge badge-primary"><?php echo $exam['type']; ?></span></td>
                                    <td><?php echo formatDate($exam['exam_date']); ?></td>
                                    <td><?php echo formatDate($exam['created_at'], 'Y-m-d H:i'); ?></td>
                                    <td>
                                        <a href="exam_details.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info" title="详情">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                        <a href="exam_scores.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-success" title="成绩管理">
                                            <i class="fas fa-list-alt"></i>
                                        </a>
                                        <a href="exam_upload.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-warning" title="上传成绩">
                                            <i class="fas fa-upload"></i>
                                        </a>
                                        <a href="exam_analysis.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary" title="成绩分析">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                        <a href="javascript:void(0);" class="btn btn-sm btn-danger delete-exam" data-id="<?php echo $exam['id']; ?>" title="删除">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">暂无考试记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 删除确认模态框 -->
<div class="modal" id="delete-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认删除</h5>
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>您确定要删除这次考试吗？此操作将同时删除与此考试相关的所有成绩数据，且不可恢复！</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <a href="#" id="confirm-delete" class="btn btn-danger">确认删除</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 搜索功能
    const searchInput = document.getElementById('search-exams');
    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#exam-list tr');
        
        rows.forEach(row => {
            const nameCell = row.cells[1].textContent.toLowerCase();
            if (nameCell.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // 类型筛选
    const typeFilter = document.getElementById('filter-type');
    typeFilter.addEventListener('change', function() {
        const selectedType = this.value;
        const rows = document.querySelectorAll('#exam-list tr');
        
        rows.forEach(row => {
            if (!selectedType || row.getAttribute('data-type') === selectedType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // 删除确认
    const deleteButtons = document.querySelectorAll('.delete-exam');
    const deleteModal = document.getElementById('delete-modal');
    const confirmDeleteButton = document.getElementById('confirm-delete');
    const closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const examId = this.getAttribute('data-id');
            confirmDeleteButton.setAttribute('href', 'exams.php?action=delete&id=' + examId);
            deleteModal.style.display = 'block';
        });
    });
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            deleteModal.style.display = 'none';
        });
    });
    
    // 点击模态框外部关闭
    window.addEventListener('click', function(event) {
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    });
    
    // 添加动画效果
    const examList = document.getElementById('exam-list');
    const rows = examList.querySelectorAll('tr');
    rows.forEach((row, index) => {
        row.style.opacity = 0;
        row.style.transform = 'translateY(20px)';
        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        setTimeout(() => {
            row.style.opacity = 1;
            row.style.transform = 'translateY(0)';
        }, 50 * index);
    });
});
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 