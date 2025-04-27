<?php
/**
 * 成绩分析系统 - 学生帮助中心
 * 
 * 提供学生使用系统的帮助指南
 */

// 包含配置文件
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/session.php';

// 设置页面标题
$pageTitle = '帮助中心';

// 包含学生页面头部
include_once '../components/student_header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- 侧边栏 -->
        <div class="col-md-3">
            <div class="student-sidebar">
                <h3>帮助主题</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#system-intro">
                            <i class="fas fa-info-circle"></i> 系统介绍
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#view-scores">
                            <i class="fas fa-list-alt"></i> 查看成绩
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#analysis-guide">
                            <i class="fas fa-chart-pie"></i> 成绩分析
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#ranking-guide">
                            <i class="fas fa-trophy"></i> 排名查看
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#faq">
                            <i class="fas fa-question-circle"></i> 常见问题
                        </a>
                    </li>
                </ul>
                
                <h3 class="mt-4">快速导航</h3>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/index.php">
                            <i class="fas fa-tachometer-alt"></i> 返回首页
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/feedback.php">
                            <i class="fas fa-comment-alt"></i> 意见反馈
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- 主内容区 -->
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">
                        <i class="fas fa-question-circle text-primary mr-2"></i>
                        帮助中心
                    </h2>
                    <p class="card-text">
                        欢迎使用成绩分析系统学生版帮助中心。本页面提供系统使用指南，帮助您更好地使用各项功能。
                    </p>
                </div>
            </div>
            
            <!-- 系统介绍 -->
            <div id="system-intro" class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle mr-2"></i>
                        系统介绍
                    </h5>
                </div>
                <div class="card-body">
                    <h6 class="text-primary">什么是成绩分析系统？</h6>
                    <p>
                        成绩分析系统是一个专为学生、教师和管理员设计的在线平台，旨在帮助您跟踪、分析和管理考试成绩数据。
                        本系统提供直观的图表、详细的数据分析和个性化的学习建议。
                    </p>
                    
                    <h6 class="text-primary mt-4">系统功能概览</h6>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-home text-primary mr-2"></i>首页概览</h6>
                                    <p class="card-text">展示您的最近考试成绩和学习进度，让您快速了解自己的学习状态。</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-list-alt text-success mr-2"></i>成绩查询</h6>
                                    <p class="card-text">提供全面的成绩查询功能，您可以查看所有历史考试的详细成绩。</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-chart-pie text-info mr-2"></i>成绩分析</h6>
                                    <p class="card-text">通过多样化的图表直观展示您的学科优势和需要提升的地方。</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title"><i class="fas fa-trophy text-warning mr-2"></i>排名查看</h6>
                                    <p class="card-text">展示您在班级中的排名情况，帮助您了解自己在班级中的相对位置。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 查看成绩 -->
            <div id="view-scores" class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list-alt mr-2"></i>
                        如何查看成绩
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb mr-2"></i> 本系统提供多种方式查看您的考试成绩，包括总览和详细查询。
                    </div>
                    
                    <h6 class="text-success">在首页查看最近考试</h6>
                    <ol>
                        <li>登录系统后，自动进入学生首页</li>
                        <li>在"最近考试成绩"卡片中，可以查看最近一次考试的成绩概览</li>
                        <li>系统会自动显示您与班级平均分的对比图表</li>
                    </ol>
                    
                    <h6 class="text-success mt-4">查看所有历史成绩</h6>
                    <ol>
                        <li>点击导航栏中的"所有成绩"菜单</li>
                        <li>在左侧边栏选择需要查看的考试</li>
                        <li>系统会显示选定考试的所有科目成绩、百分比和班级对比数据</li>
                        <li>页面底部提供直观的成绩统计图表</li>
                    </ol>
                    
                    <div class="text-center mt-4">
                        <img src="<?php echo SITE_URL; ?>/assets/img/help/view_scores.png" alt="查看成绩示例" class="img-fluid border rounded" style="max-width: 600px;">
                    </div>
                </div>
            </div>
            
            <!-- 成绩分析 -->
            <div id="analysis-guide" class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie mr-2"></i>
                        成绩分析指南
                    </h5>
                </div>
                <div class="card-body">
                    <h6 class="text-info">如何使用成绩分析功能</h6>
                    <p>
                        成绩分析页面提供了多种图表和数据分析工具，帮助您深入了解自己的学习状况。以下是使用指南：
                    </p>
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title">雷达图分析</h6>
                            <p class="card-text">
                                雷达图直观展示各科目的相对表现。图中的多边形面积越大，表示整体表现越好。
                                通过对比自己与班级平均的雷达图，可以发现自己的优势和弱势科目。
                            </p>
                        </div>
                    </div>
                    
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title">趋势图分析</h6>
                            <p class="card-text">
                                趋势图展示各科目在不同考试中的成绩变化。通过观察线条的走向，
                                您可以了解自己的进步情况和需要加强的方面。
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                        <strong>提示：</strong> 成绩分析基于历史数据，如果您是新用户或没有足够的考试记录，
                        某些分析功能可能无法提供完整的信息。请至少参加一次考试后再查看分析结果。
                    </div>
                </div>
            </div>
            
            <!-- 排名查看 -->
            <div id="ranking-guide" class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy mr-2"></i>
                        排名查看说明
                    </h5>
                </div>
                <div class="card-body">
                    <h6 class="text-warning">如何查看班级排名</h6>
                    <p>
                        排名查看功能可以帮助您了解自己在班级中的相对位置，从总分和各科目两个维度进行展示。
                    </p>
                    
                    <ul class="list-group mb-4">
                        <li class="list-group-item">
                            <i class="fas fa-arrow-right text-warning mr-2"></i>
                            <strong>总分排名：</strong> 展示您在班级中的总分排名情况，显示前10名和您自己的位置
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-arrow-right text-warning mr-2"></i>
                            <strong>科目排名：</strong> 展示您在每个科目中的排名情况，可以发现在不同科目中的排名差异
                        </li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        为了保护学生隐私，排名页面只显示前3名的具体名次和成绩，以及您自己的排名位置。
                    </div>
                </div>
            </div>
            
            <!-- 常见问题 -->
            <div id="faq" class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-question-circle mr-2"></i>
                        常见问题
                    </h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <div class="card">
                            <div class="card-header" id="faqOne">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        我看不到自己的成绩，怎么办？
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseOne" class="collapse show" aria-labelledby="faqOne" data-parent="#faqAccordion">
                                <div class="card-body">
                                    可能是因为老师尚未上传您的成绩数据。如果确认考试已结束超过3个工作日，请联系您的班主任或任课老师。
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header" id="faqTwo">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        我的成绩显示错误，如何处理？
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseTwo" class="collapse" aria-labelledby="faqTwo" data-parent="#faqAccordion">
                                <div class="card-body">
                                    如果您发现成绩有误，请及时联系相关科目的任课老师进行核对。成绩确认有误后，老师会在系统中进行修改。
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header" id="faqThree">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                        如何修改密码？
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseThree" class="collapse" aria-labelledby="faqThree" data-parent="#faqAccordion">
                                <div class="card-body">
                                    点击导航栏右上角的用户名，在下拉菜单中选择"修改密码"，或直接访问"个人资料"页面，按照提示输入当前密码和新密码即可完成修改。
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header" id="faqFour">
                                <h2 class="mb-0">
                                    <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                        系统支持查看哪些类型的考试？
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseFour" class="collapse" aria-labelledby="faqFour" data-parent="#faqAccordion">
                                <div class="card-body">
                                    本系统支持查看各类型的考试成绩，包括周测、月考、期中考试和期末考试等。只要老师在系统中录入，您就可以查看相应的成绩数据。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mb-4">
                <a href="<?php echo SITE_URL; ?>/student/index.php" class="btn btn-outline-secondary ml-2">
                    <i class="fas fa-home mr-1"></i> 返回首页
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
});
</script>

<?php
// 包含页脚
include_once '../components/student_footer.php';
?> 