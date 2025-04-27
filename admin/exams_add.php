<?php
/**
 * 成绩分析系统 - 添加考试页面
 */

// 引入必要文件
include_once '../includes/config.php';
include_once '../includes/db.php';
include_once '../includes/functions.php';
include_once '../includes/session.php';

requireAdmin();

// 创建数据库连接
$conn = getDBConnection();

// 初始化变量
$error = '';
$success = '';

// 获取所有科目
$subjects = [];
$stmt = $conn->prepare("SELECT * FROM subjects ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// 如果表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 记录接收到的表单数据用于调试
    error_log("收到考试创建请求: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
    
    // 验证必填字段
    $examName = trim($_POST['name'] ?? '');
    $examDate = trim($_POST['exam_date'] ?? '');
    // 获取当前登录的管理员ID
    $createdBy = $_SESSION['user_id'];
    // 不再设置班级ID
    $subjectIds = isset($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
    $fullScores = isset($_POST['full_scores']) ? $_POST['full_scores'] : [];
    
    // 记录检测到的科目ID和满分
    error_log("科目IDs: " . json_encode($subjectIds));
    error_log("满分: " . json_encode($fullScores));
    
    // 过滤掉空值和无效ID
    $filteredSubjectIds = [];
    $filteredFullScores = [];
    
    foreach ($subjectIds as $index => $id) {
        if (!empty($id) && isset($fullScores[$index]) && $fullScores[$index] > 0) {
            $filteredSubjectIds[] = (int)$id;
            $filteredFullScores[] = (float)$fullScores[$index];
        }
    }
    
    // 记录过滤后的数据
    error_log("过滤后科目IDs: " . json_encode($filteredSubjectIds));
    error_log("过滤后满分: " . json_encode($filteredFullScores));
    
    if (empty($examName)) {
        $error = '请输入考试名称';
    } elseif (empty($examDate)) {
        $error = '请选择考试日期';
    } elseif (empty($filteredSubjectIds)) {
        $error = '请至少选择一个科目并设置满分';
    } else {
        try {
            // 开始事务
            $conn->begin_transaction();
            
            // 验证所有科目ID是否存在
            $validSubjectIds = [];
            $placeholders = str_repeat('?,', count($filteredSubjectIds) - 1) . '?';
            $stmt = $conn->prepare("SELECT id FROM subjects WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($filteredSubjectIds)), ...$filteredSubjectIds);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $validSubjectIds[] = $row['id'];
            }
            
            // 确认所有提交的科目ID都存在
            if (count($validSubjectIds) !== count($filteredSubjectIds)) {
                throw new Exception('存在无效的科目ID');
            }
            
            // 插入考试基本信息，添加created_by字段
            $stmt = $conn->prepare("INSERT INTO exams (name, exam_date, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $examName, $examDate, $createdBy);
            
            if (!$stmt->execute()) {
                throw new Exception('创建考试失败: ' . $stmt->error);
            }
            
            $examId = $conn->insert_id;
            error_log("考试创建成功，ID: " . $examId);
            
            // 记录科目插入状态
            $subjectsAdded = 0;
            
            // 插入考试科目信息
            foreach ($filteredSubjectIds as $i => $subjectId) {
                // 确保满分值存在且有效
                if (!isset($filteredFullScores[$i])) {
                    continue; // 跳过没有有效满分的科目
                }
                
                $fullScore = $filteredFullScores[$i];
                error_log("插入科目: 考试ID={$examId}, 科目ID={$subjectId}, 满分={$fullScore}");
                
                $stmt = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_score) VALUES (?, ?, ?)");
                $stmt->bind_param("iid", $examId, $subjectId, $fullScore);
                
                if ($stmt->execute()) {
                    $subjectsAdded++;
                    error_log("科目 {$subjectId} 添加成功");
                } else {
                    throw new Exception("科目 {$subjectId} 添加失败: " . $stmt->error);
                }
            }
            
            // 提交事务
            $conn->commit();
            
            // 设置成功消息
            $success = "考试 '{$examName}' 创建成功，添加了 {$subjectsAdded} 个科目！";
            error_log($success);
            
            // 重定向到考试列表页面
            header("Location: exams.php?success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            
            // 记录错误并显示给用户
            $errorMsg = $e->getMessage();
            error_log("考试创建失败: " . $errorMsg);
            $error = "创建考试失败: " . $errorMsg;
        }
    }
}

// 不再需要获取班级列表
// $classes = [];
// $stmt = $conn->prepare("SELECT * FROM classes ORDER BY grade, name");
// $stmt->execute();
// $result = $stmt->get_result();
// while ($row = $result->fetch_assoc()) {
//     $classes[] = $row;
// }

// 页面标题
$pageTitle = '添加考试';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col">
            <h1>添加考试</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                    <li class="breadcrumb-item"><a href="exams.php">考试管理</a></li>
                    <li class="breadcrumb-item active" aria-current="page">添加考试</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4 anim-fade-in">
        <div class="card-header">
            <h5 class="mb-0">考试信息</h5>
        </div>
        <div class="card-body">
            <form id="exam-form" method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="name">考试名称 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="exam_date">考试日期 <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="exam_date" name="exam_date" required>
                        </div>
                    </div>
                </div>
                
                <h4 class="mb-3">选择考试科目 <span class="text-danger">*</span></h4>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">选择</th>
                                <th width="15%">科目编号</th>
                                <th width="50%">科目名称</th>
                                <th width="30%">满分值</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $index => $subject): ?>
                                <tr class="subject-row">
                                    <td class="text-center">
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   class="form-check-input subject-checkbox" 
                                                   id="subject_<?= $subject['id'] ?>" 
                                                   data-subject-id="<?= $subject['id'] ?>">
                                            <label class="form-check-label" for="subject_<?= $subject['id'] ?>"></label>
                                        </div>
                                    </td>
                                    <td><?= $subject['id'] ?></td>
                                    <td><?= htmlspecialchars($subject['name']) ?></td>
                                    <td>
                                        <input type="hidden" name="subject_ids[]" value="">
                                        <input type="number" 
                                               class="form-control full-score-input" 
                                               name="full_scores[]" 
                                               value="100" 
                                               min="0" 
                                               max="1000" 
                                               step="0.1" 
                                               readonly>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="form-group text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> 创建考试</button>
                    <a href="exams.php" class="btn btn-secondary btn-lg ml-2"><i class="fas fa-arrow-left"></i> 返回列表</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 设置默认日期为今天
    const today = new Date().toISOString().split('T')[0];
    const examDateInput = document.getElementById('exam_date');
    if (!examDateInput.value) {
        examDateInput.value = today;
    }
    
    // 科目选择相关功能
    const subjectRows = document.querySelectorAll('.subject-row');
    
    // 添加淡入动画效果
    subjectRows.forEach((row, index) => {
        row.style.opacity = '0';
        setTimeout(() => {
            row.style.transition = 'opacity 0.3s ease-in-out';
            row.style.opacity = '1';
        }, index * 50);
    });
    
    // 处理科目复选框的变化
    subjectRows.forEach(row => {
        const checkbox = row.querySelector('.subject-checkbox');
        const subjectIdInput = row.querySelector('input[name="subject_ids[]"]');
        const fullScoreInput = row.querySelector('.full-score-input');
        
        // 初始化相关的隐藏字段和满分输入框
        updateSubjectRow(checkbox, subjectIdInput, fullScoreInput);
        
        // 添加复选框变化事件监听器
        checkbox.addEventListener('change', function() {
            updateSubjectRow(this, subjectIdInput, fullScoreInput);
        });
    });
    
    // 更新科目行的状态（启用/禁用满分输入，更新隐藏字段）
    function updateSubjectRow(checkbox, subjectIdInput, fullScoreInput) {
        if (checkbox.checked) {
            // 选中时，启用满分输入并设置隐藏的科目ID值
            fullScoreInput.readOnly = false;
            fullScoreInput.classList.add('bg-light');
            fullScoreInput.classList.remove('bg-disabled');
            subjectIdInput.value = checkbox.dataset.subjectId;
            
            // 高亮显示选中的行
            checkbox.closest('tr').classList.add('table-active');
        } else {
            // 未选中时，禁用满分输入并清空隐藏的科目ID值
            fullScoreInput.readOnly = true;
            fullScoreInput.classList.remove('bg-light');
            fullScoreInput.classList.add('bg-disabled');
            subjectIdInput.value = '';
            
            // 移除行的高亮显示
            checkbox.closest('tr').classList.remove('table-active');
        }
    }
    
    // 表单提交前验证
    const form = document.getElementById('exam-form');
    form.addEventListener('submit', function(event) {
        // 检查是否至少选择了一个科目
        const selectedSubjects = document.querySelectorAll('input[name="subject_ids[]"][value!=""]');
        if (selectedSubjects.length === 0) {
            event.preventDefault();
            alert('请至少选择一个科目！');
            return false;
        }
        
        // 检查必填字段
        const requiredFields = ['name', 'exam_date'];
        let hasError = false;
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                hasError = true;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (hasError) {
            event.preventDefault();
            alert('请填写所有必填字段！');
            return false;
        }
        
        // 在表单提交前记录实际提交的数据（用于调试）
        console.log('提交的科目IDs:', Array.from(selectedSubjects).map(input => input.value));
        const activeScores = Array.from(document.querySelectorAll('.full-score-input')).filter((input, index) => 
            selectedSubjects[index] && selectedSubjects[index].value !== ""
        ).map(input => input.value);
        console.log('提交的满分值:', activeScores);
        
        return true;
    });
});
</script>

<style>
/* 添加样式 */
.subject-row {
    transition: background-color 0.3s;
}
.subject-row:hover {
    background-color: #f8f9fa;
}
.table-active {
    background-color: #e2f0ff !important;
}
.bg-disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}
.form-check-input {
    cursor: pointer;
    width: 20px;
    height: 20px;
}
.form-check-label {
    cursor: pointer;
    margin-left: 5px;
}
</style>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> ?> 
