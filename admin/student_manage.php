<?php
/**
 * 成绩分析系统 - 学生管理
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 处理添加学生
$successMessage = '';
$errorMessage = '';

if (isset($_POST['add_student'])) {
    $studentName = trim($_POST['name']);
    $studentNumber = trim($_POST['student_number']);
    $classId = (int)$_POST['class_id'];
    $gender = $_POST['gender'];
    $password = $_POST['password'] ? $_POST['password'] : '123456'; // 默认密码
    
    // 验证数据
    if (empty($studentName) || empty($studentNumber) || empty($classId)) {
        $errorMessage = '请填写所有必要信息';
    } else {
        // 检查学号是否已存在
        $existingStudent = fetchOne("SELECT id FROM students WHERE student_number = ?", [$studentNumber]);
        
        if ($existingStudent) {
            $errorMessage = '该学号已存在，请使用其他学号';
        } else {
            // 创建用户账号
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userId = insertData("users", [
                'username' => $studentNumber,
                'password' => $hashedPassword,
                'name' => $studentName,
                'role' => 'student',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($userId) {
                // 添加学生信息
                $result = insertData("students", [
                    'user_id' => $userId,
                    'name' => $studentName,
                    'student_number' => $studentNumber,
                    'class_id' => $classId,
                    'gender' => $gender,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($result) {
                    $successMessage = '学生添加成功';
                } else {
                    $errorMessage = '学生添加失败，请重试';
                    // 回滚用户创建
                    executeQuery("DELETE FROM users WHERE id = ?", [$userId]);
                }
            } else {
                $errorMessage = '用户账号创建失败，请重试';
            }
        }
    }
}

// 处理删除学生
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $studentId = (int)$_GET['delete'];
    
    // 获取学生用户ID
    $student = fetchOne("SELECT user_id FROM students WHERE id = ?", [$studentId]);
    
    if ($student) {
        // 首先删除学生成绩记录
        executeQuery("DELETE FROM scores WHERE student_id = ?", [$studentId]);
        
        // 删除学生记录
        $deleteResult = executeQuery("DELETE FROM students WHERE id = ?", [$studentId]);
        
        // 删除用户账号
        if ($deleteResult) {
            executeQuery("DELETE FROM users WHERE id = ?", [$student['user_id']]);
            $successMessage = '学生删除成功';
        } else {
            $errorMessage = '学生删除失败，请重试';
        }
    } else {
        $errorMessage = '找不到该学生';
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
    $searchCondition = " AND (s.name LIKE ? OR s.student_number LIKE ?)";
    $searchParams[] = "%$searchTerm%";
    $searchParams[] = "%$searchTerm%";
}

// 班级筛选
$classFilter = "";
if (isset($_GET['class_id']) && !empty($_GET['class_id']) && $_GET['class_id'] != 'all') {
    $classFilter = " AND s.class_id = ?";
    $searchParams[] = (int)$_GET['class_id'];
}

// 获取学生总数
$countQuery = "SELECT COUNT(*) AS total FROM students s WHERE 1=1" . $searchCondition . $classFilter;
$totalResults = fetchOne($countQuery, $searchParams);
$totalPages = ceil($totalResults['total'] / $limit);

// 获取学生列表
$query = "
    SELECT s.*, c.name AS class_name, c.grade, u.username
    FROM students s
    INNER JOIN classes c ON s.class_id = c.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE 1=1" . $searchCondition . $classFilter . "
    ORDER BY c.grade ASC, c.name ASC, s.name ASC
    LIMIT ? OFFSET ?
";

$searchParams[] = $limit;
$searchParams[] = $offset;

$students = fetchAll($query, $searchParams);

// 获取所有班级，用于筛选和添加学生
$classes = getAllClasses();

// 页面标题
$pageTitle = '学生管理';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">学生管理</h1>
            
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
                                <input type="text" name="search" class="form-control" placeholder="搜索学生姓名或学号" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-2">
                            <select name="class_id" class="form-control" onchange="this.form.submit()">
                                <option value="all">所有班级</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo $class['grade'] . '年级 ' . $class['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#addStudentModal">
                                <i class="fas fa-plus"></i> 添加学生
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 学生列表 -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>姓名</th>
                                        <th>学号</th>
                                        <th>性别</th>
                                        <th>班级</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): ?>
                                        <tr>
                                            <td><?php echo ($offset + $index + 1); ?></td>
                                            <td><?php echo $student['name']; ?></td>
                                            <td><?php echo $student['student_number']; ?></td>
                                            <td><?php echo $student['gender'] == 'male' ? '男' : '女'; ?></td>
                                            <td><?php echo $student['grade'] . '年级 ' . $student['class_name']; ?></td>
                                            <td><?php echo formatDate($student['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-student" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo $student['name']; ?>"
                                                        data-number="<?php echo $student['student_number']; ?>"
                                                        data-class="<?php echo $student['class_id']; ?>"
                                                        data-gender="<?php echo $student['gender']; ?>"
                                                        data-toggle="modal" data-target="#editStudentModal">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo $student['name']; ?>')" class="btn btn-sm btn-danger">
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
                                                <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['class_id']) ? '&class_id=' . $_GET['class_id'] : ''; ?>">首页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['class_id']) ? '&class_id=' . $_GET['class_id'] : ''; ?>">上一页</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // 显示当前页附近的页码
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['class_id']) ? '&class_id=' . $_GET['class_id'] : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['class_id']) ? '&class_id=' . $_GET['class_id'] : ''; ?>">下一页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['class_id']) ? '&class_id=' . $_GET['class_id'] : ''; ?>">末页</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">暂无学生数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加学生模态框 -->
<div class="modal fade" id="addStudentModal" tabindex="-1" role="dialog" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">添加学生</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">姓名</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="student_number">学号</label>
                        <input type="text" class="form-control" id="student_number" name="student_number" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">性别</label>
                        <select class="form-control" id="gender" name="gender" required>
                            <option value="male">男</option>
                            <option value="female">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="class_id">班级</label>
                        <select class="form-control" id="class_id" name="class_id" required>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo $class['grade'] . '年级 ' . $class['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">密码（默认：123456）</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="留空则使用默认密码">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" name="add_student">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑学生模态框 -->
<div class="modal fade" id="editStudentModal" tabindex="-1" role="dialog" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStudentModalLabel">编辑学生</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editStudentForm" method="POST" action="student_edit.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="student_id">
                    <div class="form-group">
                        <label for="edit_name">姓名</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_student_number">学号</label>
                        <input type="text" class="form-control" id="edit_student_number" name="student_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">性别</label>
                        <select class="form-control" id="edit_gender" name="gender" required>
                            <option value="male">男</option>
                            <option value="female">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_class_id">班级</label>
                        <select class="form-control" id="edit_class_id" name="class_id" required>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo $class['grade'] . '年级 ' . $class['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">重置密码（留空则不修改）</label>
                        <input type="password" class="form-control" id="edit_password" name="password" placeholder="留空则保持原密码不变">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 编辑学生信息填充
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-student');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const number = this.getAttribute('data-number');
            const classId = this.getAttribute('data-class');
            const gender = this.getAttribute('data-gender');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_student_number').value = number;
            document.getElementById('edit_class_id').value = classId;
            document.getElementById('edit_gender').value = gender;
        });
    });
});

// 删除确认
function confirmDelete(id, name) {
    if (confirm(`确定要删除学生"${name}"吗？此操作将删除该学生的所有相关数据且不可恢复！`)) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

<?php
// 包含页脚
include '../components/admin_footer.php';
?> 