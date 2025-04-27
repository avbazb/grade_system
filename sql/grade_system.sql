-- 创建数据库
CREATE DATABASE IF NOT EXISTS grade_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE grade_system;

-- 设置外键检查
SET FOREIGN_KEY_CHECKS=0;

-- 删除表（如果存在）
DROP TABLE IF EXISTS scores;
DROP TABLE IF EXISTS exam_subjects;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS teacher_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS users;

-- 重新启用外键检查
SET FOREIGN_KEY_CHECKS=1;

-- 创建用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建班级表
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    grade VARCHAR(20) NOT NULL,
    class_teacher_id INT NULL,
    CONSTRAINT uc_class_name UNIQUE (grade, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建教师信息表
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_class_teacher BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 添加班级表的外键约束
ALTER TABLE classes
ADD CONSTRAINT fk_class_teacher
FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- 创建学生信息表
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建科目表
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建教师科目关联表
CREATE TABLE teacher_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY (teacher_id, subject_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建考试表
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('周测', '月考', '期中', '期末') NOT NULL,
    exam_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建考试科目配置表
CREATE TABLE exam_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    full_score DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY (exam_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建成绩表
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    score DECIMAL(5,2),
    class_id INT NOT NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY (student_id, exam_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入初始化数据
-- 管理员账号
INSERT INTO users (username, password, role, name) VALUES 
('admin', '$2y$10$9nGnZ4.kqgT4WF2i7CsMaOeOvXU2d7yc68MAQD9Y9UGUe6dU4A/bC', 'admin', '系统管理员');

-- 添加初始科目
INSERT INTO subjects (name) VALUES 
('语文'), ('数学'), ('英语'), ('物理'), ('化学'), ('地理'), ('生物'), ('历史'), ('政治');

-- 触发器：当学生班级变更时更新成绩表的班级ID
DELIMITER $$
CREATE TRIGGER update_scores_class_id
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    IF NEW.class_id != OLD.class_id THEN
        UPDATE scores SET class_id = NEW.class_id WHERE student_id = NEW.id;
    END IF;
END $$
DELIMITER ; 