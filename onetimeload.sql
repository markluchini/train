-- SQL initialization script for Trivia Platform Database

-- Drop existing tables to ensure clean setup
DROP TABLE IF EXISTS telemetry_logs;
DROP TABLE IF EXISTS user_progress;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;

-- 1. Create questions table
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prompt TEXT NOT NULL,
    difficulty VARCHAR(50) NOT NULL,
    media_path VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create question_options table
CREATE TABLE question_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    explanation TEXT DEFAULT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create user_progress table
CREATE TABLE user_progress (
    user_id VARCHAR(255) PRIMARY KEY,
    progress_index INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create telemetry_logs table
CREATE TABLE telemetry_logs (
    telemetry_id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    question_id INT NOT NULL,
    selected_option_index INT NOT NULL,
    correct TINYINT(1) NOT NULL,
    score INT NOT NULL,
    reading_time DECIMAL(8, 2) NOT NULL,
    decide_time DECIMAL(8, 2) NOT NULL,
    selection_history TEXT DEFAULT NULL, -- JSON encoded array of selected indices
    device_user_agent TEXT DEFAULT NULL,
    device_screen_size VARCHAR(50) DEFAULT NULL,
    receipt_time DATETIME DEFAULT NULL,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed questions and options data
-- Question 1
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(1, 'Which CSS property combination is essential to construct a fully non-scrolling mobile layout container when viewport dimensions are fixed?', 'Beginner', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(1, 'h-screen w-screen overflow-hidden', 100, 'Using 100vh/100vw (or h-screen/w-screen) with overflow-hidden ensures the container is sized exactly to the viewport and prevents touch drag scrolling.'),
(1, 'position: absolute; width: 100%; height: 100%;', 0, 'Absolute positioning removes elements from document flow but doesn''t prevent outer viewport scrolling if content overflows.'),
(1, 'display: flex; flex-direction: column; overflow: scroll;', 0, 'Using overflow: scroll explicitly enables scrollbars, which runs counter to building a non-scrolling layout.');

-- Question 2
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(2, 'What is the primary architectural purpose of using the Repository Pattern in a service-oriented application?', 'Intermediate', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(2, 'To decouple domain/business logic from data access and storage implementation details.', 100, 'Repository pattern abstracts data access behind an interface, allowing storage layers (like JSON files, MySQL, or NoSQL) to change without affecting domain logic.'),
(2, 'To automatically translate JavaScript runtime object schemas into relational SQL tables.', 0, 'Translating objects to relational structures is the responsibility of an Object-Relational Mapper (ORM), not the Repository pattern itself.'),
(2, 'To implement low-latency caching and query pooling directly in the database connection layer.', 0, 'While repositories can integrate caching, their primary purpose is abstraction and decoupling rather than network or connection optimization.');

-- Question 3
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(3, 'In React 19, what happens when you trigger a state update with the exact same value as the current state during a render cycle?', 'Complex', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(3, 'React uses Object.is comparison and bails out without re-rendering components or firing effects.', 100, 'React compares state values using the Object.is algorithm. If values are identical, it skips re-rendering child components and triggering associated hooks to optimize performance.'),
(3, 'React performs a deep-equal comparison of properties and re-renders only modified fields.', 0, 'React state updates utilize shallow comparison (Object.is) and do not inspect nested object properties unless customized.'),
(3, 'React throws a warning in StrictMode because state mutations must always produce a new reference.', 0, 'Updating state with the same value is not considered an error or mutation; it is a valid optimization path (bailout) supported by React.');

-- Question 4
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(4, 'How does Tailwind CSS v4 change the build integration compared to Tailwind CSS v3?', 'Intermediate', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(4, 'It uses a native Vite compiler plugin (@tailwindcss/vite) that eliminates the need for separate postcss.config.js and tailwind.config.js configurations.', 100, 'Tailwind v4 is built on a rust-powered engine with native Vite support, meaning config is set up directly via CSS directives (`@import \'tailwindcss\'`) and the Vite plugin.'),
(4, 'It requires Node JS v22+ and can only be bundled using the Rolldown compiler in production mode.', 0, 'While it requires newer Node engines, it does not mandate Rolldown; it runs under standard Vite, Webpack, or as a standalone CLI.'),
(4, 'It deprecates client-side build tools and shifts compile engines entirely into Edge workers.', 0, 'Tailwind compiles locally at build time just like previous versions to keep bundles lightweight.');

-- Question 5
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(5, 'Which PHP mechanism should be used to protect database operations from SQL Injection when executing queries with user-supplied parameters?', 'Beginner', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(5, 'PDO Prepared Statements with bound parameters (bindParam / execute).', 100, 'Prepared statements send sql structure and parameters separately to the database engine, rendering injection attacks ineffective.'),
(5, 'Applying htmlspecialchars() to escape quotes and operators before database ingestion.', 0, 'htmlspecialchars escaping is meant for XSS mitigation in HTML output, not database injection protection, and can lead to corrupt query syntax.'),
(5, 'Using base64_encode to transform all string fields before running queries.', 0, 'Encoding input obscurs data but doesn''t solve database parser injection flaws, and ruins indexing/query filters unless base64 decoded inside the DB.');

-- Question 6
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(6, 'In a RESTful architecture, which HTTP method is appropriate for applying partial modifications to a resource?', 'Beginner', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(6, 'PATCH', 100, 'PATCH is explicitly designed for partial updates of an existing resource, whereas PUT is for replacing the entire resource.'),
(6, 'PUT', 0, 'PUT is used for full resource replacements (or creations). While sometimes used for partial updates, it is technically non-standard for partial modifications.'),
(6, 'POST', 0, 'POST is typically used for creating new resources rather than modifying existing ones.');

-- Question 7
INSERT INTO questions (id, prompt, difficulty, media_path) VALUES 
(7, 'What does the TypeScript ''unknown'' type represent, and how does it differ from the ''any'' type?', 'Complex', '');

INSERT INTO question_options (question_id, option_text, score, explanation) VALUES
(7, 'It represents any value, but unlike ''any'', it is type-safe; you cannot perform operations on it without first narrowing the type via type guards or assertions.', 100, '''unknown'' is the type-safe counterpart of ''any''. You can assign anything to it, but you must check its type (e.g. using typeof, instanceof) before accessing properties or executing it.'),
(7, 'It represents values that cannot exist at runtime, such as the return type of functions that throw errors.', 0, 'This describes the ''never'' type, not the ''unknown'' type.'),
(7, 'It is a deprecated type alias that was replaced by structural interfaces in modern TypeScript versions.', 0, '`unknown` was introduced in TypeScript 3.0 and remains a fundamental element of modern type safety.');
