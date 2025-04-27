<?php
/**
 * 成绩分析系统 - 考试成绩分析页面
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

// 处理班级和科目筛选
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$showPercentage = isset($_GET['percentage']) && $_GET['percentage'] == 1;

// 获取所有班级
$classes = getAllClasses();

// 构建班级和科目映射
$classMap = [];
foreach ($classes as $class) {
    $classMap[$class['id']] = $class['grade'] . ' ' . $class['name'];
}

$subjectMap = [];
foreach ($examSubjects as $subject) {
    $subjectMap[$subject['subject_id']] = [
        'name' => $subject['subject_name'],
        'full_score' => $subject['full_score']
    ];
}

// 获取统计数据
// 1. 总体统计
$totalStats = fetchOne("SELECT 
                        COUNT(DISTINCT s.student_id) as student_count,
                        COUNT(DISTINCT s.class_id) as class_count,
                        COUNT(*) as score_count,
                        AVG(s.score) as avg_score,
                        MAX(s.score) as max_score,
                        MIN(s.score) as min_score
                        FROM scores s
                        WHERE s.exam_id = $examId AND s.score IS NOT NULL");

// 2. 班级平均分
$classAvgScores = fetchAll("SELECT c.id, CONCAT(c.grade, ' ', c.name) as class_name, 
                            AVG(s.score) as avg_score, COUNT(DISTINCT s.student_id) as student_count
                            FROM scores s 
                            JOIN classes c ON s.class_id = c.id 
                            WHERE s.exam_id = $examId AND s.score IS NOT NULL
                            " . ($subjectId > 0 ? " AND s.subject_id = $subjectId" : "") . "
                            GROUP BY s.class_id 
                            ORDER BY avg_score DESC");

// 3. 科目平均分
$subjectAvgScores = fetchAll("SELECT s.subject_id, sub.name as subject_name, es.full_score,
                            AVG(s.score) as avg_score, COUNT(DISTINCT s.student_id) as student_count,
                            MAX(s.score) as max_score, MIN(s.score) as min_score
                            FROM scores s 
                            JOIN subjects sub ON s.subject_id = sub.id
                            JOIN exam_subjects es ON s.subject_id = es.subject_id AND s.exam_id = es.exam_id
                            WHERE s.exam_id = $examId AND s.score IS NOT NULL
                            " . ($classId > 0 ? " AND s.class_id = $classId" : "") . "
                            GROUP BY s.subject_id
                            ORDER BY avg_score DESC");

// 4. 成绩分布
$scoreRanges = [];
$scoreRangeLabels = [];
$scoreDistribution = [];

if ($subjectId > 0 && isset($subjectMap[$subjectId])) {
    // 单科目成绩分布
    $fullScore = $subjectMap[$subjectId]['full_score'];
    $step = $fullScore / 10;
    
    // 生成分数段
    for ($i = 0; $i < 10; $i++) {
        $min = $i * $step;
        $max = ($i + 1) * $step;
        $scoreRanges[] = [$min, $max];
        
        if ($showPercentage) {
            $minPercent = round(($min / $fullScore) * 100);
            $maxPercent = round(($max / $fullScore) * 100);
            $scoreRangeLabels[] = "$minPercent%-$maxPercent%";
        } else {
            $scoreRangeLabels[] = round($min, 1) . '-' . round($max, 1);
        }
    }
    
    // 查询每个分数段的学生数量
    foreach ($scoreRanges as $index => $range) {
        $min = $range[0];
        $max = $range[1];
        
        $count = fetchOne("SELECT COUNT(*) as count
                          FROM scores s
                          WHERE s.exam_id = $examId
                          AND s.subject_id = $subjectId
                          AND s.score >= $min AND s.score < $max
                          " . ($classId > 0 ? " AND s.class_id = $classId" : ""));
        
        $scoreDistribution[] = $count ? $count['count'] : 0;
    }
} else {
    // 总分成绩分布
    $query = "SELECT 
              SUM(s.score) as total_score,
              COUNT(DISTINCT s.student_id) as student_count
              FROM scores s
              WHERE s.exam_id = $examId
              " . ($classId > 0 ? " AND s.class_id = $classId" : "") . "
              GROUP BY s.student_id";
    
    $studentTotals = fetchAll($query);
    
    // 计算最高可能总分
    $maxPossibleTotal = 0;
    foreach ($examSubjects as $subject) {
        $maxPossibleTotal += $subject['full_score'];
    }
    
    // 生成分数段
    $step = $maxPossibleTotal / 10;
    for ($i = 0; $i < 10; $i++) {
        $min = $i * $step;
        $max = ($i + 1) * $step;
        $scoreRanges[] = [$min, $max];
        
        if ($showPercentage) {
            $minPercent = round(($min / $maxPossibleTotal) * 100);
            $maxPercent = round(($max / $maxPossibleTotal) * 100);
            $scoreRangeLabels[] = "$minPercent%-$maxPercent%";
        } else {
            $scoreRangeLabels[] = round($min, 1) . '-' . round($max, 1);
        }
    }
    
    // 统计每个分数段的学生数量
    $distribution = array_fill(0, 10, 0);
    
    foreach ($studentTotals as $studentTotal) {
        $total = $studentTotal['total_score'];
        
        for ($i = 0; $i < 10; $i++) {
            $min = $scoreRanges[$i][0];
            $max = $scoreRanges[$i][1];
            
            if ($total >= $min && $total < $max) {
                $distribution[$i]++;
                break;
            }
            
            // 最高分特殊处理
            if ($i == 9 && $total == $max) {
                $distribution[$i]++;
            }
        }
    }
    
    $scoreDistribution = $distribution;
}

// 页面标题
$pageTitle = '考试成绩分析';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col">
            <h1>考试成绩分析</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                    <li class="breadcrumb-item"><a href="exams.php">考试管理</a></li>
                    <li class="breadcrumb-item"><a href="exam_details.php?id=<?php echo $examId; ?>">考试详情</a></li>
                    <li class="breadcrumb-item active" aria-current="page">成绩分析</li>
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
    
    <form action="" method="get" id="filter-form" class="mb-4">
        <input type="hidden" name="id" value="<?php echo $examId; ?>">
        
        <div class="card mb-4 anim-fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h5 class="mb-0">数据筛选</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="class_id" class="form-label">班级</label>
                            <select name="class_id" id="class_id" class="form-select">
                                <option value="0">全部班级</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $classId == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo $class['grade'] . ' ' . $class['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="subject_id" class="form-label">科目</label>
                            <select name="subject_id" id="subject_id" class="form-select">
                                <option value="0">全部科目</option>
                                <?php foreach ($examSubjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>" <?php echo $subjectId == $subject['subject_id'] ? 'selected' : ''; ?>>
                                        <?php echo $subject['subject_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group mt-4 pt-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="percentage" id="percentage" value="1" <?php echo $showPercentage ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="percentage">显示百分比成绩</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group mt-4 pt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> 应用筛选
                            </button>
                            <a href="exam_analysis.php?id=<?php echo $examId; ?>" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> 重置
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.3s;">
                <div class="card-header">
                    <h5 class="mb-0">统计概览</h5>
                </div>
                <div class="card-body">
                    <div class="dashboard">
                        <div class="stat-card">
                            <h3>参考人数</h3>
                            <div class="stat-value"><?php echo $totalStats['student_count']; ?></div>
                            <div class="stat-description">总参考学生数</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>班级数</h3>
                            <div class="stat-value"><?php echo $totalStats['class_count']; ?></div>
                            <div class="stat-description">参考班级数量</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>
                                <?php if ($subjectId > 0): ?>
                                    平均分
                                <?php else: ?>
                                    平均单科分
                                <?php endif; ?>
                            </h3>
                            <div class="stat-value">
                                <?php 
                                $avg = round($totalStats['avg_score'], 1);
                                echo $avg;
                                
                                if ($showPercentage && $subjectId > 0) {
                                    $percent = round(($avg / $subjectMap[$subjectId]['full_score']) * 100, 1);
                                    echo " ($percent%)";
                                }
                                ?>
                            </div>
                            <div class="stat-description">所有成绩的平均值</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>
                                <?php if ($subjectId > 0): ?>
                                    最高分
                                <?php else: ?>
                                    最高单科分
                                <?php endif; ?>
                            </h3>
                            <div class="stat-value">
                                <?php 
                                $max = round($totalStats['max_score'], 1);
                                echo $max;
                                
                                if ($showPercentage && $subjectId > 0) {
                                    $percent = round(($max / $subjectMap[$subjectId]['full_score']) * 100, 1);
                                    echo " ($percent%)";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php if ($subjectId > 0): ?>
                            <?php echo $subjectMap[$subjectId]['name']; ?> 成绩分布
                        <?php else: ?>
                            总分成绩分布
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="score-distribution-chart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.5s;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php if ($subjectId > 0): ?>
                            班级 <?php echo $subjectMap[$subjectId]['name']; ?> 平均分
                        <?php else: ?>
                            班级平均分
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="class-avg-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.6s;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <?php if ($classId > 0): ?>
                            <?php echo $classMap[$classId]; ?> 科目平均分
                        <?php else: ?>
                            各科目平均分
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="subject-avg-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4 anim-fade-in" style="animation-delay: 0.7s;">
                <div class="card-header">
                    <h5 class="mb-0">科目对比</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="subject-comparison-chart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 成绩分布图
    const scoreDistLabels = <?php echo json_encode($scoreRangeLabels); ?>;
    const scoreDistData = <?php echo json_encode($scoreDistribution); ?>;
    
    createBarChart('score-distribution-chart', scoreDistLabels, [{
        label: '学生人数',
        data: scoreDistData,
        backgroundColor: 'rgba(54, 162, 235, 0.5)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1
    }], {
        title: '成绩分布'
    });
    
    // 班级平均分图
    const classLabels = [];
    const classAvgData = [];
    
    <?php foreach ($classAvgScores as $classScore): ?>
        classLabels.push('<?php echo $classScore['class_name']; ?>');
        classAvgData.push(<?php echo round($classScore['avg_score'], 1); ?>);
    <?php endforeach; ?>
    
    createBarChart('class-avg-chart', classLabels, [{
        label: '平均分',
        data: classAvgData,
        backgroundColor: 'rgba(75, 192, 192, 0.5)',
        borderColor: 'rgba(75, 192, 192, 1)',
        borderWidth: 1
    }], {
        title: '班级平均分对比'
    });
    
    // 科目平均分图
    const subjectLabels = [];
    const subjectAvgData = [];
    const subjectMaxScores = [];
    
    <?php foreach ($subjectAvgScores as $subjectScore): ?>
        subjectLabels.push('<?php echo $subjectScore['subject_name']; ?>');
        subjectAvgData.push(<?php echo round($subjectScore['avg_score'], 1); ?>);
        subjectMaxScores.push(<?php echo $subjectScore['full_score']; ?>);
    <?php endforeach; ?>
    
    <?php if ($showPercentage): ?>
        // 显示百分比
        const percentageData = subjectAvgData.map((avg, i) => (avg / subjectMaxScores[i]) * 100);
        
        createBarChart('subject-avg-chart', subjectLabels, [{
            label: '平均分百分比',
            data: percentageData.map(p => Math.round(p * 10) / 10),  // 四舍五入到小数点后1位
            backgroundColor: 'rgba(153, 102, 255, 0.5)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }], {
            title: '科目平均分百分比',
            yAxes: [{
                ticks: {
                    min: 0,
                    max: 100,
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }]
        });
    <?php else: ?>
        // 显示实际分数
        createBarChart('subject-avg-chart', subjectLabels, [{
            label: '平均分',
            data: subjectAvgData,
            backgroundColor: 'rgba(153, 102, 255, 0.5)',
            borderColor: 'rgba(153, 102, 255, 1)',
            borderWidth: 1
        }], {
            title: '科目平均分'
        });
    <?php endif; ?>
    
    // 科目对比图（雷达图）
    <?php if (count($subjectAvgScores) > 2): ?>
        <?php if ($showPercentage): ?>
            // 显示百分比
            createRadarChart('subject-comparison-chart', subjectLabels, [{
                label: '平均分百分比',
                data: percentageData,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(255, 99, 132, 1)'
            }], {
                title: '科目成绩对比(百分比)',
                scale: {
                    ticks: {
                        min: 0,
                        max: 100
                    }
                }
            });
        <?php else: ?>
            // 创建科目对比的混合图表
            createMixedChart('subject-comparison-chart', 
                subjectLabels,
                [{
                    label: '平均分',
                    data: subjectAvgData,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }],
                [{
                    label: '满分',
                    data: subjectMaxScores,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0)',
                    borderDash: [5, 5]
                }],
                {
                    title: '科目平均分与满分对比'
                }
            );
        <?php endif; ?>
    <?php endif; ?>
    
    // 表单自动提交
    document.getElementById('class_id').addEventListener('change', function() {
        document.getElementById('filter-form').submit();
    });
    
    document.getElementById('subject_id').addEventListener('change', function() {
        document.getElementById('filter-form').submit();
    });
    
    document.getElementById('percentage').addEventListener('change', function() {
        document.getElementById('filter-form').submit();
    });
});
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 