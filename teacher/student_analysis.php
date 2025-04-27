<?php
/**
 * 成绩分析系统 - 学生分析页面
 * 展示单个学生的历史考试数据分析，包括：
 * - 单科成绩变化趋势
 * - 单科排名变化趋势
 * - 总分变化趋势
 * - 总分排名变化趋势
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

// 获取班级和学生参数
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selectedSubjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// 获取教师相关的班级列表
$classes = [];
// 移除权限限制，所有教师都可以看到所有班级
$classes = fetchAll(
    "SELECT * FROM classes 
     ORDER BY grade ASC, name ASC"
);

// 如果没有选择班级但有班级列表，默认选择第一个
if ($selectedClassId == 0 && count($classes) > 0) {
    $selectedClassId = $classes[0]['id'];
}

// 获取班级学生列表
$students = [];
if ($selectedClassId) {
    $students = fetchAll(
        "SELECT * FROM students 
         WHERE class_id = ? 
         ORDER BY name ASC", 
        [$selectedClassId]
    );
    
    // 如果没有选择学生但有学生列表，默认选择第一个
    if ($selectedStudentId == 0 && count($students) > 0) {
        $selectedStudentId = $students[0]['id'];
    }
}

// 获取教师可教授的科目列表
$subjects = [];
if ($selectedClassId) {
    // 修改查询方式，不再通过exams表的class_id过滤，因为考试面向所有班级
    $subjects = fetchAll(
        "SELECT DISTINCT s.* 
         FROM subjects s
         JOIN exam_subjects es ON s.id = es.subject_id
         ORDER BY s.name ASC"
    );
    
    // 如果没有选择科目但有科目列表，默认选择第一个
    if ($selectedSubjectId == 0 && count($subjects) > 0) {
        $selectedSubjectId = $subjects[0]['id'];
    }
}

// 获取学生信息
$studentInfo = null;
if ($selectedStudentId) {
    $studentInfo = fetchOne(
        "SELECT * FROM students WHERE id = ?", 
        [$selectedStudentId]
    );
}

// 获取班级信息
$classInfo = null;
if ($selectedClassId) {
    $classInfo = fetchOne(
        "SELECT * FROM classes WHERE id = ?", 
        [$selectedClassId]
    );
}

// 获取学生历次考试信息（最近10次考试）
$examData = [];
if ($selectedStudentId && $selectedClassId) {
    // 获取考试基本信息
    $exams = fetchAll(
        "SELECT DISTINCT e.id, e.name, e.exam_date 
         FROM exams e 
         JOIN scores s ON e.id = s.exam_id 
         WHERE s.student_id = ? 
         ORDER BY e.exam_date ASC 
         LIMIT 10", 
        [$selectedStudentId]
    );
    
    foreach ($exams as $exam) {
        $examId = $exam['id'];
        $examData[$examId] = [
            'id' => $examId,
            'name' => $exam['name'],
            'date' => formatDate($exam['exam_date']),
            'subjects' => [],
            'total_score' => 0,
            'total_full_score' => 0,
            'total_rank' => 0,
            'total_students' => 0
        ];
        
        // 获取考试科目成绩
        $subjectScores = fetchAll(
            "SELECT s.subject_id, s.score, sub.name as subject_name, es.full_score 
             FROM scores s 
             JOIN subjects sub ON s.subject_id = sub.id 
             JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id 
             WHERE s.exam_id = ? AND s.student_id = ?", 
            [$examId, $selectedStudentId]
        );
        
        $totalScore = 0;
        $totalFullScore = 0;
        
        foreach ($subjectScores as $score) {
            $subjectId = $score['subject_id'];
            $scoreValue = $score['score'] ?? 0;
            $fullScore = $score['full_score'] ?? 100;
            
            // 计算学科排名
            $subjectRank = fetchOne(
                "SELECT COUNT(*) + 1 as rank 
                 FROM scores 
                 WHERE exam_id = ? AND subject_id = ? AND score > ? AND score IS NOT NULL", 
                [$examId, $subjectId, $scoreValue]
            );
            
            // 获取参加同一考试同一科目的学生总数
            $totalSubjectStudents = fetchOne(
                "SELECT COUNT(*) as count 
                 FROM scores 
                 WHERE exam_id = ? AND subject_id = ? AND score IS NOT NULL", 
                [$examId, $subjectId]
            );
            
            $examData[$examId]['subjects'][$subjectId] = [
                'id' => $subjectId,
                'name' => $score['subject_name'],
                'score' => $scoreValue,
                'full_score' => $fullScore,
                'rank' => $subjectRank['rank'] ?? 0,
                'total_students' => $totalSubjectStudents['count'] ?? 0
            ];
            
            $totalScore += $scoreValue;
            $totalFullScore += $fullScore;
        }
        
        $examData[$examId]['total_score'] = $totalScore;
        $examData[$examId]['total_full_score'] = $totalFullScore;
        
        // 计算总分排名
        if ($totalScore > 0) {
            $totalRank = fetchOne(
                "SELECT COUNT(*) + 1 as rank FROM (
                    SELECT student_id, SUM(score) as total 
                    FROM scores 
                    WHERE exam_id = ? AND class_id = ? AND score IS NOT NULL
                    GROUP BY student_id 
                    HAVING SUM(score) > ?
                ) as ranks", 
                [$examId, $selectedClassId, $totalScore]
            );
            
            // 获取参加同一考试的学生总数
            $totalExamStudents = fetchOne(
                "SELECT COUNT(DISTINCT student_id) as count 
                 FROM scores 
                 WHERE exam_id = ? AND class_id = ? AND score IS NOT NULL", 
                [$examId, $selectedClassId]
            );
            
            $examData[$examId]['total_rank'] = $totalRank['rank'] ?? 0;
            $examData[$examId]['total_students'] = $totalExamStudents['count'] ?? 0;
        }
    }
}

// 页面标题
$pageTitle = '学生分析';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">学生成绩分析</h1>
            
            <!-- 班级、学生和科目选择 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="class_id">选择班级</label>
                                <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()">
                                    <?php if (count($classes) > 0): ?>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['grade'] . $class['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">无可用班级</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="student_id">选择学生</label>
                                <select class="form-control" id="student_id" name="student_id" onchange="this.form.submit()" <?php echo $selectedClassId == 0 ? 'disabled' : ''; ?>>
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>" <?php echo $selectedStudentId == $student['id'] ? 'selected' : ''; ?>>
                                                <?php echo $student['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">无可用学生</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="subject_id">选择科目</label>
                                <select class="form-control" id="subject_id" name="subject_id" onchange="this.form.submit()" <?php echo $selectedClassId == 0 ? 'disabled' : ''; ?>>
                                    <?php if (count($subjects) > 0): ?>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubjectId == $subject['id'] ? 'selected' : ''; ?>>
                                                <?php echo $subject['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">无可用科目</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($studentInfo && $classInfo && count($examData) > 0): ?>
                <div class="alert alert-info">
                    <strong>当前分析：</strong> <?php echo $classInfo['grade'] . $classInfo['name']; ?> - <?php echo $studentInfo['name']; ?> 
                    <?php if ($selectedSubjectId && isset($subjects)): ?>
                        <?php foreach ($subjects as $subject): ?>
                            <?php if ($subject['id'] == $selectedSubjectId): ?>
                                - <?php echo $subject['name']; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 学生基本信息卡片 -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="h5 mb-0">学生基本信息</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>姓名：</strong> <?php echo $studentInfo['name']; ?></p>
                                <p class="mb-1"><strong>学号：</strong> <?php echo $studentInfo['student_id']; ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>班级：</strong> <?php echo $classInfo['grade'] . $classInfo['name']; ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>历史考试次数：</strong> <?php echo count($examData); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 成绩变化趋势图 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="h5 mb-0">科目成绩变化趋势</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="subjectScoreChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="h5 mb-0">科目排名变化趋势</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="subjectRankChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="h5 mb-0">总分变化趋势</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="totalScoreChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="h5 mb-0">总分排名变化趋势</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 350px;">
                                    <canvas id="totalRankChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 历次考试成绩详情表格 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 mb-0">历次考试成绩详情</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>考试名称</th>
                                        <th>考试日期</th>
                                        <?php if ($selectedSubjectId > 0): ?>
                                            <th><?php 
                                                foreach($subjects as $subject) {
                                                    if ($subject['id'] == $selectedSubjectId) {
                                                        echo $subject['name'] . '分数';
                                                        break;
                                                    }
                                                }
                                            ?></th>
                                            <th><?php 
                                                foreach($subjects as $subject) {
                                                    if ($subject['id'] == $selectedSubjectId) {
                                                        echo $subject['name'] . '排名';
                                                        break;
                                                    }
                                                }
                                            ?></th>
                                        <?php else: ?>
                                            <th>总分</th>
                                            <th>总分排名</th>
                                        <?php endif; ?>
                                        <th>得分率</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $reversedExamData = array_reverse($examData);
                                    foreach ($reversedExamData as $exam): 
                                    ?>
                                        <tr>
                                            <td><?php echo $exam['name']; ?></td>
                                            <td><?php echo $exam['date']; ?></td>
                                            <?php if ($selectedSubjectId > 0): ?>
                                                <?php if (isset($exam['subjects'][$selectedSubjectId])): ?>
                                                    <td>
                                                        <?php 
                                                            $score = $exam['subjects'][$selectedSubjectId]['score'];
                                                            $fullScore = $exam['subjects'][$selectedSubjectId]['full_score'];
                                                            $percentage = ($fullScore > 0) ? ($score / $fullScore) * 100 : 0;
                                                            
                                                            $textClass = '';
                                                            if ($percentage < 60) {
                                                                $textClass = 'text-danger';
                                                            } elseif ($percentage >= 85) {
                                                                $textClass = 'text-success';
                                                            }
                                                            
                                                            echo "<span class='{$textClass}'>" . number_format($score, 1) . "/" . number_format($fullScore, 1) . "</span>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            $rank = $exam['subjects'][$selectedSubjectId]['rank'];
                                                            $totalStudents = $exam['subjects'][$selectedSubjectId]['total_students'];
                                                            
                                                            $rankPercentage = ($totalStudents > 0) ? ($rank / $totalStudents) * 100 : 0;
                                                            $textClass = '';
                                                            
                                                            if ($rankPercentage <= 10) {
                                                                $textClass = 'text-success';
                                                            } elseif ($rankPercentage >= 80) {
                                                                $textClass = 'text-danger';
                                                            }
                                                            
                                                            echo "<span class='{$textClass}'>" . $rank . "/" . $totalStudents . "</span>";
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                            echo number_format($percentage, 1) . "%";
                                                        ?>
                                                    </td>
                                                <?php else: ?>
                                                    <td colspan="3" class="text-center">无数据</td>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <td>
                                                    <?php 
                                                        $totalScore = $exam['total_score'];
                                                        $totalFullScore = $exam['total_full_score'];
                                                        $percentage = ($totalFullScore > 0) ? ($totalScore / $totalFullScore) * 100 : 0;
                                                        
                                                        $textClass = '';
                                                        if ($percentage < 60) {
                                                            $textClass = 'text-danger';
                                                        } elseif ($percentage >= 85) {
                                                            $textClass = 'text-success';
                                                        }
                                                        
                                                        echo "<span class='{$textClass}'>" . number_format($totalScore, 1) . "/" . number_format($totalFullScore, 1) . "</span>";
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $rank = $exam['total_rank'];
                                                        $totalStudents = $exam['total_students'];
                                                        
                                                        $rankPercentage = ($totalStudents > 0) ? ($rank / $totalStudents) * 100 : 0;
                                                        $textClass = '';
                                                        
                                                        if ($rankPercentage <= 10) {
                                                            $textClass = 'text-success';
                                                        } elseif ($rankPercentage >= 80) {
                                                            $textClass = 'text-danger';
                                                        }
                                                        
                                                        echo "<span class='{$textClass}'>" . $rank . "/" . $totalStudents . "</span>";
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        echo number_format($percentage, 1) . "%";
                                                    ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($selectedClassId && $selectedStudentId): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 没有找到该学生的考试数据。
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($studentInfo && $classInfo && count($examData) > 0): ?>
        // 准备图表数据 - 确保数据格式正确
        const examLabels = [<?php 
            $labels = [];
            foreach ($examData as $exam) {
                $labels[] = '"' . $exam['name'] . '"';
            }
            echo implode(', ', $labels);
        ?>];
        
        // 科目成绩变化趋势图
        const subjectScoreCtx = document.getElementById('subjectScoreChart');
        if (subjectScoreCtx) {
            const selectedSubjectId = <?php echo $selectedSubjectId; ?>;
            const subjectScores = [<?php 
                $scores = [];
                foreach ($examData as $exam) {
                    if ($selectedSubjectId > 0) {
                        if (isset($exam['subjects'][$selectedSubjectId])) {
                            $scores[] = $exam['subjects'][$selectedSubjectId]['score'];
                        } else {
                            $scores[] = 'null';
                        }
                    } else {
                        $scores[] = $exam['total_score'];
                    }
                }
                echo implode(', ', $scores);
            ?>];
            
            const subjectFullScores = [<?php 
                $fullScores = [];
                foreach ($examData as $exam) {
                    if ($selectedSubjectId > 0) {
                        if (isset($exam['subjects'][$selectedSubjectId])) {
                            $fullScores[] = $exam['subjects'][$selectedSubjectId]['full_score'];
                        } else {
                            $fullScores[] = 'null';
                        }
                    } else {
                        $fullScores[] = $exam['total_full_score'];
                    }
                }
                echo implode(', ', $fullScores);
            ?>];
            
            // 计算得分率，处理null值
            const subjectScoreRates = [];
            for (let i = 0; i < subjectScores.length; i++) {
                if (subjectScores[i] === null || subjectFullScores[i] === null || subjectFullScores[i] === 0) {
                    subjectScoreRates.push(null);
                } else {
                    subjectScoreRates.push((subjectScores[i] / subjectFullScores[i]) * 100);
                }
            }
            
            new Chart(subjectScoreCtx, {
                type: 'line',
                data: {
                    labels: examLabels,
                    datasets: [
                        {
                            label: '分数',
                            data: subjectScores,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            spanGaps: true
                        },
                        {
                            label: '得分率(%)',
                            data: subjectScoreRates,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.3,
                            yAxisID: 'y1',
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '分数'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: '得分率(%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
        
        // 科目排名变化趋势图
        const subjectRankCtx = document.getElementById('subjectRankChart');
        if (subjectRankCtx) {
            const selectedSubjectId = <?php echo $selectedSubjectId; ?>;
            const subjectRanks = [<?php 
                $ranks = [];
                foreach ($examData as $exam) {
                    if ($selectedSubjectId > 0) {
                        if (isset($exam['subjects'][$selectedSubjectId])) {
                            $ranks[] = $exam['subjects'][$selectedSubjectId]['rank'];
                        } else {
                            $ranks[] = 'null';
                        }
                    } else {
                        $ranks[] = $exam['total_rank'];
                    }
                }
                echo implode(', ', $ranks);
            ?>];
            
            const subjectTotalStudents = [<?php 
                $totalStudents = [];
                foreach ($examData as $exam) {
                    if ($selectedSubjectId > 0) {
                        if (isset($exam['subjects'][$selectedSubjectId])) {
                            $totalStudents[] = $exam['subjects'][$selectedSubjectId]['total_students'];
                        } else {
                            $totalStudents[] = 'null';
                        }
                    } else {
                        $totalStudents[] = $exam['total_students'];
                    }
                }
                echo implode(', ', $totalStudents);
            ?>];
            
            // 计算排名百分比，处理null值
            const subjectRankPercentages = [];
            for (let i = 0; i < subjectRanks.length; i++) {
                if (subjectRanks[i] === null || subjectTotalStudents[i] === null || subjectTotalStudents[i] === 0) {
                    subjectRankPercentages.push(null);
                } else {
                    subjectRankPercentages.push((subjectRanks[i] / subjectTotalStudents[i]) * 100);
                }
            }
            
            new Chart(subjectRankCtx, {
                type: 'line',
                data: {
                    labels: examLabels,
                    datasets: [
                        {
                            label: '排名',
                            data: subjectRanks,
                            borderColor: 'rgba(255, 159, 64, 1)',
                            backgroundColor: 'rgba(255, 159, 64, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10
                        }
                    },
                    scales: {
                        y: {
                            reverse: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '排名 (1为最高)'
                            }
                        }
                    }
                }
            });
        }
        
        // 总分变化趋势图
        const totalScoreCtx = document.getElementById('totalScoreChart');
        if (totalScoreCtx) {
            const totalScores = [<?php 
                $totals = [];
                foreach ($examData as $exam) {
                    $totals[] = $exam['total_score'];
                }
                echo implode(', ', $totals);
            ?>];
            
            const totalFullScores = [<?php 
                $totalFulls = [];
                foreach ($examData as $exam) {
                    $totalFulls[] = $exam['total_full_score'];
                }
                echo implode(', ', $totalFulls);
            ?>];
            
            // 计算得分率，处理可能的0值
            const totalScoreRates = [];
            for (let i = 0; i < totalScores.length; i++) {
                if (totalFullScores[i] === 0) {
                    totalScoreRates.push(null);
                } else {
                    totalScoreRates.push((totalScores[i] / totalFullScores[i]) * 100);
                }
            }
            
            new Chart(totalScoreCtx, {
                type: 'line',
                data: {
                    labels: examLabels,
                    datasets: [
                        {
                            label: '总分',
                            data: totalScores,
                            borderColor: 'rgba(153, 102, 255, 1)',
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            spanGaps: true
                        },
                        {
                            label: '得分率(%)',
                            data: totalScoreRates,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.3,
                            yAxisID: 'y1',
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '总分'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: '得分率(%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
        
        // 总分排名变化趋势图
        const totalRankCtx = document.getElementById('totalRankChart');
        if (totalRankCtx) {
            const totalRanks = [<?php 
                $ranks = [];
                foreach ($examData as $exam) {
                    $ranks[] = $exam['total_rank'];
                }
                echo implode(', ', $ranks);
            ?>];
            
            const totalStudents = [<?php 
                $students = [];
                foreach ($examData as $exam) {
                    $students[] = $exam['total_students'];
                }
                echo implode(', ', $students);
            ?>];
            
            // 计算排名百分比，处理可能的0值
            const totalRankPercentages = [];
            for (let i = 0; i < totalRanks.length; i++) {
                if (totalStudents[i] === 0) {
                    totalRankPercentages.push(null);
                } else {
                    totalRankPercentages.push((totalRanks[i] / totalStudents[i]) * 100);
                }
            }
            
            new Chart(totalRankCtx, {
                type: 'line',
                data: {
                    labels: examLabels,
                    datasets: [
                        {
                            label: '排名',
                            data: totalRanks,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            spanGaps: true
                        },
                        {
                            label: '排名百分比(%)',
                            data: totalRankPercentages,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.3,
                            yAxisID: 'y1',
                            spanGaps: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            padding: 10
                        }
                    },
                    scales: {
                        y: {
                            reverse: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '排名 (1为最高)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 100,
                            reverse: true,
                            title: {
                                display: true,
                                text: '排名百分比(%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
    <?php endif; ?>
});
</script>

<?php
// 包含页脚
include '../components/teacher_footer.php';
?> 