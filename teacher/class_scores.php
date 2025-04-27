<?php
/**
 * 成绩分析系统 - 班级成绩页面
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

// 获取班主任班级（如果是班主任）
$classTeacherClasses = [];
if ($teacherInfo['is_class_teacher']) {
    $classTeacherClasses = getClassTeacherClasses($teacherInfo['id']);
}

// 默认选择第一个班级
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (count($classTeacherClasses) > 0 ? $classTeacherClasses[0]['id'] : 0);

// 获取选定班级的信息
$classInfo = null;
if ($selectedClassId) {
    $classInfo = fetchOne("SELECT * FROM classes WHERE id = ?", [$selectedClassId]);
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

// 获取学生成绩
$studentScores = [];
if ($examInfo && count($students) > 0) {
    foreach ($students as $student) {
        $scores = fetchAll(
            "SELECT s.*, sub.name as subject_name 
             FROM scores s 
             JOIN subjects sub ON s.subject_id = sub.id 
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
        $hasAllScores = true;
        
        foreach ($examSubjects as $subject) {
            if (isset($studentScores[$student['id']][$subject['subject_id']])) {
                $score = $studentScores[$student['id']][$subject['subject_id']]['score'];
                $total += $score;
            } else {
                $hasAllScores = false;
                break;
            }
        }
        
        if ($hasAllScores) {
            $totalScores[$student['id']] = [
                'student_id' => $student['id'],
                'student_name' => $student['name'],
                'total_score' => $total
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

// 页面标题
$pageTitle = '班级成绩';

// 包含页头
include '../components/teacher_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">班级成绩</h1>
            
            <?php if (count($classTeacherClasses) === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 您目前不是任何班级的班主任，无法查看班级成绩。
                </div>
            <?php else: ?>
                <!-- 班级和考试选择 -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="class_id">选择班级</label>
                                    <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()">
                                        <?php foreach ($classTeacherClasses as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['grade'] . $class['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
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
                
                <?php if ($examInfo && count($examSubjects) > 0): ?>
                    <!-- 成绩统计卡片 -->
                    <div class="row mb-4">
                        <?php foreach ($examSubjects as $subject): ?>
                            <?php $stats = $subjectStats[$subject['subject_id']] ?? []; ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h3 class="card-title h5">
                                            <?php echo $subject['subject_name']; ?>
                                            <span class="badge badge-primary float-right">满分: <?php echo $subject['full_score']; ?></span>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-4 text-center border-right">
                                                <h4 class="h5 text-primary"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?></h4>
                                                <small class="text-muted">平均分</small>
                                            </div>
                                            <div class="col-4 text-center border-right">
                                                <h4 class="h5 text-success"><?php echo number_format($stats['max_score'] ?? 0, 1); ?></h4>
                                                <small class="text-muted">最高分</small>
                                            </div>
                                            <div class="col-4 text-center">
                                                <h4 class="h5 text-danger"><?php echo number_format($stats['min_score'] ?? 0, 1); ?></h4>
                                                <small class="text-muted">最低分</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between">
                                                <small>及格率</small>
                                                <small>
                                                    <?php 
                                                    $passRate = ($stats['total_count'] > 0) ? 
                                                        round(($stats['pass_count'] / $stats['total_count']) * 100, 1) : 0;
                                                    echo $passRate . '%'; 
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $passRate; ?>%"></div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <small>优秀率</small>
                                                <small>
                                                    <?php 
                                                    $excellentRate = ($stats['total_count'] > 0) ? 
                                                        round(($stats['excellent_count'] / $stats['total_count']) * 100, 1) : 0;
                                                    echo $excellentRate . '%'; 
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $excellentRate; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                                            <?php foreach ($examSubjects as $subject): ?>
                                                <th><?php echo $subject['subject_name']; ?></th>
                                            <?php endforeach; ?>
                                            <th>总分</th>
                                            <th>平均分</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($students) > 0): ?>
                                            <?php foreach ($students as $student): ?>
                                                <?php 
                                                $studentId = $student['id'];
                                                $totalScore = 0;
                                                $scoreCount = 0;
                                                $hasMissingScores = false;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php 
                                                        if (isset($totalScores[$studentId])) {
                                                            echo $totalScores[$studentId]['rank'];
                                                        } else {
                                                            echo "-";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo $student['student_id']; ?></td>
                                                    <td><?php echo $student['name']; ?></td>
                                                    
                                                    <?php foreach ($examSubjects as $subject): ?>
                                                        <td>
                                                            <?php 
                                                            if (isset($studentScores[$studentId][$subject['subject_id']])) {
                                                                $score = $studentScores[$studentId][$subject['subject_id']]['score'];
                                                                $totalScore += $score;
                                                                $scoreCount++;
                                                                
                                                                // 计算得分率，低于60%显示红色，高于85%显示绿色
                                                                $scoreRate = ($score / $subject['full_score']) * 100;
                                                                $colorClass = '';
                                                                
                                                                if ($scoreRate < 60) {
                                                                    $colorClass = 'text-danger';
                                                                } elseif ($scoreRate >= 85) {
                                                                    $colorClass = 'text-success';
                                                                }
                                                                
                                                                echo "<span class='{$colorClass}'>" . number_format($score, 1) . "</span>";
                                                            } else {
                                                                $hasMissingScores = true;
                                                                echo "<span class='text-muted'>-</span>";
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <td>
                                                        <?php 
                                                        if (!$hasMissingScores && $scoreCount > 0) {
                                                            echo number_format($totalScore, 1);
                                                        } else {
                                                            echo "<span class='text-muted'>-</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if (!$hasMissingScores && $scoreCount > 0) {
                                                            echo number_format($totalScore / $scoreCount, 1);
                                                        } else {
                                                            echo "<span class='text-muted'>-</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo 5 + count($examSubjects); ?>" class="text-center">暂无学生数据</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 数据可视化 -->
                    <div class="row">
                        <!-- 平均分对比图 -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">各科平均分对比</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="subjectAverageChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 成绩分布图 -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">总分成绩分布</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="scoreDistributionChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 及格率/优秀率对比图 -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">各科及格率/优秀率对比</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="passRateChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 分数段人数统计 -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">分数段人数统计</h3>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="scoreRangeChart"></canvas>
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
    <?php if ($examInfo && count($examSubjects) > 0 && count($subjectStats) > 0): ?>
        // 准备图表数据
        const subjectNames = <?php 
            echo json_encode(array_map(function($subject) {
                return $subject['subject_name'];
            }, $examSubjects)); 
        ?>;
        
        const avgScores = <?php 
            echo json_encode(array_map(function($subject) use ($subjectStats) {
                return $subjectStats[$subject['subject_id']]['avg_score'] ?? 0;
            }, $examSubjects)); 
        ?>;
        
        const fullScores = <?php 
            echo json_encode(array_map(function($subject) {
                return $subject['full_score'];
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
        
        // 各科平均分对比图
        createBarChart('subjectAverageChart', subjectNames, [
            {
                label: '平均分',
                data: avgScores.map(score => parseFloat(score)),
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }
        ], {
            plugins: {
                title: {
                    display: true,
                    text: '各科平均分对比'
                }
            }
        });
        
        // 及格率/优秀率对比图
        createPassRateChart('passRateChart', subjectNames, passRates, excellentRates);
        
        <?php if (count($totalScores) > 0): ?>
            // 总分成绩分布直方图
            createScoreDistributionChart('scoreDistributionChart', <?php echo json_encode(array_column($totalScores, 'total_score')); ?>);
            
            // 分数段人数统计图
            createScoreRangeChart('scoreRangeChart', <?php echo json_encode(array_column($totalScores, 'total_score')); ?>);
        <?php endif; ?>
    <?php endif; ?>
});

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
                    text: '各科及格率/优秀率对比'
                }
            }
        }
    });
}

// 创建成绩分布直方图
function createScoreDistributionChart(canvasId, scores) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 计算成绩分布
    const min = Math.min(...scores);
    const max = Math.max(...scores);
    const binSize = 50; // 每个区间的大小
    const bins = {};
    
    // 初始化区间
    for (let i = Math.floor(min / binSize) * binSize; i <= Math.ceil(max / binSize) * binSize; i += binSize) {
        bins[i] = 0;
    }
    
    // 统计各区间人数
    scores.forEach(score => {
        const binIndex = Math.floor(score / binSize) * binSize;
        bins[binIndex]++;
    });
    
    // 转换为数组格式
    const labels = Object.keys(bins).map(key => `${key}-${parseInt(key) + binSize}`);
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
                    text: '总分成绩分布'
                }
            }
        }
    });
}

// 创建分数段人数统计图
function createScoreRangeChart(canvasId, scores) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // 定义分数段
    const ranges = [
        { label: '优秀(≥85%)', min: 85, color: 'rgba(40, 167, 69, 0.7)' },
        { label: '良好(75-84%)', min: 75, max: 84, color: 'rgba(0, 123, 255, 0.7)' },
        { label: '中等(60-74%)', min: 60, max: 74, color: 'rgba(255, 193, 7, 0.7)' },
        { label: '不及格(<60%)', max: 59, color: 'rgba(220, 53, 69, 0.7)' }
    ];
    
    // 计算总分的满分值
    const totalFullScore = <?php 
        echo array_sum(array_map(function($subject) {
            return $subject['full_score'];
        }, $examSubjects)); 
    ?>;
    
    // 统计各分数段人数
    const data = ranges.map(range => {
        return scores.filter(score => {
            const percent = (score / totalFullScore) * 100;
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
                    text: '班级分数段人数统计'
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
    const examName = <?php echo $examInfo ? ("'" . $examInfo['name'] . "'") : "''"; ?>;
    const className = <?php echo $classInfo ? ("'" . $classInfo['grade'] . $classInfo['name'] . "'") : "''"; ?>;
    const filename = '班级成绩表_' + className + '_' + examName + '.csv';
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