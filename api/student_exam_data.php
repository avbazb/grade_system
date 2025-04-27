<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// 检查用户是否为学生
if ($_SESSION['role'] != 'student') {
    echo json_encode(['error' => '无权访问']);
    exit;
}

// 获取GET参数
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// 验证考试ID
if ($exam_id <= 0) {
    echo json_encode(['error' => '无效的考试ID']);
    exit;
}

// 获取学生ID
$student_id = $_SESSION['user_id'];

// 获取学生所在班级
$class_sql = "SELECT c.id, c.grade FROM students s JOIN classes c ON s.class_id = c.id WHERE s.id = ?";
$stmt = $conn->prepare($class_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$class_result = $stmt->get_result();

if ($class_result->num_rows == 0) {
    echo json_encode(['error' => '找不到学生班级信息']);
    exit;
}

$class_data = $class_result->fetch_assoc();
$class_id = $class_data['id'];
$grade = $class_data['grade'];

// 获取考试信息
$exam_sql = "SELECT name FROM exams WHERE id = ?";
$stmt = $conn->prepare($exam_sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam_result = $stmt->get_result();

if ($exam_result->num_rows == 0) {
    echo json_encode(['error' => '找不到考试信息']);
    exit;
}

$exam_data = $exam_result->fetch_assoc();
$exam_name = $exam_data['name'];

// 获取学生信息
$student_sql = "SELECT name FROM students WHERE id = ?";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_result = $stmt->get_result();
$student_data = $student_result->fetch_assoc();
$student_name = $student_data['name'];

// 获取科目及成绩信息
$subjects_sql = "SELECT es.subject_id, s.name AS subject_name, es.full_score, 
                 sc.score, 
                 (SELECT AVG(sc2.score) FROM scores sc2 
                  JOIN students st ON sc2.student_id = st.id 
                  WHERE sc2.exam_id = ? AND sc2.subject_id = es.subject_id AND st.class_id = ?) AS class_avg,
                 (SELECT AVG(sc3.score) FROM scores sc3 
                  JOIN students st3 ON sc3.student_id = st3.id 
                  JOIN classes c3 ON st3.class_id = c3.id 
                  WHERE sc3.exam_id = ? AND sc3.subject_id = es.subject_id AND c3.grade = ?) AS grade_avg
                 FROM exam_subjects es
                 JOIN subjects s ON es.subject_id = s.id
                 LEFT JOIN scores sc ON sc.exam_id = es.exam_id AND sc.subject_id = es.subject_id AND sc.student_id = ?
                 WHERE es.exam_id = ?";

$stmt = $conn->prepare($subjects_sql);
$stmt->bind_param("iiiiiii", $exam_id, $class_id, $exam_id, $grade, $student_id, $exam_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

// 准备返回数据
$response = [
    'student_name' => $student_name,
    'exam_name' => $exam_name,
    'subjects' => [],
    'class_avg' => 0,
    'grade_avg' => 0
];

$total_score = 0;
$total_class_avg = 0;
$total_grade_avg = 0;
$subject_count = 0;

while ($subject = $subjects_result->fetch_assoc()) {
    $subject_data = [
        'id' => $subject['subject_id'],
        'name' => $subject['subject_name'],
        'full_score' => $subject['full_score'],
        'score' => $subject['score'] ? $subject['score'] : 0,
        'class_avg' => round($subject['class_avg'], 1),
        'grade_avg' => round($subject['grade_avg'], 1)
    ];
    
    $response['subjects'][] = $subject_data;
    
    $total_score += $subject_data['score'];
    $total_class_avg += $subject_data['class_avg'];
    $total_grade_avg += $subject_data['grade_avg'];
    $subject_count++;
}

// 计算总平均分
if ($subject_count > 0) {
    $response['total_score'] = $total_score;
    $response['class_avg'] = round($total_class_avg / $subject_count, 1);
    $response['grade_avg'] = round($total_grade_avg / $subject_count, 1);
}

// 返回JSON
header('Content-Type: application/json');
echo json_encode($response);
?> 