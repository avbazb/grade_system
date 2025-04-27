<?php
/**
 * 成绩分析系统 - 学生班级排名页面
 * 
 * 本页面展示学生在班级中的排名情况
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '班级排名';

// 包含学生页面头部
include_once '../components/student_header.php';

// 获取考试列表
$exams = array();
$rankingData = array();
$subjectRankings = array();
$gradeRankingData = array(); // 添加年级排名数据变量

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
    
    if ($selectedExamId) {
        // 获取班级总分排名
        $sql = "SELECT 
                s.student_id,
                st.name as student_name,
                SUM(s.score) as total_score,
                ROUND((SUM(s.score) / SUM(es.full_score)) * 100, 1) as percentage
                FROM scores s
                INNER JOIN students st ON s.student_id = st.id
                INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
                WHERE s.exam_id = ? AND s.class_id = ?
                GROUP BY s.student_id, st.name
                ORDER BY total_score DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedExamId, $studentInfo['class_id']]);
        $rankingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取年级总分排名 (新增)
        // 首先获取学生所在年级的所有班级ID
        $sql = "SELECT id FROM classes WHERE grade = (SELECT grade FROM classes WHERE id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$studentInfo['class_id']]);
        $gradeClassIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 然后获取年级排名数据
        if (!empty($gradeClassIds)) {
            $placeholders = str_repeat('?,', count($gradeClassIds) - 1) . '?';
            $sql = "SELECT 
                    s.student_id,
                    c.name as class_name,
                    SUM(s.score) as total_score,
                    ROUND((SUM(s.score) / SUM(es.full_score)) * 100, 1) as percentage
                    FROM scores s
                    INNER JOIN students st ON s.student_id = st.id
                    INNER JOIN classes c ON s.class_id = c.id
                    INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
                    WHERE s.exam_id = ? AND s.class_id IN ({$placeholders})
                    GROUP BY s.student_id, c.name
                    ORDER BY total_score DESC
                    LIMIT 3"; // 只获取前三名
            
            $params = array_merge([$selectedExamId], $gradeClassIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $gradeRankingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 查询当前学生在年级的排名
            $sql = "SELECT 
                    (SELECT COUNT(*) + 1 FROM 
                        (SELECT student_id, SUM(score) as student_total 
                         FROM scores 
                         WHERE exam_id = ? AND class_id IN ({$placeholders}) 
                         GROUP BY student_id) as t 
                     WHERE t.student_total > (SELECT SUM(score) FROM scores WHERE exam_id = ? AND student_id = ?)) as grade_rank,
                    (SELECT COUNT(DISTINCT student_id) FROM scores WHERE exam_id = ? AND class_id IN ({$placeholders})) as total_students";
            
            $params = array_merge([$selectedExamId], $gradeClassIds, [$selectedExamId, $studentId, $selectedExamId], $gradeClassIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $studentGradeRank = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果学生不在前三名，添加学生的年级排名数据
            if ($studentGradeRank && $studentGradeRank['grade_rank'] > 3) {
                // 获取学生总分信息
                $sql = "SELECT 
                        SUM(s.score) as total_score,
                        ROUND((SUM(s.score) / SUM(es.full_score)) * 100, 1) as percentage,
                        c.name as class_name
                        FROM scores s
                        INNER JOIN classes c ON s.class_id = c.id
                        INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
                        WHERE s.exam_id = ? AND s.student_id = ?
                        GROUP BY c.name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$selectedExamId, $studentId]);
                $studentGradeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($studentGradeInfo) {
                    $studentGradeInfo['student_id'] = $studentId;
                    $studentGradeInfo['is_current'] = true;
                    $studentGradeInfo['grade_rank'] = $studentGradeRank['grade_rank'];
                    $studentGradeInfo['total_students'] = $studentGradeRank['total_students'];
                    $gradeRankingData[] = $studentGradeInfo;
                }
            }
        }
        
        // 获取科目列表
        $sql = "SELECT DISTINCT s.id, s.name 
                FROM subjects s 
                INNER JOIN scores sc ON s.id = sc.subject_id 
                WHERE sc.exam_id = ? AND sc.class_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedExamId, $studentInfo['class_id']]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取各科目排名
        foreach ($subjects as $subject) {
            $sql = "SELECT 
                    s.student_id,
                    st.name as student_name,
                    s.score,
                    es.full_score,
                    ROUND((s.score / es.full_score) * 100, 1) as percentage
                    FROM scores s
                    INNER JOIN students st ON s.student_id = st.id
                    INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
                    WHERE s.exam_id = ? AND s.class_id = ? AND s.subject_id = ?
                    ORDER BY s.score DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selectedExamId, $studentInfo['class_id'], $subject['id']]);
            $subjectRankings[$subject['name']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                <h3 class="mt-4">排名查看</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="#total-ranking">
                            <i class="fas fa-trophy"></i> 班级排名
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#grade-ranking">
                            <i class="fas fa-medal"></i> 年级排名
                        </a>
                    </li>
                    <?php foreach ($subjectRankings as $subjectName => $rankings): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#subject-<?php echo str_replace(' ', '-', strtolower($subjectName)); ?>">
                            <i class="fas fa-book"></i> <?php echo $subjectName; ?>
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
                        <i class="fas fa-trophy text-primary mr-2"></i>
                        排名情况
                    </h2>
                    <p class="card-text">
                        本页面展示您在班级和年级中的排名情况，包括总分排名和各科目排名。您可以从左侧选择不同的考试查看排名。
                    </p>
                </div>
            </div>
            
            <?php if ($selectedExamId && isset($currentExam)): ?>
            <!-- 考试信息 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo $currentExam['name']; ?> 排名情况
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb mr-2"></i> 本页面仅显示前3名的分数数据和您自己的位置，姓名已隐藏保护隐私。
                    </div>
                </div>
            </div>
            
            <!-- 班级总分排名 -->
            <div id="total-ranking" class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy mr-2"></i>
                        班级总分排名
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rankingData)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>排名</th>
                                    <th>总分</th>
                                    <th>百分比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userRank = 0;
                                $showEllipsis = false;
                                
                                foreach ($rankingData as $index => $student): 
                                    $rank = $index + 1;
                                    $isCurrentStudent = ($student['student_id'] == $studentId);
                                    
                                    if ($isCurrentStudent) {
                                        $userRank = $rank;
                                    }
                                    
                                    // 只显示前3名和当前学生
                                    if ($rank <= 3 || $isCurrentStudent):
                                        // 如果当前学生排名超过3名，且前面已经显示了前3名，则显示省略号
                                        if ($rank > 3 && !$showEllipsis && $userRank > 3) {
                                            echo '<tr><td colspan="3" class="text-center">...</td></tr>';
                                            $showEllipsis = true;
                                        }
                                ?>
                                <tr class="<?php echo $isCurrentStudent ? 'table-primary' : ''; ?>">
                                    <td><?php echo $rank; ?></td>
                                    <td>
                                        <?php echo $student['total_score']; ?>
                                        <?php if ($isCurrentStudent): ?>
                                        <span class="badge badge-primary ml-1">我</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getScoreLevel($student['percentage']); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $student['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $student['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $student['percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle mr-2"></i> 暂无排名数据。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 年级总分排名(新增) -->
            <div id="grade-ranking" class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-medal mr-2"></i>
                        年级总分排名
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($gradeRankingData)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>排名</th>
                                    <th>班级</th>
                                    <th>总分</th>
                                    <th>百分比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $showEllipsis = false;
                                
                                foreach ($gradeRankingData as $index => $student): 
                                    $isCurrentStudent = isset($student['is_current']) && $student['is_current'];
                                    $rank = $isCurrentStudent ? $student['grade_rank'] : ($index + 1);
                                    
                                    // 如果当前学生排名超过3名，且前面已经显示了前3名，则显示省略号
                                    if ($isCurrentStudent && $rank > 3 && !$showEllipsis) {
                                        echo '<tr><td colspan="4" class="text-center">...</td></tr>';
                                        $showEllipsis = true;
                                    }
                                ?>
                                <tr class="<?php echo $isCurrentStudent ? 'table-primary' : ''; ?>">
                                    <td><?php echo $rank; ?></td>
                                    <td><?php echo $student['class_name']; ?></td>
                                    <td>
                                        <?php echo $student['total_score']; ?>
                                        <?php if ($isCurrentStudent): ?>
                                        <span class="badge badge-primary ml-1">我</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getScoreLevel($student['percentage']); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $student['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $student['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $student['percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (!$showEllipsis && !empty($studentGradeRank) && $studentGradeRank['grade_rank'] > 3): ?>
                                <tr><td colspan="4" class="text-center">您在年级中排名第 <?php echo $studentGradeRank['grade_rank']; ?> 名（共 <?php echo $studentGradeRank['total_students']; ?> 人）</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle mr-2"></i> 暂无年级排名数据。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 各科目排名 -->
            <?php foreach ($subjectRankings as $subjectName => $rankings): ?>
            <div id="subject-<?php echo str_replace(' ', '-', strtolower($subjectName)); ?>" class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-book mr-2"></i>
                        <?php echo $subjectName; ?> 排名
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($rankings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>排名</th>
                                    <th>分数</th>
                                    <th>满分</th>
                                    <th>百分比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userRank = 0;
                                $showEllipsis = false;
                                
                                foreach ($rankings as $index => $student): 
                                    $rank = $index + 1;
                                    $isCurrentStudent = ($student['student_id'] == $studentId);
                                    
                                    if ($isCurrentStudent) {
                                        $userRank = $rank;
                                    }
                                    
                                    // 只显示前3名和当前学生
                                    if ($rank <= 3 || $isCurrentStudent):
                                        // 如果当前学生排名超过3名，且前面已经显示了前3名，则显示省略号
                                        if ($rank > 3 && !$showEllipsis && $userRank > 3) {
                                            echo '<tr><td colspan="4" class="text-center">...</td></tr>';
                                            $showEllipsis = true;
                                        }
                                ?>
                                <tr class="<?php echo $isCurrentStudent ? 'table-primary' : ''; ?>">
                                    <td><?php echo $rank; ?></td>
                                    <td>
                                        <?php echo $student['score']; ?>
                                        <?php if ($isCurrentStudent): ?>
                                        <span class="badge badge-primary ml-1">我</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $student['full_score']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getScoreLevel($student['percentage']); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $student['percentage']; ?>%;" 
                                                 aria-valuenow="<?php echo $student['percentage']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $student['percentage']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle mr-2"></i> 暂无该科目排名数据。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle mr-2"></i> 请从左侧选择一个考试查看排名情况。
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// 确保Chart.js被正确加载
if (typeof Chart === 'undefined') {
    // 如果没有加载，那么我们添加一个新的script标签来加载它
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"><\/script>');
}
</script>

<?php
// 帮助函数：根据百分比返回Bootstrap颜色类
function getScoreLevel($percentage) {
    if ($percentage >= 90) return 'success';  // 优秀
    if ($percentage >= 80) return 'primary';  // 良好
    if ($percentage >= 70) return 'info';     // 中等
    if ($percentage >= 60) return 'warning';  // 及格
    return 'danger';                         // 不及格
}

// 包含页脚
include_once '../components/student_footer.php';
?> 