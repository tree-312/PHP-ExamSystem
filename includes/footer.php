<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_PATH; ?>assets/js/common.js"></script>

<!-- 仅在普通用户首页显示页脚 -->
<?php
// 获取当前页面路径
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri = $_SERVER['REQUEST_URI'];

// 判断是否为普通用户首页
$isUserHomePage = ($currentPage === 'home.php' && strpos($currentUri, '/user/') !== false);

if ($isUserHomePage):
    ?>
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4 mb-3 mb-md-0">
                    <p class="mb-1"><i class="bi bi-heart-fill"></i> 本系统由Tree开源</p>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <p class="mb-1"><i class="bi bi-wechat"></i> 联系方式</p>
                    <p class="mb-0">如有发现题目答案错误或者想增加新题库，请联系邮箱：<strong>......@gmail.com</strong></p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1"><i class="bi bi-book"></i> 项目信息</p>
                    <p class="mb-0">黑龙江科技大学-信息管理与信息系统-移动商务期末作业</p>
                </div>
            </div>
<!--            <div class="row mt-3">-->
<!--                <div class="col text-center text-muted">-->
<!--                    <p class="mb-0">&copy; --><?php //echo date('Y'); ?><!-- 大学生刷题系统</p>-->
<!--                </div>-->
<!--            </div>-->
        </div>
    </footer>
<?php endif; ?>
</body>
</html>


