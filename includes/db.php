<?php
/**
 * 数据库连接文件
 */

// 引入配置文件
require_once 'config.php';

// PDO 数据库连接
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

/**
 * 获取数据库连接
 * 
 * @return mysqli 数据库连接对象
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        // 创建连接
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // 检查连接
        if ($conn->connect_error) {
            die("数据库连接失败: " . $conn->connect_error);
        }
        
        // 设置字符集
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * 执行SQL查询
 * 
 * @param string $sql SQL查询语句
 * @return mysqli_result|bool 查询结果
 */
function query($sql) {
    $conn = getDBConnection();
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("SQL错误: " . $conn->error . " 在查询: " . $sql);
    }
    
    return $result;
}

/**
 * 获取单行数据
 * 
 * @param string $sql SQL查询语句
 * @param array $params 查询参数数组（可选）
 * @return array|null 查询结果数组或null
 */
function fetchOne($sql, $params = []) {
    if (!empty($params)) {
        // 如果提供了参数，使用预处理语句
        $conn = getDBConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("预处理错误: " . $conn->error . " 在查询: " . $sql);
            return null;
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
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        }
        
        $stmt->close();
        return null;
    } else {
        // 没有参数的常规查询
        $result = query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}

/**
 * 获取多行数据
 * 
 * @param string $sql SQL查询语句
 * @param array $params 查询参数数组（可选）
 * @return array 查询结果数组
 */
function fetchAll($sql, $params = []) {
    if (!empty($params)) {
        // 如果提供了参数，使用预处理语句
        $conn = getDBConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("预处理错误: " . $conn->error . " 在查询: " . $sql);
            return [];
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
        
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        $stmt->close();
        return $rows;
    } else {
        // 没有参数的常规查询
        $result = query($sql);
        $rows = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        
        return $rows;
    }
}

/**
 * 准备SQL语句并执行
 * 
 * @param string $sql SQL预处理语句
 * @param string $types 参数类型
 * @param array $params 参数数组
 * @return mysqli_stmt|bool 预处理语句对象或false
 */
function prepareAndExecute($sql, $types, $params) {
    $conn = getDBConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("预处理错误: " . $conn->error . " 在查询: " . $sql);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    if ($stmt->error) {
        error_log("执行错误: " . $stmt->error . " 在查询: " . $sql);
        return false;
    }
    
    return $stmt;
}

/**
 * 插入数据
 * 
 * @param string $table 表名
 * @param array $data 要插入的数据 ['列名' => '值']
 * @return int|bool 插入的ID或false
 */
function insert($table, $data) {
    $conn = getDBConnection();
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    
    $types = '';
    $values = [];
    
    foreach ($data as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        
        $values[] = $value;
    }
    
    $stmt = prepareAndExecute($sql, $types, $values);
    
    if ($stmt) {
        $id = $conn->insert_id;
        $stmt->close();
        return $id;
    }
    
    return false;
}

/**
 * 更新数据
 * 
 * @param string $table 表名
 * @param array $data 要更新的数据 ['列名' => '值']
 * @param string $where WHERE条件
 * @param string $types WHERE参数的类型
 * @param array $whereParams WHERE条件的参数
 * @return bool 更新是否成功
 */
function update($table, $data, $where, $whereTypes = '', $whereParams = []) {
    $conn = getDBConnection();
    
    $set = [];
    $types = '';
    $values = [];
    
    foreach ($data as $column => $value) {
        $set[] = "{$column} = ?";
        
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        
        $values[] = $value;
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $set);
    
    if (!empty($where)) {
        $sql .= " WHERE {$where}";
        $types .= $whereTypes;
        $values = array_merge($values, $whereParams);
    }
    
    $stmt = prepareAndExecute($sql, $types, $values);
    
    if ($stmt) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }
    
    return false;
}

/**
 * 删除数据
 * 
 * @param string $table 表名
 * @param string $where WHERE条件
 * @param string $types WHERE参数的类型
 * @param array $params WHERE条件的参数
 * @return bool 删除是否成功
 */
function delete($table, $where, $types, $params) {
    $conn = getDBConnection();
    
    $sql = "DELETE FROM {$table}";
    
    if (!empty($where)) {
        $sql .= " WHERE {$where}";
    }
    
    $stmt = prepareAndExecute($sql, $types, $params);
    
    if ($stmt) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }
    
    return false;
}

/**
 * 清理用户输入
 * 
 * @param string $data 用户输入数据
 * @return string 清理后的数据
 */
function sanitize($data) {
    $conn = getDBConnection();
    return $conn->real_escape_string($data);
} 