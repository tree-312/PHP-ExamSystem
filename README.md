# 📚 大学生刷题系统
> 移动商务期末设计 - 黑龙江科技大学信息管理与信息系统专业

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap)
![ECharts](https://img.shields.io/badge/ECharts-5-FF6B00?style=for-the-badge&logo=apacheecharts)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## 🌟 项目简介
博主目前就读于哈尔滨·黑龙江科技大学信息管理与信息系统专业（大三），本项目最初是为完成移动商务期末作业开发，同时也为了方便自己和同学刷题——毕竟老师发放的 Word/PDF 格式题库灵活性太差了。

### 📝 开发背景
- 最初尝试使用免费的 Trae 开发，但效果不佳，项目无法正常运行且结构混乱
- 转而使用 Cursor 完成核心开发（忍痛付费😝）
- 专业背景：学习过 Python、Java、Spring、MySQL、C 语言，但未系统学习 PHP
- 专业定位和课程要求导致项目的开发周期短，需快速完成 Web 网站开发并部署到云服务器
- 当然老师是允许使用 AI 辅助开发的（AI 浪潮，打不过就加入～）

> 💡 小彩蛋：黑龙江科技大学的寒假超长哦（2025.12.22 - 2026.03.09），悄悄说的🤫

这是一个基于 PHP + MySQL 构建的完整刷题系统，功能完善，可直接用于学习练手或课程作业提交（不敢保证无 Bug，但核心功能稳定运行💪）。

## 🛠️ 技术栈
| 分类 | 技术选型 |
|------|----------|
| 后端 | PHP（原生） |
| 数据库 | MySQL >= 5.7 |
| 前端 | HTML + CSS + JavaScript（原生） |
| UI 框架 | Bootstrap 5 |
| 图表库 | ECharts 5 |
| 服务器 | Apache / Nginx |

## 功能特性

### 管理员功能
- ✅ 用户管理（增删改）
- ✅ 题库管理（增删改）
- ✅ 章节管理（增删改）
- ✅ 题目管理（增删改）
- ✅ CSV/JSON 批量导入题目
- ✅ 系统统计图表

### 用户功能
- ✅ **用户注册**：支持新用户注册
- ✅ 登录/注册
- ✅ 选择题库和章节
- ✅ 顺序/乱序刷题（乱序模式自动排除已答题目）
- ✅ 支持单选、多选、填空、论述题
- ✅ 题目搜索
- ✅ 题库预览（统一显示/隐藏答案按钮）
- ✅ 个人统计图表（正确率、易错章节、答题量）
- ✅ **组卷练习**（从题库随机抽题，自定义题目数量）
  - 创建组卷（选择题库和题目数量，题目乱序）
  - 答题界面（上一题/下一题，自动保存答案）
  - 提交后查看全部题目结果（可滚动查看，题目乱序显示）

## 安装部署

### 1. 环境要求

- PHP >= 7.4
- MySQL >= 5.7
- Apache/Nginx Web服务器

### 2. 数据库配置

1. 导入数据库结构：
```bash
mysql -u root -p < schema.sql
```

2. 导入示例数据（可选）：
```bash
mysql -u root -p < sample_data.sql
```

3. 修改数据库配置：
编辑 `config.php` 文件，修改数据库连接信息：
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'exam_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Web服务器配置

#### Apache配置
确保已启用 `mod_rewrite` 模块，项目根目录下的 `.htaccess` 文件会自动处理。

#### Nginx配置
```nginx
server {
    listen 80;
    server_name localhost;
    root /path/to/exam_system;
    index index.php login.php;

    location / {
        try_files $uri $uri/ /login.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 4. 访问系统

- 默认管理员账号：`admin` / `admin`
- 访问地址：`http://localhost/exam_system/login.php`
或 `http://localhost/exam_system` 均可

## 项目结构

```
exam_system/
├── admin/                 # 管理员后台
│   ├── dashboard.php      # 仪表盘
│   ├── users.php          # 用户管理
│   ├── banks.php          # 题库管理
│   ├── chapters.php       # 章节管理
│   ├── questions.php      # 题目管理
│   ├── import.php         # 批量导入
│   └── includes/
│       └── sidebar.php    # 侧边栏
├── user/                  # 用户前端
│   ├── home.php          # 首页
│   ├── quiz.php          # 刷题界面
│   ├── create_exam.php   # 创建组卷
│   ├── exam.php          # 组卷答题界面
│   ├── search.php        # 搜索题目
│   ├── preview.php       # 题库预览
│   ├── stats.php         # 统计图表
│   └── includes/
│       └── navbar.php    # 导航栏
├── api/                  # API接口
│   ├── get_chapters.php  # 获取章节
│   ├── get_question.php  # 获取题目
│   ├── get_prev_question.php # 获取上一题
│   ├── submit_answer.php # 提交答案
│   ├── reset_chapter.php # 重置章节进度
│   ├── save_exam_answer.php # 保存组卷答案
│   └── submit_exam.php   # 提交组卷
├── assets/               # 静态资源
│   ├── css/
│   │   └── style.css     # 样式文件
│   └── js/
│       └── common.js     # 公共JS
├── includes/             # 公共组件
│   ├── header.php        # 页面头部
│   └── footer.php        # 页面底部
├── config.php            # 配置文件
├── login.php             # 登录页面
├── logout.php            # 退出登录
├── schema.sql            # 数据库结构
├── sample_data.sql       # 示例数据
└── README.md             # 说明文档
```

## 批量导入格式

### CSV格式
- 1、第一行为固定表头：bank,chapter,title,type,options,answer,analysis（顺序不可改）
- 2、选项之间用 | 分隔，无需手动填写A/B/C标签（系统自动生成）
- 3、多选题答案用逗号分隔，且必须用英文双引号包裹（如："A,C"）
- 4、题型仅支持：single（单选）、multiple（多选）、fill（填空）、essay（论述）
- 5、字段内容包含逗号时，必须用英文双引号包裹（如答案"A,C"）
- 6、填空/论述题的options字段可留空，answer字段直接填写文本

```csv
bank,chapter,title,type,options,answer,analysis
数据挖掘,支持向量机,什么是SVM?,single,支持向量机|神经网络|决策树|K-means,A,SVM是一种监督学习算法
ERP理论与应用,ERP概述,ERP的主要特点？,multiple,集成性|实时性|模块化|复杂性,"A,C",ERP具有集成性和模块化特点
Python基础,变量,Python变量命名规则？,fill,,只能以字母/下划线开头,变量命名核心规则
Java基础,异常,简述try-catch执行顺序,essay,,,try块执行→异常则执行catch
```

### JSON格式
- 1、根节点必须包含：bank（题库名称，字符串）、questions（题目列表，数组）
- 2、每个题目必须包含：chapter（章节名称）、title（题目内容）、type（题型）
- 3、题型取值：single（单选）、multiple（多选）、fill（填空）、essay（论述）
- 4、单选/多选题必填：options（选项数组，如["选项1","选项2"]）、answer（答案数组，如["A"]或["A","C"]）
- 5、填空/论述题：options可留空数组，answer数组填文本（填空）或留空（论述）
- 6、答案格式：单选/多选必须是大写字母数组（如["A"]），填空/论述为文本数组（如["TPS（每秒事务数）"]）
- 7、所有字段使用英文引号，语法需严格符合JSON规范（逗号、括号闭合）

```json
{
  "bank": "软件测试题库",
  "questions": [
    {
      "chapter": "功能测试",
      "type": "single",
      "title": "以下哪项不是黑盒测试方法？",
      "options": ["等价类划分", "边界值分析", "语句覆盖", "场景法"],
      "answer": ["C"],
      "analysis": "语句覆盖属于白盒测试的逻辑覆盖方法"
    },
    {
      "chapter": "自动化测试",
      "type": "multiple",
      "title": "Python自动化测试常用的库有哪些？（多选）",
      "options": ["Selenium", "Appium", "pytest", "MySQLdb", "unittest"],
      "answer": ["A", "B", "C", "E"],
      "analysis": "MySQLdb是数据库操作库，非自动化测试核心库"
    },
    {
      "chapter": "性能测试",
      "type": "fill",
      "title": "性能测试中，衡量系统处理能力的核心指标是（）",
      "options": [],
      "answer": ["TPS（每秒事务数）"],
      "analysis": "TPS是Transactions Per Second的缩写，直接反映系统吞吐量"
    },
    {
      "chapter": "接口测试",
      "type": "essay",
      "title": "简述接口测试的主要流程",
      "options": [],
      "answer": [],
      "analysis": "1. 梳理接口文档；2. 设计测试用例；3. 执行测试（工具：Postman/JMeter）；4. 验证返回结果；5. 输出测试报告"
    }
  ]
}
```

## 题型说明

- **single**: 单选题（答案格式：`["A"]`）
- **multiple**: 多选题（答案格式：`["A", "C"]`）
- **fill**: 填空题（答案格式：`["答案内容"]`）
- **essay**: 论述题（答案格式：`["答案内容"]`）

## 功能说明

### 管理员后台
- **仪表盘**：显示系统统计数据和使用图表
- **用户管理**：管理用户账号和权限
- **题库管理**：管理题库和描述信息
- **章节管理**：管理章节和排序
- **题目管理**：管理题目、选项、答案和解析（支持编辑功能）
- **批量导入**：支持CSV和JSON格式批量导入

### 用户前端
- **首页**：选择题库和章节，显示学习进度，快速创建组卷
- **刷题界面**：
  - 支持顺序/乱序模式（乱序自动排除已答题目）
  - 上一题/下一题导航
  - 提交答案后显示解析
  - 隐藏答案功能，可重新答题
  - 重置章节进度功能
- **组卷练习**：
  - 从题库中随机抽取题目
  - 自定义题目数量
  - 答题过程中可保存答案
  - 提交后查看得分和答案解析
- **搜索功能**：关键词搜索题目
- **题库预览**：浏览所有题目，统一显示/隐藏答案
- **统计图表**：查看个人学习统计和图表

## UI设计特色

- **现代极简主义美学**：清新柔和的渐变配色，轻盈通透的视觉体验
- **精致的微交互**：按钮悬停效果、平滑过渡动画
- **清晰的信息层级**：通过微妙的阴影和模块化卡片布局呈现
- **舒适的视觉比例**：精心打磨的圆角、合适的间距
- **响应式设计**：完美适配PC、平板、手机

## 开发说明

### 数据库设计

- `users`: 用户表
- `question_banks`: 题库表
- `chapters`: 章节表
- `questions`: 题目表
- `options`: 选项表
- `user_answers`: 用户答题记录表
- `user_progress`: 用户进度表
- `user_exams`: 组卷表
- `exam_questions`: 组卷题目关联表

### API接口

- `GET /api/get_chapters.php?bank_id=1`: 获取章节列表
- `GET /api/get_question.php?bank_id=1&chapter_id=1&random=0&current_id=1`: 获取题目（顺序模式支持current_id）
- `GET /api/get_prev_question.php?bank_id=1&chapter_id=1&current_question_id=1`: 获取上一题
- `POST /api/submit_answer.php`: 提交答案
- `POST /api/reset_chapter.php`: 重置章节进度
- `POST /api/save_exam_answer.php`: 保存组卷答案
- `POST /api/submit_exam.php`: 提交组卷

## 注意事项

1. 生产环境请修改默认管理员密码
2. 建议开启HTTPS保护数据传输安全
3. 定期备份数据库
4. 根据实际需求调整PHP配置（上传文件大小限制等）

## ✍️ 作者
**姜琛**
- 院校：黑龙江科技大学
- 专业：信息管理与信息系统
- 项目初衷：完成移动商务期末作业 + 打造便捷的刷题工具

