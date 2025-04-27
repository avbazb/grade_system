<?php
/**
 * 通用函数文件
 */

// 引入数据库文件
require_once 'db.php';

/**
 * 获取当前登录用户信息
 * 
 * @return array|null 用户信息或null
 */
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        return fetchOne("SELECT * FROM users WHERE id = {$userId}");
    }
    return null;
}

/**
 * 检查用户是否已登录
 * 
 * @return bool 是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * 检查用户是否是管理员
 * 
 * @return bool 是否是管理员
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getCurrentUser();
    return ($user && $user['role'] === 'admin');
}

/**
 * 检查用户是否是教师
 * 
 * @return bool 是否是教师
 */
function isTeacher() {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getCurrentUser();
    return ($user && $user['role'] === 'teacher');
}

/**
 * 检查用户是否是学生
 * 
 * @return bool 是否是学生
 */
function isStudent() {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getCurrentUser();
    return ($user && $user['role'] === 'student');
}

/**
 * 检查教师是否是班主任
 * 
 * @param int $teacherId 教师ID
 * @return bool 是否是班主任
 */
function isClassTeacher($teacherId) {
    $result = fetchOne("SELECT is_class_teacher FROM teachers WHERE id = {$teacherId}");
    return ($result && $result['is_class_teacher'] == 1);
}

/**
 * 获取学生信息
 * 
 * @param int $userId 用户ID
 * @return array|null 学生信息或null
 */
function getStudentInfo($userId) {
    return fetchOne("SELECT s.*, c.name as class_name, c.grade 
                    FROM students s 
                    JOIN classes c ON s.class_id = c.id 
                    WHERE s.user_id = {$userId}");
}

/**
 * 获取教师信息
 * 
 * @param int $userId 用户ID
 * @return array|null 教师信息或null
 */
function getTeacherInfo($userId) {
    return fetchOne("SELECT * FROM teachers WHERE user_id = {$userId}");
}

/**
 * 获取教师所教授的科目
 * 
 * @param int $teacherId 教师ID
 * @return array 科目列表
 */
function getTeacherSubjects($teacherId) {
    return fetchAll("SELECT ts.id AS teacher_subject_id, s.id, s.name, c.id AS class_id, c.name as class_name, c.grade 
                    FROM teacher_subjects ts 
                    JOIN subjects s ON ts.subject_id = s.id 
                    JOIN classes c ON ts.class_id = c.id 
                    WHERE ts.teacher_id = {$teacherId}");
}

/**
 * 检查教师是否教授某个班级的某个科目
 * 
 * @param int $teacherId 教师ID
 * @param int $classId 班级ID
 * @param int $subjectId 科目ID
 * @return bool 是否教授
 */
function isTeacherOfSubjectInClass($teacherId, $classId, $subjectId) {
    $result = fetchOne("SELECT id FROM teacher_subjects 
                        WHERE teacher_id = {$teacherId} 
                        AND class_id = {$classId} 
                        AND subject_id = {$subjectId}");
    return !empty($result);
}

/**
 * 获取班主任所管理的班级
 * 
 * @param int $teacherId 教师ID
 * @return array 班级列表
 */
function getClassTeacherClasses($teacherId) {
    return fetchAll("SELECT * FROM classes WHERE class_teacher_id = {$teacherId}");
}

/**
 * 生成密码哈希
 * 
 * @param string $password 明文密码
 * @return string 密码哈希
 */
function passwordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 验证密码
 * 
 * @param string $password 明文密码
 * @param string $hash 密码哈希
 * @return bool 验证是否通过
 */
function passwordVerify($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 重定向到指定URL
 * 
 * @param string $url 目标URL
 */
function redirect($url) {
    header("Location: {$url}");
    exit();
}

/**
 * 显示错误信息
 * 
 * @param string $message 错误信息
 * @return string 错误HTML
 */
function showError($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

/**
 * 显示成功信息
 * 
 * @param string $message 成功信息
 * @return string 成功HTML
 */
function showSuccess($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

/**
 * 获取所有科目
 * 
 * @return array 科目列表
 */
function getAllSubjects() {
    return fetchAll("SELECT * FROM subjects ORDER BY name");
}

/**
 * 获取所有班级
 * 
 * @return array 班级列表
 */
function getAllClasses() {
    return fetchAll("SELECT * FROM classes ORDER BY grade, name");
}

/**
 * 获取所有考试
 * 
 * @return array 考试列表
 */
function getAllExams() {
    return fetchAll("SELECT * FROM exams ORDER BY exam_date DESC");
}

/**
 * 获取考试的科目设置
 * 
 * @param int $examId 考试ID
 * @return array 科目设置列表
 */
function getExamSubjects($examId) {
    return fetchAll("SELECT es.*, s.name as subject_name 
                    FROM exam_subjects es 
                    JOIN subjects s ON es.subject_id = s.id 
                    WHERE es.exam_id = {$examId}");
}

/**
 * 获取考试信息
 * 
 * @param int $examId 考试ID
 * @return array|null 考试信息或null
 */
function getExamInfo($examId) {
    return fetchOne("SELECT * FROM exams WHERE id = {$examId}");
}

/**
 * 检查字符串是否为JSON
 * 
 * @param string $string 要检查的字符串
 * @return bool 是否为JSON
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * 获取分页参数
 * 
 * @param int $total 总记录数
 * @param int $currentPage 当前页码
 * @param int $perPage 每页记录数
 * @return array 分页参数
 */
function getPagination($total, $currentPage = 1, $perPage = 10) {
    $totalPages = ceil($total / $perPage);
    
    if ($currentPage < 1) {
        $currentPage = 1;
    } elseif ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
        'perPage' => $perPage,
        'offset' => $offset
    ];
}

/**
 * 显示分页导航
 * 
 * @param array $pagination 分页参数
 * @param string $baseUrl 基础URL
 * @return string 分页HTML
 */
function showPagination($pagination, $baseUrl) {
    $html = '<ul class="pagination justify-content-center">';
    
    // 上一页
    if ($pagination['currentPage'] > 1) {
        $prevPage = $pagination['currentPage'] - 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}&page={$prevPage}'>上一页</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><a class='page-link' href='#'>上一页</a></li>";
    }
    
    // 页码
    $start = max(1, $pagination['currentPage'] - 2);
    $end = min($pagination['totalPages'], $pagination['currentPage'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['currentPage']) {
            $html .= "<li class='page-item active'><a class='page-link' href='#'>{$i}</a></li>";
        } else {
            $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}&page={$i}'>{$i}</a></li>";
        }
    }
    
    // 下一页
    if ($pagination['currentPage'] < $pagination['totalPages']) {
        $nextPage = $pagination['currentPage'] + 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$baseUrl}&page={$nextPage}'>下一页</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><a class='page-link' href='#'>下一页</a></li>";
    }
    
    $html .= '</ul>';
    
    return $html;
}

/**
 * 格式化日期
 * 
 * @param string $date 日期字符串
 * @param string $format 格式
 * @return string 格式化后的日期
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * 生成随机字符串
 * 
 * @param int $length 字符串长度
 * @return string 随机字符串
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * 将成绩转换为百分制
 * 
 * @param float $score 原始成绩
 * @param float $fullScore 满分
 * @return float 百分制成绩
 */
function convertToPercentage($score, $fullScore) {
    if ($fullScore == 0) {
        return 0;
    }
    return round(($score / $fullScore) * 100, 2);
}

/**
 * 向数据库表中插入数据
 * 
 * @param string $table 表名
 * @param array $data 要插入的数据 ['列名' => '值']
 * @return int|bool 插入的ID或false
 */
function insertData($table, $data) {
    return insert($table, $data);
}

/**
 * 执行带参数的SQL查询
 * 
 * @param string $sql SQL查询语句
 * @param array $params 参数数组
 * @return bool 是否成功
 */
function executeQuery($sql, $params = []) {
    if (empty($params)) {
        return query($sql) !== false;
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("预处理错误: " . $conn->error . " 在查询: " . $sql);
        return false;
    }
    
    // 创建绑定参数数组
    $types = '';
    $bindParams = [];
    
    // 确定参数类型并创建绑定数组
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_string($param)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $bindParams[] = $param;
    }
    
    // 动态绑定参数
    if (!empty($bindParams)) {
        // 创建引用参数数组用于bind_param
        $bindParamsRefs = [];
        $bindParamsRefs[] = &$types;
        
        for ($i = 0; $i < count($bindParams); $i++) {
            $bindParamsRefs[] = &$bindParams[$i];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParamsRefs);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * 获取教师角色显示文本
 * 
 * @param array $teacher 教师信息数组
 * @return string 格式化的角色显示HTML
 */
function getTeacherRole($teacher) {
    if ($teacher['is_class_teacher'] == 1) {
        return '<span class="badge badge-primary">班主任</span>';
    } else {
        return '<span class="badge badge-info">任课教师</span>';
    }
}

/**
 * 获取班级名称
 * 
 * @param int $classId 班级ID
 * @return string 班级名称
 */
function getClassName($classId) {
    $class = fetchOne("SELECT name FROM classes WHERE id = " . intval($classId));
    return $class ? $class['name'] : '未知班级';
}

/**
 * 格式化考试数据为AI分析需要的格式
 * 
 * @param int $exam_id 考试ID
 * @param int $class_id 班级ID（可选）
 * @param int $subject_id 科目ID（可选）
 * @param string $grade 年级（可选，当提供班级ID时会自动获取）
 * @param object $conn 数据库连接对象
 * @return array 格式化后的考试数据
 */
function formatExamData($exam_id, $class_id = 0, $subject_id = 0, $grade = '', $conn = null) {
    if ($conn === null) {
        $conn = getDBConnection();
    }
    
    // 获取考试信息
    $exam_sql = "SELECT name FROM exams WHERE id = ?";
    $stmt = $conn->prepare($exam_sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam_result = $stmt->get_result();
    
    if ($exam_result->num_rows == 0) {
        return ['error' => '找不到考试信息'];
    }
    
    $exam_data = $exam_result->fetch_assoc();
    $exam_name = $exam_data['name'];
    
    // 准备返回数据
    $response = [
        'exam_name' => $exam_name
    ];
    
    // 如果指定了班级，获取班级信息
    if ($class_id > 0) {
        $class_sql = "SELECT name, grade FROM classes WHERE id = ?";
        $stmt = $conn->prepare($class_sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        
        if ($class_result->num_rows > 0) {
            $class_data = $class_result->fetch_assoc();
            $response['class_name'] = $class_data['name'];
            $response['grade'] = $class_data['grade'];
            $grade = $class_data['grade']; // 更新年级变量为班级的年级
        } else {
            return ['error' => '找不到班级信息'];
        }
    } elseif (empty($grade)) {
        // 如果没有指定班级，但需要年级信息
        return ['error' => '请指定年级或班级'];
    } else {
        $response['grade'] = $grade;
    }
    
    // 如果指定了科目，获取科目信息
    if ($subject_id > 0) {
        $subject_sql = "SELECT name FROM subjects WHERE id = ?";
        $stmt = $conn->prepare($subject_sql);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        
        if ($subject_result->num_rows > 0) {
            $subject_data = $subject_result->fetch_assoc();
            $response['subject_name'] = $subject_data['name'];
            
            // 获取满分
            $full_score_sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($full_score_sql);
            $stmt->bind_param("ii", $exam_id, $subject_id);
            $stmt->execute();
            $full_score_result = $stmt->get_result();
            
            if ($full_score_result->num_rows > 0) {
                $full_score_data = $full_score_result->fetch_assoc();
                $response['full_score'] = $full_score_data['full_score'];
            } else {
                $response['full_score'] = 100; // 默认满分
            }
        } else {
            return ['error' => '找不到科目信息'];
        }
    }
    
    // 获取学生详细成绩数据
    if ($class_id > 0) {
        // 获取班级内所有学生
        $students_sql = "SELECT id, name FROM students WHERE class_id = ? ORDER BY name";
        $stmt = $conn->prepare($students_sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        $students = [];
        $scores = [];
        
        // 先获取科目列表，用于排序
        $subject_order = [];
        if ($subject_id > 0) {
            // 如果指定了科目，只包含该科目
            $subject_order = [$subject_id];
        } else {
            // 获取所有考试科目
            $subjects_sql = "SELECT es.subject_id, s.name 
                            FROM exam_subjects es 
                            JOIN subjects s ON es.subject_id = s.id 
                            WHERE es.exam_id = ? 
                            ORDER BY s.name";
            $stmt = $conn->prepare($subjects_sql);
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();
            
            while ($subject = $subjects_result->fetch_assoc()) {
                $subject_order[] = $subject['subject_id'];
            }
        }
        
        // 获取所有科目名称的映射
        $subject_names = [];
        foreach ($subject_order as $subj_id) {
            $subj_name_sql = "SELECT name FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($subj_name_sql);
            $stmt->bind_param("i", $subj_id);
            $stmt->execute();
            $subj_name_result = $stmt->get_result();
            if ($subj_name_result->num_rows > 0) {
                $subject_names[$subj_id] = $subj_name_result->fetch_assoc()['name'];
            }
        }
        
        // 组装学生数据
        while ($student = $students_result->fetch_assoc()) {
            $student_id = $student['id'];
            $student_info = ['name' => $student['name'], 'scores' => []];
            
            // 获取该学生的所有科目成绩
            if ($subject_id > 0) {
                // 如果指定了科目，只获取该科目成绩
                $scores_sql = "SELECT subject_id, score 
                             FROM scores 
                             WHERE exam_id = ? AND student_id = ? AND subject_id = ?";
                $stmt = $conn->prepare($scores_sql);
                $stmt->bind_param("iii", $exam_id, $student_id, $subject_id);
            } else {
                // 否则获取所有科目成绩
                $scores_sql = "SELECT subject_id, score 
                             FROM scores 
                             WHERE exam_id = ? AND student_id = ?";
                $stmt = $conn->prepare($scores_sql);
                $stmt->bind_param("ii", $exam_id, $student_id);
            }
            
            $stmt->execute();
            $scores_result = $stmt->get_result();
            
            // 将成绩数据转换为关联数组
            $student_scores = [];
            while ($score = $scores_result->fetch_assoc()) {
                $student_scores[$score['subject_id']] = $score['score'];
            }
            
            // 按照科目顺序添加成绩
            foreach ($subject_order as $subj_id) {
                $subj_name = $subject_names[$subj_id] ?? '未知科目';
                
                // 添加成绩，如果没有则为null
                if (isset($student_scores[$subj_id])) {
                    $student_info['scores'][$subj_name] = floatval($student_scores[$subj_id]);
                } else {
                    $student_info['scores'][$subj_name] = null;
                }
            }
            
            $students[] = $student_info;
        }
        
        // 添加数据到响应
        $response['students'] = $students;
        
        // 添加科目顺序
        if (count($subject_order) > 0) {
            $subject_names_list = [];
            foreach ($subject_order as $subj_id) {
                if (isset($subject_names[$subj_id])) {
                    $subject_names_list[] = $subject_names[$subj_id];
                }
            }
            $response['subject_order'] = $subject_names_list;
        }
        
        // 如果选择了科目，进行单科分析
        if ($subject_id > 0) {
            // 构建成绩查询的条件
            $score_conditions = " AND st.class_id = ?";
            
            // 获取成绩统计信息
            $stats_sql = "SELECT 
                         MAX(sc.score) AS max_score,
                         MIN(sc.score) AS min_score,
                         AVG(sc.score) AS avg_score,
                         COUNT(sc.id) AS count
                         FROM scores sc
                         JOIN students st ON sc.student_id = st.id
                         WHERE sc.exam_id = ? AND sc.subject_id = ?" . $score_conditions;
            
            $stmt = $conn->prepare($stats_sql);
            $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
            $stmt->execute();
            $stats_result = $stmt->get_result();
            
            if ($stats_result->num_rows > 0) {
                $stats_data = $stats_result->fetch_assoc();
                $response['max_score'] = round($stats_data['max_score'], 1);
                $response['min_score'] = round($stats_data['min_score'], 1);
                $response['avg_score'] = round($stats_data['avg_score'], 1);
                $response['count'] = $stats_data['count'];
            }
            
            // 获取年级平均分
            $grade_avg_sql = "SELECT AVG(sc.score) AS grade_avg
                            FROM scores sc
                            JOIN students st ON sc.student_id = st.id
                            JOIN classes c ON st.class_id = c.id
                            WHERE sc.exam_id = ? AND sc.subject_id = ? AND c.grade = ?";
            
            $stmt = $conn->prepare($grade_avg_sql);
            $stmt->bind_param("iis", $exam_id, $subject_id, $grade);
            $stmt->execute();
            $grade_avg_result = $stmt->get_result();
            
            if ($grade_avg_result->num_rows > 0) {
                $grade_avg_data = $grade_avg_result->fetch_assoc();
                $response['grade_avg_score'] = round($grade_avg_data['grade_avg'], 1);
            }
            
            // 获取分数段分布
            $ranges = [
                ['min' => 0, 'max' => 60, 'range' => '0-60分'],
                ['min' => 60, 'max' => 70, 'range' => '60-70分'],
                ['min' => 70, 'max' => 80, 'range' => '70-80分'],
                ['min' => 80, 'max' => 90, 'range' => '80-90分'],
                ['min' => 90, 'max' => 100, 'range' => '90-100分'],
            ];
            
            $response['score_ranges'] = [];
            
            foreach ($ranges as $range) {
                $range_sql = "SELECT COUNT(sc.id) AS count
                            FROM scores sc
                            JOIN students st ON sc.student_id = st.id
                            WHERE sc.exam_id = ? AND sc.subject_id = ? AND sc.score >= ? AND sc.score < ? AND st.class_id = ?";
                
                $stmt = $conn->prepare($range_sql);
                $stmt->bind_param("iiiid", $exam_id, $subject_id, $range['min'], $range['max'], $class_id);
                $stmt->execute();
                $range_result = $stmt->get_result();
                
                if ($range_result->num_rows > 0) {
                    $range_data = $range_result->fetch_assoc();
                    $response['score_ranges'][] = [
                        'range' => $range['range'],
                        'count' => $range_data['count']
                    ];
                }
            }
            
            // 满分段单独计算
            $full_score_sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($full_score_sql);
            $stmt->bind_param("ii", $exam_id, $subject_id);
            $stmt->execute();
            $full_score_result = $stmt->get_result();
            
            if ($full_score_result->num_rows > 0) {
                $full_score_data = $full_score_result->fetch_assoc();
                $full_score = $full_score_data['full_score'];
                
                $full_range_sql = "SELECT COUNT(sc.id) AS count
                                FROM scores sc
                                JOIN students st ON sc.student_id = st.id
                                WHERE sc.exam_id = ? AND sc.subject_id = ? AND sc.score = ? AND st.class_id = ?";
                
                $stmt = $conn->prepare($full_range_sql);
                $stmt->bind_param("iiii", $exam_id, $subject_id, $full_score, $class_id);
                $stmt->execute();
                $full_range_result = $stmt->get_result();
                
                if ($full_range_result->num_rows > 0) {
                    $full_range_data = $full_range_result->fetch_assoc();
                    if ($full_range_data['count'] > 0) {
                        $response['score_ranges'][] = [
                            'range' => '满分',
                            'count' => $full_range_data['count']
                        ];
                    }
                }
            }
        } else if ($class_id > 0) {
            // 班级多科目分析
            $subject_sql = "SELECT es.subject_id, s.name, es.full_score
                          FROM exam_subjects es
                          JOIN subjects s ON es.subject_id = s.id
                          WHERE es.exam_id = ?
                          ORDER BY s.name";
            
            $stmt = $conn->prepare($subject_sql);
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();
            
            $subjects = [];
            
            while ($subject = $subjects_result->fetch_assoc()) {
                $subject_id = $subject['subject_id'];
                
                // 获取班级成绩统计信息
                $stats_sql = "SELECT 
                            MAX(sc.score) AS max_score,
                            MIN(sc.score) AS min_score,
                            AVG(sc.score) AS avg_score,
                            COUNT(sc.id) AS count
                            FROM scores sc
                            JOIN students st ON sc.student_id = st.id
                            WHERE sc.exam_id = ? AND sc.subject_id = ? AND st.class_id = ?";
                
                $stmt = $conn->prepare($stats_sql);
                $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
                $stmt->execute();
                $stats_result = $stmt->get_result();
                
                if ($stats_result->num_rows > 0) {
                    $stats_data = $stats_result->fetch_assoc();
                    
                    // 获取年级平均分
                    $grade_avg_sql = "SELECT AVG(sc.score) AS grade_avg
                                    FROM scores sc
                                    JOIN students st ON sc.student_id = st.id
                                    JOIN classes c ON st.class_id = c.id
                                    WHERE sc.exam_id = ? AND sc.subject_id = ? AND c.grade = ?";
                    
                    $stmt = $conn->prepare($grade_avg_sql);
                    $stmt->bind_param("iis", $exam_id, $subject_id, $grade);
                    $stmt->execute();
                    $grade_avg_result = $stmt->get_result();
                    
                    $grade_avg = null;
                    if ($grade_avg_result->num_rows > 0) {
                        $grade_avg_data = $grade_avg_result->fetch_assoc();
                        $grade_avg = round($grade_avg_data['grade_avg'], 1);
                    }
                    
                    $subjects[] = [
                        'id' => $subject_id,
                        'name' => $subject['name'],
                        'full_score' => $subject['full_score'],
                        'max_score' => round($stats_data['max_score'], 1),
                        'min_score' => round($stats_data['min_score'], 1),
                        'avg_score' => round($stats_data['avg_score'], 1),
                        'grade_avg_score' => $grade_avg,
                        'count' => $stats_data['count']
                    ];
                }
            }
            
            $response['subjects'] = $subjects;
        }
    } else if (!empty($grade)) {
        // 如果只指定了年级，添加基本的年级数据
        // 获取年级内所有班级
        $classes_sql = "SELECT id, name FROM classes WHERE grade = ? ORDER BY name";
        $stmt = $conn->prepare($classes_sql);
        $stmt->bind_param("s", $grade);
        $stmt->execute();
        $classes_result = $stmt->get_result();
        
        $classes = [];
        while ($class = $classes_result->fetch_assoc()) {
            $classes[] = $class;
        }
        
        $response['classes'] = $classes;
        
        // 获取年级内所有科目平均分
        $subjects_sql = "SELECT es.subject_id, s.name, es.full_score
                        FROM exam_subjects es
                        JOIN subjects s ON es.subject_id = s.id
                        WHERE es.exam_id = ?
                        ORDER BY s.name";
        $stmt = $conn->prepare($subjects_sql);
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $subjects_result = $stmt->get_result();
        
        $subjects = [];
        while ($subject = $subjects_result->fetch_assoc()) {
            $subject_id = $subject['subject_id'];
            
            // 获取科目在年级内的统计数据
            $stats_sql = "SELECT 
                        AVG(sc.score) AS avg_score,
                        MAX(sc.score) AS max_score,
                        MIN(sc.score) AS min_score,
                        COUNT(sc.id) AS count
                        FROM scores sc
                        JOIN students st ON sc.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE sc.exam_id = ? AND sc.subject_id = ? AND c.grade = ?";
            
            $stmt = $conn->prepare($stats_sql);
            $stmt->bind_param("iis", $exam_id, $subject_id, $grade);
            $stmt->execute();
            $stats_result = $stmt->get_result();
            
            if ($stats_result->num_rows > 0) {
                $stats_data = $stats_result->fetch_assoc();
                
                $subjects[] = [
                    'id' => $subject_id,
                    'name' => $subject['name'],
                    'full_score' => $subject['full_score'],
                    'avg_score' => round($stats_data['avg_score'], 1),
                    'max_score' => round($stats_data['max_score'], 1),
                    'min_score' => round($stats_data['min_score'], 1),
                    'count' => $stats_data['count']
                ];
            }
        }
        
        $response['subjects'] = $subjects;
    }
    
    return $response;
} 