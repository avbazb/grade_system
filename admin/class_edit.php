<?php
/**
 * 成绩分析系统 - 班级信息编辑
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 初始化消息
$successMessage = '';
$errorMessage = '';

// 获取班级ID
$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$classId) {
    redirect('class_manage.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $className = trim($_POST['name']);
    $grade = trim($_POST['grade']);
    $classTeacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    // 验证数据
    if (empty($className) || empty($grade)) {
        $errorMessage = '请填写班级名称和年级';
    } else {
        // 检查班级是否存在
        $class = fetchOne("SELECT * FROM classes WHERE id = ?", [$classId]);
        
        if (!$class) {
            $errorMessage = '找不到该班级';
        } else {
            // 检查班级名称和年级是否已被其他班级使用
            $existingClass = fetchOne("SELECT id FROM classes WHERE name = ? AND grade = ? AND id != ?", [$className, $grade, $classId]);
            
            if ($existingClass) {
                $errorMessage = '该班级名称和年级组合已被使用，请使用其他名称或年级';
            } else {
                // 更新班级信息
                $result = executeQuery("UPDATE classes SET name = ?, grade = ? WHERE id = ?", 
                    [$className, $grade, $classId]);
                
                if ($result) {
                    // 更新班主任信息
                    // 首先，将原班主任（如果有）的班主任标识清除
                    $currentTeacher = fetchOne("SELECT id FROM teachers WHERE class_id = ? AND is_class_teacher = 1", [$classId]);
                    if ($currentTeacher) {
                        executeQuery("UPDATE teachers SET is_class_teacher = 0, class_id = NULL WHERE id = ?", [$currentTeacher['id']]);
                    }
                    
                    // 如果选择了新班主任，更新为班主任
                    if ($classTeacherId) {
                        executeQuery("UPDATE teachers SET is_class_teacher = 1, class_id = ? WHERE id = ?", [$classId, $classTeacherId]);
                    }
                    
                    // 更新班级科目关系
                    // 首先删除原有关系
                    executeQuery("DELETE FROM class_subjects WHERE class_id = ?", [$classId]);
                    
                    // 添加新的科目关系
                    if (!empty($subjects)) {
                        foreach ($subjects as $subjectId) {
                            insertData("class_subjects", [
                                'class_id' => $classId,
                                'subject_id' => $subjectId
                            ]);
                        }
                    }
                    
                    $successMessage = '班级信息更新成功';
                } else {
                    $errorMessage = '班级信息更新失败，请重试';
                }
            }
        }
    }
}

// 获取班级信息
$class = fetchOne("
    SELECT c.*, 
        (SELECT t.id FROM teachers t WHERE t.class_id = c.id AND t.is_class_teacher = 1) AS class_teacher_id,
        (SELECT GROUP_CONCAT(cs.subject_id) FROM class_subjects cs WHERE cs.class_id = c.id) AS subject_ids
    FROM classes c
    WHERE c.id = ?
", [$classId]);

if (!$class) {
    redirect('class_manage.php');
}

// 转换科目ID为数组
$classSubjectIds = $class['subject_ids'] ? explode(',', $class['subject_ids']) : [];

// 获取班主任信息
$classTeacher = null;
if ($class['class_teacher_id']) {
    $classTeacher = fetchOne("SELECT id, name, teacher_number FROM teachers WHERE id = ?", [$class['class_teacher_id']]);
}

// 获取可用的班主任（未担任班主任的教师或当前班级的班主任）
$availableTeachers = fetchAll("
    SELECT t.id, t.name, t.teacher_number 
    FROM teachers t
    WHERE (t.is_class_teacher = 0 OR t.class_id IS NULL OR t.class_id = ?)
    ORDER BY t.name ASC
", [$classId]);

// 获取所有科目，用于班级科目分配
$subjects = fetchAll("SELECT * FROM subjects ORDER BY name ASC");

// 获取班级学生数量
$studentCount = fetchOne("SELECT COUNT(*) as count FROM students WHERE class_id = ?", [$classId]);

// 页面标题
$pageTitle = '编辑班级';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">编辑班级</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="grade">年级</label>
                                    <input type="text" class="form-control" id="grade" name="grade" value="<?php echo $class['grade']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">班级名称</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $class['name']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="class_teacher_id">班主任</label>
                            <select class="form-control" id="class_teacher_id" name="class_teacher_id">
                                <option value="">请选择班主任</option>
                                <?php foreach ($availableTeachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($class['class_teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                        <?php echo $teacher['name'] . ' (' . $teacher['teacher_number'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>开设科目</label>
                            <div class="subject-checkboxes">
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="subject_<?php echo $subject['id']; ?>" name="subjects[]" value="<?php echo $subject['id']; ?>" <?php echo in_array($subject['id'], $classSubjectIds) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="subject_<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>班级信息</label>
                            <ul class="list-group mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    学生数量
                                    <span class="badge badge-primary badge-pill"><?php echo $studentCount['count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    创建时间
                                    <span><?php echo formatDate($class['created_at']); ?></span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="form-group text-center">
                            <a href="class_manage.php" class="btn btn-secondary mr-2">返回</a>
                            <button type="submit" class="btn btn-primary">保存修改</button>
                            <?php if ($studentCount['count'] > 0): ?>
                                <a href="class_analysis.php?id=<?php echo $classId; ?>" class="btn btn-info ml-2">
                                    <i class="fas fa-chart-bar"></i> 班级分析
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($studentCount['count'] > 0): ?>
            <!-- 班级学生列表 -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3>班级学生 <span class="badge badge-primary"><?php echo $studentCount['count']; ?></span></h3>
                </div>
                <div class="card-body">
                    <?php
                    // 获取班级学生
                    $students = fetchAll("
                        SELECT s.*, u.username
                        FROM students s
                        INNER JOIN users u ON s.user_id = u.id
                        WHERE s.class_id = ?
                        ORDER BY s.name ASC
                    ", [$classId]);
                    ?>
                    
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>姓名</th>
                                        <th>学号</th>
                                        <th>性别</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <tr>
                                            <td><?php echo ($index + 1); ?></td>
                                            <td><?php echo $student['name']; ?></td>
                                            <td><?php echo $student['student_number']; ?></td>
                                            <td><?php echo $student['gender'] == 'male' ? '男' : '女'; ?></td>
                                            <td>
                                                <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">暂无学生数据</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.subject-checkboxes {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 10px;
}

.subject-checkboxes .custom-control {
    margin-bottom: 8px;
}
</style>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 