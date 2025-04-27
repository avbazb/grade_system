<?php
/**
 * 成绩分析系统 - 班级成绩分析
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 获取班级ID
$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 获取考试ID
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// 获取所有班级
$classes = getAllClasses();

// 如果未指定班级ID且有班级数据，默认使用第一个班级
if (!$classId && count($classes) > 0) {
    $classId = $classes[0]['id'];
}

// 获取班级信息
$class = null;
if ($classId) {
    $class = fetchOne("
        SELECT c.*, 
            (SELECT t.name FROM teachers t WHERE t.class_id = c.id AND t.is_class_teacher = 1) AS class_teacher_name
        FROM classes c
        WHERE c.id = ?
    ", [$classId]);
}

// 获取班级考试列表
$exams = [];
if ($classId) {
    $exams = fetchAll("
        SELECT DISTINCT e.*
        FROM exams e
        JOIN scores s ON e.id = s.exam_id
        JOIN students st ON s.student_id = st.id
        WHERE st.class_id = ?
        ORDER BY e.exam_date DESC
    ", [$classId]);
}

// 如果未指定考试ID且有考试数据，默认使用第一个考试
if (!$examId && count($exams) > 0) {
    $examId = $exams[0]['id'];
}

// 获取考试信息
$exam = null;
if ($examId) {
    $exam = fetchOne("SELECT * FROM exams WHERE id = ?", [$examId]);
}

// 获取班级科目列表
$subjects = [];
if ($classId) {
    $subjects = fetchAll("
        SELECT s.*
        FROM subjects s
        JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = ?
        ORDER BY s.name ASC
    ", [$classId]);
}

// 获取班级学生成绩数据
$studentScores = [];
$subjectAverages = [];
$classAverage = 0;
$passRates = [];
$excellentRates = [];
$scoreDistributions = [];

if ($classId && $examId) {
    // 获取学生成绩
    $studentScores = fetchAll("
        SELECT st.id, st.name, st.student_number, st.gender,
            GROUP_CONCAT(CONCAT(sub.name, ':', IFNULL(s.score, 0)) ORDER BY sub.name SEPARATOR ',') AS subject_scores,
            SUM(IFNULL(s.score, 0)) AS total_score
        FROM students st
        JOIN class_subjects cs ON st.class_id = cs.class_id
        JOIN subjects sub ON cs.subject_id = sub.id
        LEFT JOIN scores s ON st.id = s.student_id AND sub.id = s.subject_id AND s.exam_id = ?
        WHERE st.class_id = ?
        GROUP BY st.id
        ORDER BY total_score DESC
    ", [$examId, $classId]);
    
    // 计算各科目平均分和及格率、优秀率
    foreach ($subjects as $subject) {
        $subjectData = fetchOne("
            SELECT 
                AVG(IFNULL(s.score, 0)) AS average,
                COUNT(s.id) AS total,
                SUM(CASE WHEN s.score >= 60 THEN 1 ELSE 0 END) AS pass_count,
                SUM(CASE WHEN s.score >= 90 THEN 1 ELSE 0 END) AS excellent_count
            FROM scores s
            JOIN students st ON s.student_id = st.id
            WHERE st.class_id = ? AND s.subject_id = ? AND s.exam_id = ?
        ", [$classId, $subject['id'], $examId]);
        
        $subjectAverages[$subject['id']] = round($subjectData['average'], 1);
        $passRates[$subject['id']] = $subjectData['total'] > 0 ? round(($subjectData['pass_count'] / $subjectData['total']) * 100, 1) : 0;
        $excellentRates[$subject['id']] = $subjectData['total'] > 0 ? round(($subjectData['excellent_count'] / $subjectData['total']) * 100, 1) : 0;
        
        // 获取分数段分布
        $scoreRanges = [
            '0-59' => 0,
            '60-69' => 0,
            '70-79' => 0,
            '80-89' => 0,
            '90-100' => 0
        ];
        
        $scoreDistribution = fetchAll("
            SELECT 
                CASE 
                    WHEN s.score < 60 THEN '0-59'
                    WHEN s.score BETWEEN 60 AND 69 THEN '60-69'
                    WHEN s.score BETWEEN 70 AND 79 THEN '70-79'
                    WHEN s.score BETWEEN 80 AND 89 THEN '80-89'
                    ELSE '90-100'
                END AS score_range,
                COUNT(*) AS count
            FROM scores s
            JOIN students st ON s.student_id = st.id
            WHERE st.class_id = ? AND s.subject_id = ? AND s.exam_id = ?
            GROUP BY score_range
            ORDER BY score_range
        ", [$classId, $subject['id'], $examId]);
        
        foreach ($scoreDistribution as $range) {
            $scoreRanges[$range['score_range']] = (int)$range['count'];
        }
        
        $scoreDistributions[$subject['id']] = $scoreRanges;
    }
    
    // 计算班级总平均分
    $classAverageData = fetchOne("
        SELECT AVG(s.score) AS average
        FROM scores s
        JOIN students st ON s.student_id = st.id
        WHERE st.class_id = ? AND s.exam_id = ?
    ", [$classId, $examId]);
    
    $classAverage = round($classAverageData['average'], 1);
}

// 页面标题
$pageTitle = '班级成绩分析';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">班级成绩分析</h1>
            
            <!-- 选择控件 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-5 mb-2">
                            <label for="class_id">选择班级</label>
                            <select class="form-control" id="class_id" name="id" onchange="this.form.submit()">
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['grade'] . ' ' . $c['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($classId && count($exams) > 0): ?>
                            <div class="col-md-5 mb-2">
                                <label for="exam_id">选择考试</label>
                                <select class="form-control" id="exam_id" name="exam_id" onchange="this.form.submit()">
                                    <?php foreach ($exams as $e): ?>
                                        <option value="<?php echo $e['id']; ?>" <?php echo $examId == $e['id'] ? 'selected' : ''; ?>>
                                            <?php echo $e['name'] . ' (' . formatDate($e['exam_date']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2 mb-2 d-flex align-items-end">
                            <a href="class_manage.php" class="btn btn-secondary btn-block">返回班级管理</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($class && $exam): ?>
                <!-- 班级和考试基本信息 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2>
                            <?php echo $class['grade'] . ' ' . $class['name']; ?> - 
                            <?php echo $exam['name']; ?> (<?php echo formatDate($exam['exam_date']); ?>)
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>班级信息</h4>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        班主任
                                        <span><?php echo $class['class_teacher_name'] ? $class['class_teacher_name'] : '未指定'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        学生人数
                                        <span><?php echo count($studentScores); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        科目数量
                                        <span><?php echo count($subjects); ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h4>考试信息</h4>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        考试类型
                                        <span><?php echo $exam['type']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        考试日期
                                        <span><?php echo formatDate($exam['exam_date']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        班级平均分
                                        <span class="badge badge-primary badge-pill"><?php echo $classAverage; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 科目分析 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>各科目成绩分析</h3>
                    </div>
                    <div class="card-body">
                        <!-- 平均分对比图 -->
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <h4 class="text-center">科目平均分对比</h4>
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="subjectAverageChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 科目详细数据表格 -->
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="text-center mb-3">科目详细数据</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>科目</th>
                                                <th>平均分</th>
                                                <th>及格率</th>
                                                <th>优秀率</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subjects as $subject): ?>
                                                <tr>
                                                    <td><?php echo $subject['name']; ?></td>
                                                    <td><?php echo $subjectAverages[$subject['id']]; ?></td>
                                                    <td><?php echo $passRates[$subject['id']]; ?>%</td>
                                                    <td><?php echo $excellentRates[$subject['id']]; ?>%</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info" onclick="showSubjectDetails(<?php echo $subject['id']; ?>, '<?php echo $subject['name']; ?>')">
                                                            <i class="fas fa-chart-pie"></i> 详情
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 学生成绩 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>学生成绩排名</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($studentScores) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>排名</th>
                                            <th>姓名</th>
                                            <th>学号</th>
                                            <?php foreach ($subjects as $subject): ?>
                                                <th><?php echo $subject['name']; ?></th>
                                            <?php endforeach; ?>
                                            <th>总分</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($studentScores as $index => $student): ?>
                                            <?php
                                            // 解析科目成绩
                                            $scores = [];
                                            if (!empty($student['subject_scores'])) {
                                                $subjectScorePairs = explode(',', $student['subject_scores']);
                                                foreach ($subjectScorePairs as $pair) {
                                                    list($subjectName, $score) = explode(':', $pair);
                                                    $scores[$subjectName] = $score;
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo ($index + 1); ?></td>
                                                <td><?php echo $student['name']; ?></td>
                                                <td><?php echo $student['student_number']; ?></td>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <td>
                                                        <?php echo isset($scores[$subject['name']]) ? $scores[$subject['name']] : '-'; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td><?php echo $student['total_score']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">暂无学生成绩数据</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 科目详情模态框 -->
                <div class="modal fade" id="subjectDetailsModal" tabindex="-1" role="dialog" aria-labelledby="subjectDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="subjectDetailsModalLabel">科目详情</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="subjectDistributionChart"></canvas>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // 科目平均分图表
                    const subjectCtx = document.getElementById('subjectAverageChart').getContext('2d');
                    new Chart(subjectCtx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo implode(', ', array_map(function($subject) { return "'" . $subject['name'] . "'"; }, $subjects)); ?>],
                            datasets: [{
                                label: '平均分',
                                data: [<?php echo implode(', ', array_map(function($subject) use ($subjectAverages) { return $subjectAverages[$subject['id']]; }, $subjects)); ?>],
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            }
                        }
                    });
                });
                
                // 显示科目详情
                function showSubjectDetails(subjectId, subjectName) {
                    const modal = $('#subjectDetailsModal');
                    modal.find('.modal-title').text(subjectName + ' - 分数分布');
                    
                    // 获取科目分数分布数据
                    const distributionData = <?php echo json_encode($scoreDistributions); ?>;
                    const subjectData = distributionData[subjectId];
                    
                    // 创建饼图
                    const ctx = document.getElementById('subjectDistributionChart').getContext('2d');
                    if (window.distributionChart) {
                        window.distributionChart.destroy();
                    }
                    
                    window.distributionChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: Object.keys(subjectData),
                            datasets: [{
                                data: Object.values(subjectData),
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.7)',  // 0-59 (不及格)
                                    'rgba(255, 205, 86, 0.7)',  // 60-69 (及格)
                                    'rgba(75, 192, 192, 0.7)',  // 70-79 (中等)
                                    'rgba(54, 162, 235, 0.7)',  // 80-89 (良好)
                                    'rgba(153, 102, 255, 0.7)'  // 90-100 (优秀)
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ${value} 人 (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    modal.modal('show');
                }
                </script>
            <?php elseif (count($classes) > 0): ?>
                <div class="alert alert-info">请选择班级和考试以查看分析数据</div>
            <?php else: ?>
                <div class="alert alert-warning">暂无班级数据</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 