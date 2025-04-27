/**
 * 成绩分析系统 - Excel处理文件
 * 使用SheetJS库
 */

/**
 * 解析Excel文件
 * 
 * @param {File} file Excel文件对象
 * @param {Function} callback 回调函数，接收解析结果
 */
function parseExcelFile(file, callback) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        
        // 获取第一个工作表
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        
        // 转换为JSON
        const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
        
        callback(jsonData);
    };
    
    reader.readAsArrayBuffer(file);
}

/**
 * 识别成绩表格数据
 * 
 * @param {Array} data 表格数据
 * @return {Object} 识别结果
 */
function recognizeGradeData(data) {
    if (!data || data.length < 4) {
        return {
            success: false,
            message: '表格数据不足，至少需要4行数据'
        };
    }
    
    // 基础科目名称
    const baseSubjects = ['语文', '数学', '英语', '物理', '化学', '地理', '生物', '历史', '政治'];
    
    // 学生数据列表
    const students = [];
    
    // 识别表头
    const headers = data[0];
    
    // 查找学号、班级、姓名的列索引
    let studentIdIndex = -1;
    let classIndex = -1;
    let nameIndex = -1;
    
    // 科目列表及其索引
    const subjects = [];
    const subjectIndices = {};
    
    // 查找表头中的关键列
    headers.forEach((header, index) => {
        if (!header) return;
        
        header = String(header).trim();
        
        if (header === '学号' || header.includes('学号')) {
            studentIdIndex = index;
        } else if (header === '班级' || header.includes('班级')) {
            classIndex = index;
        } else if (header === '姓名' || header.includes('姓名')) {
            nameIndex = index;
        } else {
            // 检查是否为科目
            const subject = baseSubjects.find(subj => header === subj || header.includes(subj));
            if (subject) {
                subjects.push(subject);
                subjectIndices[subject] = index;
            }
        }
    });
    
    // 验证是否找到必要的列
    if (studentIdIndex === -1 || classIndex === -1 || nameIndex === -1) {
        return {
            success: false,
            message: '未能识别表格中的学号、班级或姓名列'
        };
    }
    
    // 如果没有找到任何科目
    if (subjects.length === 0) {
        return {
            success: false,
            message: '未能识别表格中的科目列'
        };
    }
    
    // 从第2行开始解析学生数据（跳过表头）
    for (let i = 1; i < data.length; i++) {
        const row = data[i];
        if (!row || !row[studentIdIndex] || !row[nameIndex]) {
            continue; // 跳过空行或无学号/姓名的行
        }
        
        const student = {
            student_id: String(row[studentIdIndex]).trim(),
            class_name: row[classIndex] ? String(row[classIndex]).trim() : '',
            name: String(row[nameIndex]).trim(),
            scores: {}
        };
        
        // 解析科目成绩
        subjects.forEach(subject => {
            const index = subjectIndices[subject];
            if (index !== undefined && row[index] !== undefined) {
                const score = parseFloat(row[index]);
                if (!isNaN(score)) {
                    student.scores[subject] = score;
                } else {
                    student.scores[subject] = null; // 无效成绩
                }
            } else {
                student.scores[subject] = null; // 没有该科目成绩
            }
        });
        
        students.push(student);
    }
    
    return {
        success: true,
        students: students,
        subjects: subjects
    };
}

/**
 * 将数据导出为Excel文件
 * 
 * @param {Array} data 表格数据
 * @param {Array} headers 表头数组
 * @param {string} filename 文件名
 */
function exportToExcel(data, headers, filename = 'export') {
    // 创建工作表
    const ws = XLSX.utils.json_to_sheet(data, { header: headers });
    
    // 创建工作簿
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
    
    // 导出文件
    XLSX.writeFile(wb, filename + '.xlsx');
}

/**
 * 生成成绩导入模板
 * 
 * @param {Array} subjects 科目数组
 * @param {string} filename 文件名
 */
function generateGradeTemplate(subjects, filename = 'grade_template') {
    // 创建表头
    const headers = ['学号', '班级', '姓名', ...subjects];
    
    // 创建示例数据
    const data = [
        {
            '学号': '20230001',
            '班级': '高一(1)班',
            '姓名': '张三',
            ...subjects.reduce((obj, subject) => {
                obj[subject] = '';
                return obj;
            }, {})
        },
        {
            '学号': '20230002',
            '班级': '高一(1)班',
            '姓名': '李四',
            ...subjects.reduce((obj, subject) => {
                obj[subject] = '';
                return obj;
            }, {})
        }
    ];
    
    // 创建工作表
    const ws = XLSX.utils.json_to_sheet(data, { header: headers });
    
    // 创建工作簿
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
    
    // 导出文件
    XLSX.writeFile(wb, filename + '.xlsx');
} 