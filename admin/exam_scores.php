<?php
/**
 * 成绩分析系统 - 考试成绩管理页面
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

// 构建查询语句
$sql = "SELECT s.id, s.student_id, s.name, s.class_id, c.grade, c.name as class_name, 
        GROUP_CONCAT(CONCAT(sc.subject_id, ':', sc.score) SEPARATOR ',') as subject_scores
        FROM students s
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN scores sc ON s.id = sc.student_id AND sc.exam_id = $examId";

$where = [];

if ($classId > 0) {
    $where[] = "s.class_id = $classId";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY s.id ORDER BY c.grade, c.name, s.name";

// 获取学生成绩数据
$students = fetchAll($sql);

// 处理成绩数据，转换为更易使用的格式
foreach ($students as &$student) {
    $scoresRaw = $student['subject_scores'] ? explode(',', $student['subject_scores']) : [];
    $scores = [];
    
    foreach ($scoresRaw as $scoreItem) {
        $parts = explode(':', $scoreItem);
        if (count($parts) == 2) {
            $subjId = (int)$parts[0];
            $score = $parts[1] !== '' ? (float)$parts[1] : null;
            $scores[$subjId] = $score;
        }
    }
    
    $student['scores'] = $scores;
}

// 处理成绩更新请求
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    $scores = $_POST['scores'] ?? [];
    $subjects = $_POST['subjects'] ?? [];
    
    if (!empty($studentIds) && !empty($scores) && !empty($subjects)) {
        $conn = getDBConnection();
        $conn->begin_transaction();
        
        try {
            foreach ($studentIds as $index => $studentId) {
                $score = $scores[$index] !== '' ? (float)$scores[$index] : null;
                $subjectId = (int)$subjects[$index];
                $studentId = (int)$studentId;
                
                // 检查此成绩是否已存在
                $existingScore = fetchOne("SELECT id FROM scores 
                                          WHERE exam_id = $examId 
                                          AND student_id = $studentId 
                                          AND subject_id = $subjectId");
                
                if ($existingScore) {
                    // 更新成绩
                    $stmt = $conn->prepare("UPDATE scores SET score = ?, updated_by = ?, updated_at = NOW() 
                                           WHERE exam_id = ? AND student_id = ? AND subject_id = ?");
                    $stmt->bind_param("diiii", $score, $_SESSION['user_id'], $examId, $studentId, $subjectId);
                    $stmt->execute();
                } else {
                    // 获取学生班级
                    $studentInfo = fetchOne("SELECT class_id FROM students WHERE id = $studentId");
                    
                    if ($studentInfo) {
                        // 插入新成绩
                        $stmt = $conn->prepare("INSERT INTO scores (exam_id, student_id, subject_id, score, class_id, updated_by) 
                                               VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiidii", $examId, $studentId, $subjectId, $score, $studentInfo['class_id'], $_SESSION['user_id']);
                        $stmt->execute();
                    }
                }
            }
            
            // 提交事务
            $conn->commit();
            
            // 设置成功消息
            $success = '成绩更新成功！';
            
            // 重新加载页面以显示最新数据
            header("Location: exam_scores.php?id=$examId&class_id=$classId&subject_id=$subjectId&percentage=" . ($showPercentage ? '1' : '0') . "&success=1");
            exit;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            
            // 设置错误消息
            $error = '成绩更新失败：' . $e->getMessage();
        }
    } else {
        $error = '无效的成绩数据';
    }
}

// 检查是否有成功消息
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = '成绩更新成功！';
}

// 页面标题
$pageTitle = '考试成绩管理';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row mb-3">
        <div class="col">
            <h1>考试成绩管理</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">首页</a></li>
                    <li class="breadcrumb-item"><a href="exams.php">考试管理</a></li>
                    <li class="breadcrumb-item"><a href="exam_details.php?id=<?php echo $examId; ?>">考试详情</a></li>
                    <li class="breadcrumb-item active" aria-current="page">成绩管理</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
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
    
    <div class="card mb-4 anim-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">成绩查询</h5>
                </div>
                <div class="col-auto">
                    <a href="exam_upload.php?id=<?php echo $examId; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-upload"></i> 上传成绩
                    </a>
                    <button type="button" id="export-excel" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> 导出Excel
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form action="" method="get" id="filter-form" class="mb-4">
                <input type="hidden" name="id" value="<?php echo $examId; ?>">
                
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
                                <i class="fas fa-search"></i> 查询
                            </button>
                            <a href="exam_scores.php?id=<?php echo $examId; ?>" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> 重置
                            </a>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="search-box mb-3">
                <input type="text" id="search-students" class="search-input" placeholder="搜索学生...">
                <i class="fas fa-search search-icon"></i>
            </div>
            
            <form action="" method="post">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="scores-table">
                        <thead>
                            <tr>
                                <th>学号</th>
                                <th>班级</th>
                                <th>姓名</th>
                                
                                <?php 
                                // 如果选择了特定科目，只显示该科目
                                if ($subjectId > 0 && isset($subjectMap[$subjectId])) {
                                    echo '<th>' . $subjectMap[$subjectId]['name'] . '</th>';
                                } else {
                                    // 否则显示所有科目
                                    foreach ($examSubjects as $subject) {
                                        echo '<th>' . $subject['subject_name'] . '</th>';
                                    }
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="student-list">
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                    <tr class="student-row">
                                        <td><?php echo $student['student_id']; ?></td>
                                        <td><?php echo $student['grade'] . ' ' . $student['class_name']; ?></td>
                                        <td><?php echo $student['name']; ?></td>
                                        
                                        <?php
                                        // 如果选择了特定科目，只显示该科目的成绩输入框
                                        if ($subjectId > 0 && isset($subjectMap[$subjectId])) {
                                            $score = isset($student['scores'][$subjectId]) ? $student['scores'][$subjectId] : null;
                                            $displayScore = $score !== null ? $score : '';
                                            
                                            if ($showPercentage && $score !== null) {
                                                $percentage = convertToPercentage($score, $subjectMap[$subjectId]['full_score']);
                                                $displayScore = $percentage . '%';
                                            }
                                            
                                            echo '<td>';
                                            echo '<input type="hidden" name="student_ids[]" value="' . $student['id'] . '">';
                                            echo '<input type="hidden" name="subjects[]" value="' . $subjectId . '">';
                                            
                                            if ($showPercentage) {
                                                echo $displayScore;
                                            } else {
                                                echo '<input type="number" name="scores[]" class="form-control form-control-sm" value="' . $displayScore . '" min="0" max="' . $subjectMap[$subjectId]['full_score'] . '" step="0.5">';
                                            }
                                            
                                            echo '</td>';
                                        } else {
                                            // 否则遍历显示所有科目的成绩
                                            foreach ($examSubjects as $subject) {
                                                $subjectId = $subject['subject_id'];
                                                $score = isset($student['scores'][$subjectId]) ? $student['scores'][$subjectId] : null;
                                                $displayScore = $score !== null ? $score : '-';
                                                
                                                if ($showPercentage && $score !== null) {
                                                    $percentage = convertToPercentage($score, $subject['full_score']);
                                                    $displayScore = $percentage . '%';
                                                }
                                                
                                                echo '<td>' . $displayScore . '</td>';
                                            }
                                        }
                                        ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $subjectId > 0 ? 4 : count($examSubjects) + 3; ?>" class="text-center">暂无成绩数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($subjectId > 0 && !$showPercentage): ?>
                    <div class="mt-3 text-right">
                        <button type="submit" name="save_scores" class="btn btn-success">
                            <i class="fas fa-save"></i> 保存成绩
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 搜索功能
    const searchInput = document.getElementById('search-students');
    const studentRows = document.querySelectorAll('.student-row');
    
    searchInput.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        
        studentRows.forEach(row => {
            const studentId = row.cells[0].textContent.toLowerCase();
            const className = row.cells[1].textContent.toLowerCase();
            const studentName = row.cells[2].textContent.toLowerCase();
            
            if (studentId.includes(searchText) || className.includes(searchText) || studentName.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // 导出Excel功能
    document.getElementById('export-excel').addEventListener('click', function() {
        const table = document.getElementById('scores-table');
        const examName = <?php echo json_encode($exam['name']); ?>;
        
        tableToExcel(table.id, examName + '_成绩表');
    });
    
    // 添加动画效果
    studentRows.forEach((row, index) => {
        row.style.opacity = 0;
        row.style.transform = 'translateY(10px)';
        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        setTimeout(() => {
            row.style.opacity = 1;
            row.style.transform = 'translateY(0)';
        }, 20 * index);
    });
    
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