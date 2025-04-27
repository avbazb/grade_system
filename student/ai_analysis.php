<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// 检查权限
requireStudent();

// 获取学生信息
$student_id = $_SESSION['user_id'];
$student_info = get_student_info($student_id);

// 检查学生信息是否存在
if (!$student_info) {
    die("无法获取学生信息，请联系管理员");
}

// 获取学生所在班级的考试列表
$class_id = $student_info['class_id'];
$exams = get_class_exams($class_id);

// 页面标题
$page_title = 'AI成绩分析';

// 包含页眉
include '../components/student_header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">AI成绩分析</h5>
                </div>
                <div class="card-body">
                    <form id="aiAnalysisForm" class="mb-4">
                        <div class="form-group">
                            <label for="exam_id"><strong>选择考试：</strong></label>
                            <select class="form-control" id="exam_id" name="exam_id" required>
                                <option value="">请选择考试</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <div id="aiResponseArea" class="border rounded p-3 markdown-body" style="min-height: 300px; display: none;"></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">调试信息</h5>
                </div>
                <div class="card-body">
                    <div id="debugArea" class="border rounded p-3 bg-light" style="min-height: 100px; display: none;">
                        <h6>API原始响应：</h6>
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
            // 获取学生的考试数据
            const response = await fetch(`../api/student_exam_data.php?exam_id=${examId}`);
            if (!response.ok) {
                throw new Error('获取考试数据失败');
            }
            
            const examData = await response.json();
            if (examData.error) {
                throw new Error(examData.error);
            }
            
            // 构建提示信息
            const systemPrompt = "你是一位经验丰富的教育专家，擅长分析学生成绩数据并提供有针对性的学习建议。请根据提供的考试成绩数据，进行全面分析并提出改进建议。分析应包括优势科目、薄弱科目、与班级平均水平的差距、进步空间等维度。";
            
            // 准备给AI的数据格式
            const studentData = {
                student_name: examData.student_name,
                exam_name: examData.exam_name,
                subjects: examData.subjects,
                class_avg: examData.class_avg,
                grade_avg: examData.grade_avg
            };
            
            // 构建用户提示
            const userPrompt = `请分析以下学生在考试中的表现，并提供改进建议：
            
学生姓名：${studentData.student_name}
考试名称：${studentData.exam_name}

各科成绩详情：
${studentData.subjects.map(subject => 
    `${subject.name}: 得分 ${subject.score}/${subject.full_score} (班级平均: ${subject.class_avg}, 年级平均: ${subject.grade_avg})`
).join('\n')}

请提供详细的分析和针对性的学习建议，包括优势科目的继续发展策略和薄弱科目的提升方法。`;

            // 调用智普AI API
            const apiEndpoint = '../api/zhipu_ai.php';
            
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
            rawResponse.textContent = responseText;
            
            // 检查响应是否为HTML (通常表示PHP错误)
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
            } catch (parseError) {
                console.error('JSON解析错误:', parseError);
                console.log('收到的原始响应:', responseText.substring(0, 500) + '...');
                throw new Error('解析AI响应失败: 返回的不是有效的JSON格式');
            }
            
            if (!analysisResult || !analysisResult.content) {
                throw new Error('AI返回了无效的响应格式');
            }
            
            // 清理AI响应中的<think>标签内容
            analysisResult.content = removeThinkTags(analysisResult.content);
            
            // 停止倒计时
            stopCountdown();
            
            // 显示响应区域
            aiResponseArea.style.display = 'block';
            loading.style.display = 'none';
            
            // 直接将内容转换为HTML显示，不使用Markdown渲染
            aiResponseArea.innerHTML = `<div class="analysis-content">
                ${analysisResult.content.replace(/\n/g, '<br>')}
            </div>`;
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

// 辅助函数：移除<think>标签内容
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
include '../components/student_footer.php';

/**
 * 获取学生信息
 */
function get_student_info($student_id) {
    $conn = getDBConnection();
    $sql = "SELECT s.id, s.name, s.class_id
            FROM students s
            WHERE s.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * 获取班级的考试列表
 */
function get_class_exams($class_id) {
    $conn = getDBConnection();
    $sql = "SELECT e.id, e.name, e.exam_date, e.type
            FROM exams e
            JOIN classes c ON c.grade = (SELECT grade FROM classes WHERE id = ?)
            WHERE e.created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)
            GROUP BY e.id
            ORDER BY e.exam_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $exams = array();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    
    return $exams;
}
?> 