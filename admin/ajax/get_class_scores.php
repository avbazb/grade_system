<?php
/**
 * 成绩分析系统 - 获取班级平均分AJAX接口
 */

// 引入必要文件
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

// 检查权限
requireAdmin();

// 设置返回类型为JSON
header('Content-Type: application/json');

// 获取考试ID
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if ($examId <= 0) {
    echo json_encode([]);
    exit;
}

// 获取参与考试的班级平均分数据
$classScores = fetchAll("SELECT c.id, CONCAT(c.grade, ' ', c.name) as class_name, 
                        AVG(s.score) as avg_score 
                        FROM scores s 
                        JOIN classes c ON s.class_id = c.id 
                        WHERE s.exam_id = $examId AND s.score IS NOT NULL 
                        GROUP BY s.class_id 
                        ORDER BY avg_score DESC");

// 返回数据
echo json_encode($classScores);
exit; 