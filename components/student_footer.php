    <!-- 主内容区结束 -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="<?php echo SITE_URL; ?>/student/help.php">帮助中心</a>
                    <a href="这里填问卷地址">反馈建议</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript 库 -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/charts.js"></script>
    
    <!-- 图表工具函数 -->
    <script>
    /**
     * 创建柱状图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} labels - X轴标签
     * @param {Array} datasets - 数据集
     * @param {Object} options - 图表选项
     */
    function createBarChart(canvasId, labels, datasets, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // 默认配置
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: options.title ? true : false,
                    text: options.title || '',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };
        
        // 合并选项
        const chartOptions = {...defaultOptions, ...options};
        
        // 创建图表
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions
        });
    }
    
    /**
     * 创建折线图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} labels - X轴标签
     * @param {Array} datasets - 数据集
     * @param {Object} options - 图表选项
     */
    function createLineChart(canvasId, labels, datasets, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // 默认配置
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: options.title ? true : false,
                    text: options.title || '',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    position: 'top',
                }
            }
        };
        
        // 合并选项
        const chartOptions = {...defaultOptions, ...options};
        
        // 创建图表
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions
        });
    }
    
    /**
     * 创建雷达图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} labels - 雷达图各维度标签
     * @param {Array} datasets - 数据集
     * @param {Object} options - 图表选项
     */
    function createRadarChart(canvasId, labels, datasets, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // 默认配置
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: options.title ? true : false,
                    text: options.title || '',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    position: 'top',
                }
            },
            scales: {
                r: {
                    angleLines: {
                        display: true
                    },
                    suggestedMin: 0
                }
            }
        };
        
        // 合并选项
        const chartOptions = {...defaultOptions, ...options};
        
        // 创建图表
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions
        });
    }
    
    /**
     * 创建饼图/环形图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} labels - 饼图各部分标签
     * @param {Array} data - 数据数组
     * @param {Object} options - 图表选项
     */
    function createPieChart(canvasId, labels, data, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        // 生成背景颜色
        const backgroundColors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)',
            'rgba(199, 199, 199, 0.7)',
            'rgba(83, 102, 255, 0.7)',
            'rgba(40, 159, 64, 0.7)',
            'rgba(210, 199, 199, 0.7)'
        ];
        
        // 默认配置
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: options.title ? true : false,
                    text: options.title || '',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    position: 'top',
                }
            }
        };
        
        // 合并选项
        const chartOptions = {...defaultOptions, ...options};
        
        // 创建图表
        new Chart(ctx, {
            type: options.doughnut ? 'doughnut' : 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors.slice(0, data.length),
                    borderWidth: 1
                }]
            },
            options: chartOptions
        });
    }
    
    // 下拉菜单功能
    document.addEventListener('DOMContentLoaded', function() {
        // 获取所有下拉菜单
        const dropdowns = document.querySelectorAll('.navbar-dropdown');
        
        // 为每个下拉菜单添加点击事件
        dropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('.nav-link');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // 点击触发器切换下拉菜单的显示
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // 关闭其他打开的下拉菜单
                dropdowns.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown) {
                        otherDropdown.querySelector('.dropdown-menu').classList.remove('show');
                    }
                });
                
                // 切换当前下拉菜单
                menu.classList.toggle('show');
            });
        });
        
        // 点击页面其他位置关闭下拉菜单
        document.addEventListener('click', function() {
            dropdowns.forEach(dropdown => {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu.classList.contains('show')) {
                    menu.classList.remove('show');
                }
            });
        });
        
        // 高亮当前页面对应的侧边栏链接
        if (document.querySelector('.student-sidebar')) {
            const currentPath = window.location.pathname;
            const sidebarLinks = document.querySelectorAll('.student-sidebar .nav-link');
            
            sidebarLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (currentPath.includes(href)) {
                    link.classList.add('active');
                }
            });
        }
    });
    </script>
</body>
</html> 