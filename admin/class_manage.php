<?php
/**
 * 成绩分析系统 - 班级管理
 */

// 引入必要文件
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireAdmin();

// 处理添加班级
$successMessage = '';
$errorMessage = '';

if (isset($_POST['add_class'])) {
    $className = trim($_POST['name']);
    $grade = trim($_POST['grade']);
    $classTeacherId = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    // 验证数据
    if (empty($className) || empty($grade)) {
        $errorMessage = '请填写班级名称和年级';
    } else {
        // 检查班级是否已存在
        $existingClass = fetchOne("SELECT id FROM classes WHERE name = ? AND grade = ?", [$className, $grade]);
        
        if ($existingClass) {
            $errorMessage = '该班级已存在，请使用其他名称或年级';
        } else {
            // 添加班级
            $result = insertData("classes", [
                'name' => $className,
                'grade' => $grade,
                'class_teacher_id' => $classTeacherId
            ]);
            
            if ($result) {
                $classId = $result;
                
                // 如果指定了班主任，更新教师表
                if ($classTeacherId) {
                    executeQuery("UPDATE teachers SET is_class_teacher = 1 WHERE id = ?", [$classTeacherId]);
                }
                
                // 添加班级科目关系
                if (!empty($subjects)) {
                    foreach ($subjects as $subjectId) {
                        // 检查该科目教师
                        $teacher = fetchOne("
                            SELECT id FROM teachers
                            WHERE is_class_teacher = 0
                            LIMIT 1
                        ");
                        
                        $teacherId = $teacher ? $teacher['id'] : null;
                        
                        if (!$teacherId) {
                            // 如果没有可用教师，创建一个记录但不关联教师
                            insertData("teacher_subjects", [
                                'subject_id' => $subjectId,
                                'class_id' => $classId,
                                'teacher_id' => 1 // 使用一个默认的教师ID，后面可以通过教师管理进行分配
                            ]);
                        } else {
                            // 分配一个教师
                            insertData("teacher_subjects", [
                                'teacher_id' => $teacherId,
                                'subject_id' => $subjectId,
                                'class_id' => $classId
                            ]);
                        }
                    }
                }
                
                $successMessage = '班级添加成功';
            } else {
                $errorMessage = '班级添加失败，请重试';
            }
        }
    }
}

// 处理删除班级
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $classId = (int)$_GET['delete'];
    
    // 检查班级是否存在
    $class = fetchOne("SELECT * FROM classes WHERE id = ?", [$classId]);
    
    if ($class) {
        // 检查班级是否有学生
        $studentsCount = fetchOne("SELECT COUNT(*) as count FROM students WHERE class_id = ?", [$classId]);
        
        if ($studentsCount['count'] > 0) {
            $errorMessage = '班级中还有学生，请先删除或转移学生后再删除班级';
        } else {
            // 删除班级科目关系
            executeQuery("DELETE FROM teacher_subjects WHERE class_id = ?", [$classId]);
            
            // 更新班主任信息
            executeQuery("UPDATE teachers SET is_class_teacher = 0 WHERE id IN (SELECT class_teacher_id FROM classes WHERE id = ?)", [$classId]);
            
            // 删除班级
            $result = executeQuery("DELETE FROM classes WHERE id = ?", [$classId]);
            
            if ($result) {
                $successMessage = '班级删除成功';
            } else {
                $errorMessage = '班级删除失败，请重试';
            }
        }
    } else {
        $errorMessage = '找不到该班级';
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
    $searchCondition = " WHERE (c.name LIKE ? OR c.grade LIKE ?)";
    $searchParams[] = "%$searchTerm%";
    $searchParams[] = "%$searchTerm%";
}

// 获取班级总数
$countQuery = "SELECT COUNT(*) AS total FROM classes c" . $searchCondition;
$totalResults = fetchOne($countQuery, $searchParams);
$totalPages = ceil($totalResults['total'] / $limit);

// 获取班级列表
$query = "
    SELECT 
        c.id, 
        c.name, 
        c.grade, 
        t.name AS class_teacher_name,
        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count,
        (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') 
         FROM teacher_subjects ts
         JOIN subjects s ON ts.subject_id = s.id 
         WHERE ts.class_id = c.id
         GROUP BY ts.class_id) AS subjects
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.id
    " . $searchCondition . "
    ORDER BY c.grade DESC, c.name ASC
    LIMIT ? OFFSET ?
";

$searchParams[] = $limit;
$searchParams[] = $offset;

$classes = fetchAll($query, $searchParams);

// 获取可用的班主任（未担任班主任的教师）
$availableTeachers = fetchAll("
    SELECT t.id, t.name, u.username as teacher_number 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.is_class_teacher = 0 OR t.is_class_teacher IS NULL
    ORDER BY t.name ASC
");

// 获取所有科目，用于班级科目分配
$subjects = fetchAll("SELECT * FROM subjects ORDER BY name ASC");

// 页面标题
$pageTitle = '班级管理';

// 包含页头
include '../components/admin_header.php';
?>

<div class="container mt-3">
    <div class="row">
        <div class="col">
            <h1 class="mb-4">班级管理</h1>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <!-- 搜索和添加 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-8 mb-2">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="搜索班级名称或年级" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#addClassModal">
                                <i class="fas fa-plus"></i> 添加班级
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 班级列表 -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($classes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>班级名称</th>
                                        <th>年级</th>
                                        <th>班主任</th>
                                        <th>学生数量</th>
                                        <th>开设科目</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $index => $class): ?>
                                        <tr>
                                            <td><?php echo ($offset + $index + 1); ?></td>
                                            <td><?php echo $class['name']; ?></td>
                                            <td><?php echo $class['grade']; ?></td>
                                            <td><?php echo $class['class_teacher_name'] ? $class['class_teacher_name'] : '<span class="text-muted">未指定</span>'; ?></td>
                                            <td><?php echo $class['student_count']; ?></td>
                                            <td><?php echo $class['subjects'] ? $class['subjects'] : '<span class="text-muted">无</span>'; ?></td>
                                            <td>
                                                <a href="class_edit.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $class['id']; ?>, '<?php echo $class['grade'] . ' ' . $class['name']; ?>')" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> 删除
                                                </a>
                                                <a href="class_analysis.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-chart-bar"></i> 分析
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
                                                <a class="page-link" href="?page=1<?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">首页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">上一页</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // 显示当前页附近的页码
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">下一页</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">末页</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">暂无班级数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 添加班级模态框 -->
<div class="modal fade" id="addClassModal" tabindex="-1" role="dialog" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClassModalLabel">添加班级</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="grade">年级</label>
                        <input type="text" class="form-control" id="grade" name="grade" placeholder="如：高一、初二" required>
                    </div>
                    <div class="form-group">
                        <label for="name">班级名称</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="如：1班、2班" required>
                    </div>
                    <div class="form-group">
                        <label for="class_teacher_id">班主任（可选）</label>
                        <select class="form-control" id="class_teacher_id" name="class_teacher_id">
                            <option value="">请选择班主任</option>
                            <?php foreach ($availableTeachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
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
                                    <input type="checkbox" class="custom-control-input" id="subject_<?php echo $subject['id']; ?>" name="subjects[]" value="<?php echo $subject['id']; ?>">
                                    <label class="custom-control-label" for="subject_<?php echo $subject['id']; ?>"><?php echo $subject['name']; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" name="add_class">添加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 删除确认
function confirmDelete(id, name) {
    if (confirm(`确定要删除班级"${name}"吗？此操作将删除该班级的所有相关数据且不可恢复！`)) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

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