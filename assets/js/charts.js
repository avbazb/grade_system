/**
 * 成绩分析系统 - 图表JavaScript文件
 * 使用Chart.js库
 */

// 全局Chart.js配置
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "San Francisco", "Helvetica Neue", Helvetica, Arial, sans-serif';
    Chart.defaults.color = '#8e8e93';
    Chart.defaults.scale.grid.color = 'rgba(0, 0, 0, 0.05)';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.animation.duration = 1000;
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
}

// 图表颜色配置
const chartColors = {
    primary: '#007aff',
    secondary: '#5ac8fa',
    success: '#34c759',
    danger: '#ff3b30',
    warning: '#ff9500',
    info: '#5ac8fa',
    gray: '#8e8e93',
    subjects: [
        '#007aff',  // 语文
        '#34c759',  // 数学
        '#5ac8fa',  // 英语
        '#ff9500',  // 物理
        '#ff3b30',  // 化学
        '#af52de',  // 地理
        '#5856d6',  // 生物
        '#ff2d55',  // 历史
        '#03c2fc'   // 政治
    ]
};

/**
 * 创建柱状图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} datasets 数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createBarChart(canvasId, labels, datasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                beginAtZero: true
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
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
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} datasets 数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createLineChart(canvasId, labels, datasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                beginAtZero: true
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * 创建饼图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} data 数据数组
 * @param {Array} backgroundColor 背景色数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createPieChart(canvasId, labels, data, backgroundColor = [], options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'right',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.formattedValue;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((context.raw / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    if (backgroundColor.length === 0) {
        backgroundColor = chartColors.subjects.slice(0, data.length);
    }
    
    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColor,
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * 创建雷达图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} datasets 数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createRadarChart(canvasId, labels, datasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            r: {
                beginAtZero: true
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * 创建散点图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} datasets 数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createScatterChart(canvasId, datasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const x = context.parsed.x;
                        const y = context.parsed.y;
                        return `${label} (${x}, ${y})`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'X轴'
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Y轴'
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * 创建堆叠柱状图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} datasets 数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createStackedBarChart(canvasId, labels, datasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // 设置堆叠
    datasets.forEach(dataset => {
        dataset.stack = 'Stack';
    });
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                stacked: true,
                beginAtZero: true
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * 创建混合图表（柱状图+折线图）
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} labels 标签数组
 * @param {Array} barDatasets 柱状图数据集数组
 * @param {Array} lineDatasets 折线图数据集数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createMixedChart(canvasId, labels, barDatasets, lineDatasets, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    // 设置类型
    barDatasets.forEach(dataset => {
        dataset.type = 'bar';
    });
    
    lineDatasets.forEach(dataset => {
        dataset.type = 'line';
        dataset.tension = 0.1;
    });
    
    const datasets = [...barDatasets, ...lineDatasets];
    
    const defaultOptions = {
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                beginAtZero: true
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * 创建成绩分布直方图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} scores 成绩数组
 * @param {number} binSize 分组大小
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createScoreDistributionChart(canvasId, scores, binSize = 10, options = {}) {
    // 确保scores是一个数组并且包含数字
    if (!Array.isArray(scores) || scores.length === 0) {
        console.error('scores必须是一个非空数组');
        return null;
    }
    
    // 确保所有分数都是数字
    const numericScores = scores.map(score => parseFloat(score)).filter(score => !isNaN(score));
    
    if (numericScores.length === 0) {
        console.error('scores数组中没有有效的数字');
        return null;
    }
    
    // 计算分组
    const bins = {};
    const maxScore = Math.max.apply(null, numericScores);
    
    for (let i = 0; i <= maxScore; i += binSize) {
        bins[i] = 0;
    }
    
    numericScores.forEach(score => {
        const bin = Math.floor(score / binSize) * binSize;
        bins[bin] = (bins[bin] || 0) + 1;
    });
    
    const labels = Object.keys(bins).map(bin => `${bin}-${parseInt(bin) + binSize - 1}`);
    const data = Object.values(bins);
    
    const defaultOptions = {
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `人数: ${context.formattedValue}`;
                    }
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: '分数段'
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: '人数'
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createBarChart(canvasId, labels, [{
        data: data,
        backgroundColor: chartColors.primary,
        borderColor: chartColors.primary,
        borderWidth: 1
    }], chartOptions);
}

/**
 * 创建学生成绩雷达图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} subjects 科目数组
 * @param {Array} scores 成绩数组
 * @param {Array} maxScores 满分数组
 * @param {boolean} isPercentage 是否百分制
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createStudentRadarChart(canvasId, subjects, scores, maxScores, isPercentage = false, options = {}) {
    // 处理百分制
    let displayScores = [...scores];
    
    if (isPercentage) {
        displayScores = scores.map((score, index) => {
            return (score / maxScores[index]) * 100;
        });
    }
    
    const defaultOptions = {
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.formattedValue;
                        return `${label}: ${value}${isPercentage ? '%' : '分'}`;
                    }
                }
            }
        },
        scales: {
            r: {
                beginAtZero: true,
                max: isPercentage ? 100 : Math.max.apply(null, maxScores)
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createRadarChart(canvasId, subjects, [{
        label: '学生成绩',
        data: displayScores,
        fill: true,
        backgroundColor: 'rgba(0, 122, 255, 0.2)',
        borderColor: chartColors.primary,
        pointBackgroundColor: chartColors.primary,
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: chartColors.primary
    }], chartOptions);
}

/**
 * 创建班级各科目平均分柱状图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} subjects 科目数组
 * @param {Array} averages 平均分数组
 * @param {Array} maxScores 满分数组
 * @param {boolean} isPercentage 是否百分制
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createClassAverageChart(canvasId, subjects, averages, maxScores, isPercentage = false, options = {}) {
    // 处理百分制
    let displayAverages = [...averages];
    
    if (isPercentage) {
        displayAverages = averages.map((avg, index) => {
            return (avg / maxScores[index]) * 100;
        });
    }
    
    const backgroundColor = subjects.map((_, index) => chartColors.subjects[index % chartColors.subjects.length]);
    
    const defaultOptions = {
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.formattedValue;
                        return `平均分: ${value}${isPercentage ? '%' : '分'}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: isPercentage ? 100 : Math.max.apply(null, maxScores)
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createBarChart(canvasId, subjects, [{
        data: displayAverages,
        backgroundColor: backgroundColor,
        borderColor: backgroundColor,
        borderWidth: 1
    }], chartOptions);
}

/**
 * 创建学生历次考试成绩折线图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} examNames 考试名称数组
 * @param {Object} scoreData 各科目成绩数据 {科目名: [成绩数组]}
 * @param {Object} maxScoreData 各科目满分数据 {科目名: [满分数组]}
 * @param {boolean} isPercentage 是否百分制
 * @param {Array} selectedSubjects 选中的科目数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createExamTrendChart(canvasId, examNames, scoreData, maxScoreData, isPercentage = false, selectedSubjects = null, options = {}) {
    const datasets = [];
    
    // 如果没有指定科目，则显示所有科目
    if (!selectedSubjects) {
        selectedSubjects = Object.keys(scoreData);
    }
    
    selectedSubjects.forEach((subject, index) => {
        if (!scoreData[subject]) return;
        
        let displayScores = [...scoreData[subject]];
        
        if (isPercentage && maxScoreData[subject]) {
            displayScores = scoreData[subject].map((score, idx) => {
                return score / maxScoreData[subject][idx] * 100;
            });
        }
        
        datasets.push({
            label: subject,
            data: displayScores,
            borderColor: chartColors.subjects[index % chartColors.subjects.length],
            backgroundColor: chartColors.subjects[index % chartColors.subjects.length],
            tension: 0.1,
            fill: false
        });
    });
    
    const defaultOptions = {
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.dataset.label || '';
                        const value = context.formattedValue;
                        return `${label}: ${value}${isPercentage ? '%' : '分'}`;
                    }
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createLineChart(canvasId, examNames, datasets, chartOptions);
}

/**
 * 创建班级排名变化折线图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} examNames 考试名称数组
 * @param {Array} ranks 排名数组
 * @param {Array} totalStudents 总人数数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createRankTrendChart(canvasId, examNames, ranks, totalStudents, options = {}) {
    // 计算排名百分比
    const rankPercentages = ranks.map((rank, index) => {
        return (rank / totalStudents[index]) * 100;
    });
    
    const defaultOptions = {
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const index = context.dataIndex;
                        const rank = ranks[index];
                        const total = totalStudents[index];
                        return `排名: ${rank}/${total} (${context.formattedValue}%)`;
                    }
                }
            }
        },
        scales: {
            y: {
                reverse: true,
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: '排名百分比(%)'
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createLineChart(canvasId, examNames, [{
        label: '排名百分比',
        data: rankPercentages,
        borderColor: chartColors.primary,
        backgroundColor: chartColors.primary,
        tension: 0.1,
        fill: false
    }], chartOptions);
}

/**
 * 创建成绩对比散点图
 * 
 * @param {string} canvasId Canvas元素ID
 * @param {Array} scoresX X轴成绩数组
 * @param {Array} scoresY Y轴成绩数组
 * @param {string} labelX X轴标签
 * @param {string} labelY Y轴标签
 * @param {Array} studentNames 学生姓名数组
 * @param {Object} options 图表选项
 * @return {Chart} Chart.js实例
 */
function createScoreComparisonChart(canvasId, scoresX, scoresY, labelX, labelY, studentNames = [], options = {}) {
    // 准备数据
    const data = scoresX.map((score, index) => {
        return {
            x: score,
            y: scoresY[index],
            name: studentNames[index] || `学生${index + 1}`
        };
    });
    
    const defaultOptions = {
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const pointIndex = context.dataIndex;
                        const name = data[pointIndex].name;
                        const x = context.parsed.x;
                        const y = context.parsed.y;
                        return `${name}: ${labelX}(${x}), ${labelY}(${y})`;
                    }
                }
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: labelX
                }
            },
            y: {
                title: {
                    display: true,
                    text: labelY
                }
            }
        }
    };
    
    const chartOptions = Object.assign({}, defaultOptions, options);
    
    return createScatterChart(canvasId, [{
        data: data,
        backgroundColor: chartColors.primary
    }], chartOptions);
}

/**
 * 更新图表数据
 * 
 * @param {Chart} chart Chart.js实例
 * @param {Array} labels 标签数组
 * @param {Array} datasets 数据集数组
 */
function updateChartData(chart, labels = null, datasets = null) {
    if (!chart) return;
    
    if (labels) {
        chart.data.labels = labels;
    }
    
    if (datasets) {
        chart.data.datasets = datasets;
    }
    
    chart.update();
}

/**
 * 销毁图表
 * 
 * @param {Chart} chart Chart.js实例
 */
function destroyChart(chart) {
    if (chart) {
        chart.destroy();
    }
} 