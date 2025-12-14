// 公共JavaScript函数库 - 提供系统通用的前端功能

/**
 * AJAX请求封装函数
 * 提供了简洁的方式发送AJAX请求，并处理响应
 * 
 * @param {string} url - 请求的URL地址
 * @param {object} data - 请求参数对象
 * @param {string} method - 请求方法（POST/GET），默认为POST
 * @param {function} callback - 请求完成后的回调函数
 */
function ajaxRequest(url, data, method = 'POST', callback) {
    // 创建XMLHttpRequest对象
    const xhr = new XMLHttpRequest();
    // 打开请求连接
    xhr.open(method, url, true);
    // 设置请求头（POST请求时使用表单编码）
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    // 请求状态变化事件处理
    xhr.onreadystatechange = function() {
        // 当请求完成（readyState === 4）
        if (xhr.readyState === 4) {
            // 当请求成功（status === 200）
            if (xhr.status === 200) {
                try {
                    // 解析JSON格式的响应数据
                    const response = JSON.parse(xhr.responseText);
                    // 如果提供了回调函数，调用它并传递响应数据
                    if (callback) callback(response);
                } catch (e) {
                    // 处理JSON解析错误
                    console.error('JSON解析错误:', e);
                    if (callback) callback({ success: false, message: '响应格式错误' });
                }
            } else {
                // 处理请求失败的情况
                if (callback) callback({ success: false, message: '请求失败' });
            }
        }
    };
    
    // 发送请求
    if (method === 'POST' && data) {
        // 如果是POST请求且有数据，将数据转换为URL编码的字符串
        const formData = new URLSearchParams(data).toString();
        xhr.send(formData);
    } else {
        // GET请求或没有数据的POST请求
        xhr.send();
    }
}

/**
 * 显示提示消息函数
 * 在页面顶部显示一个可自动消失的提示框
 * 
 * @param {string} message - 要显示的消息内容
 * @param {string} type - 消息类型（info/success/warning/danger），默认为info
 */
function showAlert(message, type = 'info') {
    // 创建提示框元素
    const alertDiv = document.createElement('div');
    // 设置提示框样式类
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    // 设置提示框内容和关闭按钮
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // 查找插入位置（优先使用.main-content容器，否则使用body）
    const container = document.querySelector('.main-content') || document.body;
    // 在容器的第一个子元素前插入提示框
    container.insertBefore(alertDiv, container.firstChild);
    
    // 5秒后自动移除提示框
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * 确认对话框函数
 * 显示浏览器默认的确认对话框，用户确认后执行回调
 * 
 * @param {string} message - 确认消息内容
 * @param {function} callback - 用户确认后执行的回调函数
 */
function confirmAction(message, callback) {
    // 显示确认对话框
    if (confirm(message)) {
        // 用户确认后执行回调
        callback();
    }
}

/**
 * 格式化日期函数
 * 将日期字符串转换为本地化的日期时间格式
 * 
 * @param {string} dateString - ISO格式的日期字符串
 * @returns {string} 本地化的日期时间字符串（中文格式）
 */
function formatDate(dateString) {
    // 创建Date对象
    const date = new Date(dateString);
    // 转换为中文本地化格式
    return date.toLocaleString('zh-CN');
}

/**
 * 设置加载中状态函数
 * 禁用按钮并显示加载指示器
 * 
 * @param {HTMLElement} element - 要设置状态的DOM元素（通常是按钮）
 * @param {boolean} loading - 是否为加载状态，默认为true
 */
function setLoading(element, loading = true) {
    if (loading) {
        // 设置为加载状态：禁用按钮并显示加载文本
        element.disabled = true;
        element.innerHTML = '<span class="loading"></span> 处理中...';
    } else {
        // 取消加载状态：启用按钮
        element.disabled = false;
    }
}

/**
 * 移动端侧边栏切换函数
 * 切换侧边栏的显示/隐藏状态
 */
function toggleSidebar() {
    // 查找侧边栏元素
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        // 切换show类（控制显示/隐藏）
        sidebar.classList.toggle('show');
    }
}

// 页面加载完成后执行的初始化代码
document.addEventListener('DOMContentLoaded', function() {
    // 移动端菜单按钮事件监听
    const menuBtn = document.querySelector('.menu-toggle');
    if (menuBtn) {
        // 点击菜单按钮时切换侧边栏
        menuBtn.addEventListener('click', toggleSidebar);
    }
    
    // 自动隐藏页面上已有的提示消息，但排除答案显示框
    const alerts = document.querySelectorAll('.alert:not(.answer-box)');
    alerts.forEach(alert => {
        // 5秒后关闭提示消息
        setTimeout(() => {
            // 使用Bootstrap的Alert组件关闭提示
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});