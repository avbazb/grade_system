    <!-- 主内容区结束 -->
    
    <!-- 页脚 -->
    <footer class="mt-5 p-3 text-center">
    </footer>
    
    <!-- JavaScript文件 -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/charts.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/excel.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 实现下拉菜单功能
        const dropdowns = document.querySelectorAll('.navbar-dropdown');
        
        dropdowns.forEach(function(dropdown) {
            const toggle = dropdown.querySelector('.nav-link');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // 切换显示状态
                    if (menu.style.display === 'block') {
                        menu.style.display = 'none';
                    } else {
                        // 关闭所有其他打开的下拉菜单
                        document.querySelectorAll('.dropdown-menu').forEach(function(m) {
                            m.style.display = 'none';
                        });
                        
                        // 显示当前菜单
                        menu.style.display = 'block';
                    }
                });
            }
        });
        
        // 点击文档其他地方关闭下拉菜单
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        });
    });
    </script>
</body>
</html> 