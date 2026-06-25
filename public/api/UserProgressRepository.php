<?php

class UserProgressRepository {
    private string $filePath;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    /**
     * Get the current question index for a user.
     * Defaults to 0 if no progress exists.
     * @param string $userId
     * @return int
     */
    public function getProgress(string $userId): int {
        if (!file_exists($this->filePath)) {
            return 0;
        }
        $data = file_get_contents($this->filePath);
        $progressMap = json_decode($data, true);
        if (!is_array($progressMap) || !isset($progressMap[$userId])) {
            return 0;
        }
        return (int)$progressMap[$userId];
    }

    /**
     * Save/update progress for a user.
     * @param string $userId
     * @param int $questionIndex
     * @return bool
     */
    public function saveProgress(string $userId, int $questionIndex): bool {
        $progressMap = [];
        if (file_exists($this->filePath)) {
            $data = file_get_contents($this->filePath);
            $progressMap = json_decode($data, true);
            if (!is_array($progressMap)) {
                $progressMap = [];
            }
        }
        $progressMap[$userId] = $questionIndex;
        return file_put_contents($this->filePath, json_encode($progressMap, JSON_PRETTY_PRINT)) !== false;
    }
}
