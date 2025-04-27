<?php
/**
 * 成绩分析系统 - 学生首页
 * 
 * 本页面展示学生最近的考试成绩和整体学习进展
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '学生首页';

// 包含学生页面头部
include_once '../components/student_header.php';

// 获取最近一次考试信息
$latestExam = null;
$latestScores = array();
$examPerformance = array();
$classAverage = array();
$subjects = array();

if ($studentId) {
    // 获取学生所在班级的ID
    $classId = $studentInfo['class_id'];
    
    // 获取最近一次考试信息
    $sql = "SELECT e.* FROM exams e 
            INNER JOIN scores s ON e.id = s.exam_id 
            WHERE s.student_id = ? 
            ORDER BY e.exam_date DESC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $latestExam = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latestExam) {
        // 获取该考试的所有科目及分数
        $sql = "SELECT s.score, e.full_score, sub.name as subject_name, s.subject_id 
                FROM scores s 
                INNER JOIN exam_subjects e ON s.subject_id = e.subject_id AND s.exam_id = e.exam_id
                INNER JOIN subjects sub ON s.subject_id = sub.id
                WHERE s.student_id = ? AND s.exam_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$studentId, $latestExam['id']]);
        $latestScores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 计算总分和平均分
        $totalScore = 0;
        $totalFullScore = 0;
        foreach ($latestScores as $score) {
            $totalScore += $score['score'];
            $totalFullScore += $score['full_score'];
            
            // 为图表准备数据
            $subjects[] = $score['subject_name'];
            $examPerformance[] = $score['score'];
            
            // 获取班级平均分
            $sql = "SELECT AVG(score) as avg_score 
                    FROM scores 
                    WHERE exam_id = ? AND subject_id = ? AND class_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$latestExam['id'], $score['subject_id'], $classId]);
            $avgResult = $stmt->fetch(PDO::FETCH_ASSOC);
            // 确保avg_score存在并且不为null，再进行round计算
            $avgScore = isset($avgResult['avg_score']) ? $avgResult['avg_score'] : 0;
            $classAverage[] = round($avgScore, 1);
        }
        
        // 计算最近考试的总分和总平均分
        $overallPercentage = ($totalScore / $totalFullScore) * 100;
    }
    
    // 获取学生近期学习状态（这里可以是最近几次考试的总分百分比）
    $sql = "SELECT e.id, e.name, e.exam_date, 
            (SUM(s.score) / SUM(es.full_score)) * 100 as percentage 
            FROM exams e 
            INNER JOIN scores s ON e.id = s.exam_id 
            INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id 
            WHERE s.student_id = ? 
            GROUP BY e.id
            ORDER BY e.exam_date DESC 
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $recentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取未完成的作业或任务（示例数据，实际项目中需要创建对应表）
    $pendingTasks = [
        ['name' => '数学作业', 'due_date' => date('Y-m-d', strtotime('+2 days')), 'subject' => '数学'],
        ['name' => '语文作文', 'due_date' => date('Y-m-d', strtotime('+1 week')), 'subject' => '语文'],
        ['name' => '英语口语练习', 'due_date' => date('Y-m-d', strtotime('+3 days')), 'subject' => '英语']
    ];
}
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>个人信息</h3>
                <?php if ($studentInfo): ?>
                <div class="student-info">
                    <p><strong>姓名：</strong> <?php echo $studentInfo['name']; ?></p>
                    <p><strong>学号：</strong> <?php echo $studentInfo['student_id']; ?></p>
                    <p><strong>班级：</strong> <?php echo getClassName($studentInfo['class_id']); ?></p>
                </div>
                <?php endif; ?>
                
                <h3 class="mt-4">快速导航</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/student/index.php">
                            <i class="fas fa-tachometer-alt"></i> 学习概览
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
            <!-- 欢迎信息 -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">
                        <i class="fas fa-user-graduate text-primary mr-2"></i>
                        欢迎您，<?php echo $currentUser['name']; ?>！
                    </h2>
                    <p class="card-text">
                        这里是您的个人学习中心，您可以查看成绩、分析学习进度，以及了解您在班级中的表现。
                    </p>
                </div>
            </div>
            
            <!-- 最近一次考试成绩 -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line mr-2"></i>
                        <?php echo $latestExam ? '最近考试：'.$latestExam['name'] : '暂无考试数据'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($latestExam && !empty($latestScores)): ?>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center mb-4">
                                    <div class="display-4 text-primary font-weight-bold">
                                        <?php echo number_format($overallPercentage, 1); ?>%
                                    </div>
                                    <p class="text-muted">总体完成率</p>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>科目</th>
                                                <th>成绩</th>
                                                <th>满分</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($latestScores as $score): ?>
                                            <tr>
                                                <td><?php echo $score['subject_name']; ?></td>
                                                <td><?php echo $score['score']; ?></td>
                                                <td><?php echo $score['full_score']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <canvas id="scoreComparisonChart" width="400" height="250"></canvas>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> 暂无考试数据，请等待老师上传成绩。
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 学习进度 -->
            <div class="row">
                <div class="col-md-7">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-area mr-2"></i>
                                学习进展
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentExams)): ?>
                                <canvas id="learningProgressChart" width="400" height="250"></canvas>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> 暂无历史考试数据。
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 确保Chart.js被正确加载
if (typeof Chart === 'undefined') {
    // 如果没有加载，那么我们添加一个新的script标签来加载它
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"><\/script>');
}

// 柱状图 - 科目成绩对比
<?php if ($latestExam && !empty($subjects)): ?>
// 在页面加载完成后初始化图表
window.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded 事件被触发');
    
    // 检查Canvas元素是否存在
    const scoreComparisonCanvas = document.getElementById('scoreComparisonChart');
    if (scoreComparisonCanvas) {
        console.log('找到scoreComparisonChart元素');
        console.log('科目数据:', <?php echo json_encode($subjects); ?>);
        console.log('成绩数据:', <?php echo json_encode($examPerformance); ?>);
        console.log('班级平均数据:', <?php echo json_encode($classAverage); ?>);
    } else {
        console.error('未找到scoreComparisonChart元素');
    }
    
    // 等待一小段时间确保Chart.js加载完成
    setTimeout(function() {
        // 检查Chart是否被定义
        if (typeof Chart === 'undefined') {
            console.error('Chart.js 未加载');
            return;
        }
        
        console.log('开始创建科目成绩图表...');
        
        // 科目成绩对比图
        try {
            const scoreCtx = document.getElementById('scoreComparisonChart').getContext('2d');
            const scoreChart = new Chart(scoreCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($subjects); ?>,
                    datasets: [
                        {
                            label: '我的成绩',
                            data: <?php echo json_encode($examPerformance); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: '班级平均',
                            data: <?php echo json_encode($classAverage); ?>,
                            backgroundColor: 'rgba(255, 159, 64, 0.6)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: '科目成绩与班级平均分对比'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            console.log('科目成绩图表创建成功');
        } catch(error) {
            console.error('创建科目成绩图表时出错:', error);
        }
        
        <?php if (!empty($recentExams)): ?>
        // 学习进度折线图
        try {
            const learningProgressCanvas = document.getElementById('learningProgressChart');
            if (learningProgressCanvas) {
                console.log('找到learningProgressChart元素');
                
                const progressCtx = document.getElementById('learningProgressChart').getContext('2d');
                const progressChart = new Chart(progressCtx, {
                    type: 'line',
                    data: {
                        labels: [
                            <?php 
                            $examLabels = [];
                            $examScores = [];
                            foreach (array_reverse($recentExams) as $exam) {
                                $examLabels[] = $exam['name'];
                                $examScores[] = round($exam['percentage'], 1);
                            }
                            echo '"' . implode('", "', $examLabels) . '"';
                            ?>
                        ],
                        datasets: [{
                            label: '百分比(%)',
                            data: [<?php echo implode(', ', $examScores); ?>],
                            fill: true,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: '历次考试总体表现'
                            }
                        },
                        scales: {
                            y: {
                                min: 0,
                                max: 100
                            }
                        }
                    }
                });
                console.log('学习进度图表创建成功');
            } else {
                console.error('未找到learningProgressChart元素');
            }
        } catch(error) {
            console.error('创建学习进度图表时出错:', error);
        }
        <?php endif; ?>
    }, 500); // 等待500毫秒确保一切就绪
});
<?php endif; ?>
</script>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 