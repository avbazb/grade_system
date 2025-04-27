<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';


// 获取GET参数
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$grade = isset($_GET['grade']) ? $_GET['grade'] : '';
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// 验证考试ID
if ($exam_id <= 0) {
    echo json_encode(['error' => '无效的考试ID']);
    exit;
}

// 使用通用函数格式化考试数据
$response = formatExamData($exam_id, $class_id, $subject_id, $grade, $conn);

// 返回JSON
header('Content-Type: application/json');
echo json_encode($response);
?> 