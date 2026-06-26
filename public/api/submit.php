<?php
// CORS Headers for multi-port local dev and cross-origin access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/QuestionRepository.php';
require_once __DIR__ . '/UserProgressRepository.php';
require_once __DIR__ . '/TelemetryRepository.php';

// Retrieve POST body
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Invalid JSON payload"
        ]
    ]);
    exit;
}

$userId = isset($data['userId']) ? trim($data['userId']) : '';
$questionId = isset($data['questionId']) ? (int)$data['questionId'] : 0;
$selectedOptionIndex = isset($data['selectedOptionIndex']) ? (int)$data['selectedOptionIndex'] : -1;
$readingTime = isset($data['readingTime']) ? (float)$data['readingTime'] : 0.0;
$decideTime = isset($data['decideTime']) ? (float)$data['decideTime'] : 0.0;
$selectionHistory = isset($data['selectionHistory']) ? (array)$data['selectionHistory'] : [];
$deviceMetadata = isset($data['deviceMetadata']) ? (array)$data['deviceMetadata'] : [];
$timestamps = isset($data['timestamps']) ? (array)$data['timestamps'] : [];

if (empty($userId) || $questionId <= 0 || $selectedOptionIndex < 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Missing required fields: userId, questionId, selectedOptionIndex"
        ]
    ]);
    exit;
}

    try {
        $db = getDbConnection();
        $questionRepo = new QuestionRepository($db);
        $progressRepo = new UserProgressRepository($db);
        $telemetryRepo = new TelemetryRepository($db);
    
        // Find the question to verify correctness
        $question = $questionRepo->getById($questionId);
        if (!$question) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "data" => [
                    "message" => "Question not found"
                ]
            ]);
            exit;
        }
    
        $options = $question['options'];
        if (!isset($options[$selectedOptionIndex])) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "data" => [
                    "message" => "Selected option index out of range"
                ]
            ]);
            exit;
        }
    
        $selectedOption = $options[$selectedOptionIndex];
        $correct = $selectedOption['score'] > 0;
        $scoreAwarded = (int)$selectedOption['score'];
        $explanation = $selectedOption['explanation'];
    
        // 1. Log Telemetry to MySQL
        $newTelemetryEntry = [
            "telemetryId" => "tel_" . bin2hex(random_bytes(8)),
            "userId" => $userId,
            "questionId" => $questionId,
            "selectedOptionIndex" => $selectedOptionIndex,
            "correct" => $correct,
            "score" => $scoreAwarded,
            "readingTime" => $readingTime,
            "decideTime" => $decideTime,
            "selectionHistory" => $selectionHistory,
            "deviceMetadata" => $deviceMetadata,
            "timestamps" => $timestamps
        ];
        $telemetryRepo->log($newTelemetryEntry);
    
        // 2. Increment User Progress Index
        $currentProgressIndex = $progressRepo->getProgress($userId);
        $progressRepo->saveProgress($userId, $currentProgressIndex + 1);

    echo json_encode([
        "status" => "success",
        "data" => [
            "correct" => $correct,
            "score" => $scoreAwarded,
            "explanation" => $explanation
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "An internal server error occurred: " . $e->getMessage()
        ]
    ]);
}
