<?php
// 处理AJAX请求 - 放在最开始，避免任何其他输出
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_data') {
    // 设置内容类型为JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 关闭任何可能的输出缓冲
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        // 获取POST参数
        $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;
        $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
        $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        
        if (!$exam_id) {
            throw new Exception('请选择考试');
        }
        
        // 引入必要文件（不输出任何内容）
        define('IN_TEACHER', true);
        require_once('../includes/config.php');
        require_once('../includes/db.php');
        require_once('../includes/functions.php');
        require_once('../includes/session.php');
        
        // 验证教师权限
        if (!isTeacher()) {
            throw new Exception('没有访问权限，请重新登录');
        }
        
        // 获取数据
        $data = get_teacher_exam_analysis_data($exam_id, $class_id, $subject_id);
        
        // 返回JSON数据
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        // 捕获所有异常
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    // 确保脚本在此结束
    exit;
}

// 正常网页内容继续
define('IN_TEACHER', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireTeacher();

// 获取教师信息
$teacher_id = $_SESSION['user_id'];

// 临时处理 - 检查class_subject_teacher表是否存在
$conn = getDBConnection();
$tableExists = false;
$checkTableResult = $conn->query("SHOW TABLES LIKE 'class_subject_teacher'");
if ($checkTableResult && $checkTableResult->num_rows > 0) {
    $tableExists = true;
}

// 获取教师教授的班级
$teacher_classes = [];
if ($tableExists) {
    $teacher_classes = get_teacher_classes($teacher_id);
} else {
    // 如果表不存在，获取所有班级作为替代
    $teacher_classes = get_all_classes();
}

// 获取教师教授的科目
$teacher_subjects = [];
if ($tableExists) {
    $teacher_subjects = get_teacher_subjects($teacher_id);
} else {
    // 如果表不存在，获取所有科目作为替代
    $teacher_subjects = get_all_subjects();
}

// 获取考试列表
$exams = [];
if ($tableExists) {
    $exams = get_teacher_exams($teacher_id);
} else {
    // 如果表不存在，获取全部考试作为替代
    $exams = get_all_exams();
}

// 页面标题
$page_title = 'AI成绩分析';

// 包含页眉
include '../components/teacher_header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">AI成绩分析</h5>
                </div>
                <div class="card-body">
                    <form id="aiAnalysisForm" class="mb-4" method="post">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="exam_id"><strong>选择考试：</strong></label>
                                    <select class="form-control" id="exam_id" name="exam_id" required>
                                        <option value="">请选择考试</option>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="class_id"><strong>选择班级：</strong></label>
                                    <select class="form-control" id="class_id" name="class_id">
                                        <option value="">全部班级</option>
                                        <?php foreach ($teacher_classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="subject_id"><strong>选择科目：</strong></label>
                                    <select class="form-control" id="subject_id" name="subject_id">
                                        <option value="">全部科目</option>
                                        <?php foreach ($teacher_subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">生成分析</button>
                    </form>
                    
                    <div class="ai-response-container">
                        <div id="loading" style="display:none;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border text-primary me-3" role="status"></div>
                                <span id="loading-text">AI正在分析中，预计还有<span id="countdown">15</span>秒完成...</span>
                            </div>
                            <div class="progress">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div id="aiResponseArea" class="border rounded p-3 markdown-body" style="min-height: 400px; display: none;"></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">调试信息（仅供参考）</h5>
                </div>
                <div class="card-body">
                    <div id="debugArea" class="border rounded p-3 bg-light" style="min-height: 100px; display: none;">
                        <h6>获取数据：</h6>
                        <pre id="rawResponse" style="white-space: pre-wrap; word-break: break-all;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入Markdown解析库 -->
<script src="../assets/js/markdown.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const aiAnalysisForm = document.getElementById('aiAnalysisForm');
    const aiResponseArea = document.getElementById('aiResponseArea');
    const loading = document.getElementById('loading');
    const countdownEl = document.getElementById('countdown');
    const loadingText = document.getElementById('loading-text');
    const progressBar = document.getElementById('progress-bar');
    
    // 调试相关元素
    const debugArea = document.getElementById('debugArea');
    const rawResponse = document.getElementById('rawResponse');
    
    // 定义copyBtn变量，如果页面上没有该元素则为null
    const copyBtn = document.getElementById('copyBtn');
    
    let countdownTimer;
    let countdown = 15;

    function startCountdown() {
        countdown = 15;
        countdownEl.textContent = countdown;
        progressBar.style.width = '0%';
        
        countdownTimer = setInterval(function() {
            countdown--;
            countdownEl.textContent = countdown;
            
            // 更新进度条
            const progress = Math.round(((15 - countdown) / 15) * 100);
            progressBar.style.width = progress + '%';
            
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                loadingText.textContent = 'AI仍在努力分析中，请耐心等待...';
            }
        }, 1000);
    }

    function stopCountdown() {
        clearInterval(countdownTimer);
    }

    aiAnalysisForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const examId = document.getElementById('exam_id').value;
        const classId = document.getElementById('class_id').value;
        const subjectId = document.getElementById('subject_id').value;
        
        if (!examId) {
            alert('请选择一个考试');
            return;
        }
        
        // 显示加载状态
        loading.style.display = 'block';
        aiResponseArea.style.display = 'none';
        aiResponseArea.innerHTML = '';
        
        // 开始倒计时
        startCountdown();
        
        try {
            // 测试模式开关
            const testMode = false;
            
            if (testMode) {
                const systemPrompt = "你是一位经验丰富的教育专家和数据分析师，擅长分析学生成绩数据并提供教学建议。";
                const userPrompt = "请提供一个简短的成绩分析示例，以测试AI接口是否正常工作。";
                
                try {
                    debugArea.style.display = 'block';
                    rawResponse.textContent = "正在请求API...";
                    
                    // 直接调用智普AI API（注意采用和test_ai_api.php相同的相对路径）
                    const aiResponse = await fetch('../api/zhipu_ai.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            systemPrompt: systemPrompt,
                            userPrompt: userPrompt
                        })
                    });
                    
                    // 检查响应
                    if (!aiResponse.ok) {
                        throw new Error('AI API请求失败: ' + aiResponse.status);
                    }
                    
                    // 先获取原始文本响应
                    const responseText = await aiResponse.text();
                    
                    // 显示原始响应用于调试
                    rawResponse.textContent = responseText;
                    
                    // 检查响应是否为HTML或PHP错误
                    if (responseText.trim().startsWith('<') || responseText.includes('<!DOCTYPE') || responseText.includes('<br')) {
                        console.error('服务器返回了HTML而不是JSON:', responseText);
                        throw new Error('服务器返回了非法的响应格式');
                    }
                    
                    // 尝试解析为JSON
                    let analysisResult = JSON.parse(responseText);
                    
                    if (!analysisResult.content) {
                        throw new Error('API响应缺少content字段');
                    }
                    
                    // 停止倒计时
                    stopCountdown();
                    
                    // 隐藏loading状态
                    loading.style.display = 'none';
                    
                    // 清理内容
                    analysisResult.content = removeThinkTags(analysisResult.content);
                    
                    // 显示分析结果
                    aiResponseArea.innerHTML = `<div class="alert alert-info">测试模式已启用</div>
                    <div class="analysis-content">${analysisResult.content.replace(/\n/g, '<br>')}</div>`;
                    aiResponseArea.style.display = 'block';
                    
                } catch (error) {
                    stopCountdown();
                    loading.style.display = 'none';
                    aiResponseArea.innerHTML = `<div class="alert alert-danger">
                        <h4>测试模式调用AI出错</h4>
                        <p>${error.message}</p>
                    </div>`;
                    aiResponseArea.style.display = 'block';
                    console.error('AI测试调用错误:', error);
                }
                return; // 测试模式下直接返回，不执行后续代码
            }
            
            // 使用表单提交获取数据并分析
            const formData = new FormData();
            formData.append('action', 'get_data');
            formData.append('exam_id', examId);
            if (classId) formData.append('class_id', classId);
            if (subjectId) formData.append('subject_id', subjectId);
            
            // 开启调试区域，显示请求信息
            debugArea.style.display = 'block';
            rawResponse.textContent = "正在请求数据...\n";
            rawResponse.textContent += `请求参数: exam_id=${examId}${classId ? ', class_id='+classId : ''}${subjectId ? ', subject_id='+subjectId : ''}\n\n`;
            
            try {
                // 请求数据
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                // 记录HTTP状态
                rawResponse.textContent += `HTTP状态: ${response.status} ${response.statusText}\n`;
                
                // 获取原始响应文本
                const responseText = await response.text();
                
                // 检查是否是HTML响应
                if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html') || responseText.includes('<br')) {
                    rawResponse.textContent += "\n收到HTML响应而非JSON。这通常表明PHP脚本有错误或输出了HTML内容：\n\n";
                    rawResponse.textContent += responseText.substring(0, 1000) + (responseText.length > 1000 ? "...(内容过长已截断)" : "");
                    
                    // 提取HTML中的错误信息
                    let errorMessage = '服务器返回了HTML而不是JSON，请检查PHP脚本';
                    const errorMatch = responseText.match(/<b>([^<]+)<\/b>/);
                    if (errorMatch && errorMatch[1]) {
                        errorMessage = errorMatch[1];
                    }
                    
                    throw new Error(errorMessage);
                } else {
                    rawResponse.textContent += "\n原始响应:\n" + responseText;
                }
                
                // 尝试解析为JSON
                let examData;
                try {
                    examData = JSON.parse(responseText);
                } catch (jsonError) {
                    throw new Error(`解析JSON失败: ${jsonError.message}。原始响应内容可能不是有效的JSON格式`);
                }
                
                if (!response.ok) {
                    throw new Error('获取数据失败: ' + response.status + (examData?.error ? ` - ${examData.error}` : ''));
                }
                
                if (examData.error) {
                    throw new Error(examData.error);
                }
                
                // 显示格式化的JSON
                rawResponse.textContent = "获取的数据:\n" + JSON.stringify(examData, null, 2);
                
                // 构建提示信息
                const systemPrompt = "你是一位经验丰富的教育专家和数据分析师，擅长分析学生成绩数据并提供教学建议。请根据提供的考试成绩数据，进行全面分析并提出改进建议。分析应包括成绩分布、优势与不足、教学改进方向等维度。";
                
                // 准备用户提示
                let userPrompt;
                
                if (subjectId) {
                    // 单科分析
                    userPrompt = `请分析以下${examData.class_name || '班级'}在${examData.exam_name}考试中${examData.subject_name}科目的表现：

考试信息：${examData.exam_name}
科目：${examData.subject_name}
满分：${examData.full_score}

成绩统计：
- 最高分：${examData.max_score}
- 最低分：${examData.min_score}
- 平均分：${examData.avg_score}
- 年级平均分：${examData.grade_avg_score}

分数段分布：
${examData.score_ranges.map(range => 
    `${range.range}: ${range.count}人`
).join('\n')}

请提供详细的数据分析、教学评估、存在问题和针对性的教学建议。`;
                } else {
                    // 班级整体分析或多科目分析
                    userPrompt = `请分析以下${examData.class_name || '班级'}在${examData.exam_name}考试中的整体表现：

考试信息：${examData.exam_name}
${examData.class_name ? `班级：${examData.class_name}` : ''}

各科目成绩统计：
${examData.subjects.map(subject => 
    `${subject.name}：
    - 平均分：${subject.avg_score}/${subject.full_score}
    - 最高分：${subject.max_score}
    - 最低分：${subject.min_score}
    - 年级平均分：${subject.grade_avg_score}`
).join('\n\n')}

请提供详细的数据分析、优势与不足分析、学科间的对比分析以及针对性的教学改进建议。`;
                }
                
                // 调用智普AI API
                const apiEndpoint = '../api/zhipu_ai.php';
                
                try {
                    // 发送请求
                    const aiResponse = await fetch(apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            systemPrompt: systemPrompt,
                            userPrompt: userPrompt
                        })
                    });
                    
                    // 检查响应
                    if (!aiResponse.ok) {
                        throw new Error('AI API请求失败: ' + aiResponse.status);
                    }
                    
                    // 先获取原始文本响应
                    const responseText = await aiResponse.text();
                    
                    // 显示原始响应用于调试
                    debugArea.style.display = 'block';
                    rawResponse.textContent += "\n\nAI响应:\n" + responseText;
                    
                    // 检查响应是否为HTML或PHP错误
                    if (responseText.trim().startsWith('<') || responseText.includes('<!DOCTYPE') || responseText.includes('<br')) {
                        console.error('服务器返回了HTML而不是JSON:', responseText);
                        
                        // 提取HTML中的错误信息
                        let errorMessage = '服务器返回了非法的响应格式';
                        const errorMatch = responseText.match(/<b>([^<]+)<\/b>/);
                        if (errorMatch && errorMatch[1]) {
                            errorMessage = errorMatch[1];
                        }
                        
                        throw new Error(errorMessage);
                    }
                    
                    // 尝试解析为JSON
                    let analysisResult;
                    try {
                        analysisResult = JSON.parse(responseText);
                        
                        if (!analysisResult.content) {
                            throw new Error('API响应缺少content字段');
                        }
                        
                        // 停止倒计时
                        stopCountdown();
                        
                        // 隐藏loading状态
                        loading.style.display = 'none';
                        
                        // 清理content内容
                        analysisResult.content = removeThinkTags(analysisResult.content);
                        
                        // 显示分析结果 - 直接作为HTML展示，而不使用Markdown
                        aiResponseArea.innerHTML = `<div class="analysis-content">
                            ${analysisResult.content.replace(/\n/g, '<br>')}
                        </div>`;
                        aiResponseArea.style.display = 'block';
                        
                        // 允许复制结果
                        if (copyBtn) copyBtn.style.display = 'block';
                        
                    } catch (jsonError) {
                        console.error('JSON解析错误:', jsonError);
                        console.error('原始响应:', responseText);
                        
                        // 直接显示原始响应（如果不是JSON）
                        loading.style.display = 'none';
                        
                        aiResponseArea.innerHTML = `<div class="alert alert-warning">
                            <h4>API返回了非JSON格式的响应</h4>
                            <p>原始响应内容如下：</p>
                            <pre style="max-height:300px;overflow:auto;">${escapeHtml(responseText)}</pre>
                        </div>`;
                        aiResponseArea.style.display = 'block';
                        
                        throw new Error('无法解析API响应为JSON: ' + jsonError.message);
                    }
                } catch (error) {
                    // 停止计时器和进度条
                    stopCountdown();
                    
                    // 隐藏loading状态
                    loading.style.display = 'none';
                    
                    // 显示错误信息
                    aiResponseArea.innerHTML = `<div class="alert alert-danger">
                        <h4>生成分析时出错</h4>
                        <p>${error.message}</p>
                        <p>请稍后重试或联系管理员。</p>
                    </div>`;
                    aiResponseArea.style.display = 'block';
                    
                    console.error('AI分析生成错误:', error);
                }
            } catch (error) {
                console.error('出错了:', error);
                // 停止倒计时
                stopCountdown();
                
                // 显示错误信息
                aiResponseArea.style.display = 'block';
                loading.style.display = 'none';
                aiResponseArea.innerHTML = `<div class="alert alert-danger">分析生成失败: ${error.message}</div>`;
            }
        } catch (error) {
            console.error('出错了:', error);
            // 停止倒计时
            stopCountdown();
            
            // 显示错误信息
            aiResponseArea.style.display = 'block';
            loading.style.display = 'none';
            aiResponseArea.innerHTML = `<div class="alert alert-danger">分析生成失败: ${error.message}</div>`;
        }
    });
});

// 辅助函数：HTML转义
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// 清理函数：移除<think>标签内容
function removeThinkTags(content) {
    // 移除<think>标签内的所有内容
    return content.replace(/<think>[\s\S]*?<\/think>/g, '')
        // 移除没有闭合的<think>标签及其后内容
        .replace(/<think>[\s\S]*/g, '')
        // 移除其他可能的思考标签
        .replace(/<reasoning>[\s\S]*?<\/reasoning>/g, '')
        .replace(/<reasoning>[\s\S]*/g, '');
}
</script>

<?php
// 包含页脚
include '../components/teacher_footer.php';

/**
 * 获取教师的班级
 */
function get_teacher_classes($teacher_id) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT DISTINCT c.id, c.name, c.grade
                FROM class_subject_teacher cst
                JOIN classes c ON cst.class_id = c.id
                WHERE cst.teacher_id = ?
                ORDER BY c.grade, c.name";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // 如果prepare失败，返回空数组
            return array();
        }
        
        $stmt->bind_param("i", $teacher_id);
        if (!$stmt->execute()) {
            // 如果执行失败，返回空数组
            return array();
        }
        
        $result = $stmt->get_result();
        
        $classes = array();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        
        return $classes;
    } catch (Exception $e) {
        // 捕获任何异常，返回空数组
        return array();
    }
}

/**
 * 获取教师教授的科目
 */
function get_teacher_subjects($teacher_id) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT DISTINCT s.id, s.name
                FROM class_subject_teacher cst
                JOIN subjects s ON cst.subject_id = s.id
                WHERE cst.teacher_id = ?
                ORDER BY s.name";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // 如果prepare失败，返回空数组
            return array();
        }
        
        $stmt->bind_param("i", $teacher_id);
        if (!$stmt->execute()) {
            // 如果执行失败，返回空数组
            return array();
        }
        
        $result = $stmt->get_result();
        
        $subjects = array();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        
        return $subjects;
    } catch (Exception $e) {
        // 捕获任何异常，返回空数组
        return array();
    }
}

/**
 * 获取与教师相关的考试列表
 */
function get_teacher_exams($teacher_id) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT DISTINCT e.id, e.name, e.exam_date, e.type
                FROM exams e
                JOIN class_subject_teacher cst ON e.grade = (SELECT grade FROM classes WHERE id = cst.class_id)
                WHERE cst.teacher_id = ? AND e.created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
                ORDER BY e.exam_date DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // 如果prepare失败，返回空数组
            return array();
        }
        
        $stmt->bind_param("i", $teacher_id);
        if (!$stmt->execute()) {
            // 如果执行失败，返回空数组
            return array();
        }
        
        $result = $stmt->get_result();
        
        $exams = array();
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        
        return $exams;
    } catch (Exception $e) {
        // 捕获任何异常，返回空数组
        return array();
    }
}

/**
 * 获取所有班级 (临时替代方案)
 */
function get_all_classes() {
    $conn = getDBConnection();
    $sql = "SELECT id, name, grade FROM classes ORDER BY grade, name";
    
    $result = $conn->query($sql);
    
    $classes = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
    
    return $classes;
}

/**
 * 获取所有科目 (临时替代方案)
 */
function get_all_subjects() {
    $conn = getDBConnection();
    $sql = "SELECT id, name FROM subjects ORDER BY name";
    
    $result = $conn->query($sql);
    
    $subjects = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
    
    return $subjects;
}

/**
 * 获取所有考试 (临时替代方案)
 */
function get_all_exams() {
    $conn = getDBConnection();
    $sql = "SELECT id, name, exam_date, type
            FROM exams
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ORDER BY exam_date DESC";
    
    $result = $conn->query($sql);
    
    $exams = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }
    
    return $exams;
}

/**
 * 直接从数据库获取教师考试分析所需数据
 * 
 * 替代api/teacher_exam_data.php的功能，直接在PHP中获取数据
 * 
 * @param int $exam_id 考试ID
 * @param int|null $class_id 班级ID（可选）
 * @param int|null $subject_id 科目ID（可选）
 * @return array 分析所需的数据数组
 */
function get_teacher_exam_analysis_data($exam_id, $class_id = null, $subject_id = null) {
    $conn = getDBConnection();
    $result = array();
    
    // 获取当前教师ID
    $teacher_id = $_SESSION['user_id'];
    
    // 1. 获取考试基本信息
    $sql = "SELECT id, name, exam_date FROM exams WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam_result = $stmt->get_result();
    
    if ($exam_result->num_rows == 0) {
        return array('error' => '考试不存在');
    }
    
    $exam = $exam_result->fetch_assoc();
    $result['exam_id'] = $exam['id'];
    $result['exam_name'] = $exam['name'];
    $result['exam_date'] = $exam['exam_date'];
    
    // 检查class_subject_teacher表是否存在
    $tableExists = false;
    $checkTableResult = $conn->query("SHOW TABLES LIKE 'class_subject_teacher'");
    if ($checkTableResult && $checkTableResult->num_rows > 0) {
        $tableExists = true;
    }
    
    // 检查教师是否有权限获取这个班级和科目的数据
    if ($class_id) {
        $hasClassAccess = false;
        
        // 检查教师是否教授这个班级
        if ($tableExists) {
            $sql = "SELECT 1 FROM class_subject_teacher 
                    WHERE teacher_id = ? AND class_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $teacher_id, $class_id);
            $stmt->execute();
            $access_result = $stmt->get_result();
            
            if ($access_result->num_rows > 0) {
                $hasClassAccess = true;
            }
        } else {
            // 如果表不存在，假定教师有权限
            $hasClassAccess = true;
        }
        
        if (!$hasClassAccess) {
            return array('error' => '您没有权限查看该班级的数据');
        }
    }
    
    if ($subject_id) {
        $hasSubjectAccess = false;
        
        // 检查教师是否教授这个科目
        if ($tableExists) {
            $sql = "SELECT 1 FROM class_subject_teacher 
                    WHERE teacher_id = ? AND subject_id = ?";
            if ($class_id) {
                $sql .= " AND class_id = ?";
            }
            
            $stmt = $conn->prepare($sql);
            
            if ($class_id) {
                $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
            } else {
                $stmt->bind_param("ii", $teacher_id, $subject_id);
            }
            
            $stmt->execute();
            $access_result = $stmt->get_result();
            
            if ($access_result->num_rows > 0) {
                $hasSubjectAccess = true;
            }
        } else {
            // 如果表不存在，假定教师有权限
            $hasSubjectAccess = true;
        }
        
        if (!$hasSubjectAccess) {
            return array('error' => '您没有权限查看该科目的数据');
        }
    }
    
    // 2. 处理不同的分析场景
    
    // 情形1: 班级 + 科目（单科班级分析）
    if ($class_id && $subject_id) {
        // 获取班级信息
        $sql = "SELECT name FROM classes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        $class = $class_result->fetch_assoc();
        $result['class_name'] = $class['name'];
        
        // 获取科目信息
        $sql = "SELECT name FROM subjects WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject = $subject_result->fetch_assoc();
        $result['subject_name'] = $subject['name'];
        
        // 获取满分
        $sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $exam_id, $subject_id);
        $stmt->execute();
        $full_score_result = $stmt->get_result();
        $full_score_row = $full_score_result->fetch_assoc();
        $result['full_score'] = $full_score_row ? $full_score_row['full_score'] : 100;
        
        // 获取班级该科目成绩
        $sql = "SELECT s.score 
                FROM scores s
                JOIN students st ON s.student_id = st.id
                WHERE s.exam_id = ? AND st.class_id = ? AND s.subject_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $exam_id, $class_id, $subject_id);
        $stmt->execute();
        $scores_result = $stmt->get_result();
        
        // 计算统计数据
        $scores = array();
        while ($row = $scores_result->fetch_assoc()) {
            if ($row['score'] !== null) {
                $scores[] = (float)$row['score'];
            }
        }
        
        if (count($scores) == 0) {
            $result['error'] = '没有找到该班级该科目的成绩数据';
            return $result;
        }
        
        $result['max_score'] = max($scores);
        $result['min_score'] = min($scores);
        $result['avg_score'] = round(array_sum($scores) / count($scores), 2);
        
        // 计算年级平均分
        $sql = "SELECT AVG(s.score) as grade_avg
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE s.exam_id = ? AND s.subject_id = ? AND c.grade = (SELECT grade FROM classes WHERE id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $exam_id, $subject_id, $class_id);
        $stmt->execute();
        $grade_avg_result = $stmt->get_result();
        $grade_avg_row = $grade_avg_result->fetch_assoc();
        $result['grade_avg_score'] = round($grade_avg_row['grade_avg'], 2);
        
        // 计算分数段分布
        $result['score_ranges'] = calculate_score_ranges($scores, $result['full_score']);
    }
    // 情形2: 只有班级（班级整体分析）
    else if ($class_id && !$subject_id) {
        // 获取班级信息
        $sql = "SELECT name FROM classes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $class_result = $stmt->get_result();
        $class = $class_result->fetch_assoc();
        $result['class_name'] = $class['name'];
        
        // 获取该班级所有科目
        $sql = "SELECT DISTINCT s.subject_id, sub.name 
                FROM scores s
                JOIN subjects sub ON s.subject_id = sub.id
                JOIN students st ON s.student_id = st.id
                WHERE s.exam_id = ? AND st.class_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $exam_id, $class_id);
        $stmt->execute();
        $subjects_result = $stmt->get_result();
        
        $subjects = array();
        while ($subject = $subjects_result->fetch_assoc()) {
            $subjects[] = array(
                'id' => $subject['subject_id'],
                'name' => $subject['name']
            );
        }
        
        if (count($subjects) == 0) {
            $result['error'] = '没有找到该班级的成绩数据';
            return $result;
        }
        
        // 获取每个科目的统计数据
        $result['subjects'] = array();
        foreach ($subjects as $subject) {
            // 获取满分
            $sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $exam_id, $subject['id']);
            $stmt->execute();
            $full_score_result = $stmt->get_result();
            $full_score_row = $full_score_result->fetch_assoc();
            $full_score = $full_score_row ? $full_score_row['full_score'] : 100;
            
            // 获取成绩
            $sql = "SELECT s.score 
                    FROM scores s
                    JOIN students st ON s.student_id = st.id
                    WHERE s.exam_id = ? AND st.class_id = ? AND s.subject_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $exam_id, $class_id, $subject['id']);
            $stmt->execute();
            $scores_result = $stmt->get_result();
            
            $scores = array();
            while ($row = $scores_result->fetch_assoc()) {
                if ($row['score'] !== null) {
                    $scores[] = (float)$row['score'];
                }
            }
            
            if (count($scores) > 0) {
                $subject_data = array(
                    'id' => $subject['id'],
                    'name' => $subject['name'],
                    'full_score' => $full_score,
                    'max_score' => max($scores),
                    'min_score' => min($scores),
                    'avg_score' => round(array_sum($scores) / count($scores), 2)
                );
                
                // 年级平均分
                $sql = "SELECT AVG(s.score) as grade_avg
                        FROM scores s
                        JOIN students st ON s.student_id = st.id
                        JOIN classes c ON st.class_id = c.id
                        WHERE s.exam_id = ? AND s.subject_id = ? AND c.grade = (SELECT grade FROM classes WHERE id = ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $exam_id, $subject['id'], $class_id);
                $stmt->execute();
                $grade_avg_result = $stmt->get_result();
                $grade_avg_row = $grade_avg_result->fetch_assoc();
                $subject_data['grade_avg_score'] = round($grade_avg_row['grade_avg'], 2);
                
                $result['subjects'][] = $subject_data;
            }
        }
    }
    // 情形3: 只有科目（教师所教全部班级的该科目分析）
    else if ($subject_id && !$class_id) {
        // 获取科目信息
        $sql = "SELECT name FROM subjects WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject = $subject_result->fetch_assoc();
        $result['subject_name'] = $subject['name'];
        
        // 获取满分
        $sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $exam_id, $subject_id);
        $stmt->execute();
        $full_score_result = $stmt->get_result();
        $full_score_row = $full_score_result->fetch_assoc();
        $result['full_score'] = $full_score_row ? $full_score_row['full_score'] : 100;
        
        // 获取教师教授的班级
        $teacher_classes = array();
        
        if ($tableExists) {
            // 从class_subject_teacher表获取
            $sql = "SELECT class_id FROM class_subject_teacher 
                    WHERE teacher_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $teacher_id, $subject_id);
            $stmt->execute();
            $classes_result = $stmt->get_result();
            
            while ($class = $classes_result->fetch_assoc()) {
                $teacher_classes[] = $class['class_id'];
            }
        } else {
            // 如果表不存在，获取所有班级
            $sql = "SELECT id FROM classes";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $classes_result = $stmt->get_result();
            
            while ($class = $classes_result->fetch_assoc()) {
                $teacher_classes[] = $class['id'];
            }
        }
        
        if (empty($teacher_classes)) {
            $result['error'] = '没有找到您教授的班级';
            return $result;
        }
        
        // 构建SQL IN子句
        $placeholders = implode(',', array_fill(0, count($teacher_classes), '?'));
        
        // 获取该科目所有班级的成绩
        $sql = "SELECT s.score 
                FROM scores s
                JOIN students st ON s.student_id = st.id
                WHERE s.exam_id = ? AND s.subject_id = ? AND st.class_id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        
        // 绑定参数 - 修复这里的错误：类型字符串长度需要与参数数量一致
        $types = "ii" . str_repeat('i', count($teacher_classes));
        $bind_params = array($types, $exam_id, $subject_id);
        foreach ($teacher_classes as $class_id) {
            $bind_params[] = $class_id;
        }
        
        call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params));
        $stmt->execute();
        $scores_result = $stmt->get_result();
        
        $scores = array();
        while ($row = $scores_result->fetch_assoc()) {
            if ($row['score'] !== null) {
                $scores[] = (float)$row['score'];
            }
        }
        
        if (count($scores) == 0) {
            $result['error'] = '没有找到该科目的成绩数据';
            return $result;
        }
        
        $result['max_score'] = max($scores);
        $result['min_score'] = min($scores);
        $result['avg_score'] = round(array_sum($scores) / count($scores), 2);
        
        // 计算分数段分布
        $result['score_ranges'] = calculate_score_ranges($scores, $result['full_score']);
        
        // 获取各班级统计数据
        $sql = "SELECT c.id, c.name, 
                    MAX(s.score) as max_score,
                    MIN(s.score) as min_score,
                    AVG(s.score) as avg_score
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE s.exam_id = ? AND s.subject_id = ? AND st.class_id IN ($placeholders)
                GROUP BY c.id, c.name
                ORDER BY c.name";
        
        $stmt = $conn->prepare($sql);
        // 重新绑定参数
        call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params));
        $stmt->execute();
        $classes_result = $stmt->get_result();
        
        $result['classes'] = array();
        while ($class = $classes_result->fetch_assoc()) {
            $result['classes'][] = array(
                'id' => $class['id'],
                'name' => $class['name'],
                'max_score' => round($class['max_score'], 2),
                'min_score' => round($class['min_score'], 2),
                'avg_score' => round($class['avg_score'], 2)
            );
        }
    }
    // 情形4: 无筛选（教师所教全部班级整体分析）
    else {
        // 获取教师教授的班级
        $teacher_classes = array();
        
        if ($tableExists) {
            // 从class_subject_teacher表获取
            $sql = "SELECT DISTINCT class_id FROM class_subject_teacher WHERE teacher_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $classes_result = $stmt->get_result();
            
            while ($class = $classes_result->fetch_assoc()) {
                $teacher_classes[] = $class['class_id'];
            }
        } else {
            // 如果表不存在，获取所有班级
            $sql = "SELECT id FROM classes";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $classes_result = $stmt->get_result();
            
            while ($class = $classes_result->fetch_assoc()) {
                $teacher_classes[] = $class['id'];
            }
        }
        
        if (empty($teacher_classes)) {
            $result['error'] = '没有找到您教授的班级';
            return $result;
        }
        
        // 构建SQL IN子句
        $placeholders = implode(',', array_fill(0, count($teacher_classes), '?'));
        
        // 获取所有相关班级的科目列表
        $sql = "SELECT DISTINCT s.subject_id, sub.name 
                FROM scores s
                JOIN subjects sub ON s.subject_id = sub.id
                JOIN students st ON s.student_id = st.id
                WHERE s.exam_id = ? AND st.class_id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        
        // 绑定参数 - 修复这里的错误：类型字符串长度需要与参数数量一致
        $types = "i" . str_repeat('i', count($teacher_classes));
        $bind_params = array($types, $exam_id);
        foreach ($teacher_classes as $class_id) {
            $bind_params[] = $class_id;
        }
        
        call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params));
        $stmt->execute();
        $subjects_result = $stmt->get_result();
        
        $subjects = array();
        while ($subject = $subjects_result->fetch_assoc()) {
            $subjects[] = array(
                'id' => $subject['subject_id'],
                'name' => $subject['name']
            );
        }
        
        // 获取每个科目的统计数据
        $result['subjects'] = array();
        foreach ($subjects as $subject) {
            // 获取满分
            $sql = "SELECT full_score FROM exam_subjects WHERE exam_id = ? AND subject_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $exam_id, $subject['id']);
            $stmt->execute();
            $full_score_result = $stmt->get_result();
            $full_score_row = $full_score_result->fetch_assoc();
            $full_score = $full_score_row ? $full_score_row['full_score'] : 100;
            
            // 获取该科目所有班级的成绩
            $sql = "SELECT s.score 
                    FROM scores s
                    JOIN students st ON s.student_id = st.id
                    WHERE s.exam_id = ? AND s.subject_id = ? AND st.class_id IN ($placeholders)";
            
            $stmt = $conn->prepare($sql);
            
            // 绑定参数 - 修复这里的错误
            $types = "ii" . str_repeat('i', count($teacher_classes));
            $bind_params = array($types, $exam_id, $subject['id']);
            foreach ($teacher_classes as $class_id) {
                $bind_params[] = $class_id;
            }
            
            call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params));
            $stmt->execute();
            $scores_result = $stmt->get_result();
            
            $scores = array();
            while ($row = $scores_result->fetch_assoc()) {
                if ($row['score'] !== null) {
                    $scores[] = (float)$row['score'];
                }
            }
            
            if (count($scores) > 0) {
                $result['subjects'][] = array(
                    'id' => $subject['id'],
                    'name' => $subject['name'],
                    'full_score' => $full_score,
                    'max_score' => max($scores),
                    'min_score' => min($scores),
                    'avg_score' => round(array_sum($scores) / count($scores), 2),
                    'grade_avg_score' => round(array_sum($scores) / count($scores), 2) // 对于教师来说，这里的年级平均分实际上就是他教的班级的平均分
                );
            }
        }
        
        // 获取各班级统计数据
        $sql = "SELECT c.id, c.name, 
                    MAX(s.score) as max_score,
                    MIN(s.score) as min_score,
                    AVG(s.score) as avg_score
                FROM scores s
                JOIN students st ON s.student_id = st.id
                JOIN classes c ON st.class_id = c.id
                WHERE s.exam_id = ? AND st.class_id IN ($placeholders)
                GROUP BY c.id, c.name
                ORDER BY c.name";
        
        $stmt = $conn->prepare($sql);
        
        // 重新绑定参数
        $types = "i" . str_repeat('i', count($teacher_classes));
        $bind_params = array($types, $exam_id);
        foreach ($teacher_classes as $class_id) {
            $bind_params[] = $class_id;
        }
        
        call_user_func_array(array($stmt, 'bind_param'), refValues($bind_params));
        $stmt->execute();
        $classes_result = $stmt->get_result();
        
        $result['classes'] = array();
        while ($class = $classes_result->fetch_assoc()) {
            $result['classes'][] = array(
                'id' => $class['id'],
                'name' => $class['name'],
                'max_score' => round($class['max_score'], 2),
                'min_score' => round($class['min_score'], 2),
                'avg_score' => round($class['avg_score'], 2)
            );
        }
    }

    return $result;
}

/**
 * 计算分数段分布
 * 
 * @param array $scores 分数数组
 * @param float $full_score 满分
 * @return array 分数段分布数组
 */
function calculate_score_ranges($scores, $full_score) {
    // 根据满分设置分数段
    $ranges = array();
    
    if ($full_score == 100) {
        $ranges = array(
            array('min' => 90, 'max' => 100, 'range' => '90-100分', 'count' => 0),
            array('min' => 80, 'max' => 89.99, 'range' => '80-89分', 'count' => 0),
            array('min' => 70, 'max' => 79.99, 'range' => '70-79分', 'count' => 0),
            array('min' => 60, 'max' => 69.99, 'range' => '60-69分', 'count' => 0),
            array('min' => 0, 'max' => 59.99, 'range' => '0-59分', 'count' => 0)
        );
    } else if ($full_score == 150) {
        $ranges = array(
            array('min' => 135, 'max' => 150, 'range' => '135-150分', 'count' => 0),
            array('min' => 120, 'max' => 134.99, 'range' => '120-134分', 'count' => 0),
            array('min' => 105, 'max' => 119.99, 'range' => '105-119分', 'count' => 0),
            array('min' => 90, 'max' => 104.99, 'range' => '90-104分', 'count' => 0),
            array('min' => 0, 'max' => 89.99, 'range' => '0-89分', 'count' => 0)
        );
    } else {
        // 自动生成5个分段
        $step = $full_score / 5;
        for ($i = 4; $i >= 0; $i--) {
            $min = $i > 0 ? round($i * $step, 2) : 0;
            $max = $i < 4 ? round(($i + 1) * $step - 0.01, 2) : $full_score;
            $ranges[] = array(
                'min' => $min,
                'max' => $max,
                'range' => "{$min}-{$max}分",
                'count' => 0
            );
        }
    }
    
    // 统计各分数段人数
    foreach ($scores as $score) {
        foreach ($ranges as &$range) {
            if ($score >= $range['min'] && $score <= $range['max']) {
                $range['count']++;
                break;
            }
        }
    }
    
    // 只返回range和count字段
    $result = array();
    foreach ($ranges as $range) {
        $result[] = array(
            'range' => $range['range'],
            'count' => $range['count']
        );
    }
    
    return $result;
}

/**
 * 辅助函数：用于call_user_func_array的参数引用处理
 */
function refValues($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}
?> 