<?php
/**
 * 成绩分析系统 - 科目成绩页面
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

// 默认选择第一个科目-班级组合
$selectedSubjectClassId = isset($_GET['subject_class_id']) ? $_GET['subject_class_id'] : '';

if (empty($selectedSubjectClassId) && count($teacherSubjects) > 0) {
    // 确保teacherSubjects[0]中有class_id
    $selectedSubjectClassId = $teacherSubjects[0]['id'] . '-' . (isset($teacherSubjects[0]['class_id']) ? $teacherSubjects[0]['class_id'] : 0);
}

$selectedSubjectId = 0;
$selectedClassId = 0;

if (!empty($selectedSubjectClassId)) {
    $parts = explode('-', $selectedSubjectClassId);
    if (count($parts) == 2) {
        $selectedSubjectId = (int)$parts[0];
        $selectedClassId = (int)$parts[1];
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

// 获取考试列表（默认最近5次）
$selectedExamId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$exams = [];

if ($classInfo) {
    $exams = fetchAll(
        "SELECT * FROM exams 
         ORDER BY exam_date DESC 
         LIMIT 5"
    );
    
    // 如果没有指定考试，默认选择最新的一次
    if (!$selectedExamId && count($exams) > 0) {
        $selectedExamId = $exams[0]['id'];
    }
}

// 获取选定考试的详细信息
$examInfo = null;
if ($selectedExamId) {
    $examInfo = fetchOne("SELECT * FROM exams WHERE id = ?", [$selectedExamId]);
}

// 获取考试科目设置
$examSubjectInfo = null;
if ($examInfo && $selectedSubjectId) {
    $examSubjectInfo = fetchOne(
        "SELECT * FROM exam_subjects 
         WHERE exam_id = ? AND subject_id = ?", 
        [$selectedExamId, $selectedSubjectId]
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
if ($examInfo && $selectedSubjectId && count($students) > 0) {
    foreach ($students as $student) {
        $score = fetchOne(
            "SELECT * FROM scores 
             WHERE exam_id = ? AND student_id = ? AND subject_id = ?", 
            [$selectedExamId, $student['id'], $selectedSubjectId]
        );
        
        if ($score) {
            $studentScores[$student['id']] = $score;
        }
    }
}

// 计算科目统计信息
$subjectStats = null;
if ($examInfo && $selectedSubjectId && $examSubjectInfo) {
    $subjectStats = fetchOne(
        "SELECT 
            AVG(score) as avg_score,
            MAX(score) as max_score,
            MIN(score) as min_score,
            COUNT(CASE WHEN score >= ? * 0.6 THEN 1 END) as pass_count,
            COUNT(CASE WHEN score >= ? * 0.85 THEN 1 END) as excellent_count,
            COUNT(score) as total_count
         FROM scores 
         WHERE exam_id = ? AND subject_id = ?", 
        [$examSubjectInfo['full_score'], $examSubjectInfo['full_score'], $selectedExamId, $selectedSubjectId]
    );
}

// 计算排名
$ranks = [];
if (count($studentScores) > 0) {
    // 获取所有学生该科目的成绩
    $scores = fetchAll(
        "SELECT student_id, score 
         FROM scores 
         WHERE exam_id = ? AND subject_id = ? 
         ORDER BY score DESC", 
        [$selectedExamId, $selectedSubjectId]
    );
    
    $rank = 1;
    $prevScore = null;
    $prevRank = 1;
    
    foreach ($scores as $score) {
        if ($prevScore !== null && $score['score'] < $prevScore) {
            $rank = $prevRank + 1;
        }
        
        $ranks[$score['student_id']] = $rank;
        $prevScore = $score['score'];
        $prevRank = $rank;
        $rank++;
    }
}

// 计算历次考试的趋势数据
$trendData = [];
if ($classInfo && $selectedSubjectId) {
    $pastExams = fetchAll(
        "SELECT e.id, e.name, e.exam_date 
         FROM exams e
         JOIN exam_subjects es ON e.id = es.exam_id
         WHERE es.subject_id = ?
         ORDER BY e.exam_date ASC
         LIMIT 5", 
        [$selectedSubjectId]
    );
    
    foreach ($pastExams as $exam) {
        $stats = fetchOne(
            "SELECT 
                AVG(score) as avg_score,
                COUNT(CASE WHEN score >= es.full_score * 0.6 THEN 1 END) as pass_count,
                COUNT(score) as total_count
             FROM scores s
             JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id
             WHERE s.exam_id = ? AND s.subject_id = ?", 
            [$exam['id'], $selectedSubjectId]
        );
        
        $passRate = ($stats['total_count'] > 0) ? 
            round(($stats['pass_count'] / $stats['total_count']) * 100, 1) : 0;
        
        $trendData[] = [
            'exam_id' => $exam['id'],
            'name' => $exam['name'],
            'date' => formatDate($exam['exam_date']),
            'avg_score' => $stats['avg_score'],
            'pass_rate' => $passRate
        ];
    }
}

// 页面标题
$pageTitle = '科目成绩';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">科目成绩</h1>
            
            <?php if (count($teacherSubjects) === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 您目前没有任教科目，无法查看科目成绩。
                </div>
            <?php else: ?>
                <!-- 科目班级和考试选择 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject_class_id">选择科目和班级</label>
                                    <select class="form-control" id="subject_class_id" name="subject_class_id" onchange="this.form.submit()">
                                        <?php foreach ($teacherSubjects as $subject): ?>
                                            <?php $value = $subject['id'] . '-' . $subject['class_id']; ?>
                                            <option value="<?php echo $value; ?>" <?php echo $selectedSubjectClassId == $value ? 'selected' : ''; ?>>
                                                <?php echo $subject['name'] . ' (' . $subject['grade'] . $subject['class_name'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                
                <?php if ($examInfo && $subjectInfo && $examSubjectInfo): ?>
                    <!-- 成绩统计卡片 -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h3 class="h5 mb-0">
                                        <?php echo $subjectInfo['name']; ?> - <?php echo $classInfo['grade']; ?>年级<?php echo $classInfo['name']; ?> - <?php echo $examInfo['name']; ?>
                                        <span class="badge badge-primary float-right">满分: <?php echo $examSubjectInfo['full_score']; ?></span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <div class="border-right h-100">
                                                <h4 class="h2 text-primary"><?php echo number_format($subjectStats['avg_score'] ?? 0, 1); ?></h4>
                                                <p class="text-muted">平均分</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="border-right h-100">
                                                <h4 class="h2 text-success"><?php echo number_format($subjectStats['max_score'] ?? 0, 1); ?></h4>
                                                <p class="text-muted">最高分</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="border-right h-100">
                                                <h4 class="h2 text-danger"><?php echo number_format($subjectStats['min_score'] ?? 0, 1); ?></h4>
                                                <p class="text-muted">最低分</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="h-100">
                                                <?php 
                                                $passRate = ($subjectStats['total_count'] > 0) ? 
                                                    round(($subjectStats['pass_count'] / $subjectStats['total_count']) * 100, 1) : 0;
                                                
                                                $excellentRate = ($subjectStats['total_count'] > 0) ? 
                                                    round(($subjectStats['excellent_count'] / $subjectStats['total_count']) * 100, 1) : 0;
                                                ?>
                                                <h4 class="h2 text-warning"><?php echo $passRate; ?>%</h4>
                                                <p class="text-muted">及格率</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 成绩分布图表 -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">成绩分布</h3>
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
                                    <h3 class="h5 mb-0">分数段分布</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="scoreRangeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 历次考试趋势 -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">历次考试趋势</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="examTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 学生成绩表格 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h2 class="h5 mb-0">学生成绩列表</h2>
                                <button class="btn btn-sm btn-success" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> 导出Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="scoresTable">
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
                                        <?php if (count($students) > 0): ?>
                                            <?php foreach ($students as $student): ?>
                                                <?php 
                                                $studentId = $student['id'];
                                                $hasScore = isset($studentScores[$studentId]);
                                                $score = $hasScore ? $studentScores[$studentId]['score'] : null;
                                                $scoreRate = $hasScore ? ($score / $examSubjectInfo['full_score']) * 100 : 0;
                                                $rank = $hasScore ? ($ranks[$studentId] ?? '-') : '-';
                                                
                                                // 确定等级
                                                $level = '-';
                                                $levelClass = '';
                                                
                                                if ($hasScore) {
                                                    if ($scoreRate >= 85) {
                                                        $level = '优秀';
                                                        $levelClass = 'text-success';
                                                    } elseif ($scoreRate >= 75) {
                                                        $level = '良好';
                                                        $levelClass = 'text-primary';
                                                    } elseif ($scoreRate >= 60) {
                                                        $level = '及格';
                                                        $levelClass = 'text-warning';
                                                    } else {
                                                        $level = '不及格';
                                                        $levelClass = 'text-danger';
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo $rank; ?></td>
                                                    <td><?php echo $student['student_id']; ?></td>
                                                    <td><?php echo $student['name']; ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($hasScore) {
                                                            $colorClass = '';
                                                            if ($scoreRate < 60) {
                                                                $colorClass = 'text-danger';
                                                            } elseif ($scoreRate >= 85) {
                                                                $colorClass = 'text-success';
                                                            }
                                                            echo "<span class='{$colorClass}'>" . number_format($score, 1) . "</span>";
                                                        } else {
                                                            echo "<span class='text-muted'>-</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($hasScore) {
                                                            echo number_format($scoreRate, 1) . "%";
                                                        } else {
                                                            echo "<span class='text-muted'>-</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($hasScore) {
                                                            echo "<span class='{$levelClass}'>{$level}</span>";
                                                        } else {
                                                            echo "<span class='text-muted'>-</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">暂无学生数据</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 成绩分析 -->
                    <div class="row mb-4">
                        <!-- 成绩区间分布 -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">成绩区间分析</h3>
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
                                                ['min' => 0, 'max' => 59, 'label' => '不及格(0-59分)'],
                                                ['min' => 60, 'max' => 69, 'label' => '及格(60-69分)'],
                                                ['min' => 70, 'max' => 79, 'label' => '中等(70-79分)'],
                                                ['min' => 80, 'max' => 89, 'label' => '良好(80-89分)'],
                                                ['min' => 90, 'max' => 100, 'label' => '优秀(90-100分)']
                                            ];
                                            
                                            $fullScore = $examSubjectInfo['full_score'];
                                            $totalStudents = count($studentScores);
                                            
                                            foreach ($scoreRanges as $range) {
                                                $minScore = ($range['min'] / 100) * $fullScore;
                                                $maxScore = ($range['max'] / 100) * $fullScore;
                                                
                                                $count = 0;
                                                foreach ($studentScores as $score) {
                                                    if ($score['score'] >= $minScore && $score['score'] <= $maxScore) {
                                                        $count++;
                                                    }
                                                }
                                                
                                                $percentage = $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0;
                                                
                                                $rowClass = '';
                                                if ($range['min'] < 60) {
                                                    $rowClass = 'table-danger';
                                                } elseif ($range['min'] >= 90) {
                                                    $rowClass = 'table-success';
                                                }
                                            ?>
                                                <tr class="<?php echo $rowClass; ?>">
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
                        
                        <!-- 临界生统计 -->
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
                                            $passLine = 0.6 * $examSubjectInfo['full_score'];
                                            $criticalLine = 0.55 * $examSubjectInfo['full_score'];
                                            
                                            foreach ($studentScores as $studentId => $score) {
                                                if ($score['score'] >= $criticalLine && $score['score'] < $passLine) {
                                                    $student = array_filter($students, function($s) use ($studentId) {
                                                        return $s['id'] == $studentId;
                                                    });
                                                    
                                                    $student = reset($student);
                                                    $criticalFailCount++;
                                            ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo $student['name']; ?>
                                                    <span class="badge badge-danger badge-pill">
                                                        <?php echo number_format($score['score'], 1); ?>
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
                                            $excellentLine = 0.85 * $examSubjectInfo['full_score'];
                                            $criticalExcellentLine = 0.8 * $examSubjectInfo['full_score'];
                                            
                                            foreach ($studentScores as $studentId => $score) {
                                                if ($score['score'] >= $criticalExcellentLine && $score['score'] < $excellentLine) {
                                                    $student = array_filter($students, function($s) use ($studentId) {
                                                        return $s['id'] == $studentId;
                                                    });
                                                    
                                                    $student = reset($student);
                                                    $criticalExcellentCount++;
                                            ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo $student['name']; ?>
                                                    <span class="badge badge-warning badge-pill">
                                                        <?php echo number_format($score['score'], 1); ?>
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
                <?php elseif ($classInfo): ?>
                    <div class="alert alert-info">该班级暂无考试数据，请先创建考试并录入成绩。</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($examInfo && $subjectInfo && $examSubjectInfo && count($studentScores) > 0): ?>
        // 准备成绩分布图表
        const scores = <?php 
            echo json_encode(array_map(function($score) {
                return floatval($score['score']);
            }, $studentScores)); 
        ?>;
        
        // 成绩分布直方图
        createScoreDistributionChart('scoreDistributionChart', scores, <?php echo $examSubjectInfo['full_score'] / 20; ?>);
        
        // 分数段饼图
        createScoreRangeChart('scoreRangeChart', scores, <?php echo $examSubjectInfo['full_score']; ?>);
        
        <?php if (count($trendData) > 0): ?>
        // 历次考试趋势图
        createExamTrendChart('examTrendChart', <?php echo json_encode($trendData); ?>);
        <?php endif; ?>
    <?php endif; ?>
});

// 创建成绩分布直方图
function createScoreDistributionChart(canvasId, scores, fullScore) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 计算成绩分布
    const min = Math.min.apply(null, scores);
    const max = Math.max.apply(null, scores);
    const range = max - min;
    
    // 根据满分值和数据范围确定合适的bin大小
    let binSize;
    if (fullScore <= 100) {
        binSize = 5; // 5分一档
    } else if (fullScore <= 150) {
        binSize = 10; // 10分一档
    } else {
        binSize = 20; // 20分一档
    }
    
    const bins = {};
    
    // 初始化区间
    for (let i = 0; i <= fullScore; i += binSize) {
        bins[i] = 0;
    }
    
    // 统计各区间人数
    scores.forEach(score => {
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

// 创建历次考试趋势图
function createExamTrendChart(canvasId, trendData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    const labels = trendData.map(item => item.name);
    const avgScores = trendData.map(item => item.avg_score);
    const passRates = trendData.map(item => item.pass_rate);
    
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

// 导出Excel功能
function exportToExcel() {
    const table = document.getElementById('scoresTable');
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
    const subjectName = <?php echo $subjectInfo ? ("'" . $subjectInfo['name'] . "'") : "''"; ?>;
    const className = <?php echo $classInfo ? ("'" . $classInfo['grade'] . $classInfo['name'] . "'") : "''"; ?>;
    const examName = <?php echo $examInfo ? ("'" . $examInfo['name'] . "'") : "''"; ?>;
    const filename = '科目成绩_' + subjectName + '_' + className + '_' + examName + '.csv';
    const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
// 包含页脚
include '../components/teacher_footer.php';
?> 