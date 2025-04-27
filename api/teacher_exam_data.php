<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// 检查用户是否为教师
if ($_SESSION['role'] != 'teacher') {
    echo json_encode(['error' => '无权访问']);
    exit;
}

// 获取GET参数
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// 验证考试ID
if ($exam_id <= 0) {
    echo json_encode(['error' => '无效的考试ID']);
    exit;
}

// 获取教师ID
$teacher_id = $_SESSION['user_id'];

// 检查教师对班级和科目的权限
if ($class_id > 0 && $subject_id > 0) {
    // 验证教师是否有权限访问该班级和科目
    $permission_sql = "SELECT COUNT(*) as count FROM class_subject_teacher 
                       WHERE teacher_id = ? AND class_id = ? AND subject_id = ?";
    $stmt = $conn->prepare($permission_sql);
    if ($stmt) {
        $stmt->bind_param("iii", $teacher_id, $class_id, $subject_id);
        $stmt->execute();
        $permission_result = $stmt->get_result();
        $permission = $permission_result->fetch_assoc();
        
        if ($permission['count'] == 0) {
            // 检查是否是班主任
            $class_teacher_sql = "SELECT COUNT(*) as count FROM classes 
                                 WHERE class_teacher_id = ? AND id = ?";
            $stmt = $conn->prepare($class_teacher_sql);
            $stmt->bind_param("ii", $teacher_id, $class_id);
            $stmt->execute();
            $class_teacher_result = $stmt->get_result();
            $class_teacher = $class_teacher_result->fetch_assoc();
            
            if ($class_teacher['count'] == 0) {
                // 不是任课教师也不是班主任
                echo json_encode(['error' => '您没有权限查看该班级的该科目数据']);
                exit;
            }
        }
    }
}

// 使用通用函数格式化考试数据
$response = formatExamData($exam_id, $class_id, $subject_id, '', $conn);

// 返回JSON
header('Content-Type: application/json');
echo json_encode($response);
?> 