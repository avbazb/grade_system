<?php
/**
 * 成绩分析系统 - 学生所有成绩页面
 * 
 * 本页面展示学生历次考试的所有科目成绩
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '所有成绩';

// 包含学生页面头部
include_once '../components/student_header.php';

// 获取考试列表
$exams = array();
if ($studentId) {
    // 获取学生所有考试数据，按日期排序
    $sql = "SELECT DISTINCT e.id, e.name, e.type, e.exam_date 
            FROM exams e 
            INNER JOIN scores s ON e.id = s.exam_id 
            WHERE s.student_id = ? 
            ORDER BY e.exam_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取筛选参数
    $selectedExamId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : (count($exams) > 0 ? $exams[0]['id'] : 0);
    
    // 获取选定考试的所有科目成绩
    if ($selectedExamId) {
        $sql = "SELECT s.score, sub.name as subject_name, es.full_score,
                (s.score/es.full_score*100) as percentage,
                (SELECT AVG(s2.score) FROM scores s2 WHERE s2.exam_id = s.exam_id AND s2.subject_id = s.subject_id AND s2.class_id = s.class_id) as class_avg,
                (SELECT MAX(s3.score) FROM scores s3 WHERE s3.exam_id = s.exam_id AND s3.subject_id = s.subject_id AND s3.class_id = s.class_id) as class_max,
                (SELECT COUNT(*) + 1 FROM scores s4 WHERE s4.exam_id = s.exam_id AND s4.subject_id = s.subject_id AND s4.class_id = s.class_id AND s4.score > s.score) as subject_rank,
                (SELECT COUNT(DISTINCT student_id) FROM scores WHERE exam_id = s.exam_id AND subject_id = s.subject_id AND class_id = s.class_id) as total_students
                FROM scores s
                INNER JOIN subjects sub ON s.subject_id = sub.id
                INNER JOIN exam_subjects es ON s.subject_id = es.subject_id AND s.exam_id = es.exam_id
                WHERE s.student_id = ? AND s.exam_id = ?
                ORDER BY sub.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$studentId, $selectedExamId]);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取总分和排名
        $sql = "SELECT 
                SUM(s.score) as total_score,
                SUM(es.full_score) as total_full_score,
                (SELECT COUNT(*) + 1 FROM 
                    (SELECT student_id, SUM(score) as student_total 
                     FROM scores 
                     WHERE exam_id = ? AND class_id = ? 
                     GROUP BY student_id) as t 
                 WHERE t.student_total > (SELECT SUM(score) FROM scores WHERE exam_id = ? AND student_id = ?)) as class_rank,
                (SELECT COUNT(DISTINCT student_id) FROM scores WHERE exam_id = ? AND class_id = ?) as total_students
                FROM scores s
                INNER JOIN exam_subjects es ON s.subject_id = es.subject_id AND s.exam_id = es.exam_id
                WHERE s.exam_id = ? AND s.student_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedExamId, $studentInfo['class_id'], $selectedExamId, $studentId, $selectedExamId, $studentInfo['class_id'], $selectedExamId, $studentId]);
        $totalInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 计算总体完成率
        if ($totalInfo && $totalInfo['total_full_score'] > 0) {
            $totalInfo['percentage'] = ($totalInfo['total_score'] / $totalInfo['total_full_score']) * 100;
        }
        
        // 获取当前考试信息
        $currentExam = null;
        foreach ($exams as $exam) {
            if ($exam['id'] == $selectedExamId) {
                $currentExam = $exam;
                break;
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>考试筛选</h3>
                <ul class="nav flex-column">
                    <?php foreach ($exams as $exam): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $selectedExamId == $exam['id'] ? 'active' : ''; ?>" 
                           href="?exam_id=<?php echo $exam['id']; ?>">
                            <i class="fas fa-file-alt"></i> <?php echo $exam['name']; ?>
                            <small class="text-muted d-block"><?php echo $exam['exam_date']; ?> (<?php echo $exam['type']; ?>)</small>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <h3 class="mt-4">快速导航</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/index.php">
                            <i class="fas fa-tachometer-alt"></i> 返回首页
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/analysis.php">
                            <i class="fas fa-chart-pie"></i> 成绩分析
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/rankings.php">
                            <i class="fas fa-medal"></i> 排名情况
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
                        <i class="fas fa-list-alt text-primary mr-2"></i>
                        成绩详情
                    </h2>
                    <p class="card-text">
                        本页面展示您的各科目详细成绩，您可以从左侧选择不同的考试查看。
                    </p>
                </div>
            </div>
            
            <?php if ($selectedExamId && isset($currentExam)): ?>
            <!-- 考试信息 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo $currentExam['name']; ?> (<?php echo $currentExam['type']; ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="h5 mb-0">考试日期</div>
                                <div class="h4 text-primary">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?php echo $currentExam['exam_date']; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (isset($totalInfo)): ?>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="h5 mb-0">总分</div>
                                <div class="h3 text-primary">
                                    <?php echo $totalInfo['total_score']; ?> / <?php echo $totalInfo['total_full_score']; ?>
                                    <small class="text-muted">(<?php echo number_format($totalInfo['percentage'], 1); ?>%)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="h5 mb-0">班级排名</div>
                                <div class="h3 text-primary">
                                    <?php echo $totalInfo['class_rank']; ?> / <?php echo $totalInfo['total_students']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 成绩详情 -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ul mr-2"></i>
                        科目成绩详情
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (isset($scores) && count($scores) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>科目</th>
                                    <th>成绩</th>
                                    <th>满分</th>
                                    <th>百分比</th>
                                    <th>班级平均</th>
                                    <th>班级最高</th>
                                    <th>科目排名</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scores as $score): ?>
                                <tr>
                                    <td><?php echo $score['subject_name']; ?></td>
                                    <td class="font-weight-bold"><?php echo $score['score']; ?></td>
                                    <td><?php echo $score['full_score']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getScoreLevel($score['percentage']); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $score['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $score['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo number_format($score['percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($score['class_avg'], 1); ?></td>
                                    <td><?php echo $score['class_max']; ?></td>
                                    <td><?php echo $score['subject_rank']; ?> / <?php echo $score['total_students']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle mr-2"></i> 暂无该考试的成绩数据。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i> 请从左侧选择一个考试查看详细成绩。
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// 帮助函数：根据百分比返回Bootstrap颜色类
function getScoreLevel($percentage) {
    if ($percentage >= 90) return 'success';  // 优秀
    if ($percentage >= 80) return 'primary';  // 良好
    if ($percentage >= 70) return 'info';     // 中等
    if ($percentage >= 60) return 'warning';  // 及格
    return 'danger';                         // 不及格
}
</script>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 