<?php

class QuestionRepository {
    private string $filePath;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    /**
     * Load all questions from the JSON store.
     * @return array
     */
    public function getAll(): array {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $data = file_get_contents($this->filePath);
        $questions = json_decode($data, true);
        return is_array($questions) ? $questions : [];
    }

    /**
     * Find a question by its ID.
     * @param int|string $id
     * @return array|null
     */
    public function getById($id): ?array {
        $questions = $this->getAll();
        foreach ($questions as $question) {
            if ($question['id'] == $id) {
                return $question;
            }
        }
        return null;
    }

    /**
     * Get a slice of questions.
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getSlice(int $offset, int $limit): array {
        $questions = $this->getAll();
        return array_slice($questions, $offset, $limit);
    }

    /**
     * Count total questions.
     * @return int
     */
    public function count(): int {
        return count($this->getAll());
    }
}
