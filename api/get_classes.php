<?php
/**
 * 成绩分析系统 - API：获取指定年级的班级列表
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 设置响应类型为JSON
header('Content-Type: application/json');

// 检查是否有年级参数
if (!isset($_GET['grade']) || empty($_GET['grade'])) {
    echo json_encode(['error' => '缺少年级参数']);
    exit;
}

// 获取年级参数
$grade = $_GET['grade'];

try {
    // 获取指定年级的班级列表
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name FROM classes WHERE grade = ? ORDER BY name");
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
    
    // 返回班级列表
    echo json_encode($classes);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode(['error' => '获取班级列表失败: ' . $e->getMessage()]);
}
?> 