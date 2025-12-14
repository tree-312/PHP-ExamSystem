<?php
/**
 * 用户刷题页面
 * 实现题目展示、答题提交、答案解析、进度管理等核心功能
 */

// 引入配置文件（包含数据库连接、工具函数等）
require_once '../config.php';

// 检查用户权限（确保只有普通用户可以访问此页面）
checkUser();

// 设置页面标题
$page_title = '刷题';

// 获取数据库连接
$pdo = getDB();

// 获取请求参数
$bankId = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : 0; // 题库ID
$chapterId = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0; // 章节ID
$randomMode = isset($_GET['random']) && $_GET['random'] == '1'; // 是否为随机模式

// 参数验证：检查是否缺少必要参数
if (!$bankId || !$chapterId) {
    // 缺少参数时重定向到首页
    header('Location: home.php');
    exit;
}

// 获取题库和章节信息
$stmt = $pdo->prepare("SELECT * FROM question_banks WHERE id = ?");
$stmt->execute([$bankId]);
$bank = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM chapters WHERE id = ?");
$stmt->execute([$chapterId]);
$chapter = $stmt->fetch();

// 验证题库和章节是否存在
if (!$bank || !$chapter) {
    // 不存在时重定向到首页
    header('Location: home.php');
    exit;
}
?>
<?php include '../includes/header.php'; ?> <!-- 引入页面头部 -->
<?php include 'includes/navbar.php'; ?> <!-- 引入用户导航栏 -->

<div class="container quiz-container">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($bank['name']); ?></h5> <!-- 显示题库名称 -->
                    <small class="text-muted"><?php echo htmlspecialchars($chapter['chapter_name']); ?></small> <!-- 显示章节名称 -->
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <!-- 返回按钮 -->
                    <a href="<?php echo BASE_PATH; ?>user/home.php?bank_id=<?php echo $bankId; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline">返回</span>
                    </a>
                    <!-- 切换顺序/乱序模式按钮 -->
                    <button class="btn btn-<?php echo $randomMode ? 'success' : 'outline-primary'; ?> btn-sm" onclick="toggleRandom()">
                        <i class="bi bi-<?php echo $randomMode ? 'shuffle' : 'list'; ?>"></i>
                        <span class="d-none d-md-inline"><?php echo $randomMode ? '乱序模式' : '顺序模式'; ?></span>
                        <span class="d-md-none"><?php echo $randomMode ? '乱序' : '顺序'; ?></span>
                    </button>
                    <!-- 重置章节进度按钮 -->
                    <button class="btn btn-outline-danger btn-sm" onclick="resetChapter()">
                        <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-md-inline">重置进度</span>
                        <span class="d-md-none">重置</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 题目容器 -->
    <div id="questionContainer" class="question-card">
        <div class="text-center py-5">
            <div class="loading"></div>
            <p class="mt-3">加载题目中...</p>
        </div>
    </div>
</div>

<script>
    // 全局变量定义
    const BASE_PATH = '<?php echo BASE_PATH; ?>'; // 基础路径
    let currentQuestionId = null; // 当前题目ID
    let isAnswered = false; // 是否已答题标记
    let randomMode = <?php echo $randomMode ? 'true' : 'false'; ?>; // 是否为随机模式
    let bankId = <?php echo $bankId; ?>; // 题库ID
    let chapterId = <?php echo $chapterId; ?>; // 章节ID

    /**
     * 切换顺序/乱序模式
     */
    function toggleRandom() {
        randomMode = !randomMode;
        // 刷新页面应用新的模式
        window.location.href = `${BASE_PATH}user/quiz.php?bank_id=${bankId}&chapter_id=${chapterId}&random=${randomMode ? 1 : 0}`;
    }

    /**
     * 加载题目
     * @param {number|null} currentId - 当前题目ID（用于顺序模式下获取下一题）
     */
    function loadQuestion(currentId = null) {
        let url = `${BASE_PATH}api/get_question.php?bank_id=${bankId}&chapter_id=${chapterId}&random=${randomMode ? 1 : 0}`;
        if (currentId && !randomMode) {
            url += `&current_id=${currentId}`;
        }
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.question) {
                    displayQuestion(data.question);
                } else {
                    // 没有更多题目时显示完成提示
                    document.getElementById('questionContainer').innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">恭喜！本章节题目已完成</h5>
                        <a href="${BASE_PATH}user/home.php?bank_id=${bankId}" class="btn btn-primary mt-3">返回章节列表</a>
                    </div>
                `;
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('加载题目失败', 'danger');
            });
    }

    /**
     * 显示题目
     * @param {object} question - 题目数据
     * @param {boolean} isPrevQuestion - 是否为上一题
     */
    function displayQuestion(question, isPrevQuestion = false) {
        currentQuestionId = question.id;
        isAnswered = question.answered || false;

        // 题型名称映射
        const typeNames = {
            'single': '单选题',
            'multiple': '多选题',
            'fill': '填空题',
            'essay': '论述题'
        };

        // 生成选项HTML
        let optionsHtml = '';
        if (question.options && question.options.length > 0) {
            // 乱序模式下打乱选项（上一题不打乱）
            let options = [...question.options];
            if (randomMode && !isPrevQuestion) {
                options = options.sort(() => Math.random() - 0.5);
            }

            // 生成选项列表
            options.forEach(opt => {
                const inputType = question.type === 'multiple' ? 'checkbox' : 'radio';
                const checked = (isAnswered && question.user_answer && question.user_answer.includes(opt.option_label)) ? 'checked' : '';
                const selectedClass = checked ? 'selected' : '';
                optionsHtml += `
                <div class="option-item ${selectedClass}" onclick="selectOption(this)">
                    <input type="${inputType}" name="answer" value="${opt.option_label}" style="display: none;" ${checked}>
                    <span class="option-label">${opt.option_label}</span>
                    <span>${opt.option_text}</span>
                </div>
            `;
            });
        } else {
            // 文本输入型题目（填空题、论述题）
            const textValue = (isAnswered && question.user_answer && question.user_answer.length > 0) ? question.user_answer[0] : '';
            optionsHtml = `
            <div class="mb-3">
                <textarea class="form-control" id="textAnswer" rows="4" placeholder="请输入答案..." ${isAnswered ? 'readonly' : ''}>${textValue}</textarea>
            </div>
        `;
        }

        // 生成答题结果HTML
        let resultHtml = '';
        if (isAnswered) {
            const correctAnswer = JSON.parse(question.answer || '[]');
            const isCorrect = question.is_correct;
            const analysis = question.analysis || '';

            // 论述题处理（不显示正确/错误，只显示答案和解析）
            if (question.type === 'essay') {
                resultHtml = `
                <div class="alert alert-info mt-3">
                    <h5><i class="bi bi-info-circle"></i> 参考答案</h5>
                    <p class="mb-1"><strong>答案：</strong>${Array.isArray(correctAnswer) ? correctAnswer.join(', ') : correctAnswer}</p>
                    ${analysis ? `<div class="analysis-box mt-2">${analysis}</div>` : ''}
                </div>
            `;
            } else {
                // 其他题型处理（显示正确/错误状态）
                resultHtml = `
                <div class="alert alert-${isCorrect ? 'success' : 'danger'} mt-3">
                    <h5><i class="bi bi-${isCorrect ? 'check-circle' : 'x-circle'}"></i> ${isCorrect ? '回答正确！' : '回答错误'}</h5>
                    <p class="mb-1"><strong>正确答案：</strong>${Array.isArray(correctAnswer) ? correctAnswer.join(', ') : correctAnswer}</p>
                    ${analysis ? `<div class="analysis-box mt-2">${analysis}</div>` : ''}
                </div>
            `;
            }
        }

        // 生成完整的题目HTML
        const html = `
        <div class="question-title">
            <span class="question-type-badge type-${question.type}">${typeNames[question.type]}</span>
            ${question.title}
        </div>
        <div id="optionsArea">
            ${optionsHtml}
        </div>
        <div id="resultArea">${resultHtml}</div>
        <div class="mt-4 d-flex justify-content-between align-items-center">
            <button class="btn btn-outline-secondary btn-lg" id="prevBtn" onclick="loadPrevQuestion()">
                <i class="bi bi-arrow-left"></i> 上一题
            </button>
            <button class="btn btn-primary btn-lg" id="submitBtn" onclick="submitAnswer()" ${isAnswered ? 'style="display: none;"' : ''}>
                <i class="bi bi-check-circle"></i> 提交答案
            </button>
            <button class="btn btn-warning btn-lg" id="hideAnswerBtn" onclick="hideAnswer()" ${isAnswered ? '' : 'style="display: none;"'}>
                <i class="bi bi-eye-slash"></i> 隐藏答案
            </button>
            <button class="btn btn-outline-primary btn-lg" id="nextBtn" onclick="loadNextQuestion()">
                <i class="bi bi-arrow-right"></i> 下一题
            </button>
        </div>
    `;

        // 更新题目容器内容
        document.getElementById('questionContainer').innerHTML = html;

        // 已答题时显示正确/错误标记
        if (isAnswered) {
            setTimeout(() => {
                const correctAnswer = JSON.parse(question.answer || '[]');
                document.querySelectorAll('.option-item').forEach(item => {
                    item.style.pointerEvents = 'none'; // 禁用点击
                    const input = item.querySelector('input');
                    if (input) {
                        if (correctAnswer.includes(input.value)) {
                            item.classList.add('correct'); // 标记正确答案
                        } else if (input.checked && !correctAnswer.includes(input.value)) {
                            item.classList.add('wrong'); // 标记错误答案
                        }
                    }
                });
            }, 100);
        }
    }

    /**
     * 选择选项（单选/多选）
     * @param {HTMLElement} element - 选项元素
     */
    function selectOption(element) {
        const input = element.querySelector('input[type="radio"], input[type="checkbox"]');
        if (!input) return;

        if (input.type === 'radio') {
            // 单选题：只能选择一个选项
            document.querySelectorAll('.option-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            input.checked = true;
        } else {
            // 多选题：可以选择多个选项
            element.classList.toggle('selected');
            input.checked = !input.checked;
        }
    }

    /**
     * 提交答案
     */
    function submitAnswer() {
        let userAnswer = [];

        if (currentQuestionId) {
            // 获取选中的答案
            const checkedInputs = document.querySelectorAll('input[name="answer"]:checked');
            if (checkedInputs.length > 0) {
                checkedInputs.forEach(input => userAnswer.push(input.value));
            } else {
                // 处理文本输入型题目
                const textAnswer = document.getElementById('textAnswer');
                if (textAnswer && textAnswer.value.trim()) {
                    userAnswer = [textAnswer.value.trim()];
                }
            }

            // 准备请求数据
            const formData = new URLSearchParams({
                question_id: currentQuestionId,
                user_answer: JSON.stringify(userAnswer)
            });

            // 发送提交答案请求
            fetch(`${BASE_PATH}api/submit_answer.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // 显示答题结果
                        showResult(data.is_correct, data.correct_answer, data.analysis);
                        isAnswered = true;
                    } else {
                        showAlert(data.message || '提交失败', 'danger');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showAlert('提交失败', 'danger');
                });
        }
    }

    /**
     * 显示答题结果
     * @param {boolean} isCorrect - 是否答对
     * @param {array} correctAnswer - 正确答案
     * @param {string} analysis - 解析
     */
    function showResult(isCorrect, correctAnswer, analysis) {
        // 获取题目类型
        const questionType = document.querySelector('.question-type-badge').textContent.includes('论述') ? 'essay' : 'other';

        // 禁用选项
        document.querySelectorAll('.option-item').forEach(item => {
            item.style.pointerEvents = 'none';
            const input = item.querySelector('input');
            if (input) {
                if (correctAnswer.includes(input.value)) {
                    item.classList.add('correct');
                } else if (input.checked && !correctAnswer.includes(input.value)) {
                    item.classList.add('wrong');
                }
            }
        });

        // 禁用文本输入
        const textAnswer = document.getElementById('textAnswer');
        if (textAnswer) {
            textAnswer.readOnly = true;
        }

        // 生成结果HTML
        let resultHtml = '';
        if (questionType === 'essay') {
            // 论述题结果显示
            resultHtml = `
            <div class="alert alert-info mt-3">
                <h5><i class="bi bi-info-circle"></i> 参考答案</h5>
                <p class="mb-1"><strong>答案：</strong>${Array.isArray(correctAnswer) ? correctAnswer.join(', ') : correctAnswer}</p>
                ${analysis ? `<div class="analysis-box mt-2">${analysis}</div>` : ''}
            </div>
        `;
        } else {
            // 其他题型结果显示
            resultHtml = `
            <div class="alert alert-${isCorrect ? 'success' : 'danger'} mt-3">
                <h5><i class="bi bi-${isCorrect ? 'check-circle' : 'x-circle'}"></i> ${isCorrect ? '回答正确！' : '回答错误'}</h5>
                <p class="mb-1"><strong>正确答案：</strong>${Array.isArray(correctAnswer) ? correctAnswer.join(', ') : correctAnswer}</p>
                ${analysis ? `<div class="analysis-box mt-2">${analysis}</div>` : ''}
            </div>
        `;
        }

        // 更新结果区域
        document.getElementById('resultArea').innerHTML = resultHtml;
        document.getElementById('resultArea').style.display = 'block';
        // 切换按钮显示状态
        document.getElementById('submitBtn').style.display = 'none';
        document.getElementById('hideAnswerBtn').style.display = 'inline-block';
        isAnswered = true;
    }

    /**
     * 隐藏答案（重新答题）
     */
    function hideAnswer() {
        // 重置状态
        isAnswered = false;

        // 清除结果区域
        document.getElementById('resultArea').innerHTML = '';
        document.getElementById('resultArea').style.display = 'none';

        // 恢复选项可点击
        document.querySelectorAll('.option-item').forEach(item => {
            item.style.pointerEvents = 'auto';
            item.classList.remove('correct', 'wrong', 'selected');
            const input = item.querySelector('input');
            if (input) {
                input.checked = false;
            }
        });

        // 恢复文本输入
        const textAnswer = document.getElementById('textAnswer');
        if (textAnswer) {
            textAnswer.readOnly = false;
            textAnswer.value = '';
        }

        // 切换按钮显示状态
        document.getElementById('submitBtn').style.display = 'inline-block';
        document.getElementById('hideAnswerBtn').style.display = 'none';
    }

    /**
     * 加载上一题
     */
    function loadPrevQuestion() {
        if (!currentQuestionId) {
            showAlert('没有上一题', 'warning');
            return;
        }

        // 发送获取上一题请求
        fetch(`${BASE_PATH}api/get_prev_question.php?bank_id=${bankId}&chapter_id=${chapterId}&current_question_id=${currentQuestionId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.question) {
                    displayQuestion(data.question, true);
                } else {
                    showAlert('没有上一题', 'warning');
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('加载上一题失败', 'danger');
            });
    }

    /**
     * 加载下一题
     */
    function loadNextQuestion() {
        if (!isAnswered) {
            // 未答题时询问是否跳过
            if (confirm('您还没有提交答案，确定要跳过这一题吗？')) {
                loadQuestion(currentQuestionId);
            }
            return;
        }
        loadQuestion(currentQuestionId);
    }

    /**
     * 重置章节进度
     */
    function resetChapter() {
        if (!confirm('确定要重置本章节的刷题进度吗？这将清除所有答题记录！')) {
            return;
        }

        // 准备请求数据
        const formData = new URLSearchParams({
            chapter_id: chapterId
        });

        // 发送重置进度请求
        fetch(`${BASE_PATH}api/reset_chapter.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('重置成功，可以重新开始刷题', 'success');
                    setTimeout(() => {
                        currentQuestionId = null;
                        loadQuestion();
                    }, 1000);
                } else {
                    showAlert(data.message || '重置失败', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showAlert('重置失败', 'danger');
            });
    }

    // 页面加载时获取第一题
    loadQuestion();
</script>

<?php include '../includes/footer.php'; ?>