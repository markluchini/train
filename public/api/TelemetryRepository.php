<?php

class TelemetryRepository {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Log a new telemetry entry.
     * @param array $entry
     * @return bool
     */
    public function log(array $entry): bool {
        $sql = "INSERT INTO telemetry_logs (
                    telemetry_id, user_id, question_id, selected_option_index, 
                    correct, score, reading_time, decide_time, selection_history, 
                    device_user_agent, device_screen_size, receipt_time, start_time, 
                    end_time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        $selectionHistoryJson = !empty($entry['selectionHistory']) ? json_encode($entry['selectionHistory']) : '[]';
        $userAgent = $entry['deviceMetadata']['userAgent'] ?? null;
        $screenSize = $entry['deviceMetadata']['screenSize'] ?? null;
        $receiptTime = $entry['timestamps']['receiptTime'] ?? null;
        $startTime = $entry['timestamps']['startTime'] ?? null;
        $endTime = $entry['timestamps']['endTime'] ?? null;

        // Convert ISO-8601 string to MySQL DATETIME format (Y-m-d H:i:s)
        $receiptTimeFormatted = $this->isoToMysqlDateTime($receiptTime);
        $startTimeFormatted = $this->isoToMysqlDateTime($startTime);
        $endTimeFormatted = $this->isoToMysqlDateTime($endTime);

        return $stmt->execute([
            $entry['telemetryId'],
            $entry['userId'],
            (int)$entry['questionId'],
            (int)$entry['selectedOptionIndex'],
            (int)$entry['correct'],
            (int)$entry['score'],
            (float)$entry['readingTime'],
            (float)$entry['decideTime'],
            $selectionHistoryJson,
            $userAgent,
            $screenSize,
            $receiptTimeFormatted,
            $startTimeFormatted,
            $endTimeFormatted
        ]);
    }

    /**
     * Get all telemetry logs.
     * @return array
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT 
                                    telemetry_id AS telemetryId,
                                    user_id AS userId,
                                    question_id AS questionId,
                                    selected_option_index AS selectedOptionIndex,
                                    correct,
                                    score,
                                    reading_time AS readingTime,
                                    decide_time AS decideTime,
                                    selection_history AS selectionHistory,
                                    device_user_agent AS deviceUserAgent,
                                    device_screen_size AS deviceScreenSize,
                                    receipt_time AS receiptTime,
                                    start_time AS startTime,
                                    end_time AS endTime,
                                    submitted_at AS submittedAt
                                  FROM telemetry_logs 
                                  ORDER BY submitted_at DESC");
        $results = $stmt->fetchAll();
        foreach ($results as &$row) {
            $row['correct'] = (bool)$row['correct'];
            $row['score'] = (int)$row['score'];
            $row['readingTime'] = (float)$row['readingTime'];
            $row['decideTime'] = (float)$row['decideTime'];
            $row['questionId'] = (int)$row['questionId'];
            $row['selectedOptionIndex'] = (int)$row['selectedOptionIndex'];
            $row['selectionHistory'] = json_decode($row['selectionHistory'] ?? '[]', true) ?: [];
        }
        return $results;
    }

    /**
     * Helper to convert ISO-8601 dates to MySQL format.
     */
    private function isoToMysqlDateTime(?string $isoString): ?string {
        if (empty($isoString)) return null;
        try {
            $date = new DateTime($isoString);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
}
