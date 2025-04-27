<?php
/**
 * 成绩分析系统 - 考试成绩上传页面
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 获取考试ID
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($examId <= 0) {
    // 没有有效的考试ID，重定向到考试列表
    header('Location: exams.php');
    exit;
}

// 获取考试信息
$exam = getExamInfo($examId);

if (!$exam) {
    // 考试不存在，重定向到考试列表
    header('Location: exams.php');
    exit;
}

// 获取考试科目
$examSubjects = getExamSubjects($examId);

// 将科目ID和名称映射为关联数组，便于后续使用
$subjectMap = [];
foreach ($examSubjects as $subject) {
    $subjectMap[$subject['subject_name']] = [
        'id' => $subject['subject_id'],
        'full_score' => $subject['full_score']
    ];
}

// 获取所有班级
$classes = getAllClasses();

// 页面标题
$pageTitle = '上传考试成绩';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col">
            <h1>上传考试成绩</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                    <li class="breadcrumb-item"><a href="exams.php">考试管理</a></li>
                    <li class="breadcrumb-item"><a href="exam_details.php?id=<?php echo $examId; ?>">考试详情</a></li>
                    <li class="breadcrumb-item active" aria-current="page">上传成绩</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="card mb-4 anim-fade-in">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">考试信息</h5>
                </div>
                <div class="col-auto">
                    <span class="badge badge-primary"><?php echo $exam['type']; ?></span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>考试名称：</strong> <?php echo $exam['name']; ?></p>
                    <p><strong>考试日期：</strong> <?php echo formatDate($exam['exam_date']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>包含科目：</strong> 
                        <?php 
                        $subjectNames = array_column($examSubjects, 'subject_name');
                        echo implode('、', $subjectNames); 
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.2s;">
                <div class="card-header">
                    <h5 class="mb-0">上传成绩文件</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p><i class="fas fa-info-circle"></i> 请上传Excel格式的成绩文件，第一行应包含学号、班级、姓名和科目名称。</p>
                        <p>支持的科目名称：<?php echo implode('、', array_keys($subjectMap)); ?></p>
                    </div>
                    
                    <form id="upload-form" class="mt-3">
                        <div class="form-group">
                            <label for="excel-file" class="form-label">选择Excel文件</label>
                            <input type="file" id="excel-file" name="excel_file" class="form-control" accept=".xlsx,.xls">
                        </div>
                        
                        <div class="mt-3">
                            <button type="button" id="upload-btn" class="btn btn-primary btn-block">
                                <i class="fas fa-upload"></i> 上传并预览
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-4">
                        <h6 class="mb-2">数据格式说明：</h6>
                        <ol class="small text-muted">
                            <li>Excel文件第一行必须包含表头</li>
                            <li>表头必须包含"学号"、"班级"、"姓名"列</li>
                            <li>其他列会被识别为科目成绩</li>
                            <li>系统会自动匹配已知的科目名称</li>
                            <li>学生将根据学号自动匹配或创建</li>
                            <li>如果学生班级发生变化，将被自动更新</li>
                        </ol>
                        
                        <div class="mt-3">
                            <a href="javascript:void(0);" id="download-template" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-download"></i> 下载模板
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">成绩预览</h5>
                        </div>
                        <div class="col-auto" id="preview-controls" style="display: none;">
                            <div class="search-box">
                                <input type="text" id="search-scores" class="search-input" placeholder="搜索学生...">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="loading" style="display: none;">
                        <div class="text-center py-5">
                            <div class="spinner"></div>
                            <p class="mt-3">正在解析Excel文件，请稍候...</p>
                        </div>
                    </div>
                    
                    <div id="preview-message" class="alert alert-info">
                        请上传Excel文件来预览成绩数据
                    </div>
                    
                    <div id="preview-container" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="preview-table">
                                <thead id="preview-header">
                                    <!-- 表头将动态生成 -->
                                </thead>
                                <tbody id="preview-body">
                                    <!-- 内容将动态生成 -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 text-right">
                            <button type="button" id="cancel-btn" class="btn btn-secondary">
                                <i class="fas fa-times"></i> 取消
                            </button>
                            <button type="button" id="save-btn" class="btn btn-success">
                                <i class="fas fa-save"></i> 保存成绩
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 模态框：保存进度 -->
<div class="modal" id="save-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">保存进度</h5>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <div class="spinner"></div>
                    <p id="save-status" class="mt-3">正在保存成绩数据，请稍候...</p>
                    <div class="progress mt-3">
                        <div id="save-progress" class="progress-bar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 缓存DOM元素
    const uploadForm = document.getElementById('upload-form');
    const excelFile = document.getElementById('excel-file');
    const uploadBtn = document.getElementById('upload-btn');
    const downloadTemplate = document.getElementById('download-template');
    const previewControls = document.getElementById('preview-controls');
    const searchScores = document.getElementById('search-scores');
    const loading = document.getElementById('loading');
    const previewMessage = document.getElementById('preview-message');
    const previewContainer = document.getElementById('preview-container');
    const previewHeader = document.getElementById('preview-header');
    const previewBody = document.getElementById('preview-body');
    const cancelBtn = document.getElementById('cancel-btn');
    const saveBtn = document.getElementById('save-btn');
    const saveModal = document.getElementById('save-modal');
    const saveProgress = document.getElementById('save-progress');
    const saveStatus = document.getElementById('save-status');
    
    // 存储解析后的数据
    let parsedData = null;
    let studentData = [];
    let mappedSubjects = {};
    
    // 科目映射数据（从PHP变量转换为JavaScript对象）
    const examSubjects = <?php echo json_encode($subjectMap); ?>;
    const examId = <?php echo $examId; ?>;
    
    // 上传按钮点击事件
    uploadBtn.addEventListener('click', function() {
        const file = excelFile.files[0];
        
        if (!file) {
            alert('请选择Excel文件');
            return;
        }
        
        // 显示加载状态
        loading.style.display = 'block';
        previewMessage.style.display = 'none';
        previewContainer.style.display = 'none';
        
        // 解析Excel文件
        parseExcelFile(file, function(data) {
            parsedData = data;
            
            // 处理解析结果
            const result = recognizeGradeData(data);
            
            if (result.success) {
                // 显示预览
                studentData = result.students;
                mappedSubjects = {};
                
                // 映射科目
                result.subjects.forEach(subject => {
                    if (examSubjects[subject]) {
                        mappedSubjects[subject] = examSubjects[subject];
                    }
                });
                
                // 生成预览表格
                generatePreviewTable(studentData, result.subjects);
                
                // 隐藏加载状态，显示预览
                loading.style.display = 'none';
                previewMessage.style.display = 'none';
                previewContainer.style.display = 'block';
                previewControls.style.display = 'block';
            } else {
                // 显示错误消息
                loading.style.display = 'none';
                previewMessage.className = 'alert alert-danger';
                previewMessage.textContent = '解析失败：' + result.message;
                previewMessage.style.display = 'block';
            }
        });
    });
    
    // 生成预览表格
    function generatePreviewTable(students, subjects) {
        // 生成表头
        let headerHTML = '<tr><th>学号</th><th>班级</th><th>姓名</th>';
        
        subjects.forEach(subject => {
            if (examSubjects[subject]) {
                headerHTML += `<th>${subject} (${examSubjects[subject].full_score}分)</th>`;
            } else {
                headerHTML += `<th>${subject} (未配置)</th>`;
            }
        });
        
        headerHTML += '</tr>';
        previewHeader.innerHTML = headerHTML;
        
        // 生成表体
        let bodyHTML = '';
        
        students.forEach((student, index) => {
            bodyHTML += `<tr>
                <td>${student.student_id}</td>
                <td>${student.class_name}</td>
                <td>${student.name}</td>`;
            
            subjects.forEach(subject => {
                const score = student.scores[subject];
                const isConfigured = examSubjects[subject] !== undefined;
                
                if (isConfigured) {
                    bodyHTML += `<td>
                        <input type="number" class="form-control form-control-sm score-input" 
                               data-student="${index}" data-subject="${subject}" 
                               value="${score !== null ? score : ''}" 
                               min="0" max="${examSubjects[subject].full_score}" step="0.5">
                    </td>`;
                } else {
                    bodyHTML += `<td class="text-muted">未配置</td>`;
                }
            });
            
            bodyHTML += '</tr>';
        });
        
        previewBody.innerHTML = bodyHTML;
        
        // 为分数输入框添加验证
        const scoreInputs = document.querySelectorAll('.score-input');
        scoreInputs.forEach(input => {
            input.addEventListener('input', function() {
                const value = parseFloat(this.value);
                const max = parseFloat(this.getAttribute('max'));
                
                if (value > max) {
                    this.value = max;
                }
            });
        });
    }
    
    // 搜索功能
    searchScores.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        const rows = previewBody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const studentId = row.cells[0].textContent.toLowerCase();
            const className = row.cells[1].textContent.toLowerCase();
            const studentName = row.cells[2].textContent.toLowerCase();
            
            if (studentId.includes(searchText) || className.includes(searchText) || studentName.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // 取消按钮点击事件
    cancelBtn.addEventListener('click', function() {
        // 重置状态
        parsedData = null;
        studentData = [];
        mappedSubjects = {};
        
        // 隐藏预览，显示提示
        previewContainer.style.display = 'none';
        previewControls.style.display = 'none';
        previewMessage.className = 'alert alert-info';
        previewMessage.textContent = '请上传Excel文件来预览成绩数据';
        previewMessage.style.display = 'block';
        
        // 重置文件输入
        uploadForm.reset();
    });
    
    // 保存按钮点击事件
    saveBtn.addEventListener('click', function() {
        // 收集当前输入的成绩
        const scoreInputs = document.querySelectorAll('.score-input');
        
        scoreInputs.forEach(input => {
            const studentIndex = parseInt(input.getAttribute('data-student'));
            const subject = input.getAttribute('data-subject');
            const value = input.value.trim();
            
            if (value !== '') {
                studentData[studentIndex].scores[subject] = parseFloat(value);
            } else {
                studentData[studentIndex].scores[subject] = null;
            }
        });
        
        // 准备提交的数据
        const submitData = [];
        
        studentData.forEach(student => {
            // 遍历配置的科目
            Object.keys(mappedSubjects).forEach(subject => {
                if (student.scores[subject] !== undefined) {
                    submitData.push({
                        exam_id: examId,
                        student_id: student.student_id,
                        name: student.name,
                        class_name: student.class_name,
                        subject: subject,
                        subject_id: mappedSubjects[subject].id,
                        score: student.scores[subject]
                    });
                }
            });
        });
        
        // 如果没有数据，提示用户
        if (submitData.length === 0) {
            alert('没有可保存的成绩数据');
            return;
        }
        
        // 显示保存进度模态框
        saveModal.style.display = 'block';
        saveProgress.style.width = '0%';
        saveStatus.textContent = '正在保存成绩数据，请稍候...';
        
        // 分批提交数据，避免请求过大
        const batchSize = 20;
        const batches = [];
        
        for (let i = 0; i < submitData.length; i += batchSize) {
            batches.push(submitData.slice(i, i + batchSize));
        }
        
        let batchIndex = 0;
        
        // 递归提交每一批数据
        function submitBatch() {
            if (batchIndex >= batches.length) {
                // 所有批次都已提交完成
                saveProgress.style.width = '100%';
                saveStatus.textContent = '保存完成！正在刷新页面...';
                
                // 延迟跳转，让用户看到完成提示
                setTimeout(() => {
                    window.location.href = 'exam_scores.php?id=' + examId;
                }, 1500);
                
                return;
            }
            
            const batch = batches[batchIndex];
            const progress = Math.round((batchIndex / batches.length) * 100);
            
            saveProgress.style.width = progress + '%';
            saveStatus.textContent = `正在保存... (${batchIndex + 1}/${batches.length})`;
            
            // 发送AJAX请求保存这一批数据
            fetch('ajax/save_scores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    exam_id: examId,
                    scores: batch
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 继续提交下一批
                    batchIndex++;
                    submitBatch();
                } else {
                    // 显示错误
                    saveModal.style.display = 'none';
                    alert('保存失败：' + data.message);
                }
            })
            .catch(error => {
                saveModal.style.display = 'none';
                alert('保存失败：' + error.message);
            });
        }
        
        // 开始提交第一批数据
        submitBatch();
    });
    
    // 下载模板
    downloadTemplate.addEventListener('click', function() {
        // 获取配置的科目
        const subjects = Object.keys(examSubjects);
        
        // 生成模板
        generateGradeTemplate(subjects, '成绩导入模板');
    });
});
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 