<?php
/**
 * 成绩分析系统 - 管理员首页
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 获取系统统计信息
$totalStudents = fetchOne("SELECT COUNT(*) as count FROM students")['count'] ?? 0;
$totalTeachers = fetchOne("SELECT COUNT(*) as count FROM teachers")['count'] ?? 0;
$totalExams = fetchOne("SELECT COUNT(*) as count FROM exams")['count'] ?? 0;
$totalClasses = fetchOne("SELECT COUNT(*) as count FROM classes")['count'] ?? 0;

// 获取最近考试
$recentExams = fetchAll("SELECT * FROM exams ORDER BY exam_date DESC LIMIT 5");

// 获取班级列表
$classes = getAllClasses();

// 页面标题
$pageTitle = '管理员控制台';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-3">管理员控制台</h1>
        </div>
    </div>
    
    <!-- 统计数据 -->
    <div class="dashboard anim-fade-in">
        <div class="stat-card">
            <h3>学生总数</h3>
            <div class="stat-value"><?php echo $totalStudents; ?></div>
            <div class="stat-description">系统中的学生总数</div>
        </div>
        
        <div class="stat-card">
            <h3>教师总数</h3>
            <div class="stat-value"><?php echo $totalTeachers; ?></div>
            <div class="stat-description">系统中的教师总数</div>
        </div>
        
        <div class="stat-card">
            <h3>考试总数</h3>
            <div class="stat-value"><?php echo $totalExams; ?></div>
            <div class="stat-description">系统中的考试总数</div>
        </div>
        
        <div class="stat-card">
            <h3>班级总数</h3>
            <div class="stat-value"><?php echo $totalClasses; ?></div>
            <div class="stat-description">系统中的班级总数</div>
        </div>
    </div>
    
    <div class="row">
        <!-- 最近考试 -->
        <div class="col">
            <div class="card anim-slide-in">
                <div class="card-header">
                    <h2>最近考试</h2>
                    <a href="exams.php" class="btn btn-sm btn-primary">查看全部</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentExams) > 0): ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>考试名称</th>
                                    <th>考试类型</th>
                                    <th>考试日期</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentExams as $exam): ?>
                                    <tr>
                                        <td><?php echo $exam['name']; ?></td>
                                        <td><span class="badge badge-primary"><?php echo $exam['type']; ?></span></td>
                                        <td><?php echo formatDate($exam['exam_date']); ?></td>
                                        <td>
                                            <a href="exam_details.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">详情</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">暂无考试记录</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <!-- 快速入口 -->
        <div class="col">
            <div class="card anim-bounce">
                <div class="card-header">
                    <h2>快速操作</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <a href="exams_add.php" class="btn btn-primary btn-lg btn-block mb-2">
                                <i class="fas fa-plus"></i> 新建考试
                            </a>
                        </div>
                        <div class="col">
                            <a href="student_manage.php" class="btn btn-success btn-lg btn-block mb-2">
                                <i class="fas fa-user-graduate"></i> 管理学生
                            </a>
                        </div>
                        <div class="col">
                            <a href="teacher_manage.php" class="btn btn-info btn-lg btn-block mb-2">
                                <i class="fas fa-chalkboard-teacher"></i> 管理教师
                            </a>
                        </div>
                        <div class="col">
                            <a href="class_manage.php" class="btn btn-warning btn-lg btn-block mb-2">
                                <i class="fas fa-users"></i> 管理班级
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <!-- 数据分析入口 -->
        <div class="col">
            <div class="card anim-slide-in">
                <div class="card-header">
                    <h2>数据分析</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (count($classes) > 0): ?>
                            <?php foreach ($classes as $index => $class): ?>
                                <?php if ($index < 4): ?>
                                    <div class="col-md-6 col-lg-3 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo $class['grade'] . ' ' . $class['name']; ?></h5>
                                                <p class="card-text">查看该班级的成绩分析和统计数据</p>
                                                <a href="class_analysis.php?id=<?php echo $class['id']; ?>" class="btn btn-primary">查看分析</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if (count($classes) > 4): ?>
                                <div class="col-md-6 col-lg-3 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">全部班级</h5>
                                            <p class="card-text">查看所有班级的成绩分析和统计数据</p>
                                            <a href="class_analysis.php" class="btn btn-primary">查看全部</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="col">
                                <div class="alert alert-info">暂无班级数据，请先添加班级</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 