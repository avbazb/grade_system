    <!-- 主内容区结束 -->
    
    <!-- 页脚 -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-right">
                    <a href="<?php echo SITE_URL; ?>/teacher/help.php" class="text-muted mr-3">
                        <i class="fas fa-question-circle"></i> 帮助中心
                    </a>
                    <a href="这里填问卷地址" class="text-muted">
                        <i class="fas fa-comment-dots"></i> 反馈意见
                    </a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript 库 -->
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/chart.min.js"></script>
    <script src="../assets/js/xlsx.full.min.js"></script>
    <script src="../assets/js/charts.js"></script>

    <!-- 添加用户下拉菜单的JavaScript代码 -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 处理用户下拉菜单
        const userDropdown = document.querySelector('.user-dropdown');
        const dropdownMenu = userDropdown.querySelector('.dropdown-menu');
        
        // 点击用户头像或名称时切换下拉菜单
        userDropdown.querySelector('.nav-link').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // 点击页面其他区域关闭下拉菜单
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
        
        // 防止点击下拉菜单内部区域关闭菜单
        dropdownMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    </script>

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
    
    /**
     * 创建箱线图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} labels - X轴标签
     * @param {Array} datasets - 数据集
     * @param {Object} options - 图表选项
     */
    function createBoxPlotChart(canvasId, labels, datasets, options = {}) {
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
            type: 'boxplot',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions
        });
    }
    
    /**
     * 创建直方图
     * @param {string} canvasId - Canvas元素ID
     * @param {Array} data - 原始数据
     * @param {Object} options - 图表选项
     */
    function createHistogram(canvasId, data, options = {}) {
        // 默认bin数量
        const binCount = options.binCount || 10;
        
        // 找到数据的最小值和最大值
        const min = Math.min(...data);
        const max = Math.max(...data);
        
        // 计算bin宽度
        const binWidth = (max - min) / binCount;
        
        // 初始化bins
        const bins = Array(binCount).fill(0);
        const binLabels = [];
        
        // 生成bin标签
        for (let i = 0; i < binCount; i++) {
            const binStart = min + (i * binWidth);
            const binEnd = binStart + binWidth;
            binLabels.push(`${binStart.toFixed(1)}-${binEnd.toFixed(1)}`);
        }
        
        // 将数据分配到bins
        data.forEach(value => {
            if (value === max) {
                // 如果是最大值，放入最后一个bin
                bins[binCount - 1]++;
            } else {
                const binIndex = Math.floor((value - min) / binWidth);
                bins[binIndex]++;
            }
        });
        
        // 使用现有的柱状图函数
        createBarChart(canvasId, binLabels, [{
            label: '频数',
            data: bins,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }], {
            title: options.title || '分数分布直方图',
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '频数'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '分数区间'
                    }
                }
            }
        });
    }
    
    /**
     * Excel表格导出函数
     * @param {string} tableId - 表格元素ID
     * @param {string} filename - 导出的文件名
     */
    function tableToExcel(tableId, filename) {
        const table = document.getElementById(tableId);
        const wb = XLSX.utils.table_to_book(table);
        XLSX.writeFile(wb, filename + '.xlsx');
    }
    
    // 下拉菜单功能
    document.addEventListener('DOMContentLoaded', function() {
        // 高亮当前页面对应的侧边栏菜单项
        const currentPath = window.location.pathname;
        const sidebarLinks = document.querySelectorAll('.teacher-sidebar .nav-link');
        
        sidebarLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && currentPath.includes(href.split('/').pop())) {
                link.classList.add('active');
            }
        });
    });
    
    // 添加侧边栏切换按钮功能（针对移动设备）
    function toggleSidebar() {
        const sidebar = document.querySelector('.teacher-sidebar');
        sidebar.classList.toggle('active');
    }
    </script>
</body>
</html> 