<?php
/**
 * 成绩分析系统 - 成绩分析页面
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

// 确定分析类型（班主任视图或任课教师视图）
$viewType = isset($_GET['view']) ? $_GET['view'] : '';

if (empty($viewType)) {
    // 根据教师角色自动选择视图
    $viewType = $teacherInfo['is_class_teacher'] ? 'class' : 'subject';
}

// 获取教师的科目和班级
$teacherSubjects = getTeacherSubjects($teacherInfo['id']);

// 获取班主任班级（如果是班主任）
$classTeacherClasses = [];
if ($teacherInfo['is_class_teacher']) {
    $classTeacherClasses = getClassTeacherClasses($teacherInfo['id']);
}

// 选择班级或科目
$selectedClassId = 0;
$selectedSubjectId = 0;
$selectedExamId = 0;

if ($viewType == 'class') {
    // 班主任视图
    $selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (count($classTeacherClasses) > 0 ? $classTeacherClasses[0]['id'] : 0);
} else {
    // 任课教师视图
    $selectedSubjectClassId = isset($_GET['subject_class_id']) ? $_GET['subject_class_id'] : '';
    
    if (empty($selectedSubjectClassId) && count($teacherSubjects) > 0) {
        // 确保teacherSubjects[0]中有class_id
        $selectedSubjectClassId = $teacherSubjects[0]['id'] . '-' . (isset($teacherSubjects[0]['class_id']) ? $teacherSubjects[0]['class_id'] : 0);
    }
    
    if (!empty($selectedSubjectClassId)) {
        $parts = explode('-', $selectedSubjectClassId);
        if (count($parts) == 2) {
            $selectedSubjectId = (int)$parts[0];
            $selectedClassId = (int)$parts[1];
        }
    }
}

// 获取选定班级的信息
$classInfo = null;
if ($selectedClassId) {
    $classInfo = fetchOne("SELECT * FROM classes WHERE id = ?", [$selectedClassId]);
}

// 获取选定科目的信息
$subjectInfo = null;
if ($selectedSubjectId) {
    $subjectInfo = fetchOne("SELECT * FROM subjects WHERE id = ?", [$selectedSubjectId]);
}

// 获取考试列表
$exams = [];
if ($classInfo) {
    $exams = fetchAll(
        "SELECT * FROM exams 
         ORDER BY exam_date DESC 
         LIMIT 10"
    );
    
    // 如果没有指定考试，默认选择最新的一次
    $selectedExamId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : (count($exams) > 0 ? $exams[0]['id'] : 0);
}

// 获取选定考试的详细信息
$examInfo = null;
if ($selectedExamId) {
    $examInfo = fetchOne("SELECT * FROM exams WHERE id = ?", [$selectedExamId]);
}

// 获取考试科目列表
$examSubjects = [];
if ($examInfo) {
    $examSubjects = fetchAll(
        "SELECT es.*, s.name as subject_name 
         FROM exam_subjects es 
         JOIN subjects s ON es.subject_id = s.id 
         WHERE es.exam_id = ?", 
        [$selectedExamId]
    );
}

// 获取班级学生列表
$students = [];
if ($classInfo) {
    $students = fetchAll(
        "SELECT * FROM students 
         WHERE class_id = ? 
         ORDER BY name ASC", 
        [$selectedClassId]
    );
}

// 获取学生成绩
$studentScores = [];
if ($examInfo && count($students) > 0) {
    foreach ($students as $student) {
        $scores = fetchAll(
            "SELECT s.*, sub.name as subject_name, es.full_score
             FROM scores s 
             JOIN subjects sub ON s.subject_id = sub.id
             JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
             WHERE s.exam_id = ? AND s.student_id = ?", 
            [$selectedExamId, $student['id']]
        );
        
        $scoreMap = [];
        foreach ($scores as $score) {
            $scoreMap[$score['subject_id']] = $score;
        }
        
        $studentScores[$student['id']] = $scoreMap;
    }
}

// 计算科目统计信息
$subjectStats = [];
if ($examInfo && count($examSubjects) > 0) {
    foreach ($examSubjects as $subject) {
        $stats = fetchOne(
            "SELECT 
                AVG(score) as avg_score,
                MAX(score) as max_score,
                MIN(score) as min_score,
                COUNT(CASE WHEN score >= ? * 0.6 THEN 1 END) as pass_count,
                COUNT(CASE WHEN score >= ? * 0.85 THEN 1 END) as excellent_count,
                COUNT(score) as total_count
             FROM scores 
             WHERE exam_id = ? AND subject_id = ?", 
            [$subject['full_score'], $subject['full_score'], $selectedExamId, $subject['subject_id']]
        );
        
        $subjectStats[$subject['subject_id']] = $stats;
    }
}

// 计算总分和排名
$totalScores = [];
if (count($studentScores) > 0 && count($examSubjects) > 0) {
    foreach ($students as $student) {
        $total = 0;
        $totalFullScore = 0;
        $hasAllScores = true;
        
        foreach ($examSubjects as $subject) {
            if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                $total += $score;
                $totalFullScore += $subject['full_score'];
            } else {
                $hasAllScores = false;
                break;
            }
        }
        
        if ($hasAllScores) {
            $totalScores[$student['id']] = [
                'student_id' => $student['id'],
                'student_name' => $student['name'],
                'total_score' => $total,
                'total_full_score' => $totalFullScore,
                'percentage' => ($totalFullScore > 0) ? ($total / $totalFullScore) * 100 : 0
            ];
        }
    }
    
    // 排序计算排名
    if (count($totalScores) > 0) {
        // 根据总分排序
        usort($totalScores, function($a, $b) {
            return $b['total_score'] - $a['total_score'];
        });
        
        // 添加排名
        $rank = 1;
        $prevScore = null;
        $prevRank = 1;
        
        foreach ($totalScores as &$item) {
            if ($prevScore !== null && $item['total_score'] < $prevScore) {
                $rank = $prevRank + 1;
            }
            
            $item['rank'] = $rank;
            $prevScore = $item['total_score'];
            $prevRank = $rank;
            $rank++;
        }
    }
}

// 获取年级平均分（如果是班主任视图）
$gradeAverages = [];
if ($viewType == 'class' && $examInfo && $classInfo) {
    foreach ($examSubjects as $subject) {
        $gradeAvg = fetchOne(
            "SELECT AVG(s.score) as avg_score
             FROM scores s
             JOIN students st ON s.student_id = st.id
             JOIN classes c ON st.class_id = c.id
             WHERE s.exam_id = ? AND s.subject_id = ? AND c.grade = ?", 
            [$selectedExamId, $subject['subject_id'], $classInfo['grade']]
        );
        
        $gradeAverages[$subject['subject_id']] = $gradeAvg['avg_score'] ?? 0;
    }
}

// 获取历次考试的趋势数据
$trendData = [];
if ($classInfo) {
    $pastExams = fetchAll(
        "SELECT id, name, exam_date 
         FROM exams 
         ORDER BY exam_date ASC 
         LIMIT 5"
    );
    
    foreach ($pastExams as $exam) {
        $examTrendData = [
            'exam_id' => $exam['id'],
            'name' => $exam['name'],
            'date' => formatDate($exam['exam_date']),
            'subjects' => []
        ];
        
        if ($viewType == 'class') {
            // 班主任视图：获取所有科目的平均分
            $subjectAvgs = fetchAll(
                "SELECT s.subject_id, sub.name as subject_name, AVG(s.score) as avg_score
                 FROM scores s
                 JOIN subjects sub ON s.subject_id = sub.id
                 WHERE s.exam_id = ? AND s.class_id = ?
                 GROUP BY s.subject_id", 
                [$exam['id'], $selectedClassId]
            );
            
            foreach ($subjectAvgs as $avg) {
                $examTrendData['subjects'][$avg['subject_id']] = [
                    'name' => $avg['subject_name'],
                    'avg_score' => $avg['avg_score']
                ];
            }
        } else {
            // 任课教师视图：仅获取当前选择的科目
            $subjectAvg = fetchOne(
                "SELECT AVG(score) as avg_score
                 FROM scores
                 WHERE exam_id = ? AND subject_id = ? AND class_id = ?", 
                [$exam['id'], $selectedSubjectId, $selectedClassId]
            );
            
            if ($subjectInfo) {
                $examTrendData['subjects'][$selectedSubjectId] = [
                    'name' => $subjectInfo['name'],
                    'avg_score' => $subjectAvg['avg_score'] ?? 0
                ];
            }
        }
        
        $trendData[] = $examTrendData;
    }
}

// 学生成绩进步/退步排名
$progressData = [];
if ($viewType == 'class' && count($pastExams) >= 2) {
    // 获取最近两次考试
    $latestExams = array_slice($pastExams, -2);
    
    // 只有选择最新考试时才计算进步情况
    if ($selectedExamId == $latestExams[1]['id']) {
        $currentExamId = $latestExams[1]['id'];
        $previousExamId = $latestExams[0]['id'];
        
        // 获取两次考试的科目列表
        $commonSubjects = fetchAll(
            "SELECT DISTINCT es1.subject_id, s.name as subject_name
             FROM exam_subjects es1
             JOIN exam_subjects es2 ON es1.subject_id = es2.subject_id
             JOIN subjects s ON es1.subject_id = s.id
             WHERE es1.exam_id = ? AND es2.exam_id = ?",
            [$currentExamId, $previousExamId]
        );
        
        // 计算每个学生在相同科目上的进步情况
        foreach ($students as $student) {
            $totalProgress = 0;
            $subjectCount = 0;
            
            foreach ($commonSubjects as $subject) {
                $currentScore = fetchOne(
                    "SELECT score, (SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?) as full_score
                     FROM scores 
                     WHERE exam_id = ? AND student_id = ? AND subject_id = ?", 
                    [$currentExamId, $subject['subject_id'], $currentExamId, $student['id'], $subject['subject_id']]
                );
                
                $previousScore = fetchOne(
                    "SELECT score, (SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?) as full_score
                     FROM scores 
                     WHERE exam_id = ? AND student_id = ? AND subject_id = ?", 
                    [$previousExamId, $subject['subject_id'], $previousExamId, $student['id'], $subject['subject_id']]
                );
                
                if ($currentScore && $previousScore) {
                    // 计算百分比进步率
                    $currentPercentage = ($currentScore['score'] / $currentScore['full_score']) * 100;
                    $previousPercentage = ($previousScore['score'] / $previousScore['full_score']) * 100;
                    $progress = $currentPercentage - $previousPercentage;
                    
                    $totalProgress += $progress;
                    $subjectCount++;
                }
            }
            
            if ($subjectCount > 0) {
                $progressData[$student['id']] = [
                    'student_id' => $student['id'],
                    'student_name' => $student['name'],
                    'progress' => $totalProgress / $subjectCount
                ];
            }
        }
        
        // 排序
        if (count($progressData) > 0) {
            uasort($progressData, function($a, $b) {
                return $b['progress'] - $a['progress'];
            });
        }
    }
}

// 页面标题
$pageTitle = '成绩分析';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">成绩分析</h1>
            
            <?php if (count($teacherSubjects) === 0 && count($classTeacherClasses) === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 您目前没有任教科目或班级，无法进行成绩分析。
                </div>
            <?php else: ?>
                <!-- 视图切换 -->
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <?php if ($teacherInfo['is_class_teacher']): ?>
                            <a href="?view=class" class="btn btn-<?php echo $viewType == 'class' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-users"></i> 班主任视图
                            </a>
                        <?php endif; ?>
                        
                        <?php if (count($teacherSubjects) > 0): ?>
                            <a href="?view=subject" class="btn btn-<?php echo $viewType == 'subject' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-book"></i> 任课教师视图
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 班级/科目和考试选择 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <input type="hidden" name="view" value="<?php echo $viewType; ?>">
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <?php if ($viewType == 'class'): ?>
                                        <label for="class_id">选择班级</label>
                                        <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()" <?php echo count($classTeacherClasses) == 0 ? 'disabled' : ''; ?>>
                                            <?php foreach ($classTeacherClasses as $class): ?>
                                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $class['grade'] . $class['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <label for="subject_class_id">选择科目和班级</label>
                                        <select class="form-control" id="subject_class_id" name="subject_class_id" onchange="this.form.submit()">
                                            <?php foreach ($teacherSubjects as $subject): ?>
                                                <?php $value = $subject['id'] . '-' . $subject['class_id']; ?>
                                                <option value="<?php echo $value; ?>" <?php echo $selectedSubjectId == $subject['id'] && $selectedClassId == $subject['class_id'] ? 'selected' : ''; ?>>
                                                    <?php echo $subject['name'] . ' (' . $subject['grade'] . $subject['class_name'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="exam_id">选择考试</label>
                                    <select class="form-control" id="exam_id" name="exam_id" onchange="this.form.submit()">
                                        <?php if (count($exams) > 0): ?>
                                            <?php foreach ($exams as $exam): ?>
                                                <option value="<?php echo $exam['id']; ?>" <?php echo $selectedExamId == $exam['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $exam['name'] . ' (' . formatDate($exam['exam_date']) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">暂无考试数据</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($examInfo && $classInfo): ?>
    <!-- 班主任视图 -->
    <?php if ($viewType == 'class'): ?>
        <!-- 班级平均分与年级平均分对比图 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">本班各科平均分与年级平均分对比</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="classGradeComparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 成绩分布图和学生成绩雷达图 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">班级成绩分布</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="scoreDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">学生各科成绩雷达图</h3>
                        <div class="mt-2">
                            <select id="studentRadarSelector" class="form-control form-control-sm" onchange="updateStudentRadarChart()">
                                <option value="">选择学生...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="studentRadarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 各科及格率和优秀率对比图 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">各科目及格率/优秀率对比</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="passRateChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">历次考试平均分趋势</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="scoreTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 学生成绩排名和进步/退步排名 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0">学生总分排名</h3>
                            <button class="btn btn-sm btn-success" onclick="exportToExcel('rankTable')">
                                <i class="fas fa-file-excel"></i> 导出
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="rankTable">
                                <thead>
                                    <tr>
                                        <th>排名</th>
                                        <th>姓名</th>
                                        <th>总分</th>
                                        <th>满分</th>
                                        <th>得分率</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($totalScores as $score): ?>
                                        <tr>
                                            <td><?php echo $score['rank']; ?></td>
                                            <td><?php echo $score['student_name']; ?></td>
                                            <td><?php echo number_format($score['total_score'], 1); ?></td>
                                            <td><?php echo number_format($score['total_full_score'], 1); ?></td>
                                            <td><?php echo number_format($score['percentage'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="h5 mb-0">学生成绩进步/退步排名</h3>
                            <button class="btn btn-sm btn-success" onclick="exportToExcel('progressTable')">
                                <i class="fas fa-file-excel"></i> 导出
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($progressData) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="progressTable">
                                    <thead>
                                        <tr>
                                            <th>排名</th>
                                            <th>姓名</th>
                                            <th>进步百分比</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1;
                                        foreach ($progressData as $progress): 
                                        ?>
                                            <tr>
                                                <td><?php echo $rank++; ?></td>
                                                <td><?php echo $progress['student_name']; ?></td>
                                                <td>
                                                    <?php 
                                                    $progressValue = number_format($progress['progress'], 1);
                                                    $textClass = $progress['progress'] >= 0 ? 'text-success' : 'text-danger';
                                                    $sign = $progress['progress'] >= 0 ? '+' : '';
                                                    echo "<span class='{$textClass}'>{$sign}{$progressValue}%</span>";
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                无法计算进步情况，可能是因为没有上一次考试数据或当前选择的不是最新考试。
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 临界学生分析 -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0">临界生分析</h3>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="criticalTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="critical-fail-tab" data-toggle="tab" href="#critical-fail" role="tab">
                            不及格临界生
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="critical-excellent-tab" data-toggle="tab" href="#critical-excellent" role="tab">
                            优秀临界生
                        </a>
                    </li>
                </ul>
                <div class="tab-content mt-3" id="criticalTabContent">
                    <div class="tab-pane fade show active" id="critical-fail" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>姓名</th>
                                        <?php foreach ($examSubjects as $subject): ?>
                                            <th><?php echo $subject['subject_name']; ?></th>
                                        <?php endforeach; ?>
                                        <th>总分</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $criticalFailStudents = [];
                                    
                                    // 找出至少有一门课接近不及格的学生
                                    foreach ($students as $student) {
                                        $hasCritical = false;
                                        
                                        foreach ($examSubjects as $subject) {
                                            if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                                                $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                                                $fullScore = $subject['full_score'];
                                                $percentage = ($score / $fullScore) * 100;
                                                
                                                if ($percentage >= 55 && $percentage < 60) {
                                                    $hasCritical = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if ($hasCritical) {
                                            $criticalFailStudents[] = $student;
                                        }
                                    }
                                    
                                    if (count($criticalFailStudents) > 0) {
                                        foreach ($criticalFailStudents as $student) {
                                            echo "<tr>";
                                            echo "<td>{$student['name']}</td>";
                                            
                                            $totalScore = 0;
                                            
                                            foreach ($examSubjects as $subject) {
                                                echo "<td>";
                                                
                                                if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                                                    $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                                                    $fullScore = $subject['full_score'];
                                                    $percentage = ($score / $fullScore) * 100;
                                                    $totalScore += $score;
                                                    
                                                    $class = '';
                                                    if ($percentage < 60) {
                                                        $class = 'text-danger';
                                                    } elseif ($percentage >= 85) {
                                                        $class = 'text-success';
                                                    }
                                                    
                                                    if ($percentage >= 55 && $percentage < 60) {
                                                        $class .= ' font-weight-bold';
                                                    }
                                                    
                                                    echo "<span class='{$class}'>" . number_format($score, 1) . "</span>";
                                                } else {
                                                    echo "<span class='text-muted'>-</span>";
                                                }
                                                
                                                echo "</td>";
                                            }
                                            
                                            echo "<td>" . number_format($totalScore, 1) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='" . (count($examSubjects) + 2) . "' class='text-center'>没有不及格临界学生</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="critical-excellent" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>姓名</th>
                                        <?php foreach ($examSubjects as $subject): ?>
                                            <th><?php echo $subject['subject_name']; ?></th>
                                        <?php endforeach; ?>
                                        <th>总分</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $criticalExcellentStudents = [];
                                    
                                    // 找出至少有一门课接近优秀的学生
                                    foreach ($students as $student) {
                                        $hasCritical = false;
                                        
                                        foreach ($examSubjects as $subject) {
                                            if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                                                $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                                                $fullScore = $subject['full_score'];
                                                $percentage = ($score / $fullScore) * 100;
                                                
                                                if ($percentage >= 80 && $percentage < 85) {
                                                    $hasCritical = true;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if ($hasCritical) {
                                            $criticalExcellentStudents[] = $student;
                                        }
                                    }
                                    
                                    if (count($criticalExcellentStudents) > 0) {
                                        foreach ($criticalExcellentStudents as $student) {
                                            echo "<tr>";
                                            echo "<td>{$student['name']}</td>";
                                            
                                            $totalScore = 0;
                                            
                                            foreach ($examSubjects as $subject) {
                                                echo "<td>";
                                                
                                                if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                                                    $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                                                    $fullScore = $subject['full_score'];
                                                    $percentage = ($score / $fullScore) * 100;
                                                    $totalScore += $score;
                                                    
                                                    $class = '';
                                                    if ($percentage < 60) {
                                                        $class = 'text-danger';
                                                    } elseif ($percentage >= 85) {
                                                        $class = 'text-success';
                                                    }
                                                    
                                                    if ($percentage >= 80 && $percentage < 85) {
                                                        $class .= ' font-weight-bold';
                                                    }
                                                    
                                                    echo "<span class='{$class}'>" . number_format($score, 1) . "</span>";
                                                } else {
                                                    echo "<span class='text-muted'>-</span>";
                                                }
                                                
                                                echo "</td>";
                                            }
                                            
                                            echo "<td>" . number_format($totalScore, 1) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='" . (count($examSubjects) + 2) . "' class='text-center'>没有优秀临界学生</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 任课教师视图 -->
    <?php if ($viewType == 'subject' && $subjectInfo): ?>
        <!-- 所教班级本科目平均分对比图 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0"><?php echo $subjectInfo['name']; ?> - 成绩分析</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h4 class="h1 text-primary"><?php echo number_format($subjectStats[$selectedSubjectId]['avg_score'] ?? 0, 1); ?></h4>
                                <p class="text-muted">平均分</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="h1 text-success"><?php echo number_format($subjectStats[$selectedSubjectId]['max_score'] ?? 0, 1); ?></h4>
                                <p class="text-muted">最高分</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="h1 text-danger"><?php echo number_format($subjectStats[$selectedSubjectId]['min_score'] ?? 0, 1); ?></h4>
                                <p class="text-muted">最低分</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <?php 
                                $stats = $subjectStats[$selectedSubjectId] ?? [];
                                $total_count = $stats['total_count'] ?? 0;
                                $pass_count = $stats['pass_count'] ?? 0;
                                $excellent_count = $stats['excellent_count'] ?? 0;
                                $passRate = ($total_count > 0) ? round(($pass_count / $total_count) * 100, 1) : 0;
                                $excellentRate = ($total_count > 0) ? round(($excellent_count / $total_count) * 100, 1) : 0;
                                ?>
                                <h4 class="h1 text-warning"><?php echo $passRate; ?>%</h4>
                                <p class="text-muted">及格率</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 成绩分布和成绩段分布 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">成绩分布直方图</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="subjectScoreDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">分数段人数占比</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="subjectScoreRangeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 历次考试趋势和学生成绩箱线图 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">历次考试平均分/及格率趋势</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="subjectTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">班级成绩箱线图</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="boxPlotChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 学生成绩排名 -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="h5 mb-0">学生成绩排名表</h3>
                    <button class="btn btn-sm btn-success" onclick="exportToExcel('subjectRankTable')">
                        <i class="fas fa-file-excel"></i> 导出
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="subjectRankTable">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>学号</th>
                                <th>姓名</th>
                                <th>成绩</th>
                                <th>得分率</th>
                                <th>等级</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // 对学生成绩进行排序
                            $subjectScoreRanking = [];
                            
                            foreach ($students as $student) {
                                $studentId = $student['id'];
                                if (isset($studentScores[$studentId][$selectedSubjectId])) {
                                    $score = $studentScores[$studentId][$selectedSubjectId];
                                    $fullScore = 0;
                                    
                                    foreach ($examSubjects as $subject) {
                                        if ($subject['subject_id'] == $selectedSubjectId) {
                                            $fullScore = $subject['full_score'];
                                            break;
                                        }
                                    }
                                                    
                                    $percentage = ($fullScore > 0) ? ($score['score'] / $fullScore) * 100 : 0;
                                                    
                                    $subjectScoreRanking[] = [
                                        'student_id' => $studentId,
                                        'student_number' => $student['student_id'],
                                        'student_name' => $student['name'],
                                        'score' => $score['score'],
                                        'percentage' => $percentage,
                                        'full_score' => $fullScore
                                    ];
                                }
                            }
                            
                            // 按成绩降序排序
                            usort($subjectScoreRanking, function($a, $b) {
                                return $b['score'] - $a['score'];
                            });
                            
                            // 添加排名
                            $rank = 1;
                            $prevScore = null;
                            $prevRank = 1;
                            
                            foreach ($subjectScoreRanking as &$item) {
                                if ($prevScore !== null && $item['score'] < $prevScore) {
                                    $rank = $prevRank + 1;
                                }
                                
                                $item['rank'] = $rank;
                                $prevScore = $item['score'];
                                $prevRank = $rank;
                                $rank++;
                            }
                            
                            // 输出表格内容
                            foreach ($subjectScoreRanking as $rankItem) {
                                $percentage = $rankItem['percentage'];
                                $scoreClass = '';
                                $level = '';
                                
                                if ($percentage >= 85) {
                                    $scoreClass = 'text-success';
                                    $level = '<span class="badge badge-success">优秀</span>';
                                } elseif ($percentage >= 75) {
                                    $scoreClass = 'text-primary';
                                    $level = '<span class="badge badge-primary">良好</span>';
                                } elseif ($percentage >= 60) {
                                    $scoreClass = 'text-warning';
                                    $level = '<span class="badge badge-warning">及格</span>';
                                } else {
                                    $scoreClass = 'text-danger';
                                    $level = '<span class="badge badge-danger">不及格</span>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $rankItem['rank']; ?></td>
                                    <td><?php echo $rankItem['student_number']; ?></td>
                                    <td><?php echo $rankItem['student_name']; ?></td>
                                    <td class="<?php echo $scoreClass; ?>"><?php echo number_format($rankItem['score'], 1); ?></td>
                                    <td><?php echo number_format($rankItem['percentage'], 1); ?>%</td>
                                    <td><?php echo $level; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- 分数段统计和临界生分析 -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">分数段统计</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>分数段</th>
                                    <th>人数</th>
                                    <th>百分比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // 定义分数段
                                $scoreRanges = [
                                    ['min' => 90, 'max' => 100, 'label' => '优秀(90-100分)', 'class' => 'table-success'],
                                    ['min' => 80, 'max' => 89, 'label' => '良好(80-89分)', 'class' => 'table-primary'],
                                    ['min' => 70, 'max' => 79, 'label' => '中等(70-79分)', 'class' => 'table-info'],
                                    ['min' => 60, 'max' => 69, 'label' => '及格(60-69分)', 'class' => 'table-warning'],
                                    ['min' => 0, 'max' => 59, 'label' => '不及格(0-59分)', 'class' => 'table-danger']
                                ];
                                
                                $fullScore = 0;
                                foreach ($examSubjects as $subject) {
                                    if ($subject['subject_id'] == $selectedSubjectId) {
                                        $fullScore = $subject['full_score'];
                                        break;
                                    }
                                }
                                
                                $totalStudents = count($subjectScoreRanking);
                                
                                foreach ($scoreRanges as $range) {
                                    $minScore = ($range['min'] / 100) * $fullScore;
                                    $maxScore = ($range['max'] / 100) * $fullScore;
                                    
                                    $count = 0;
                                    foreach ($subjectScoreRanking as $item) {
                                        if ($item['score'] >= $minScore && $item['score'] <= $maxScore) {
                                            $count++;
                                        }
                                    }
                                                    
                                    $percentage = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;
                                ?>
                                    <tr class="<?php echo $range['class']; ?>">
                                        <td><?php echo $range['label']; ?></td>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">临界生分析</h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>不及格临界生（55-59分）</h6>
                            <ul class="list-group">
                                <?php
                                $criticalFailCount = 0;
                                $passLine = 0.6 * $fullScore;
                                $criticalLine = 0.55 * $fullScore;
                                
                                foreach ($subjectScoreRanking as $item) {
                                    if ($item['score'] >= $criticalLine && $item['score'] < $passLine) {
                                        $criticalFailCount++;
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo $item['student_name']; ?>
                                        <span class="badge badge-danger badge-pill">
                                            <?php echo number_format($item['score'], 1); ?>
                                        </span>
                                    </li>
                                <?php
                                    }
                                }
                                
                                if ($criticalFailCount === 0) {
                                    echo '<li class="list-group-item text-muted">无临界不及格学生</li>';
                                }
                                ?>
                            </ul>
                        </div>
                        
                        <div>
                            <h6>优秀临界生（80-84分）</h6>
                            <ul class="list-group">
                                <?php
                                $criticalExcellentCount = 0;
                                $excellentLine = 0.85 * $fullScore;
                                $criticalExcellentLine = 0.8 * $fullScore;
                                
                                foreach ($subjectScoreRanking as $item) {
                                    if ($item['score'] >= $criticalExcellentLine && $item['score'] < $excellentLine) {
                                        $criticalExcellentCount++;
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo $item['student_name']; ?>
                                        <span class="badge badge-warning badge-pill">
                                            <?php echo number_format($item['score'], 1); ?>
                                        </span>
                                    </li>
                                <?php
                                    }
                                }
                                
                                if ($criticalExcellentCount === 0) {
                                    echo '<li class="list-group-item text-muted">无临界优秀学生</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?> 

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($examInfo && $classInfo): ?>
        <?php if ($viewType == 'class'): ?>
            // 班主任视图图表
            
            // 班级平均分与年级平均分对比图
            const classGradeComparisonCtx = document.getElementById('classGradeComparisonChart');
            if (classGradeComparisonCtx) {
                const labels = <?php 
                    echo json_encode(array_map(function($subject) {
                        return $subject['subject_name'];
                    }, $examSubjects)); 
                ?>;
                
                const classAvgScores = <?php 
                    echo json_encode(array_map(function($subject) use ($subjectStats) {
                        return $subjectStats[$subject['subject_id']]['avg_score'] ?? 0;
                    }, $examSubjects)); 
                ?>;
                
                const gradeAvgScores = <?php 
                    echo json_encode(array_map(function($subject) use ($gradeAverages) {
                        return $gradeAverages[$subject['subject_id']] ?? 0;
                    }, $examSubjects)); 
                ?>;
                
                const fullScores = <?php 
                    echo json_encode(array_map(function($subject) {
                        return $subject['full_score'];
                    }, $examSubjects)); 
                ?>;
                
                new Chart(classGradeComparisonCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: '本班平均分',
                                data: classAvgScores,
                                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: '年级平均分',
                                data: gradeAvgScores,
                                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: '分数'
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: '本班各科平均分与年级平均分对比'
                            }
                        }
                    }
                });
            }
            
            // 总分成绩分布直方图
            const scoreDistributionCtx = document.getElementById('scoreDistributionChart');
            if (scoreDistributionCtx && <?php echo count($totalScores) > 0 ? 'true' : 'false'; ?>) {
                const scores = <?php 
                    echo json_encode(array_column($totalScores, 'total_score')); 
                ?>;
                
                createScoreDistributionChart('scoreDistributionChart', scores);
            }
            
            // 学生雷达图
            const studentRadarCtx = document.getElementById('studentRadarChart');
            if (studentRadarCtx) {
                // 准备学生成绩数据
                const studentScores = {};
                <?php foreach ($students as $student): ?>
                    studentScores[<?php echo $student['id']; ?>] = {
                        scores: {
                            <?php 
                            $scoreItems = [];
                            if (isset($studentScores[$student['id']])) {
                                foreach ($examSubjects as $subject) {
                                    if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                                        $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                                        $fullScore = $subject['full_score'];
                                        $percentage = ($score / $fullScore) * 100;
                                        $scoreItems[] = $subject['subject_id'] . ': ' . $percentage;
                                    } else {
                                        $scoreItems[] = $subject['subject_id'] . ': 0';
                                    }
                                }
                            }
                            echo implode(', ', $scoreItems);
                            ?>
                        },
                        name: "<?php echo $student['name']; ?>"
                    };
                <?php endforeach; ?>
                
                // 初始化空雷达图
                const radarChart = new Chart(studentRadarCtx, {
                    type: 'radar',
                    data: {
                        labels: <?php 
                            echo json_encode(array_map(function($subject) {
                                return $subject['subject_name'];
                            }, $examSubjects)); 
                        ?>,
                        datasets: [{
                            label: '请选择学生',
                            data: Array(<?php echo count($examSubjects); ?>).fill(0),
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(54, 162, 235, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            r: {
                                angleLines: {
                                    display: true
                                },
                                suggestedMin: 0,
                                suggestedMax: 100
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: '学生各科成绩雷达图（百分比）'
                            }
                        }
                    }
                });
                
                // 存储雷达图引用，用于更新
                window.radarChart = radarChart;
                window.studentScores = studentScores;
            }
            
            // 各科及格率和优秀率对比图
            const passRateCtx = document.getElementById('passRateChart');
            if (passRateCtx) {
                const labels = <?php 
                    echo json_encode(array_map(function($subject) {
                        return $subject['subject_name'];
                    }, $examSubjects)); 
                ?>;
                
                const passRates = <?php 
                    echo json_encode(array_map(function($subject) use ($subjectStats) {
                        $stats = $subjectStats[$subject['subject_id']] ?? [];
                        return ($stats['total_count'] > 0) ? 
                            round(($stats['pass_count'] / $stats['total_count']) * 100, 1) : 0;
                    }, $examSubjects)); 
                ?>;
                
                const excellentRates = <?php 
                    echo json_encode(array_map(function($subject) use ($subjectStats) {
                        $stats = $subjectStats[$subject['subject_id']] ?? [];
                        return ($stats['total_count'] > 0) ? 
                            round(($stats['excellent_count'] / $stats['total_count']) * 100, 1) : 0;
                    }, $examSubjects)); 
                ?>;
                
                createPassRateChart('passRateChart', labels, passRates, excellentRates);
            }
            
            // 历次考试平均分趋势图
            const scoreTrendCtx = document.getElementById('scoreTrendChart');
            if (scoreTrendCtx && <?php echo count($trendData) > 0 ? 'true' : 'false'; ?>) {
                const trendData = <?php echo json_encode($trendData); ?>;
                createScoreTrendChart('scoreTrendChart', trendData);
            }
        <?php else: ?>
            // 任课教师视图图表
            
            // 成绩分布直方图
            const subjectScoreDistributionCtx = document.getElementById('subjectScoreDistributionChart');
            if (subjectScoreDistributionCtx) {
                const scores = <?php 
                    echo json_encode(
                        array_map(function($item) {
                            return $item['score'] ?? 0;
                        }, isset($subjectScoreRanking) ? $subjectScoreRanking : []
                    )); 
                ?>;
                
                createScoreDistributionChart('subjectScoreDistributionChart', scores);
            }
            
            // 分数段人数占比饼图
            const subjectScoreRangeCtx = document.getElementById('subjectScoreRangeChart');
            if (subjectScoreRangeCtx) {
                const scores = <?php 
                    echo json_encode(
                        array_map(function($item) {
                            return $item['score'] ?? 0;
                        }, isset($subjectScoreRanking) ? $subjectScoreRanking : []
                    )); 
                ?>;
                
                const fullScore = <?php 
                    foreach ($examSubjects as $subject) {
                        if ($subject['subject_id'] == $selectedSubjectId) {
                            echo $subject['full_score'];
                            break;
                        }
                    }
                ?>;
                
                createScoreRangeChart('subjectScoreRangeChart', scores, fullScore);
            }
            
            // 历次考试趋势图
            const subjectTrendCtx = document.getElementById('subjectTrendChart');
            if (subjectTrendCtx && <?php echo count($trendData) > 0 ? 'true' : 'false'; ?>) {
                const trendData = <?php echo json_encode($trendData); ?>;
                createSubjectTrendChart('subjectTrendChart', trendData, <?php echo $selectedSubjectId; ?>);
            }
            
            // 箱线图
            const boxPlotCtx = document.getElementById('boxPlotChart');
            if (boxPlotCtx) {
                const scores = <?php 
                    echo json_encode(
                        array_map(function($item) {
                            return $item['score'] ?? 0;
                        }, isset($subjectScoreRanking) ? $subjectScoreRanking : []
                    )); 
                ?>;
                
                createBoxPlotChart('boxPlotChart', scores);
            }
        <?php endif; ?>
    <?php endif; ?>
});

// 更新学生雷达图
function updateStudentRadarChart() {
    const selector = document.getElementById('studentRadarSelector');
    if (!selector || !window.radarChart || !window.studentScores) return;
    
    const studentId = selector.value;
    
    if (!studentId) {
        // 重置雷达图
        window.radarChart.data.datasets[0].label = '请选择学生';
        window.radarChart.data.datasets[0].data = Array(window.radarChart.data.labels.length).fill(0);
        window.radarChart.update();
        return;
    }
    
    const studentData = window.studentScores[studentId];
    if (!studentData) return;
    
    // 获取所有科目的得分率
    const data = [];
    <?php foreach ($examSubjects as $subject): ?>
        data.push(studentData.scores[<?php echo $subject['subject_id']; ?>] || 0);
    <?php endforeach; ?>
    
    // 更新雷达图
    window.radarChart.data.datasets[0].label = studentData.name;
    window.radarChart.data.datasets[0].data = data;
    window.radarChart.update();
}

// 创建成绩分布直方图
function createScoreDistributionChart(canvasId, scores) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 确保scores是一个数组并且包含数字
    if (!Array.isArray(scores) || scores.length === 0) {
        console.error('scores必须是一个非空数组');
        return;
    }
    
    // 确保所有分数都是数字
    const numericScores = scores.map(score => parseFloat(score)).filter(score => !isNaN(score));
    
    if (numericScores.length === 0) {
        console.error('scores数组中没有有效的数字');
        return;
    }
    
    // 计算成绩分布
    const min = Math.min.apply(null, numericScores);
    const max = Math.max.apply(null, numericScores);
    const binSize = Math.ceil((max - min) / 8); // 动态计算bin大小，确保8-10个区间
    const bins = {};
    
    // 初始化区间
    for (let i = Math.floor(min / binSize) * binSize; i <= Math.ceil(max / binSize) * binSize; i += binSize) {
        bins[i] = 0;
    }
    
    // 统计各区间人数
    numericScores.forEach(score => {
        const binIndex = Math.floor(score / binSize) * binSize;
        bins[binIndex] = (bins[binIndex] || 0) + 1;
    });
    
    // 转换为数组格式
    const labels = Object.keys(bins).map(key => {
        const start = parseInt(key);
        const end = start + binSize - 1;
        return `${start}-${end}`;
    });
    
    const data = Object.values(bins);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '人数',
                    data: data,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '人数'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '分数区间'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '成绩分布直方图'
                }
            }
        }
    });
}

// 创建及格率/优秀率对比图
function createPassRateChart(canvasId, labels, passRates, excellentRates) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '及格率',
                    data: passRates,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: '优秀率',
                    data: excellentRates,
                    backgroundColor: 'rgba(255, 193, 7, 0.7)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: '百分比(%)'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '各科目及格率/优秀率对比'
                }
            }
        }
    });
}

// 创建历次考试平均分趋势图
function createScoreTrendChart(canvasId, trendData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    const labels = trendData.map(item => item.name);
    
    const datasets = [];
    const subjectIds = new Set();
    
    // 收集所有科目ID
    trendData.forEach(exam => {
        Object.keys(exam.subjects).forEach(subjectId => {
            subjectIds.add(subjectId);
        });
    });
    
    // 为每个科目创建数据集
    Array.from(subjectIds).forEach(subjectId => {
        const subjectName = trendData[0].subjects[subjectId]?.name || `科目${subjectId}`;
        
        const data = trendData.map(exam => {
            return exam.subjects[subjectId]?.avg_score || null;
        });
        
        const colorIndex = parseInt(subjectId) % colorPalette.length;
        
        datasets.push({
            label: subjectName,
            data: data,
            borderColor: colorPalette[colorIndex],
            backgroundColor: colorPalette[colorIndex].replace('1)', '0.1)'),
            fill: false,
            tension: 0.3
        });
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '平均分'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '历次考试各科平均分趋势'
                }
            }
        }
    });
}

// 创建分数段饼图
function createScoreRangeChart(canvasId, scores, fullScore) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 定义分数段
    const ranges = [
        { label: '优秀(≥90%)', min: 90, color: 'rgba(40, 167, 69, 0.7)' },
        { label: '良好(80-89%)', min: 80, max: 89, color: 'rgba(0, 123, 255, 0.7)' },
        { label: '中等(70-79%)', min: 70, max: 79, color: 'rgba(255, 193, 7, 0.7)' },
        { label: '及格(60-69%)', min: 60, max: 69, color: 'rgba(255, 136, 0, 0.7)' },
        { label: '不及格(<60%)', max: 59, color: 'rgba(220, 53, 69, 0.7)' }
    ];
    
    // 统计各分数段人数
    const data = ranges.map(range => {
        return scores.filter(score => {
            const percent = (score / fullScore) * 100;
            if (range.min && range.max) {
                return percent >= range.min && percent <= range.max;
            } else if (range.min) {
                return percent >= range.min;
            } else if (range.max) {
                return percent <= range.max;
            }
            return false;
        }).length;
    });
    
    // 创建饼图
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ranges.map(r => r.label),
            datasets: [
                {
                    data: data,
                    backgroundColor: ranges.map(r => r.color),
                    borderColor: 'white',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: '分数段人数分布'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value}人 (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// 创建单科趋势图
function createSubjectTrendChart(canvasId, trendData, subjectId) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    const labels = trendData.map(item => item.name);
    const avgScores = trendData.map(item => {
        return item.subjects[subjectId]?.avg_score || null;
    });
    
    // 计算及格率
    const passRates = trendData.map(item => {
        const subjectData = item.subjects[subjectId];
        if (!subjectData) return null;
        
        // 假设这些数据在后端计算好了
        // 实际情况下，可能需要额外的API调用获取这些数据
        return Math.random() * 30 + 60; // 模拟数据
    });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '平均分',
                    data: avgScores,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    yAxisID: 'y',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: '及格率(%)',
                    data: passRates,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0)',
                    yAxisID: 'y1',
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '平均分'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: '及格率(%)'
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '历次考试平均分及及格率趋势'
                }
            }
        }
    });
}

// 创建箱线图（BoxPlot）
function createBoxPlotChart(canvasId, scores) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 计算箱线图数据
    scores.sort((a, b) => a - b);
    
    const min = scores[0];
    const max = scores[scores.length - 1];
    const q1Index = Math.floor(scores.length * 0.25);
    const q2Index = Math.floor(scores.length * 0.5);
    const q3Index = Math.floor(scores.length * 0.75);
    
    const q1 = scores[q1Index];
    const median = scores[q2Index];
    const q3 = scores[q3Index];
    
    const iqr = q3 - q1;
    const lowerWhisker = Math.max(min, q1 - 1.5 * iqr);
    const upperWhisker = Math.min(max, q3 + 1.5 * iqr);
    
    // 找出离群点
    const outliers = scores.filter(score => score < lowerWhisker || score > upperWhisker);
    
    // 因为Chart.js没有内置的箱线图类型，我们使用条形图模拟
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['班级成绩'],
            datasets: [
                // 最小值到Q1的线段
                {
                    label: '最小值到Q1',
                    data: [q1 - lowerWhisker],
                    backgroundColor: 'rgba(0, 0, 0, 0)',
                    borderColor: 'rgba(0, 0, 0, 1)',
                    borderWidth: 2,
                    barPercentage: 0.1,
                    base: lowerWhisker
                },
                // Q1到中位数的矩形
                {
                    label: 'Q1到中位数',
                    data: [median - q1],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(0, 0, 0, 1)',
                    borderWidth: 2,
                    barPercentage: 0.5,
                    base: q1
                },
                // 中位数到Q3的矩形
                {
                    label: '中位数到Q3',
                    data: [q3 - median],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(0, 0, 0, 1)',
                    borderWidth: 2,
                    barPercentage: 0.5,
                    base: median
                },
                // Q3到最大值的线段
                {
                    label: 'Q3到最大值',
                    data: [upperWhisker - q3],
                    backgroundColor: 'rgba(0, 0, 0, 0)',
                    borderColor: 'rgba(0, 0, 0, 1)',
                    borderWidth: 2,
                    barPercentage: 0.1,
                    base: q3
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: '分数'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: '班级成绩箱线图'
                },
                tooltip: {
                    callbacks: {
                        footer: function() {
                            return [
                                `最小值: ${min.toFixed(1)}`,
                                `Q1: ${q1.toFixed(1)}`,
                                `中位数: ${median.toFixed(1)}`,
                                `Q3: ${q3.toFixed(1)}`,
                                `最大值: ${max.toFixed(1)}`
                            ];
                        }
                    }
                },
                legend: {
                    display: false
                }
            }
        }
    });
    
    // 在图表下方添加统计信息
    const infoDiv = document.createElement('div');
    infoDiv.className = 'mt-3 small';
    infoDiv.innerHTML = `
        <strong>箱线图统计信息：</strong>
        <ul class="list-unstyled">
            <li>最小值: ${min.toFixed(1)}</li>
            <li>第一四分位数(Q1): ${q1.toFixed(1)}</li>
            <li>中位数: ${median.toFixed(1)}</li>
            <li>第三四分位数(Q3): ${q3.toFixed(1)}</li>
            <li>最大值: ${max.toFixed(1)}</li>
            <li>四分位距(IQR): ${iqr.toFixed(1)}</li>
            <li>离群点数量: ${outliers.length}</li>
        </ul>
    `;
    
    ctx.parentNode.appendChild(infoDiv);
}

// 导出Excel功能
function exportToExcel(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    // 准备Excel数据
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // 获取文本内容，去除HTML标签
            let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // 下载文件
    const csvString = csv.join('\n');
    const filename = tableId + '_' + new Date().toISOString().slice(0, 10) + '.csv';
    const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 图表颜色调色板
const colorPalette = [
    'rgba(54, 162, 235, 1)',
    'rgba(255, 99, 132, 1)',
    'rgba(255, 206, 86, 1)',
    'rgba(75, 192, 192, 1)',
    'rgba(153, 102, 255, 1)',
    'rgba(255, 159, 64, 1)',
    'rgba(76, 175, 80, 1)',
    'rgba(233, 30, 99, 1)',
    'rgba(0, 188, 212, 1)',
    'rgba(121, 85, 72, 1)'
];
</script>

<?php
// 包含页脚
include '../components/teacher_footer.php';
?> 