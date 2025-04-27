<?php
/**
 * 成绩分析系统 - 考试详情页面
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
$exam = fetchOne("SELECT e.*, u.name as creator_name 
                 FROM exams e 
                 LEFT JOIN users u ON e.created_by = u.id 
                 WHERE e.id = $examId");

if (!$exam) {
    // 考试不存在，重定向到考试列表
    header('Location: exams.php');
    exit;
}

// 获取考试科目
$examSubjects = fetchAll("SELECT es.*, s.name as subject_name 
                         FROM exam_subjects es 
                         LEFT JOIN subjects s ON es.subject_id = s.id 
                         WHERE es.exam_id = $examId");

// 统计参加考试的班级数量
$classCount = fetchOne("SELECT COUNT(DISTINCT class_id) as count FROM scores WHERE exam_id = $examId");
$classCount = $classCount ? $classCount['count'] : 0;

// 统计参加考试的学生数量
$studentCount = fetchOne("SELECT COUNT(DISTINCT student_id) as count FROM scores WHERE exam_id = $examId");
$studentCount = $studentCount ? $studentCount['count'] : 0;

// 统计已录入的成绩数量
$scoreCount = fetchOne("SELECT COUNT(*) as count FROM scores WHERE exam_id = $examId AND score IS NOT NULL");
$scoreCount = $scoreCount ? $scoreCount['count'] : 0;

// 页面标题
$pageTitle = '考试详情';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col">
            <h1>考试详情</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                    <li class="breadcrumb-item"><a href="exams.php">考试管理</a></li>
                    <li class="breadcrumb-item active" aria-current="page">考试详情</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4 anim-fade-in">
                <div class="card-header">
                    <h5 class="mb-0">考试基本信息</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="30%">考试名称：</th>
                            <td><?php echo $exam['name']; ?></td>
                        </tr>
                        <tr>
                            <th>考试类型：</th>
                            <td><span class="badge badge-primary"><?php echo $exam['type']; ?></span></td>
                        </tr>
                        <tr>
                            <th>考试日期：</th>
                            <td><?php echo formatDate($exam['exam_date']); ?></td>
                        </tr>
                        <tr>
                            <th>创建时间：</th>
                            <td><?php echo formatDate($exam['created_at'], 'Y-m-d H:i'); ?></td>
                        </tr>
                        <tr>
                            <th>创建者：</th>
                            <td><?php echo $exam['creator_name']; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="exams.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> 返回
                        </a>
                        <a href="exam_scores.php?id=<?php echo $examId; ?>" class="btn btn-success">
                            <i class="fas fa-list-alt"></i> 成绩管理
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.2s;">
                <div class="card-header">
                    <h5 class="mb-0">统计信息</h5>
                </div>
                <div class="card-body">
                    <div class="dashboard">
                        <div class="stat-card">
                            <h3>班级数量</h3>
                            <div class="stat-value"><?php echo $classCount; ?></div>
                            <div class="stat-description">参加考试的班级数</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>学生数量</h3>
                            <div class="stat-value"><?php echo $studentCount; ?></div>
                            <div class="stat-description">参加考试的学生数</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>成绩数量</h3>
                            <div class="stat-value"><?php echo $scoreCount; ?></div>
                            <div class="stat-description">已录入的成绩数量</div>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <a href="exam_upload.php?id=<?php echo $examId; ?>" class="btn btn-warning">
                            <i class="fas fa-upload"></i> 上传成绩
                        </a>
                        <a href="exam_analysis.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> 成绩分析
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <h5 class="mb-0">考试科目信息</h5>
                </div>
                <div class="card-body">
                    <?php if (count($examSubjects) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>科目名称</th>
                                        <th>满分值</th>
                                        <th>已录入成绩数</th>
                                        <th>平均分</th>
                                    </tr>
                                </thead>
                                <tbody id="subject-list">
                                    <?php foreach ($examSubjects as $index => $subject): 
                                        // 获取科目已录入的成绩数量
                                        $subjectScoreCount = fetchOne("SELECT COUNT(*) as count FROM scores WHERE exam_id = $examId AND subject_id = {$subject['subject_id']} AND score IS NOT NULL");
                                        $subjectScoreCount = $subjectScoreCount ? $subjectScoreCount['count'] : 0;
                                        
                                        // 获取科目平均分
                                        $subjectAvg = fetchOne("SELECT AVG(score) as avg FROM scores WHERE exam_id = $examId AND subject_id = {$subject['subject_id']} AND score IS NOT NULL");
                                        $subjectAvg = $subjectAvg && $subjectAvg['avg'] ? round($subjectAvg['avg'], 1) : '-';
                                    ?>
                                        <tr class="subject-row">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo $subject['subject_name']; ?></td>
                                            <td><?php echo $subject['full_score']; ?></td>
                                            <td><?php echo $subjectScoreCount; ?></td>
                                            <td>
                                                <?php if ($subjectAvg !== '-'): ?>
                                                    <?php echo $subjectAvg; ?>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar" style="width: <?php echo ($subjectAvg / $subject['full_score'] * 100); ?>%"></div>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">尚未设置考试科目信息</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.6s;">
                <div class="card-header">
                    <h5 class="mb-0">班级成绩统计</h5>
                </div>
                <div class="card-body">
                    <?php if ($classCount > 0): ?>
                        <div class="chart-container">
                            <canvas id="class-score-chart" height="300"></canvas>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            fetch('ajax/get_class_scores.php?exam_id=<?php echo $examId; ?>')
                                .then(response => response.json())
                                .then(data => {
                                    const labels = data.map(item => item.class_name);
                                    const scores = data.map(item => item.avg_score);
                                    
                                    createBarChart('class-score-chart', labels, [{
                                        label: '班级平均分',
                                        data: scores,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1
                                    }], {
                                        title: '班级平均分对比'
                                    });
                                })
                                .catch(error => console.error('Error:', error));
                        });
                        </script>
                    <?php else: ?>
                        <div class="alert alert-info">尚未录入班级成绩数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 添加动画效果
    const subjectRows = document.querySelectorAll('.subject-row');
    
    if (subjectRows.length > 0) {
        subjectRows.forEach((row, index) => {
            row.style.opacity = 0;
            row.style.transform = 'translateY(10px)';
            row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            
            setTimeout(() => {
                row.style.opacity = 1;
                row.style.transform = 'translateY(0)';
            }, 100 * index);
        });
    }
});
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 