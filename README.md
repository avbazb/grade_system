# 成绩分析系统

## 系统功能
- 学生成绩录入与管理
- 成绩统计与分析
- 多维度数据报表
- AI智能分析（基于智普AI）
- 师生互动与反馈
- 多角色权限管理

## 使用说明
1. 管理员账号可以管理系统所有数据，包括用户、班级、科目、考试等
2. 教师账号可以管理自己教授班级和科目的成绩录入与查看
3. 学生账号可以查看自己的成绩和分析报告

## 技术栈
- 前端：HTML5, CSS3, JavaScript, Bootstrap 4, jQuery, Chart.js
- 后端：PHP 7.4+, MySQL 5.7+
- API集成：智普AI大语言模型

## 注意事项
- 系统需要PHP 7.4或更高版本
- 确保api/zhipu_ai.php中的API密钥有效
- 数据库连接配置在includes/config.php文件中

## 项目简介
本项目是一个基于HTML, CSS, JavaScript, PHP和MySQL的成绩分析系统，分为学生端、教师端和管理端。系统支持成绩上传、分析、图表展示等功能。

## 项目结构
```
/
├── README.md           // 项目说明文档
├── index.php           // 系统首页
├── assets/             // 静态资源文件夹
│   ├── css/            // CSS样式文件
│   ├── js/             // JavaScript文件
│   └── img/            // 图片资源
├── includes/           // PHP通用组件
│   ├── config.php      // 数据库配置文件
│   ├── functions.php   // 通用函数
│   ├── db.php          // 数据库连接
│   └── session.php     // 会话管理
├── admin/              // 管理员端
├── teacher/            // 教师端
├── student/            // 学生端
│   ├── index.php       // 学生首页
│   ├── all_scores.php  // 所有成绩页面
│   ├── analysis.php    // 成绩分析页面
│   ├── rankings.php    // 班级排名页面
│   └── profile.php     // 个人资料页面
├── components/         // 通用UI组件
│   ├── student_header.php // 学生端页头
│   └── student_footer.php // 学生端页脚
└── sql/                // SQL数据库文件
    └── grade_system.sql // 数据库结构和初始数据
```

