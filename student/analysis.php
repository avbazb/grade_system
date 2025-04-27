<?php
/**
 * 成绩分析系统 - 学生成绩分析页面
 * 
 * 本页面提供学生个人成绩的各种分析图表和数据
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '成绩分析';

// 包含学生页面头部
include_once '../components/student_header.php';

// 获取学生考试数据
$examData = array();
$subjectTrends = array();
$subjectNames = array();
$rankingData = array();

if ($studentId) {
    // 获取学生所有考试的数据
    $sql = "SELECT e.id, e.name, e.exam_date, e.type 
            FROM exams e 
            INNER JOIN scores s ON e.id = s.exam_id 
            WHERE s.student_id = ? 
            GROUP BY e.id 
            ORDER BY e.exam_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取所有科目
    $sql = "SELECT DISTINCT s.id, s.name 
            FROM subjects s 
            INNER JOIN scores sc ON s.id = sc.subject_id 
            WHERE sc.student_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 提取科目名称列表
    $subjectNames = array_column($subjects, 'name');
    
    // 获取每次考试每个科目的成绩和班级平均分
    foreach ($exams as $exam) {
        $examId = $exam['id'];
        $examName = $exam['name'];
        $examScores = array();
        $classAvg = array();
        
        foreach ($subjects as $subject) {
            $subjectId = $subject['id'];
            $subjectName = $subject['name'];
            
            // 获取学生成绩
            $sql = "SELECT s.score, es.full_score 
                    FROM scores s 
                    INNER JOIN exam_subjects es ON s.exam_id = es.exam_id AND s.subject_id = es.subject_id 
                    WHERE s.student_id = ? AND s.exam_id = ? AND s.subject_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$studentId, $examId, $subjectId]);
            $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($scoreData) {
                $score = $scoreData['score'];
                $fullScore = $scoreData['full_score'];
                $percentage = ($score / $fullScore) * 100;
                
                $examScores[$subjectName] = [
                    'score' => $score,
                    'full_score' => $fullScore,
                    'percentage' => round($percentage, 1)
                ];
                
                // 收集科目趋势数据
                if (!isset($subjectTrends[$subjectName])) {
                    $subjectTrends[$subjectName] = array();
                }
                $subjectTrends[$subjectName][$examName] = [
                    'score' => $score,
                    'full_score' => $fullScore,
                    'percentage' => round($percentage, 1)
                ];
                
                // 获取班级平均分
                $sql = "SELECT AVG(s.score) as avg_score 
                        FROM scores s 
                        WHERE s.exam_id = ? AND s.subject_id = ? AND s.class_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$examId, $subjectId, $studentInfo['class_id']]);
                $avgData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($avgData) {
                    $classAvg[$subjectName] = [
                        'avg_score' => round($avgData['avg_score'], 1),
                        'avg_percentage' => round(($avgData['avg_score'] / $fullScore) * 100, 1)
                    ];
                }
            }
        }
        
        // 获取总分和排名数据
        $sql = "SELECT 
                student_id,
                SUM(s.score) as total_score,
                (SELECT COUNT(*) + 1 FROM 
                    (SELECT student_id, SUM(score) as student_total 
                     FROM scores 
                     WHERE exam_id = ? AND class_id = ? 
                     GROUP BY student_id) as t 
                 WHERE t.student_total > (SELECT SUM(score) FROM scores WHERE exam_id = ? AND student_id = ?)) as class_rank,
                (SELECT COUNT(DISTINCT student_id) FROM scores WHERE exam_id = ? AND class_id = ?) as total_students
                FROM scores s
                WHERE s.exam_id = ? AND s.student_id = ?
                GROUP BY s.student_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$examId, $studentInfo['class_id'], $examId, $studentId, $examId, $studentInfo['class_id'], $examId, $studentId]);
        $rankData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rankData) {
            $rankingData[$examName] = [
                'rank' => $rankData['class_rank'],
                'total' => $rankData['total_students'],
                'total_score' => $rankData['total_score']
            ];
        }
        
        $examData[$examName] = [
            'id' => $examId,
            'date' => $exam['exam_date'],
            'type' => $exam['type'],
            'scores' => $examScores,
            'class_avg' => $classAvg
        ];
    }
}

// 获取最近一次考试数据用于雷达图
$latestExamName = !empty($exams) ? $exams[0]['name'] : '';
$latestExamData = !empty($examData[$latestExamName]) ? $examData[$latestExamName] : [];
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>分析工具</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="#overview">
                            <i class="fas fa-chart-pie"></i> 总体概览
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#subject-trends">
                            <i class="fas fa-chart-line"></i> 学科趋势
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#rank-analysis">
                            <i class="fas fa-trophy"></i> 排名分析
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#strength-weakness">
                            <i class="fas fa-balance-scale"></i> 优势与弱项
                        </a>
                    </li>
                </ul>
                
                <h3 class="mt-4">考试选择</h3>
                <ul class="nav flex-column">
                    <?php foreach ($examData as $examName => $exam): ?>
                    <li class="nav-item">
                        <a class="nav-link exam-selector" data-exam="<?php echo $examName; ?>" href="javascript:void(0);">
                            <i class="fas fa-file-alt"></i> <?php echo $examName; ?>
                            <small class="text-muted d-block"><?php echo $exam['date']; ?></small>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <!-- 主内容区 -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">
                        <i class="fas fa-chart-bar text-primary mr-2"></i>
                        成绩分析
                    </h2>
                    <p class="card-text">
                        本页面提供您的成绩分析，包括各科目表现、趋势变化、排名情况以及优势科目分析。您可以从侧边栏选择不同的考试查看详细数据。
                    </p>
                </div>
            </div>
            
            <!-- 总体概览 -->
            <div id="overview" class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie mr-2"></i>
                        总体概览
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($latestExamData)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="text-center mb-3">
                                <?php echo $latestExamName; ?> 成绩详情
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>科目</th>
                                            <th>成绩</th>
                                            <th>班级平均</th>
                                            <th>与平均分差</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($latestExamData['scores'] as $subject => $data): 
                                            $avgScore = isset($latestExamData['class_avg'][$subject]) ? $latestExamData['class_avg'][$subject]['avg_score'] : 0;
                                            $diff = $data['score'] - $avgScore;
                                            $diffClass = $diff >= 0 ? 'text-success' : 'text-danger';
                                            $diffIcon = $diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                        ?>
                                        <tr>
                                            <td><?php echo $subject; ?></td>
                                            <td><?php echo $data['score']; ?> / <?php echo $data['full_score']; ?></td>
                                            <td><?php echo $avgScore; ?></td>
                                            <td class="<?php echo $diffClass; ?>">
                                                <i class="fas <?php echo $diffIcon; ?> mr-1"></i>
                                                <?php echo abs($diff); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (isset($rankingData[$latestExamName])): ?>
                            <div class="text-center mt-3">
                                <div class="d-inline-block p-3 border rounded">
                                    <div class="h4 mb-1">班级排名</div>
                                    <div class="display-4 text-primary">
                                        <?php echo $rankingData[$latestExamName]['rank']; ?>
                                        <small class="text-muted h4">/ <?php echo $rankingData[$latestExamName]['total']; ?></small>
                                    </div>
                                </div>
                                <div class="d-inline-block p-3 border rounded ml-3">
                                    <div class="h4 mb-1">总分</div>
                                    <div class="display-4 text-primary">
                                        <?php echo $rankingData[$latestExamName]['total_score']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> 暂无考试数据，请等待考试成绩上传。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 学科趋势（移除图表，显示数据表格） -->
            <div id="subject-trends" class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line mr-2"></i>
                        学科趋势
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($subjectTrends)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> 科目趋势数据表格展示：
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>科目</th>
                                    <?php 
                                    $examNames = array_keys($examData);
                                    // 反转考试顺序，从早到晚显示
                                    $examNames = array_reverse($examNames);
                                    foreach ($examNames as $exam): 
                                    ?>
                                    <th><?php echo $exam; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjectTrends as $subject => $data): ?>
                                <tr>
                                    <td><strong><?php echo $subject; ?></strong></td>
                                    <?php foreach ($examNames as $exam): ?>
                                    <td>
                                        <?php if (isset($data[$exam])): ?>
                                        <?php echo $data[$exam]['score']; ?> / <?php echo $data[$exam]['full_score']; ?>
                                        <span class="text-muted">(<?php echo $data[$exam]['percentage']; ?>%)</span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> 暂无足够的考试数据来生成趋势信息。
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 更多分析部分将在第二部分添加 -->
        </div>
    </div>
</div>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 