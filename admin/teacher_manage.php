<?php
/**
 * 成绩分析系统 - 教师管理
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 处理添加教师
$successMessage = '';
$errorMessage = '';

if (isset($_POST['add_teacher'])) {
    $teacherName = trim($_POST['name']);
    $teacherNumber = trim($_POST['teacher_number']); // 这里作为username使用
    $gender = $_POST['gender'];
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    $isClassTeacher = isset($_POST['is_class_teacher']) && $_POST['is_class_teacher'] == 1;
    $classId = $isClassTeacher ? (int)$_POST['class_id'] : null;
    $password = $_POST['password'] ? $_POST['password'] : '123456'; // 默认密码
    
    // 验证数据
    if (empty($teacherName) || empty($teacherNumber)) {
        $errorMessage = '请填写所有必要信息';
    } else {
        // 检查教师编号是否已存在
        $existingTeacher = fetchOne("SELECT id FROM users WHERE username = ? AND role = 'teacher'", [$teacherNumber]);
        
        if ($existingTeacher) {
            $errorMessage = '该教师编号已存在，请使用其他编号';
        } else {
            // 创建用户账号
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userId = insertData("users", [
                'username' => $teacherNumber,
                'password' => $hashedPassword,
                'name' => $teacherName,
                'role' => 'teacher',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($userId) {
                // 添加教师信息
                $result = insertData("teachers", [
                    'user_id' => $userId,
                    'name' => $teacherName,
                    'is_class_teacher' => $isClassTeacher ? 1 : 0
                ]);
                
                if ($result) {
                    // 如果有任教科目，添加教师科目关系
                    $teacherId = $result;
                    
                    // 如果是班主任，更新班级表
                    if ($isClassTeacher && $classId) {
                        executeQuery("UPDATE classes SET class_teacher_id = ? WHERE id = ?", [$teacherId, $classId]);
                    }
                    
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
                    
                    $successMessage = '教师添加成功';
                } else {
                    $errorMessage = '教师添加失败，请重试';
                    // 回滚用户创建
                    executeQuery("DELETE FROM users WHERE id = ?", [$userId]);
                }
            } else {
                $errorMessage = '用户账号创建失败，请重试';
            }
        }
    }
}

// 处理删除教师
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $teacherId = (int)$_GET['delete'];
    
    // 获取教师用户ID
    $teacher = fetchOne("SELECT user_id FROM teachers WHERE id = ?", [$teacherId]);
    
    if ($teacher) {
        // 删除教师科目关系
        executeQuery("DELETE FROM teacher_subjects WHERE teacher_id = ?", [$teacherId]);
        
        // 删除教师记录
        $deleteResult = executeQuery("DELETE FROM teachers WHERE id = ?", [$teacherId]);
        
        // 删除用户账号
        if ($deleteResult) {
            executeQuery("DELETE FROM users WHERE id = ?", [$teacher['user_id']]);
            $successMessage = '教师删除成功';
        } else {
            $errorMessage = '教师删除失败，请重试';
        }
    } else {
        $errorMessage = '找不到该教师';
    }
}

// 获取分页信息
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 搜索条件
$searchCondition = "";
$searchParams = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $searchCondition = " AND (t.name LIKE ? OR u.username LIKE ?)";
    $searchParams[] = "%$searchTerm%";
    $searchParams[] = "%$searchTerm%";
}

// 教师角色筛选
$roleFilter = "";
if (isset($_GET['role']) && !empty($_GET['role']) && $_GET['role'] != 'all') {
    if ($_GET['role'] == 'class_teacher') {
        $roleFilter = " AND t.is_class_teacher = 1";
    } else if ($_GET['role'] == 'subject_teacher') {
        $roleFilter = " AND t.is_class_teacher = 0";
    }
}

// 获取教师总数
$countQuery = "SELECT COUNT(*) AS total FROM teachers t WHERE 1=1" . $searchCondition . $roleFilter;
$totalResults = fetchOne($countQuery, $searchParams);
$totalPages = ceil($totalResults['total'] / $limit);

// 获取教师列表
$query = "
    SELECT 
        t.id, 
        t.name, 
        u.username as teacher_number,
        t.is_class_teacher,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        u.created_at,
        (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') 
         FROM teacher_subjects ts
         JOIN subjects s ON ts.subject_id = s.id
         WHERE ts.teacher_id = t.id) as teaching_subjects
    FROM teachers t
    INNER JOIN users u ON t.user_id = u.id
    LEFT JOIN classes c ON c.class_teacher_id = t.id
    WHERE 1=1" . $searchCondition . $roleFilter . "
    ORDER BY t.name ASC
    LIMIT ? OFFSET ?
";

$searchParams[] = $limit;
$searchParams[] = $offset;

$teachers = fetchAll($query, $searchParams);

// 获取所有班级，用于筛选和添加教师
$classes = getAllClasses();

// 从classes表中提取年级信息
$grades = fetchAll("SELECT DISTINCT grade FROM classes ORDER BY grade ASC");

// 获取所有科目
$subjects = fetchAll("SELECT * FROM subjects ORDER BY name ASC");

// 修改科目班级关系获取逻辑，不再依赖teacher_subjects表中已有的记录
$classSubjects = [];
// 获取所有班级和科目的组合
$classesData = fetchAll("SELECT id, name, grade FROM classes ORDER BY grade ASC, name ASC");
$subjectsData = fetchAll("SELECT id, name FROM subjects ORDER BY name ASC");

// 为每个班级和每个科目创建组合选项
foreach ($classesData as $class) {
    foreach ($subjectsData as $subject) {
        $classSubjects[] = [
            'id' => $subject['id'] . '-' . $class['id'],
            'name' => $subject['name'] . ' (' . $class['grade'] . $class['name'] . ')'
        ];
    }
}

// 页面标题
$pageTitle = '教师管理';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">教师管理</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <!-- 搜索和筛选 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-5 mb-2">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="搜索教师姓名或编号" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-2">
                            <select name="role" class="form-control" onchange="this.form.submit()">
                                <option value="all">所有教师角色</option>
                                <option value="class_teacher" <?php echo (isset($_GET['role']) && $_GET['role'] == 'class_teacher') ? 'selected' : ''; ?>>班主任</option>
                                <option value="subject_teacher" <?php echo (isset($_GET['role']) && $_GET['role'] == 'subject_teacher') ? 'selected' : ''; ?>>任课教师</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#addTeacherModal">
                                <i class="fas fa-plus"></i> 添加教师
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 教师列表 -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($teachers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>姓名</th>
                                        <th>教师编号</th>
                                        <th>教师角色</th>
                                        <th>班级/年级</th>
                                        <th>任教科目</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $index => $teacher): ?>
                                        <tr>
                                            <td><?php echo ($offset + $index + 1); ?></td>
                                            <td><?php echo $teacher['name']; ?></td>
                                            <td><?php echo $teacher['teacher_number']; ?></td>
                                            <td>
                                                <?php
                                                echo getTeacherRole($teacher);
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($teacher['is_class_teacher'] == 1 && $teacher['class_name']) {
                                                    echo $teacher['class_grade'] . '年级' . $teacher['class_name'];
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $teacher['teaching_subjects'] ? $teacher['teaching_subjects'] : '-'; ?></td>
                                            <td><?php echo formatDate($teacher['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="location.href='teacher_edit.php?id=<?php echo $teacher['id']; ?>'">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $teacher['id']; ?>, '<?php echo $teacher['name']; ?>')" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> 删除
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <div class="mt-3">
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?>">首页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?>">上一页</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // 显示当前页附近的页码
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?>">下一页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['role']) ? '&role=' . $_GET['role'] : ''; ?>">末页</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">暂无教师数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加教师模态框 -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" role="dialog" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTeacherModalLabel">添加教师</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">姓名</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="teacher_number">教师编号</label>
                                <input type="text" class="form-control" id="teacher_number" name="teacher_number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gender">性别</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="male">男</option>
                                    <option value="female">女</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">密码（默认：123456）</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="留空则使用默认密码">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>教师角色</label>
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="is_class_teacher" name="is_class_teacher" value="1" onchange="toggleClassTeacher()">
                            <label class="custom-control-label" for="is_class_teacher">班主任</label>
                        </div>
                        
                        <div id="class_teacher_section" style="display: none; margin-left: 20px; margin-bottom: 15px;">
                            <div class="form-group">
                                <label for="class_id">班级</label>
                                <select class="form-control" id="class_id" name="class_id">
                                    <option value="">选择班级</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo $class['grade'] . '年级 ' . $class['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>任教科目</label>
                        <select class="form-control" id="subjects" name="subjects[]" multiple size="5">
                            <?php foreach ($classSubjects as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo $item['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">按住Ctrl键可多选</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" name="add_teacher">添加</button>
                </div>
            </form>
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

// 删除确认
function confirmDelete(id, name) {
    if (confirm(`确定要删除教师"${name}"吗？此操作将删除该教师的所有相关数据且不可恢复！`)) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 