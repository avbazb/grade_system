<?php
// 设置响应头为JSON格式
header('Content-Type: application/json');



// 防止PHP错误导致输出HTML而不是JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 错误处理函数
function handleError($message, $details = '') {
    error_log("AI API错误: " . $message . " - 详情: " . $details);
    echo json_encode(['error' => $message, 'details' => $details]);
    exit;
}

// 调试日志函数
function logDebug($message) {
    error_log("AI API调试: " . $message);
}

// 防止超时
set_time_limit(300);

// 智普AI API配置
$apiKey = "your_api_key";
$apiUrl = "https://open.bigmodel.cn/api/paas/v4/chat/completions";

try {
    // 获取请求数据
    $requestBody = file_get_contents('php://input');
    if (empty($requestBody)) {
        handleError('请求数据为空');
    }
    
    logDebug("收到请求: " . $requestBody);
    
    $requestData = json_decode($requestBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        handleError('无效的JSON数据: ' . json_last_error_msg());
    }
    
    $systemPrompt = isset($requestData['systemPrompt']) ? $requestData['systemPrompt'] : '';
    $userPrompt = isset($requestData['userPrompt']) ? $requestData['userPrompt'] : '';

    if (empty($userPrompt)) {
        handleError('提示词不能为空');
    }

    // 准备请求数据
    $messages = [];

    // 添加系统提示词（如果有）
    if (!empty($systemPrompt)) {
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
    }

    // 添加用户提示词
    $messages[] = [
        'role' => 'user',
        'content' => $userPrompt
    ];

    $requestBody = [
        'model' => 'glm-z1-flash',
        'messages' => $messages,
        'stream' => false, // 禁用流式输出
        'temperature' => 0.7,
        'top_p' => 0.9,
        'max_tokens' => 8000
    ];

    // 准备cURL请求
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    // 添加超时设置以避免无限等待
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 连接超时
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 执行超时

    logDebug("开始调用智普API...");
    
    // 执行请求
    $response = curl_exec($ch);

    // 检查错误
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        logDebug("cURL错误: " . $curlError);
        handleError('AI服务出错', $curlError);
    }

    // 获取HTTP状态码
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logDebug("API返回状态码: " . $httpCode);

    // 关闭cURL连接
    curl_close($ch);

    // 检查HTTP状态码
    if ($httpCode != 200) {
        logDebug("API返回非200状态码，响应内容: " . $response);
        handleError('API返回错误，状态码: ' . $httpCode, $response);
    }

    // 解析响应
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logDebug("JSON解析错误: " . json_last_error_msg() . ", 原始响应: " . $response);
        handleError('无法解析API响应', $response);
    }

    // 检查响应内容
    if (!isset($responseData['choices'][0]['message']['content'])) {
        logDebug("响应格式不正确: " . json_encode($responseData));
        handleError('API响应格式不正确', json_encode($responseData));
    }

    // 提取生成的内容
    $content = $responseData['choices'][0]['message']['content'];
    logDebug("成功获取AI响应，长度: " . strlen($content));

    // 清理内容中的思考标签
    $content = cleanThinkTags($content);

    // 返回结果
    echo json_encode(['content' => $content]);
    
} catch (Exception $e) {
    logDebug("异常: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    handleError('发生异常', $e->getMessage());
}

/**
 * 清理AI响应中的思考标签
 * 
 * @param string $content 原始内容
 * @return string 清理后的内容
 */
function cleanThinkTags($content) {
    // 移除<think>标签及其内容
    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
    // 移除未闭合的<think>标签及其后内容
    $content = preg_replace('/<think>.*$/s', '', $content);
    // 移除其他可能的思考标签
    $content = preg_replace('/<reasoning>.*?<\/reasoning>/s', '', $content);
    $content = preg_replace('/<reasoning>.*$/s', '', $content);
    
    return $content;
}
?> 