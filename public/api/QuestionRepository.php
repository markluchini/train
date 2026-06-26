<?php

class QuestionRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Load all questions from the MySQL database.
     * @return array
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT id, prompt, difficulty, media_path AS mediaPath FROM questions ORDER BY id ASC");
        $questions = $stmt->fetchAll();
        
        if (empty($questions)) {
            return [];
        }

        // Fetch options for all questions
        $stmtOpts = $this->db->query("SELECT question_id, option_text AS text, score, explanation FROM question_options ORDER BY question_id ASC, id ASC");
        $options = $stmtOpts->fetchAll();
        
        // Group options by question_id
        $optionsByQuestion = [];
        foreach ($options as $opt) {
            $qId = (int)$opt['question_id'];
            unset($opt['question_id']);
            $opt['score'] = (int)$opt['score'];
            $optionsByQuestion[$qId][] = $opt;
        }
        
        foreach ($questions as &$q) {
            $q['id'] = (int)$q['id'];
            $q['options'] = $optionsByQuestion[$q['id']] ?? [];
        }
        return $questions;
    }

    /**
     * Find a question by its ID.
     * @param int|string $id
     * @return array|null
     */
    public function getById($id): ?array {
        $stmt = $this->db->prepare("SELECT id, prompt, difficulty, media_path AS mediaPath FROM questions WHERE id = ?");
        $stmt->execute([(int)$id]);
        $question = $stmt->fetch();
        if (!$question) {
            return null;
        }
        $question['id'] = (int)$question['id'];

        $stmtOpts = $this->db->prepare("SELECT option_text AS text, score, explanation FROM question_options WHERE question_id = ? ORDER BY id ASC");
        $stmtOpts->execute([$question['id']]);
        $options = $stmtOpts->fetchAll();
        
        foreach ($options as &$opt) {
            $opt['score'] = (int)$opt['score'];
        }
        $question['options'] = $options;
        return $question;
    }

    /**
     * Get a slice of questions.
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getSlice(int $offset, int $limit): array {
        $stmt = $this->db->prepare("SELECT id, prompt, difficulty, media_path AS mediaPath FROM questions ORDER BY id ASC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $questions = $stmt->fetchAll();
        
        if (empty($questions)) {
            return [];
        }
        
        $qIds = array_column($questions, 'id');
        $inQuery = implode(',', array_fill(0, count($qIds), '?'));
        
        $stmtOpts = $this->db->prepare("SELECT question_id, option_text AS text, score, explanation FROM question_options WHERE question_id IN ($inQuery) ORDER BY id ASC");
        $stmtOpts->execute($qIds);
        $options = $stmtOpts->fetchAll();
        
        $optionsByQuestion = [];
        foreach ($options as $opt) {
            $qId = (int)$opt['question_id'];
            unset($opt['question_id']);
            $opt['score'] = (int)$opt['score'];
            $optionsByQuestion[$qId][] = $opt;
        }
        
        foreach ($questions as &$q) {
            $q['id'] = (int)$q['id'];
            $q['options'] = $optionsByQuestion[$q['id']] ?? [];
        }
        return $questions;
    }

    /**
     * Count total questions.
     * @return int
     */
    public function count(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    }

    /**
     * Create a new question with its options.
     * @param array $q
     * @return int The new question's ID
     */
    public function create(array $q): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO questions (prompt, difficulty, media_path) VALUES (?, ?, ?)");
            $stmt->execute([
                $q['prompt'],
                $q['difficulty'],
                !empty($q['mediaPath']) ? $q['mediaPath'] : null
            ]);
            $questionId = (int)$this->db->lastInsertId();

            $stmtOpt = $this->db->prepare("INSERT INTO question_options (question_id, option_text, score, explanation) VALUES (?, ?, ?, ?)");
            foreach ($q['options'] as $opt) {
                $stmtOpt->execute([
                    $questionId,
                    $opt['text'],
                    (int)$opt['score'],
                    !empty($opt['explanation']) ? $opt['explanation'] : null
                ]);
            }
            $this->db->commit();
            return $questionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing question and its options.
     * @param int $id
     * @param array $q
     * @return bool
     */
    public function update(int $id, array $q): bool {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE questions SET prompt = ?, difficulty = ?, media_path = ? WHERE id = ?");
            $stmt->execute([
                $q['prompt'],
                $q['difficulty'],
                !empty($q['mediaPath']) ? $q['mediaPath'] : null,
                $id
            ]);

            // Delete existing options
            $stmtDel = $this->db->prepare("DELETE FROM question_options WHERE question_id = ?");
            $stmtDel->execute([$id]);

            // Insert new options
            $stmtOpt = $this->db->prepare("INSERT INTO question_options (question_id, option_text, score, explanation) VALUES (?, ?, ?, ?)");
            foreach ($q['options'] as $opt) {
                $stmtOpt->execute([
                    $id,
                    $opt['text'],
                    (int)$opt['score'],
                    !empty($opt['explanation']) ? $opt['explanation'] : null
                ]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a question.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Bulk overwrite the database with a list of questions.
     * @param array $questions
     * @return bool
     */
    public function importAll(array $questions): bool {
        $this->db->beginTransaction();
        try {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $this->db->exec("TRUNCATE TABLE question_options;");
            $this->db->exec("TRUNCATE TABLE questions;");
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $stmtQ = $this->db->prepare("INSERT INTO questions (id, prompt, difficulty, media_path) VALUES (?, ?, ?, ?)");
            $stmtOpt = $this->db->prepare("INSERT INTO question_options (question_id, option_text, score, explanation) VALUES (?, ?, ?, ?)");

            foreach ($questions as $q) {
                $qId = (int)$q['id'];
                $stmtQ->execute([
                    $qId,
                    $q['prompt'],
                    $q['difficulty'],
                    !empty($q['mediaPath']) ? $q['mediaPath'] : null
                ]);

                foreach ($q['options'] as $opt) {
                    $stmtOpt->execute([
                        $qId,
                        $opt['text'],
                        (int)$opt['score'],
                        !empty($opt['explanation']) ? $opt['explanation'] : null
                    ]);
                }
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
