<?php
// Admin Portal index file - self-contained PHP CRUD, Bulk Tools, and Analytics
session_start();

$questionsFile = __DIR__ . '/../../data_questions.json';
$telemetryFile = __DIR__ . '/../../data_telemetry.json';

// Helper to load questions
function getQuestions(string $file): array {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

// Helper to load telemetry
function getTelemetry(string $file): array {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

$questions = getQuestions($questionsFile);
$telemetry = getTelemetry($telemetryFile);

$message = '';
$error = '';

// Handle actions
$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $prompt = trim($_POST['prompt'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'Beginner';
        $mediaPath = trim($_POST['mediaPath'] ?? '');
        
        $options = [];
        for ($i = 0; $i < 3; $i++) {
            $optText = trim($_POST["opt_{$i}_text"] ?? '');
            if ($optText !== '') {
                $options[] = [
                    "text" => $optText,
                    "score" => (int)($_POST["opt_{$i}_score"] ?? 0),
                    "explanation" => trim($_POST["opt_{$i}_explanation"] ?? '')
                ];
            }
        }

        if (empty($prompt) || empty($options)) {
            $error = "Prompt text and at least one answer option are required.";
        } else {
            $maxId = 0;
            foreach ($questions as $q) {
                if ((int)$q['id'] > $maxId) $maxId = (int)$q['id'];
            }
            $newQuestion = [
                "id" => $maxId + 1,
                "prompt" => $prompt,
                "difficulty" => $difficulty,
                "mediaPath" => $mediaPath,
                "options" => $options
            ];
            $questions[] = $newQuestion;
            file_put_contents($questionsFile, json_encode($questions, JSON_PRETTY_PRINT));
            $message = "Question created successfully!";
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $prompt = trim($_POST['prompt'] ?? '');
        $difficulty = $_POST['difficulty'] ?? 'Beginner';
        $mediaPath = trim($_POST['mediaPath'] ?? '');

        $options = [];
        for ($i = 0; $i < 3; $i++) {
            $optText = trim($_POST["opt_{$i}_text"] ?? '');
            if ($optText !== '') {
                $options[] = [
                    "text" => $optText,
                    "score" => (int)($_POST["opt_{$i}_score"] ?? 0),
                    "explanation" => trim($_POST["opt_{$i}_explanation"] ?? '')
                ];
            }
        }

        if ($id <= 0 || empty($prompt) || empty($options)) {
            $error = "Invalid question ID, empty prompt, or empty options.";
        } else {
            foreach ($questions as $k => $q) {
                if ((int)$q['id'] === $id) {
                    $questions[$k] = [
                        "id" => $id,
                        "prompt" => $prompt,
                        "difficulty" => $difficulty,
                        "mediaPath" => $mediaPath,
                        "options" => $options
                    ];
                    break;
                }
            }
            file_put_contents($questionsFile, json_encode($questions, JSON_PRETTY_PRINT));
            $message = "Question updated successfully!";
        }
    }

    if ($action === 'import_json') {
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $newQuestions = json_decode($jsonContent, true);
            if (is_array($newQuestions)) {
                file_put_contents($questionsFile, json_encode($newQuestions, JSON_PRETTY_PRINT));
                $questions = $newQuestions;
                $message = "Database successfully overwritten with JSON questions!";
            } else {
                $error = "Invalid JSON file format.";
            }
        } else {
            $error = "Failed to upload JSON file.";
        }
    }

    if ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Header line
                $headers = fgetcsv($handle, 1000, ",");
                $imported = [];
                $maxId = 0;
                foreach ($questions as $q) {
                    if ((int)$q['id'] > $maxId) $maxId = (int)$q['id'];
                }

                while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($row) < 3 || empty($row[1])) continue;
                    $maxId++;
                    $options = [];
                    // Option 1
                    if (!empty($row[4])) {
                        $options[] = ["text" => $row[4], "score" => (int)($row[5] ?? 0), "explanation" => $row[6] ?? ''];
                    }
                    // Option 2
                    if (!empty($row[7])) {
                        $options[] = ["text" => $row[7], "score" => (int)($row[8] ?? 0), "explanation" => $row[9] ?? ''];
                    }
                    // Option 3
                    if (!empty($row[10])) {
                        $options[] = ["text" => $row[10], "score" => (int)($row[11] ?? 0), "explanation" => $row[12] ?? ''];
                    }

                    $imported[] = [
                        "id" => $maxId,
                        "prompt" => $row[1],
                        "difficulty" => in_array($row[2], ['Beginner', 'Intermediate', 'Complex']) ? $row[2] : 'Beginner',
                        "mediaPath" => $row[3] ?? '',
                        "options" => $options
                    ];
                }
                fclose($handle);

                $questions = array_merge($questions, $imported);
                file_put_contents($questionsFile, json_encode($questions, JSON_PRETTY_PRINT));
                $message = "Imported " . count($imported) . " questions from CSV!";
            } else {
                $error = "Failed to open CSV file.";
            }
        } else {
            $error = "Failed to upload CSV file.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $questions = array_filter($questions, fn($q) => (int)$q['id'] !== $id);
            // Re-index array keys
            $questions = array_values($questions);
            file_put_contents($questionsFile, json_encode($questions, JSON_PRETTY_PRINT));
            $message = "Question deleted successfully!";
        }
    }

    if ($action === 'export_json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=questions.json');
        echo json_encode($questions, JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=questions.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['id', 'prompt', 'difficulty', 'mediaPath', 
            'opt1_text', 'opt1_score', 'opt1_explanation', 
            'opt2_text', 'opt2_score', 'opt2_explanation', 
            'opt3_text', 'opt3_score', 'opt3_explanation'
        ]);
        foreach ($questions as $q) {
            $row = [
                $q['id'],
                $q['prompt'],
                $q['difficulty'],
                $q['mediaPath'] ?? '',
                $q['options'][0]['text'] ?? '',
                $q['options'][0]['score'] ?? 0,
                $q['options'][0]['explanation'] ?? '',
                $q['options'][1]['text'] ?? '',
                $q['options'][1]['score'] ?? 0,
                $q['options'][1]['explanation'] ?? '',
                $q['options'][2]['text'] ?? '',
                $q['options'][2]['score'] ?? 0,
                $q['options'][2]['explanation'] ?? ''
            ];
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}

// Compute Analytics
$totalAttempts = count($telemetry);
$correctAttempts = 0;
$totalReadingTime = 0;
$totalDecideTime = 0;

$questionStats = [];
$userStats = [];

foreach ($telemetry as $t) {
    if ($t['correct']) $correctAttempts++;
    $totalReadingTime += $t['readingTime'];
    $totalDecideTime += $t['decideTime'];

    // Question breakdown
    $qId = $t['questionId'];
    if (!isset($questionStats[$qId])) {
        $questionStats[$qId] = [
            'attempts' => 0,
            'correct' => 0,
            'readingTime' => 0,
            'decideTime' => 0
        ];
    }
    $questionStats[$qId]['attempts']++;
    if ($t['correct']) $questionStats[$qId]['correct']++;
    $questionStats[$qId]['readingTime'] += $t['readingTime'];
    $questionStats[$qId]['decideTime'] += $t['decideTime'];

    // User breakdown
    $uId = $t['userId'];
    if (!isset($userStats[$uId])) {
        $userStats[$uId] = [
            'attempts' => 0,
            'correct' => 0
        ];
    }
    $userStats[$uId]['attempts']++;
    if ($t['correct']) $userStats[$uId]['correct']++;
}

$overallAccuracy = $totalAttempts > 0 ? round(($correctAttempts / $totalAttempts) * 100, 1) : 0;
$avgReadingTime = $totalAttempts > 0 ? round($totalReadingTime / $totalAttempts, 2) : 0;
$avgDecideTime = $totalAttempts > 0 ? round($totalDecideTime / $totalAttempts, 2) : 0;
$avgTotalTime = $avgReadingTime + $avgDecideTime;

// Edit helper
$editQuestion = null;
if (isset($_GET['edit_id'])) {
    $eId = (int)$_GET['edit_id'];
    foreach ($questions as $q) {
        if ((int)$q['id'] === $eId) {
            $editQuestion = $q;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trivia Admin Portal</title>
    <!-- Tailwind CSS v3 via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased flex flex-col">
    <!-- Navbar -->
    <nav class="h-16 border-b border-white/5 bg-slate-900/60 backdrop-blur-md px-6 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <span class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center font-bold text-white shadow-lg">
            T
          </span>
          <div>
            <h1 class="text-sm font-bold tracking-tight text-white">TriviaTrain Admin</h1>
            <span class="text-[9px] text-slate-500 block leading-none font-medium">Control Portal</span>
          </div>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="../" class="bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white text-xs font-semibold px-4 py-2 rounded-xl transition-all border border-white/5">
                Go to Player App
            </a>
        </div>
    </nav>

    <!-- Main Content Grid -->
    <main class="flex-1 overflow-y-auto p-6 max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: CRUD lists & Analytics -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 text-xs font-semibold p-4 rounded-xl">
                    ✓ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="bg-rose-500/10 border border-rose-500/25 text-rose-400 text-xs font-semibold p-4 rounded-xl">
                    ✗ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- STATS WIDGETS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-5 shadow-lg">
                    <span class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Total Attempts</span>
                    <h3 class="text-3xl font-extrabold text-white mt-1"><?php echo $totalAttempts; ?></h3>
                </div>
                <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-5 shadow-lg">
                    <span class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Avg Accuracy</span>
                    <h3 class="text-3xl font-extrabold text-emerald-400 mt-1"><?php echo $overallAccuracy; ?>%</h3>
                </div>
                <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-5 shadow-lg">
                    <span class="text-[10px] font-bold tracking-wider text-slate-500 uppercase">Avg Response Time</span>
                    <h3 class="text-3xl font-extrabold text-indigo-400 mt-1"><?php echo $avgTotalTime; ?>s</h3>
                </div>
            </div>

            <!-- QUESTIONS LIST -->
            <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 shadow-lg">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-base font-bold text-white">Question Inventory (<?php echo count($questions); ?>)</h2>
                    <div class="flex gap-2">
                        <a href="?action=export_json" class="text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-3 py-1.5 rounded-lg transition-all border border-white/5">
                            Export JSON
                        </a>
                        <a href="?action=export_csv" class="text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-3 py-1.5 rounded-lg transition-all border border-white/5">
                            Export CSV
                        </a>
                    </div>
                </div>

                <div class="space-y-3 max-h-[480px] overflow-y-auto pr-1">
                    <?php if (empty($questions)): ?>
                        <p class="text-xs text-slate-500 text-center py-8">No questions loaded. Use the form to build some!</p>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                            <div class="p-4 rounded-xl bg-slate-950/40 border border-white/5 flex justify-between items-start gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[8px] font-bold text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded">ID: <?php echo $q['id']; ?></span>
                                        <span class="text-[8px] font-bold tracking-widest text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded uppercase border border-indigo-500/15">
                                            <?php echo htmlspecialchars($q['difficulty']); ?>
                                        </span>
                                    </div>
                                    <h4 class="text-xs font-semibold text-white leading-normal pt-1"><?php echo htmlspecialchars($q['prompt']); ?></h4>
                                    
                                    <div class="flex gap-3 text-[10px] text-slate-500 pt-1">
                                        <span><?php echo count($q['options']); ?> options</span>
                                        <?php if (!empty($q['mediaPath'])): ?>
                                            <span class="text-indigo-400">🖼 Has Media</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <a href="?edit_id=<?php echo $q['id']; ?>" class="text-[10px] bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500 hover:text-white px-2.5 py-1.5 rounded-lg font-bold border border-indigo-500/20 transition-all">
                                        Edit
                                    </a>
                                    <a href="?action=delete&id=<?php echo $q['id']; ?>" onclick="return confirm('Are you sure you want to delete this question?')" class="text-[10px] bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white px-2.5 py-1.5 rounded-lg font-bold border border-rose-500/20 transition-all">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TELEMETRY REPORT -->
            <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 shadow-lg">
                <h2 class="text-base font-bold text-white mb-4">Question Analytics Table</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left text-slate-400">
                        <thead class="text-[9px] uppercase font-bold text-slate-500 border-b border-white/5">
                            <tr>
                                <th class="pb-3">QID</th>
                                <th class="pb-3">Prompt Snapshot</th>
                                <th class="pb-3">Attempts</th>
                                <th class="pb-3">Accuracy</th>
                                <th class="pb-3">Avg Read</th>
                                <th class="pb-3">Avg Decide</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (empty($questionStats)): ?>
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-slate-500">No telemetry log entries found yet. Submit some answers in the Player App!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($questionStats as $qid => $stats): 
                                    $promptSnap = 'Deleted Question';
                                    foreach ($questions as $q) {
                                        if ((int)$q['id'] === (int)$qid) {
                                            $promptSnap = strlen($q['prompt']) > 45 ? substr($q['prompt'], 0, 45) . '...' : $q['prompt'];
                                            break;
                                        }
                                    }
                                    $acc = round(($stats['correct'] / $stats['attempts']) * 100, 1);
                                    $read = round($stats['readingTime'] / $stats['attempts'], 2);
                                    $dec = round($stats['decideTime'] / $stats['attempts'], 2);
                                ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="py-3 font-mono"><?php echo $qid; ?></td>
                                        <td class="py-3 text-slate-200"><?php echo htmlspecialchars($promptSnap); ?></td>
                                        <td class="py-3 font-mono"><?php echo $stats['attempts']; ?></td>
                                        <td class="py-3 font-mono font-bold text-emerald-400"><?php echo $acc; ?>%</td>
                                        <td class="py-3 font-mono"><?php echo $read; ?>s</td>
                                        <td class="py-3 font-mono"><?php echo $dec; ?>s</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Col: CRUD Form & Bulk Uploads -->
        <div class="space-y-6">
            
            <!-- CREATE / EDIT FORM -->
            <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 shadow-lg">
                <h2 class="text-base font-bold text-white mb-5">
                    <?php echo $editQuestion ? 'Edit Question #' . $editQuestion['id'] : 'Create New Question'; ?>
                </h2>

                <form action="?action=<?php echo $editQuestion ? 'update' : 'create'; ?>" method="POST" class="space-y-4 text-xs">
                    <?php if ($editQuestion): ?>
                        <input type="hidden" name="id" value="<?php echo $editQuestion['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Prompt Text (Markdown support)</label>
                        <textarea name="prompt" rows="3" required class="w-full bg-slate-950/60 border border-white/5 rounded-xl px-3 py-2.5 text-slate-100 focus:outline-none focus:border-indigo-500/50" placeholder="Type the question prompt..."><?php echo $editQuestion ? htmlspecialchars($editQuestion['prompt']) : ''; ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-400 font-semibold mb-1">Difficulty</label>
                            <select name="difficulty" class="w-full bg-slate-950/60 border border-white/5 rounded-xl h-10 px-3 text-slate-200 focus:outline-none focus:border-indigo-500/50">
                                <?php foreach (['Beginner', 'Intermediate', 'Complex'] as $diff): ?>
                                    <option value="<?php echo $diff; ?>" <?php echo ($editQuestion && $editQuestion['difficulty'] === $diff) ? 'selected' : ''; ?>>
                                        <?php echo $diff; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-slate-400 font-semibold mb-1">Image / Graphic Path (Opt)</label>
                            <input type="text" name="mediaPath" value="<?php echo $editQuestion ? htmlspecialchars($editQuestion['mediaPath'] ?? '') : ''; ?>" class="w-full bg-slate-950/60 border border-white/5 rounded-xl h-10 px-3 text-slate-100 focus:outline-none focus:border-indigo-500/50" placeholder="images/diagram.png">
                        </div>
                    </div>

                    <hr class="border-white/5 my-4">

                    <!-- Options -->
                    <span class="block text-[10px] uppercase font-bold tracking-wider text-slate-500 mb-2">Options (Minimum 1)</span>
                    
                    <?php for ($i = 0; $i < 3; $i++): 
                        $optText = $editQuestion['options'][$i]['text'] ?? '';
                        $optScore = $editQuestion['options'][$i]['score'] ?? ($i === 0 ? 100 : 0);
                        $optExpl = $editQuestion['options'][$i]['explanation'] ?? '';
                    ?>
                        <div class="p-3 bg-slate-950/30 border border-white/5 rounded-xl space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-500"><?php echo chr(65 + $i); ?>.</span>
                                <input type="text" name="opt_<?php echo $i; ?>_text" value="<?php echo htmlspecialchars($optText); ?>" class="flex-1 bg-slate-950/60 border border-white/5 rounded-lg h-8 px-2.5 text-slate-100 focus:outline-none focus:border-indigo-500/50" placeholder="Option text..." <?php echo $i === 0 ? 'required' : ''; ?>>
                                <select name="opt_<?php echo $i; ?>_score" class="bg-slate-950/60 border border-white/5 rounded-lg h-8 px-2 text-[10px] font-bold text-slate-300">
                                    <option value="100" <?php echo $optScore === 100 ? 'selected' : ''; ?>>Correct (100)</option>
                                    <option value="0" <?php echo $optScore === 0 ? 'selected' : ''; ?>>Incorrect (0)</option>
                                </select>
                            </div>
                            <input type="text" name="opt_<?php echo $i; ?>_explanation" value="<?php echo htmlspecialchars($optExpl); ?>" class="w-full bg-slate-950/60 border border-white/5 rounded-lg h-8 px-2.5 text-slate-400 focus:outline-none focus:border-indigo-500/50 text-[10px]" placeholder="Specific explanation/feedback...">
                        </div>
                    <?php endfor; ?>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold h-10 rounded-xl transition-all cursor-pointer">
                            <?php echo $editQuestion ? 'Save Changes' : 'Create Question'; ?>
                        </button>
                        <?php if ($editQuestion): ?>
                            <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold px-4 py-2.5 rounded-xl border border-white/5 transition-all">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- BULK DATA UPLOAD -->
            <div class="bg-slate-900/40 border border-white/5 rounded-2xl p-6 shadow-lg space-y-5 text-xs">
                <h2 class="text-base font-bold text-white">Bulk Import Database</h2>
                
                <!-- JSON import -->
                <form action="?action=import_json" method="POST" enctype="multipart/form-data" class="space-y-2">
                    <label class="block text-slate-400 font-semibold">Upload JSON File (Overwrites database)</label>
                    <div class="flex gap-2">
                        <input type="file" name="json_file" accept=".json" required class="flex-1 file:bg-white/5 file:border-0 file:rounded-lg file:px-3 file:py-1.5 file:text-xs file:text-slate-300 file:hover:bg-white/10 file:cursor-pointer text-slate-500">
                        <button type="submit" class="bg-indigo-600/20 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500 hover:text-white px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer">
                            Import
                        </button>
                    </div>
                </form>

                <hr class="border-white/5">

                <!-- CSV import -->
                <form action="?action=import_csv" method="POST" enctype="multipart/form-data" class="space-y-2">
                    <label class="block text-slate-400 font-semibold">Upload CSV File (Appends to database)</label>
                    <div class="flex gap-2">
                        <input type="file" name="csv_file" accept=".csv" required class="flex-1 file:bg-white/5 file:border-0 file:rounded-lg file:px-3 file:py-1.5 file:text-xs file:text-slate-300 file:hover:bg-white/10 file:cursor-pointer text-slate-500">
                        <button type="submit" class="bg-indigo-600/20 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500 hover:text-white px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer">
                            Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
