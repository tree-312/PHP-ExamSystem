-- 大学生刷题系统数据库结构
-- 创建数据库
CREATE DATABASE IF NOT EXISTS exam_system DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE exam_system;

-- 1. 用户表
-- 存储系统用户信息，包括管理员和普通用户
CREATE TABLE IF NOT EXISTS users (
                                     id INT PRIMARY KEY AUTO_INCREMENT COMMENT '用户ID，主键，自增',
                                     username VARCHAR(50) UNIQUE NOT NULL COMMENT '用户名，唯一，非空',
    password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希值，非空',
    role ENUM('admin', 'user') DEFAULT 'user' COMMENT '用户角色：admin(管理员)/user(普通用户)，默认普通用户',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间，默认当前时间戳'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表 - 存储系统用户信息';

-- 2. 题库表
-- 存储题库信息，每个题库包含多个章节
CREATE TABLE IF NOT EXISTS question_banks (
                                              id INT PRIMARY KEY AUTO_INCREMENT COMMENT '题库ID，主键，自增',
                                              name VARCHAR(100) NOT NULL COMMENT '题库名称，非空',
    description TEXT COMMENT '题库描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间，默认当前时间戳'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='题库表 - 存储题库信息';

-- 3. 章节表
-- 存储章节信息，每个章节属于一个题库，包含多个题目
CREATE TABLE IF NOT EXISTS chapters (
                                        id INT PRIMARY KEY AUTO_INCREMENT COMMENT '章节ID，主键，自增',
                                        bank_id INT NOT NULL COMMENT '所属题库ID',
                                        chapter_name VARCHAR(100) NOT NULL COMMENT '章节名称，非空',
    sort_order INT DEFAULT 0 COMMENT '排序顺序，默认0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间，默认当前时间戳',
    FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE,
    INDEX idx_bank_id (bank_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='章节表 - 存储章节信息';



-- 4. 题目表
-- 存储题目信息，每个题目属于一个章节和一个题库
CREATE TABLE IF NOT EXISTS questions (
                                         id INT PRIMARY KEY AUTO_INCREMENT COMMENT '题目ID，主键，自增',
                                         bank_id INT NOT NULL COMMENT '所属题库ID',
                                         chapter_id INT NOT NULL COMMENT '所属章节ID',
                                         title TEXT NOT NULL COMMENT '题目内容，非空',
                                         type ENUM('single', 'multiple', 'fill', 'essay') NOT NULL COMMENT '题目类型：single(单选题)/multiple(多选题)/fill(填空题)/essay(简答题)',
    answer JSON NOT NULL COMMENT '正确答案，JSON格式存储',
    analysis TEXT COMMENT '题目解析',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间，默认当前时间戳',
    FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
    INDEX idx_bank_id (bank_id),
    INDEX idx_chapter_id (chapter_id),
    INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='题目表 - 存储题目信息';

-- 5. 选项表
-- 存储选择题的选项信息，每个选项属于一个题目
CREATE TABLE IF NOT EXISTS options (
                                       id INT PRIMARY KEY AUTO_INCREMENT COMMENT '选项ID，主键，自增',
                                       question_id INT NOT NULL COMMENT '所属题目ID',
                                       option_label VARCHAR(10) NOT NULL COMMENT '选项标签，如A、B、C、D',
    option_text TEXT NOT NULL COMMENT '选项内容，非空',
    sort_order INT DEFAULT 0 COMMENT '排序顺序，默认0',
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='选项表 - 存储选择题的选项信息';

-- 6. 用户答题记录表
-- 存储用户的答题记录，记录用户对每个题目的回答情况
CREATE TABLE IF NOT EXISTS user_answers (
                                            id INT PRIMARY KEY AUTO_INCREMENT COMMENT '记录ID，主键，自增',
                                            user_id INT NOT NULL COMMENT '用户ID',
                                            question_id INT NOT NULL COMMENT '题目ID',
                                            is_correct TINYINT(1) DEFAULT 0 COMMENT '是否答对：0(错误)/1(正确)，默认0',
    user_answer JSON COMMENT '用户答案，JSON格式存储',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '答题时间，默认当前时间戳',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_question_id (question_id),
    INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户答题记录表 - 存储用户的答题记录';

-- 7. 用户进度表
-- 存储用户在各个章节的学习进度
CREATE TABLE IF NOT EXISTS user_progress (
                                             id INT PRIMARY KEY AUTO_INCREMENT COMMENT '进度ID，主键，自增',
                                             user_id INT NOT NULL COMMENT '用户ID',
                                             chapter_id INT NOT NULL COMMENT '章节ID',
                                             correct_count INT DEFAULT 0 COMMENT '答对题目数量，默认0',
                                             total_count INT DEFAULT 0 COMMENT '总答题数量，默认0',
                                             updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间，默认当前时间戳，更新时自动刷新',
                                             FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_chapter (user_id, chapter_id) COMMENT '联合唯一约束，确保每个用户每个章节只有一条进度记录',
    INDEX idx_user_id (user_id),
    INDEX idx_chapter_id (chapter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户进度表 - 存储用户在各个章节的学习进度';

-- 8. 组卷表
-- 存储用户创建的试卷信息
CREATE TABLE IF NOT EXISTS user_exams (
                                          id INT PRIMARY KEY AUTO_INCREMENT COMMENT '试卷ID，主键，自增',
                                          user_id INT NOT NULL COMMENT '用户ID',
                                          bank_id INT NOT NULL COMMENT '所属题库ID',
                                          title VARCHAR(200) NOT NULL COMMENT '试卷标题，非空',
    question_count INT NOT NULL COMMENT '试卷题目数量，非空',
    status ENUM('in_progress', 'completed') DEFAULT 'in_progress' COMMENT '试卷状态：in_progress(进行中)/completed(已完成)，默认进行中',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '开始时间，默认当前时间戳',
    submitted_at TIMESTAMP NULL COMMENT '提交时间，默认为空',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_id) REFERENCES question_banks(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_bank_id (bank_id),
    INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='组卷表 - 存储用户创建的试卷信息';

-- 9. 组卷题目关联表
-- 存储试卷与题目的关联信息，记录试卷包含的题目及用户的回答
CREATE TABLE IF NOT EXISTS exam_questions (
                                              id INT PRIMARY KEY AUTO_INCREMENT COMMENT '关联ID，主键，自增',
                                              exam_id INT NOT NULL COMMENT '试卷ID',
                                              question_id INT NOT NULL COMMENT '题目ID',
                                              question_order INT NOT NULL COMMENT '题目在试卷中的顺序，非空',
                                              user_answer JSON COMMENT '用户答案，JSON格式存储',
                                              is_correct TINYINT(1) DEFAULT 0 COMMENT '是否答对：0(错误)/1(正确)，默认0',
    FOREIGN KEY (exam_id) REFERENCES user_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_exam_id (exam_id),
    INDEX idx_question_id (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='组卷题目关联表 - 存储试卷与题目的关联信息';


-- 插入默认管理员账户（用户名：admin，密码：admin123）
-- 注意：密码hash需要通过PHP的password_hash函数生成，或运行 init_admin.php 脚本
-- 临时密码hash（请运行 init_admin.php 重置为正确的hash）
INSERT INTO users (username, password_hash, role) VALUES
    ('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin');

-- 插入示例题库（正确）
INSERT INTO question_banks (name, description) VALUES
                                                   ('数据挖掘', '数据挖掘算法与应用'),
                                                   ('Java基础', 'Java编程语言基础');

-- ✅ 修正后的章节插入（关键！）
INSERT INTO chapters (bank_id, chapter_name, sort_order) VALUES
                                                             (1, '支持向量机', 1),   -- 数据挖掘
                                                             (1, '线性回归', 2),     -- 数据挖掘
                                                             (2, '面向对象', 1),     -- Java基础
                                                             (2, '异常处理', 2);     -- Java基础