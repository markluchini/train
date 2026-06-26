<?php

class UserProgressRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Get the current question index for a user.
     * Defaults to 0 if no progress exists.
     * @param string $userId
     * @return int
     */
    public function getProgress(string $userId): int {
        $stmt = $this->db->prepare("SELECT progress_index FROM user_progress WHERE user_id = ?");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : 0;
    }

    /**
     * Save/update progress for a user.
     * @param string $userId
     * @param int $questionIndex
     * @return bool
     */
    public function saveProgress(string $userId, int $questionIndex): bool {
        $stmt = $this->db->prepare("INSERT INTO user_progress (user_id, progress_index) 
                                    VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE progress_index = VALUES(progress_index)");
        return $stmt->execute([$userId, $questionIndex]);
    }
}
