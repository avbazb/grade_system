<?php
/**
 * 成绩分析系统 - 教师首页
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireTeacher();

// 获取当前教师信息
$userId = $_SESSION['user_id'];
$teacherInfo = getTeacherInfo($userId);

if (!$teacherInfo) {
    // 教师信息不存在，可能是数据错误
    echo "教师信息不存在，请联系管理员。";
    exit;
}

// 获取教师的科目
$teacherSubjects = getTeacherSubjects($teacherInfo['id']);

// 获取班主任班级（如果是班主任）
$classTeacherClasses = [];
if ($teacherInfo['is_class_teacher']) {
    $classTeacherClasses = getClassTeacherClasses($teacherInfo['id']);
}

// 获取最近考试
$recentExams = fetchAll("SELECT * FROM exams ORDER BY exam_date DESC LIMIT 5");

// 页面标题
$pageTitle = '教师控制台';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-3">教师控制台</h1>
        </div>
    </div>
    
    <!-- 欢迎信息 -->
    <div class="card mb-4 anim-fade-in">
        <div class="card-body">
            <h2>欢迎, <?php echo $_SESSION['name']; ?>!</h2>
            <p>您是<?php echo $teacherInfo['is_class_teacher'] ? '班主任' : '任课教师'; ?>，可以查看和管理相关班级和科目的成绩。</p>
        </div>
    </div>
    
    <!-- 教师信息和任教情况 -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card anim-slide-in">
                <div class="card-header">
                    <h2>我的教学情况</h2>
                </div>
                <div class="card-body">
                    <?php if (count($teacherSubjects) > 0): ?>
                        <h3 class="mb-3">我的任教科目</h3>
                        <ul class="list-group mb-4">
                            <?php foreach ($teacherSubjects as $subject): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo $subject['name']; ?> - <?php echo $subject['grade'] . ' ' . $subject['class_name']; ?>
                                    <a href="subject_scores.php?subject_id=<?php echo $subject['id']; ?>&class_id=<?php echo $subject['class_id']; ?>" class="btn btn-sm btn-primary">查看成绩</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info">暂无任教科目信息，请联系管理员添加。</div>
                    <?php endif; ?>
                    
                    <?php if ($teacherInfo['is_class_teacher'] && count($classTeacherClasses) > 0): ?>
                        <h3 class="mb-3">我的班主任班级</h3>
                        <ul class="list-group">
                            <?php foreach ($classTeacherClasses as $class): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo $class['grade'] . ' ' . $class['name']; ?>
                                    <a href="class_scores.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success">班级成绩</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card anim-slide-in">
                <div class="card-header">
                    <h2>最近考试</h2>
                </div>
                <div class="card-body">
                    <?php if (count($recentExams) > 0): ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>考试名称</th>
                                    <th>考试类型</th>
                                    <th>考试日期</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExams as $exam): ?>
                                    <tr>
                                        <td><?php echo $exam['name']; ?></td>
                                        <td><span class="badge badge-primary"><?php echo $exam['type']; ?></span></td>
                                        <td><?php echo formatDate($exam['exam_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">暂无考试信息</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 快速操作 -->
    <div class="row mb-4">
        <div class="col">
            <div class="card anim-bounce">
                <div class="card-header">
                    <h2>快速操作</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (count($teacherSubjects) > 0): ?>
                            <div class="col-md-4 mb-3">
                                <a href="upload_scores.php" class="btn btn-primary btn-lg btn-block">
                                    <i class="fas fa-upload"></i> 上传成绩
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($teacherInfo['is_class_teacher'] && count($classTeacherClasses) > 0): ?>
                            <div class="col-md-4 mb-3">
                                <a href="class_analysis.php?class_id=<?php echo $classTeacherClasses[0]['id']; ?>" class="btn btn-success btn-lg btn-block">
                                    <i class="fas fa-chart-bar"></i> 班级分析
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-3">
                            <a href="profile.php" class="btn btn-info btn-lg btn-block">
                                <i class="fas fa-user-cog"></i> 个人设置
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 包含页脚
include '../components/teacher_footer.php';
?>

