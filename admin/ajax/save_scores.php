<?php
/**
 * 成绩分析系统 - AJAX处理成绩保存请求
 */

// 设置内容类型为JSON
header('Content-Type: application/json');

// 启用错误显示（开发环境使用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入必要文件
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

// 检查是否是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => '权限不足'
    ]);
    exit;
}

// 获取POST数据
$postData = file_get_contents('php://input');
error_log("接收到的原始数据: " . $postData);

$data = json_decode($postData, true);

// 检查数据是否有效
if ($data === null) {
    echo json_encode([
        'success' => false,
        'message' => 'JSON解析错误: ' . json_last_error_msg()
    ]);
    exit;
}

if (!isset($data['exam_id']) || !isset($data['scores']) || !is_array($data['scores'])) {
    echo json_encode([
        'success' => false,
        'message' => '无效的数据格式: 缺少必要字段'
    ]);
    exit;
}

// 提取数据
$examId = (int)$data['exam_id'];
$scores = $data['scores'];

error_log("处理考试ID: $examId, 成绩数量: " . count($scores));

// 验证考试ID
$exam = fetchOne("SELECT id FROM exams WHERE id = $examId");
if (!$exam) {
    echo json_encode([
        'success' => false,
        'message' => '无效的考试ID: ' . $examId
    ]);
    exit;
}

// 获取考试关联的班级（如果有）
$examClass = fetchOne("SELECT class_id FROM exams WHERE id = $examId");
$defaultClassId = $examClass && isset($examClass['class_id']) ? $examClass['class_id'] : 0;

// 获取所有有效班级ID
$validClassIds = [];
$classesResult = query("SELECT id FROM classes");
if ($classesResult) {
    while ($row = $classesResult->fetch_assoc()) {
        $validClassIds[] = (int)$row['id'];
    }
}

// 如果默认班级ID无效，选取第一个有效班级ID
if ($defaultClassId <= 0 || !in_array($defaultClassId, $validClassIds)) {
    $defaultClassId = !empty($validClassIds) ? $validClassIds[0] : 0;
}

// 检查是否存在有效班级
if (empty($validClassIds)) {
    echo json_encode([
        'success' => false,
        'message' => '系统中没有可用的班级，请先创建班级'
    ]);
    exit;
}

// 记录操作日志
error_log("开始保存考试 $examId 的成绩数据: " . count($scores) . "条记录，默认班级ID: $defaultClassId");

// 获取数据库连接
$conn = getDBConnection();

try {
    // 开始事务
    $conn->begin_transaction();
    
    // 准备更新和插入语句
    $updateStmt = $conn->prepare("UPDATE scores SET score = ?, updated_by = ?, updated_at = NOW() 
                                  WHERE exam_id = ? AND student_id = ? AND subject_id = ?");
    
    $insertStmt = $conn->prepare("INSERT INTO scores (exam_id, student_id, subject_id, score, class_id, updated_by, updated_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())");
    
    // 准备学生查询和创建语句
    $findStudentStmt = $conn->prepare("SELECT id, class_id FROM students WHERE student_id = ? OR (name = ? AND student_id = '')");
    
    // 添加用户创建语句
    $createUserStmt = $conn->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'student')");
    
    $createStudentStmt = $conn->prepare("INSERT INTO students (student_id, name, class_id, user_id) VALUES (?, ?, ?, ?)");
    $updateStudentClassStmt = $conn->prepare("UPDATE students SET class_id = ? WHERE id = ?");
    
    $updatedCount = 0;
    $insertedCount = 0;
    $studentsCreated = 0;
    $studentsUpdated = 0;
    $errorCount = 0;
    
    // 处理每条成绩数据
    foreach ($scores as $item) {
        if (!isset($item['student_id']) || !isset($item['subject_id']) || !isset($item['score'])) {
            error_log("跳过无效数据: " . json_encode($item));
            $errorCount++;
            continue;
        }
        
        // 获取学生信息
        $studentId = isset($item['student_id']) ? trim($item['student_id']) : '';
        $studentName = isset($item['student_name']) ? trim($item['student_name']) : (isset($item['name']) ? trim($item['name']) : '');
        
        // 检查班级ID是否有效
        $classId = isset($item['class_id']) ? (int)$item['class_id'] : $defaultClassId;
        if (!in_array($classId, $validClassIds)) {
            error_log("提供的班级ID {$classId} 无效，使用默认班级 {$defaultClassId}");
            $classId = $defaultClassId;
        }
        
        $subjectId = (int)$item['subject_id'];
        $score = $item['score'] !== '' ? (float)$item['score'] : null;
        $updatedBy = $_SESSION['user_id'];
        
        if (empty($studentId) && empty($studentName)) {
            error_log("学生ID和姓名都为空，跳过");
            $errorCount++;
            continue;
        }
        
        error_log("处理学生: ID=$studentId, 姓名=$studentName, 班级=$classId");
        
        // 查找学生是否存在
        $findStudentStmt->bind_param('ss', $studentId, $studentName);
        $findStudentStmt->execute();
        $studentResult = $findStudentStmt->get_result();
        $student = $studentResult->fetch_assoc();
        
        $studentDbId = 0;
        
        if ($student) {
            // 学生存在，检查班级是否需要更新
            $studentDbId = $student['id'];
            $currentClassId = $student['class_id'];
            
            if ($classId > 0 && $currentClassId != $classId && in_array($classId, $validClassIds)) {
                // 班级变更，更新学生班级
                error_log("更新学生班级: 从 $currentClassId 到 $classId");
                $updateStudentClassStmt->bind_param('ii', $classId, $studentDbId);
                $updateStudentClassStmt->execute();
                $studentsUpdated++;
            }
        } else {
            // 学生不存在，需要先创建用户再创建学生
            error_log("创建新学生: ID=$studentId, 姓名=$studentName, 班级=$classId");
            
            // 检查班级是否有效
            if (!in_array($classId, $validClassIds)) {
                error_log("无效的班级ID: $classId，无法创建学生");
                $errorCount++;
                continue;
            }
            
            // 为学生创建用户账号
            // 使用学生ID作为用户名，初始密码为123456
            $username = !empty($studentId) ? $studentId : 's'.time().rand(100, 999);
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $createUserStmt->bind_param('sss', $username, $password, $studentName);
            
            if ($createUserStmt->execute()) {
                $userId = $conn->insert_id;
                error_log("为学生创建用户成功: 用户ID=$userId");
                
                // 创建学生记录
                $createStudentStmt->bind_param('ssii', $studentId, $studentName, $classId, $userId);
                if ($createStudentStmt->execute()) {
                    $studentDbId = $conn->insert_id;
                    $studentsCreated++;
                    error_log("新学生创建成功: 数据库ID=$studentDbId");
                } else {
                    error_log("创建学生失败: " . $createStudentStmt->error);
                    $errorCount++;
                    continue;
                }
            } else {
                error_log("为学生创建用户失败: " . $createUserStmt->error);
                $errorCount++;
                continue;
            }
        }
        
        // 确保有有效的学生ID
        if ($studentDbId <= 0) {
            error_log("无效的学生数据库ID，跳过成绩保存");
            $errorCount++;
            continue;
        }
        
        error_log("处理成绩: 学生ID=$studentDbId, 科目ID=$subjectId, 分数=$score");
        
        // 检查此成绩是否已存在
        $existingScore = fetchOne("SELECT id FROM scores 
                                  WHERE exam_id = $examId 
                                  AND student_id = $studentDbId 
                                  AND subject_id = $subjectId");
        
        if ($existingScore) {
            // 更新成绩
            error_log("更新现有成绩记录: ID=" . $existingScore['id']);
            $updateStmt->bind_param("diiii", $score, $updatedBy, $examId, $studentDbId, $subjectId);
            $result = $updateStmt->execute();
            
            if ($result) {
                $updatedCount++;
            } else {
                error_log("更新失败: " . $updateStmt->error);
                $errorCount++;
            }
        } else {
            // 插入新成绩
            error_log("插入新成绩: 班级ID=$classId");
            $insertStmt->bind_param("iiidii", $examId, $studentDbId, $subjectId, $score, $classId, $updatedBy);
            $result = $insertStmt->execute();
            
            if ($result) {
                $insertedCount++;
            } else {
                error_log("插入失败: " . $insertStmt->error);
                $errorCount++;
            }
        }
    }
    
    // 提交事务
    $conn->commit();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '成绩保存成功',
        'data' => [
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'students_created' => $studentsCreated,
            'students_updated' => $studentsUpdated,
            'errors' => $errorCount
        ]
    ]);
    
    error_log("考试 $examId 的成绩数据保存成功: 插入=$insertedCount, 更新=$updatedCount, 新建学生=$studentsCreated, 更新学生=$studentsUpdated, 错误=$errorCount");
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    
    // 记录错误
    $errorMsg = $e->getMessage();
    error_log("保存成绩失败: " . $errorMsg . "\n" . $e->getTraceAsString());
    
    // 返回错误响应
    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ]);
} 