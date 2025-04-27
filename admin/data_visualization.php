<?php
/**
 * 数据可视化页面
 * 
 * 该页面用于展示各种数据分析图表，包括考试成绩分析、班级对比、学科分析等
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 页面标题
$page_title = "数据可视化";

// 确保用户有管理员权限
requireAdmin();

// 引入头部
include_once '../components/admin_header.php';

// 获取筛选参数
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$exam_type = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// 获取班级列表
$classes = fetchAll("SELECT * FROM classes ORDER BY name");

// 获取科目列表
$subjects = fetchAll("SELECT * FROM subjects ORDER BY name");

// 获取考试列表
$exams = fetchAll("SELECT * FROM exams ORDER BY exam_date DESC");

// 获取考试类型列表
$exam_types = [];
$exam_types_result = fetchAll("SELECT DISTINCT type FROM exams ORDER BY type");
foreach ($exam_types_result as $row) {
    $exam_types[] = $row['type'];
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar mr-2"></i> 数据可视化分析
                    </h5>
                </div>
                <div class="card-body">
                    <!-- 筛选条件 -->
                    <div class="filter-section mb-4">
                        <form method="get" action="" class="row g-3">
                            <div class="col-md-2">
                                <label for="class_id" class="form-label">班级</label>
                                <select name="class_id" id="class_id" class="form-select">
                                    <option value="0">全部班级</option>
                                    <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="subject_id" class="form-label">科目</label>
                                <select name="subject_id" id="subject_id" class="form-select">
                                    <option value="0">全部科目</option>
                                    <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="exam_id" class="form-label">考试</label>
                                <select name="exam_id" id="exam_id" class="form-select">
                                    <option value="0">全部考试</option>
                                    <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" <?php echo $exam_id == $exam['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="exam_type" class="form-label">考试类型</label>
                                <select name="exam_type" id="exam_type" class="form-select">
                                    <option value="">全部类型</option>
                                    <?php foreach ($exam_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $exam_type == $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_range" class="form-label">日期范围</label>
                                <select name="date_range" id="date_range" class="form-select">
                                    <option value="">全部时间</option>
                                    <option value="last_month" <?php echo $date_range == 'last_month' ? 'selected' : ''; ?>>最近一个月</option>
                                    <option value="last_3months" <?php echo $date_range == 'last_3months' ? 'selected' : ''; ?>>最近三个月</option>
                                    <option value="last_6months" <?php echo $date_range == 'last_6months' ? 'selected' : ''; ?>>最近半年</option>
                                    <option value="last_year" <?php echo $date_range == 'last_year' ? 'selected' : ''; ?>>最近一年</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> 筛选
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- 图表展示区 -->
                    <div class="row">
                        <!-- 平均分对比 -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">各科目平均分对比</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="subjectAverageChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 及格率对比 -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">各科目及格率对比</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="passRateChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 分数段分布 -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">分数段分布</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="scoreDistributionChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 班级平均分对比 -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">班级平均分对比</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="classAverageChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 考试趋势分析 -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">考试成绩趋势分析</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="examTrendChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 获取数据并处理

// 构建SQL查询条件
$conditions = [];
$params = [];

if ($class_id > 0) {
    $conditions[] = "s.class_id = ?";
    $params[] = $class_id;
}

if ($subject_id > 0) {
    $conditions[] = "sc.subject_id = ?";
    $params[] = $subject_id;
}

if ($exam_id > 0) {
    $conditions[] = "sc.exam_id = ?";
    $params[] = $exam_id;
}

if (!empty($exam_type)) {
    $conditions[] = "e.type = ?";
    $params[] = $exam_type;
}

if (!empty($date_range)) {
    switch ($date_range) {
        case 'last_month':
            $conditions[] = "e.exam_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'last_3months':
            $conditions[] = "e.exam_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            break;
        case 'last_6months':
            $conditions[] = "e.exam_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
            break;
        case 'last_year':
            $conditions[] = "e.exam_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// 获取各科目平均分
$subjectAverageQuery = "
    SELECT 
        su.name as subject_name,
        AVG(sc.score) as average_score,
        (SELECT AVG(es.full_score) FROM exam_subjects es WHERE es.subject_id = su.id) as full_score,
        COUNT(DISTINCT s.id) as student_count
    FROM 
        scores sc
    JOIN 
        students s ON sc.student_id = s.id
    JOIN 
        subjects su ON sc.subject_id = su.id
    JOIN 
        exams e ON sc.exam_id = e.id
    $whereClause
    GROUP BY 
        su.id
    ORDER BY 
        su.id
";

$subjectAverages = fetchAll($subjectAverageQuery, $params);

// 获取各科目及格率
$passRateQuery = "
    SELECT 
        su.name as subject_name,
        SUM(CASE WHEN sc.score >= 60 THEN 1 ELSE 0 END) as pass_count,
        COUNT(sc.id) as total_count,
        (SUM(CASE WHEN sc.score >= 60 THEN 1 ELSE 0 END) / COUNT(sc.id)) * 100 as pass_rate
    FROM 
        scores sc
    JOIN 
        students s ON sc.student_id = s.id
    JOIN 
        subjects su ON sc.subject_id = su.id
    JOIN 
        exams e ON sc.exam_id = e.id
    $whereClause
    GROUP BY 
        su.id
    ORDER BY 
        su.id
";

$passRates = fetchAll($passRateQuery, $params);

// 获取分数段分布
$scoreDistributionQuery = "
    SELECT 
        CASE 
            WHEN (sc.score / es.full_score) * 100 < 60 THEN '不及格'
            WHEN (sc.score / es.full_score) * 100 BETWEEN 60 AND 69 THEN '及格'
            WHEN (sc.score / es.full_score) * 100 BETWEEN 70 AND 79 THEN '良好'
            WHEN (sc.score / es.full_score) * 100 BETWEEN 80 AND 89 THEN '优秀'
            ELSE '卓越'
        END as score_range,
        COUNT(sc.id) as count
    FROM 
        scores sc
    JOIN 
        students s ON sc.student_id = s.id
    JOIN 
        subjects su ON sc.subject_id = su.id
    JOIN 
        exams e ON sc.exam_id = e.id
    JOIN
        exam_subjects es ON es.exam_id = e.id AND es.subject_id = su.id
    $whereClause
    GROUP BY 
        score_range
    ORDER BY 
        CASE 
            WHEN score_range = '不及格' THEN 1
            WHEN score_range = '及格' THEN 2
            WHEN score_range = '良好' THEN 3
            WHEN score_range = '优秀' THEN 4
            ELSE 5
        END
";

$scoreDistribution = fetchAll($scoreDistributionQuery, $params);

// 获取班级平均分对比
$classAverageQuery = "
    SELECT 
        c.name as class_name,
        AVG(sc.score) as average_score
    FROM 
        scores sc
    JOIN 
        students s ON sc.student_id = s.id
    JOIN 
        classes c ON s.class_id = c.id
    JOIN 
        subjects su ON sc.subject_id = su.id
    JOIN 
        exams e ON sc.exam_id = e.id
    $whereClause
    GROUP BY 
        c.id
    ORDER BY 
        c.id
";

$classAverages = fetchAll($classAverageQuery, $params);

// 获取考试趋势数据
$examTrendQuery = "
    SELECT 
        e.name as exam_name,
        e.exam_date,
        su.name as subject_name,
        AVG(sc.score) as average_score
    FROM 
        scores sc
    JOIN 
        students s ON sc.student_id = s.id
    JOIN 
        subjects su ON sc.subject_id = su.id
    JOIN 
        exams e ON sc.exam_id = e.id
    $whereClause
    GROUP BY 
        e.id, su.id
    ORDER BY 
        e.exam_date, su.id
";

$examTrends = fetchAll($examTrendQuery, $params);

// 处理趋势数据
$examNames = [];
$examDates = [];
$trendData = [];

foreach ($examTrends as $trend) {
    if (!in_array($trend['exam_name'] . ' (' . date('Y-m-d', strtotime($trend['exam_date'])) . ')', $examNames)) {
        $examNames[] = $trend['exam_name'] . ' (' . date('Y-m-d', strtotime($trend['exam_date'])) . ')';
        $examDates[] = $trend['exam_date'];
    }
    
    if (!isset($trendData[$trend['subject_name']])) {
        $trendData[$trend['subject_name']] = [];
    }
    
    $trendData[$trend['subject_name']][] = round($trend['average_score'], 2);
}

// 数据处理完成，JSON编码用于JavaScript
$subjectAverageJSON = json_encode(array_map(function($item) {
    $fullScore = isset($item['full_score']) ? $item['full_score'] : 100; // 默认满分100
    return [
        'subject' => $item['subject_name'],
        'average' => round($item['average_score'], 2),
        'full_score' => $fullScore,
        'percentage' => round(($item['average_score'] / $fullScore) * 100, 2)
    ];
}, $subjectAverages));

$passRateJSON = json_encode(array_map(function($item) {
    return [
        'subject' => $item['subject_name'],
        'pass_rate' => round($item['pass_rate'], 2)
    ];
}, $passRates));

$scoreDistributionJSON = json_encode(array_map(function($item) {
    return [
        'range' => $item['score_range'],
        'count' => $item['count']
    ];
}, $scoreDistribution));

$classAverageJSON = json_encode(array_map(function($item) {
    return [
        'class' => $item['class_name'],
        'average' => round($item['average_score'], 2)
    ];
}, $classAverages));

$examTrendJSON = json_encode([
    'exams' => $examNames,
    'data' => $trendData
]);
?>

<script>
// 图表初始化
document.addEventListener('DOMContentLoaded', function() {
    // 各科目平均分对比图
    const subjectAverageData = <?php echo $subjectAverageJSON; ?>;
    if (subjectAverageData.length > 0) {
        const labels = subjectAverageData.map(item => item.subject);
        const averages = subjectAverageData.map(item => item.average);
        const percentages = subjectAverageData.map(item => item.percentage);
        
        createBarChart('subjectAverageChart', labels, [
            {
                label: '平均分',
                data: averages,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }
        ], {
            title: '各科目平均分对比',
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        return `平均分: ${tooltipItem.value} (${percentages[tooltipItem.index]}%)`;
                    }
                }
            }
        });
    }
    
    // 各科目及格率对比图
    const passRateData = <?php echo $passRateJSON; ?>;
    if (passRateData.length > 0) {
        const labels = passRateData.map(item => item.subject);
        const rates = passRateData.map(item => item.pass_rate);
        
        createBarChart('passRateChart', labels, [
            {
                label: '及格率(%)',
                data: rates,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ], {
            title: '各科目及格率对比',
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: '百分比(%)'
                    }
                }
            }
        });
    }
    
    // 分数段分布图
    const scoreDistributionData = <?php echo $scoreDistributionJSON; ?>;
    if (scoreDistributionData.length > 0) {
        const labels = scoreDistributionData.map(item => item.range);
        const counts = scoreDistributionData.map(item => item.count);
        
        createPieChart('scoreDistributionChart', labels, counts, {
            title: '分数段分布',
            doughnut: true
        });
    }
    
    // 班级平均分对比图
    const classAverageData = <?php echo $classAverageJSON; ?>;
    if (classAverageData.length > 0) {
        const labels = classAverageData.map(item => item.class);
        const averages = classAverageData.map(item => item.average);
        
        createBarChart('classAverageChart', labels, [
            {
                label: '平均分',
                data: averages,
                backgroundColor: 'rgba(153, 102, 255, 0.7)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }
        ], {
            title: '班级平均分对比'
        });
    }
    
    // 考试趋势分析图
    const examTrendData = <?php echo $examTrendJSON; ?>;
    if (examTrendData.exams.length > 0) {
        const datasets = [];
        const colors = [
            { bg: 'rgba(54, 162, 235, 0.5)', border: 'rgba(54, 162, 235, 1)' },
            { bg: 'rgba(255, 99, 132, 0.5)', border: 'rgba(255, 99, 132, 1)' },
            { bg: 'rgba(75, 192, 192, 0.5)', border: 'rgba(75, 192, 192, 1)' },
            { bg: 'rgba(255, 206, 86, 0.5)', border: 'rgba(255, 206, 86, 1)' },
            { bg: 'rgba(153, 102, 255, 0.5)', border: 'rgba(153, 102, 255, 1)' }
        ];
        
        let colorIndex = 0;
        for (const [subject, scores] of Object.entries(examTrendData.data)) {
            const color = colors[colorIndex % colors.length];
            datasets.push({
                label: subject,
                data: scores,
                backgroundColor: color.bg,
                borderColor: color.border,
                borderWidth: 2,
                fill: false,
                tension: 0.1
            });
            colorIndex++;
        }
        
        createLineChart('examTrendChart', examTrendData.exams, datasets, {
            title: '考试成绩趋势分析',
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: '平均分'
                    }
                }
            }
        });
    }
});
</script>

<?php
// 引入页脚
include_once '../components/admin_footer.php';
?> 