/**
 * 成绩分析系统 - 主JavaScript文件
 */

document.addEventListener('DOMContentLoaded', function() {
    // 页面加载动画
    document.body.classList.add('fade-in');
    
    // 初始化工具提示
    initTooltips();
    
    // 初始化模态框
    initModals();
    
    // 初始化表单验证
    initFormValidation();
    
    // 初始化搜索功能
    initSearch();
    
    // 初始化切换功能
    initToggle();
    
    // 初始化筛选功能
    initFilters();
    
    // 初始化动画效果
    initAnimations();
});

/**
 * 初始化工具提示
 */
function initTooltips() {
    // 工具提示功能
    const tooltips = document.querySelectorAll('.tooltip');
    if (tooltips.length > 0) {
        tooltips.forEach(function(tooltip) {
            // 已经在CSS中实现
        });
    }
}

/**
 * 初始化模态框
 */
function initModals() {
    // 模态框打开按钮
    const modalButtons = document.querySelectorAll('[data-toggle="modal"]');
    if (modalButtons.length > 0) {
        modalButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                const modal = document.querySelector(target);
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            });
        });
    }
    
    // 模态框关闭按钮
    const closeButtons = document.querySelectorAll('.modal-close, [data-dismiss="modal"]');
    if (closeButtons.length > 0) {
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
    }
    
    // 点击模态框外部关闭
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
}

/**
 * 初始化表单验证
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    if (forms.length > 0) {
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
}

/**
 * 初始化搜索功能
 */
function initSearch() {
    const searchBoxes = document.querySelectorAll('.search-input');
    if (searchBoxes.length > 0) {
        searchBoxes.forEach(function(searchBox) {
            searchBox.addEventListener('input', function() {
                const searchValue = this.value.toLowerCase();
                const target = this.getAttribute('data-search-target');
                const items = document.querySelectorAll(target);
                
                if (items.length > 0) {
                    items.forEach(function(item) {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchValue)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }
            });
        });
    }
}

/**
 * 初始化切换功能
 */
function initToggle() {
    const toggles = document.querySelectorAll('.toggle-switch input');
    if (toggles.length > 0) {
        toggles.forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                const target = this.getAttribute('data-toggle-target');
                const targetEl = document.querySelector(target);
                
                if (targetEl) {
                    if (this.checked) {
                        targetEl.style.display = '';
                    } else {
                        targetEl.style.display = 'none';
                    }
                }
                
                // 处理百分制切换
                if (this.getAttribute('data-percentage-toggle') === 'true') {
                    const event = new CustomEvent('percentageToggle', {
                        detail: { isPercentage: this.checked }
                    });
                    document.dispatchEvent(event);
                }
            });
        });
    }
}

/**
 * 初始化筛选功能
 */
function initFilters() {
    const filterSelects = document.querySelectorAll('.filter-value');
    if (filterSelects.length > 0) {
        filterSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                const filterEvent = new CustomEvent('filterChange', {
                    detail: { filter: this.getAttribute('data-filter'), value: this.value }
                });
                document.dispatchEvent(filterEvent);
            });
        });
    }
    
    const filterButtons = document.querySelectorAll('.filter-apply');
    if (filterButtons.length > 0) {
        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filterEvent = new CustomEvent('applyFilters');
                document.dispatchEvent(filterEvent);
            });
        });
    }
    
    const resetButtons = document.querySelectorAll('.filter-reset');
    if (resetButtons.length > 0) {
        resetButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filterSelects = document.querySelectorAll('.filter-value');
                filterSelects.forEach(function(select) {
                    select.value = select.options[0].value;
                });
                
                const filterEvent = new CustomEvent('resetFilters');
                document.dispatchEvent(filterEvent);
            });
        });
    }
}

/**
 * 初始化动画效果
 */
function initAnimations() {
    // 添加滚动动画效果
    const animElements = document.querySelectorAll('.anim-fade-in, .anim-slide-in, .anim-bounce');
    
    if (animElements.length > 0) {
        const checkVisibility = function() {
            animElements.forEach(function(element) {
                if (isElementInViewport(element)) {
                    if (element.classList.contains('anim-fade-in')) {
                        element.classList.add('fade-in');
                    } else if (element.classList.contains('anim-slide-in')) {
                        element.classList.add('slide-in');
                    } else if (element.classList.contains('anim-bounce')) {
                        element.classList.add('bounce');
                    }
                }
            });
        };
        
        // 初始检查
        checkVisibility();
        
        // 滚动时检查
        window.addEventListener('scroll', checkVisibility);
    }
}

/**
 * 检查元素是否在视口中
 * 
 * @param {Element} element DOM元素
 * @return {boolean} 是否在视口中
 */
function isElementInViewport(element) {
    const rect = element.getBoundingClientRect();
    
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * 显示通知消息
 * 
 * @param {string} message 消息内容
 * @param {string} type 消息类型 (success, error, warning, info)
 * @param {number} duration 显示时长(毫秒)
 */
function showNotification(message, type = 'success', duration = 3000) {
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} slide-in`;
    notification.textContent = message;
    
    // 添加到页面
    document.body.appendChild(notification);
    
    // 定时移除
    setTimeout(function() {
        notification.classList.remove('slide-in');
        notification.classList.add('slide-out');
        
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 300);
    }, duration);
}

/**
 * AJAX请求包装器
 * 
 * @param {string} url 请求URL
 * @param {string} method HTTP方法
 * @param {Object} data 请求数据
 * @param {Function} successCallback 成功回调
 * @param {Function} errorCallback 错误回调
 */
function ajax(url, method = 'GET', data = null, successCallback = null, errorCallback = null) {
    const xhr = new XMLHttpRequest();
    
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (successCallback) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    successCallback(response);
                } catch (e) {
                    successCallback(xhr.responseText);
                }
            }
        } else {
            if (errorCallback) {
                errorCallback(xhr.statusText);
            }
        }
    };
    
    xhr.onerror = function() {
        if (errorCallback) {
            errorCallback('网络错误');
        }
    };
    
    if (data) {
        let params = '';
        
        if (typeof data === 'object') {
            for (const key in data) {
                if (params !== '') {
                    params += '&';
                }
                params += encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }
        } else {
            params = data;
        }
        
        xhr.send(params);
    } else {
        xhr.send();
    }
}

/**
 * 确认对话框
 * 
 * @param {string} message 消息内容
 * @param {Function} callback 回调函数
 */
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * 表格转Excel下载
 * 
 * @param {string} tableId 表格ID
 * @param {string} filename 文件名
 */
function tableToExcel(tableId, filename = 'data') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const wb = XLSX.utils.table_to_book(table);
    XLSX.writeFile(wb, filename + '.xlsx');
}

/**
 * 格式化日期
 * 
 * @param {Date|string} date 日期对象或日期字符串
 * @param {string} format 格式字符串
 * @return {string} 格式化后的日期字符串
 */
function formatDate(date, format = 'YYYY-MM-DD') {
    date = new Date(date);
    
    const pad = function(num) {
        return num < 10 ? '0' + num : num;
    };
    
    return format
        .replace('YYYY', date.getFullYear())
        .replace('MM', pad(date.getMonth() + 1))
        .replace('DD', pad(date.getDate()))
        .replace('HH', pad(date.getHours()))
        .replace('mm', pad(date.getMinutes()))
        .replace('ss', pad(date.getSeconds()));
} 