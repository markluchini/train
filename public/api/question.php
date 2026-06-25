<?php
// CORS Headers for multi-port local dev and cross-origin access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/QuestionRepository.php';
require_once __DIR__ . '/UserProgressRepository.php';

$userId = isset($_GET['userId']) ? trim($_GET['userId']) : '';

if (empty($userId)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "data" => [
            "message" => "Missing required query parameter: userId"
        ]
    ]);
    exit;
}

try {
    $questionRepo = new QuestionRepository(__DIR__ . '/../../data_questions.json');
    $progressRepo = new UserProgressRepository(__DIR__ . '/../../data_user_progress.json');

    $allQuestions = $questionRepo->getAll();
    $totalQuestions = count($allQuestions);

    if ($totalQuestions === 0) {
        echo json_encode([
            "status" => "success",
            "data" => [
                "history" => [],
                "current" => null,
                "prefetch" => []
            ]
        ]);
        exit;
    }

    // Get user progress index
    $progressIndex = $progressRepo->getProgress($userId);
    
    // Ensure index wraps around if it exceeds total count
    $currentIndex = $progressIndex % $totalQuestions;

    // Slice History: last 3 questions (from max(0, currentIndex - 3) up to currentIndex - 1)
    $history = [];
    $startHistoryIndex = max(0, $currentIndex - 3);
    for ($i = $startHistoryIndex; $i < $currentIndex; $i++) {
        $history[] = $allQuestions[$i];
    }

    // Current Question
    $current = $allQuestions[$currentIndex];

    // Slice Prefetch: next 3 questions (with wrap-around index calculation)
    $prefetch = [];
    for ($i = 1; $i <= 3; $i++) {
        $prefetchIndex = ($currentIndex + $i) % $totalQuestions;
        // Avoid prefetching current or overlapping history if deck size is small
        if ($prefetchIndex === $currentIndex) {
            break;
        }
        $prefetch[] = $allQuestions[$prefetchIndex];
    }

    echo json_encode([
        "status" => "success",
        "data" => [
            "history" => $history,
            "current" => $current,
            "prefetch" => $prefetch
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
