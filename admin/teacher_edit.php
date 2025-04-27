<?php
/**
 * 成绩分析系统 - 教师信息编辑
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

// 获取教师ID
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$teacherId) {
    redirect('teacher_manage.php');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherName = trim($_POST['name']);
    $teacherNumber = trim($_POST['teacher_number']);
    $gender = $_POST['gender'];
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    $isClassTeacher = isset($_POST['is_class_teacher']) && $_POST['is_class_teacher'] == 1;
    $classId = $isClassTeacher ? (int)$_POST['class_id'] : null;
    $isGradeDirector = isset($_POST['is_grade_director']) && $_POST['is_grade_director'] == 1;
    $gradeId = $isGradeDirector ? (int)$_POST['grade_id'] : null;
    $password = $_POST['password'] ? trim($_POST['password']) : '';
    
    // 验证数据
    if (empty($teacherName) || empty($teacherNumber)) {
        $errorMessage = '请填写所有必要信息';
    } else {
        // 获取教师信息
        $teacher = fetchOne("SELECT * FROM teachers WHERE id = ?", [$teacherId]);
        
        if (!$teacher) {
            $errorMessage = '找不到该教师';
        } else {
            // 检查教师编号是否已被其他教师使用
            $existingTeacher = fetchOne("SELECT id FROM teachers WHERE teacher_number = ? AND id != ?", [$teacherNumber, $teacherId]);
            
            if ($existingTeacher) {
                $errorMessage = '该教师编号已被其他教师使用，请使用其他编号';
            } else {
                // 更新教师信息
                $result = executeQuery("UPDATE teachers SET 
                    name = ?, 
                    teacher_number = ?, 
                    gender = ?, 
                    is_class_teacher = ?, 
                    class_id = ?, 
                    is_grade_director = ?, 
                    grade_id = ? 
                    WHERE id = ?", 
                    [$teacherName, $teacherNumber, $gender, $isClassTeacher ? 1 : 0, $classId, $isGradeDirector ? 1 : 0, $gradeId, $teacherId]
                );
                
                // 更新用户信息
                $userResult = executeQuery("UPDATE users SET name = ?, username = ? WHERE id = ?", 
                    [$teacherName, $teacherNumber, $teacher['user_id']]);
                
                // 如果提供了密码，更新密码
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $teacher['user_id']]);
                }
                
                // 更新教师科目关系
                if ($result) {
                    // 首先删除旧的科目关系
                    executeQuery("DELETE FROM teacher_subjects WHERE teacher_id = ?", [$teacherId]);
                    
                    // 添加新的科目关系
                    if (!empty($subjects)) {
                        foreach ($subjects as $subjectInfo) {
                            list($subjectId, $classId) = explode('-', $subjectInfo);
                            insertData("teacher_subjects", [
                                'teacher_id' => $teacherId,
                                'subject_id' => $subjectId,
                                'class_id' => $classId
                            ]);
                        }
                    }
                    
                    $successMessage = '教师信息更新成功';
                } else {
                    $errorMessage = '教师信息更新失败，请重试';
                }
            }
        }
    }
}

// 获取教师信息
$teacher = fetchOne("
    SELECT t.*, u.username 
    FROM teachers t 
    INNER JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
", [$teacherId]);

if (!$teacher) {
    redirect('teacher_manage.php');
}

// 获取所有班级
$classes = getAllClasses();

// 获取所有年级
$grades = fetchAll("SELECT * FROM grades ORDER BY id ASC");

// 获取教师任教科目
$teacherSubjects = fetchAll("
    SELECT ts.subject_id, ts.class_id, CONCAT(ts.subject_id, '-', ts.class_id) AS subject_class_id 
    FROM teacher_subjects ts 
    WHERE ts.teacher_id = ?
", [$teacherId]);

$teacherSubjectIds = [];
foreach ($teacherSubjects as $ts) {
    $teacherSubjectIds[] = $ts['subject_class_id'];
}

// 获取班级-科目配置，用于教师任教科目分配
$classSubjects = [];
$classSubjectsData = fetchAll("
    SELECT cs.class_id, cs.subject_id, c.name AS class_name, c.grade, s.name AS subject_name
    FROM class_subjects cs
    JOIN classes c ON cs.class_id = c.id
    JOIN subjects s ON cs.subject_id = s.id
    ORDER BY c.grade ASC, c.name ASC, s.name ASC
");

foreach ($classSubjectsData as $item) {
    $classSubjects[] = [
        'id' => $item['subject_id'] . '-' . $item['class_id'],
        'name' => $item['subject_name'] . ' (' . $item['grade'] . $item['class_name'] . ')'
    ];
}

// 页面标题
$pageTitle = '编辑教师';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">编辑教师</h1>
            
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
                                    <label for="name">姓名</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $teacher['name']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="teacher_number">教师编号</label>
                                    <input type="text" class="form-control" id="teacher_number" name="teacher_number" value="<?php echo $teacher['teacher_number']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gender">性别</label>
                                    <select class="form-control" id="gender" name="gender" required>
                                        <option value="male" <?php echo $teacher['gender'] == 'male' ? 'selected' : ''; ?>>男</option>
                                        <option value="female" <?php echo $teacher['gender'] == 'female' ? 'selected' : ''; ?>>女</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">重置密码（留空则不修改）</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="留空则保持原密码不变">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>教师角色</label>
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="is_class_teacher" name="is_class_teacher" value="1" <?php echo $teacher['is_class_teacher'] ? 'checked' : ''; ?> onchange="toggleClassTeacher()">
                                <label class="custom-control-label" for="is_class_teacher">班主任</label>
                            </div>
                            
                            <div id="class_teacher_section" style="display: <?php echo $teacher['is_class_teacher'] ? 'block' : 'none'; ?>; margin-left: 20px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label for="class_id">班级</label>
                                    <select class="form-control" id="class_id" name="class_id" <?php echo $teacher['is_class_teacher'] ? 'required' : ''; ?>>
                                        <option value="">选择班级</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $teacher['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo $class['grade'] . '年级 ' . $class['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_grade_director" name="is_grade_director" value="1" <?php echo $teacher['is_grade_director'] ? 'checked' : ''; ?> onchange="toggleGradeDirector()">
                                <label class="custom-control-label" for="is_grade_director">年级主任</label>
                            </div>
                            
                            <div id="grade_director_section" style="display: <?php echo $teacher['is_grade_director'] ? 'block' : 'none'; ?>; margin-left: 20px; margin-bottom: 15px;">
                                <div class="form-group">
                                    <label for="grade_id">年级</label>
                                    <select class="form-control" id="grade_id" name="grade_id" <?php echo $teacher['is_grade_director'] ? 'required' : ''; ?>>
                                        <option value="">选择年级</option>
                                        <?php foreach ($grades as $grade): ?>
                                            <option value="<?php echo $grade['id']; ?>" <?php echo $teacher['grade_id'] == $grade['id'] ? 'selected' : ''; ?>>
                                                <?php echo $grade['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>任教科目</label>
                            <select class="form-control" id="subjects" name="subjects[]" multiple size="8">
                                <?php foreach ($classSubjects as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo in_array($item['id'], $teacherSubjectIds) ? 'selected' : ''; ?>>
                                        <?php echo $item['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">按住Ctrl键可多选</small>
                        </div>
                        
                        <div class="form-group text-center">
                            <a href="teacher_manage.php" class="btn btn-secondary">返回</a>
                            <button type="submit" class="btn btn-primary">保存修改</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 切换班主任选项
function toggleClassTeacher() {
    const isClassTeacher = document.getElementById('is_class_teacher').checked;
    const classTeacherSection = document.getElementById('class_teacher_section');
    classTeacherSection.style.display = isClassTeacher ? 'block' : 'none';
    
    if (isClassTeacher) {
        document.getElementById('class_id').setAttribute('required', 'required');
    } else {
        document.getElementById('class_id').removeAttribute('required');
    }
}

// 切换年级主任选项
function toggleGradeDirector() {
    const isGradeDirector = document.getElementById('is_grade_director').checked;
    const gradeDirectorSection = document.getElementById('grade_director_section');
    gradeDirectorSection.style.display = isGradeDirector ? 'block' : 'none';
    
    if (isGradeDirector) {
        document.getElementById('grade_id').setAttribute('required', 'required');
    } else {
        document.getElementById('grade_id').removeAttribute('required');
    }
}
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 