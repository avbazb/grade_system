<?php
/**
 * 成绩分析系统 - 学生信息编辑处理
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student_manage.php');
}

// 初始化消息
$message = '';
$messageType = '';

// 获取表单数据
$studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
$name = trim($_POST['name']);
$studentNumber = trim($_POST['student_number']);
$classId = (int)$_POST['class_id'];
$gender = $_POST['gender'];
$password = trim($_POST['password']);

// 验证数据
if (empty($studentId) || empty($name) || empty($studentNumber) || empty($classId)) {
    $message = '请填写所有必要信息';
    $messageType = 'danger';
} else {
    // 获取学生信息
    $student = fetchOne("SELECT s.*, u.id AS user_id FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?", [$studentId]);
    
    if (!$student) {
        $message = '找不到该学生';
        $messageType = 'danger';
    } else {
        // 检查学号是否已被其他学生使用
        $existingStudent = fetchOne("SELECT id FROM students WHERE student_number = ? AND id != ?", [$studentNumber, $studentId]);
        
        if ($existingStudent) {
            $message = '该学号已被其他学生使用，请使用其他学号';
            $messageType = 'danger';
        } else {
            // 更新学生信息
            $result = executeQuery("UPDATE students SET name = ?, student_number = ?, class_id = ?, gender = ? WHERE id = ?", 
                [$name, $studentNumber, $classId, $gender, $studentId]);
            
            // 更新用户信息
            $userResult = executeQuery("UPDATE users SET name = ?, username = ? WHERE id = ?", 
                [$name, $studentNumber, $student['user_id']]);
            
            // 如果提供了密码，更新密码
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $student['user_id']]);
            }
            
            if ($result && $userResult) {
                $message = '学生信息更新成功';
                $messageType = 'success';
            } else {
                $message = '学生信息更新失败，请重试';
                $messageType = 'danger';
            }
        }
    }
}

// 存储消息并重定向
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $messageType;

// 重定向回学生管理页面
redirect('student_manage.php');
?> 