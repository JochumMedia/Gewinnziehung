<?php
// Simple raffle application allowing manual participant count or CSV upload

function sanitizeInput(?string $value): string
{
    return trim((string) $value);
}

function parseCsvParticipants(string $filePath): array
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $participants = [];
    $delimiters = [',', ';', '\t', '|'];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $bestSplit = str_getcsv($line, $delimiters[0]);
        $bestCount = count($bestSplit);

        foreach ($delimiters as $delimiter) {
            $currentSplit = str_getcsv($line, $delimiter);
            if (count($currentSplit) > $bestCount) {
                $bestSplit = $currentSplit;
                $bestCount = count($currentSplit);
            }
        }

        $participants[] = $bestSplit;
    }

    return $participants;
}

function chooseUniqueRandomIndices(int $total, int $count): array
{
    $available = range(0, $total - 1);
    $winners = [];

    for ($i = 0; $i < $count; $i++) {
        $randomIndex = random_int(0, count($available) - 1);
        $winners[] = $available[$randomIndex];
        array_splice($available, $randomIndex, 1);
    }

    sort($winners);

    return $winners;
}

$result = [
    'errors' => [],
    'winners' => [],
    'participants' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $winnerCount = (int) ($_POST['winner_count'] ?? 0);
    $participantCountInput = sanitizeInput($_POST['participant_count'] ?? '');

    if ($winnerCount < 1) {
        $result['errors'][] = 'Bitte geben Sie eine gültige Anzahl an Gewinnern an (mindestens 1).';
    }

    $uploadedParticipants = [];
    if (!empty($_FILES['participant_list']['tmp_name'])) {
        if ($_FILES['participant_list']['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'Die Teilnehmerliste konnte nicht hochgeladen werden.';
        } else {
            $uploadedParticipants = parseCsvParticipants($_FILES['participant_list']['tmp_name']);
            if (empty($uploadedParticipants)) {
                $result['errors'][] = 'Die hochgeladene Datei enthält keine gültigen Teilnehmerdaten.';
            }
        }
    }

    if (empty($uploadedParticipants) && $participantCountInput === '') {
        $result['errors'][] = 'Bitte geben Sie entweder eine Teilnehmerzahl an oder laden Sie eine CSV-Datei hoch.';
    }

    if (empty($result['errors'])) {
        if (!empty($uploadedParticipants)) {
            $participantCount = count($uploadedParticipants);
            if ($winnerCount > $participantCount) {
                $result['errors'][] = 'Die Anzahl der Gewinner darf die Anzahl der Teilnehmer in der Datei nicht überschreiten.';
            } else {
                $indices = chooseUniqueRandomIndices($participantCount, $winnerCount);
                foreach ($indices as $index) {
                    $participantData = $uploadedParticipants[$index];
                    $result['winners'][] = [
                        'label' => 'Teilnehmer #' . ($index + 1),
                        'value' => implode(' | ', array_map('htmlspecialchars', $participantData))
                    ];
                }
                $result['participants'] = $uploadedParticipants;
            }
        } else {
            if (!ctype_digit($participantCountInput) || (int) $participantCountInput < 1) {
                $result['errors'][] = 'Die Teilnehmerzahl muss eine positive ganze Zahl sein.';
            } elseif ($winnerCount > (int) $participantCountInput) {
                $result['errors'][] = 'Die Anzahl der Gewinner darf die Teilnehmerzahl nicht überschreiten.';
            } else {
                $participantCount = (int) $participantCountInput;
                $indices = chooseUniqueRandomIndices($participantCount, $winnerCount);
                foreach ($indices as $index) {
                    $result['winners'][] = [
                        'label' => 'Gewinner #' . (count($result['winners']) + 1),
                        'value' => 'Teilnehmernummer ' . ($index + 1)
                    ];
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Gewinnziehung</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }
        input[type="number"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        .hint {
            font-size: 0.9em;
            color: #666;
        }
        button {
            background: #0077cc;
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
        }
        button:hover {
            background: #005fa3;
        }
        .errors {
            background: #ffe8e8;
            border: 1px solid #ffb3b3;
            color: #a10000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .result-card {
            background: #f0f7ff;
            border: 1px solid #cfe0ff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .winner-label {
            font-weight: bold;
        }
        .participants-summary {
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background: #f2f2f2;
            text-align: left;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Gewinnziehung</h1>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="participant_count">Anzahl der Teilnehmer</label>
            <input type="number" name="participant_count" id="participant_count" min="1" value="<?= htmlspecialchars($_POST['participant_count'] ?? '') ?>">
            <p class="hint">Geben Sie die Gesamtzahl der Teilnehmer ein, wenn keine Datei verwendet wird.</p>
        </div>
        <div class="form-group">
            <label for="participant_list">CSV-Datei mit Teilnehmern</label>
            <input type="file" name="participant_list" id="participant_list" accept=".csv,text/csv">
            <p class="hint">Optional: Laden Sie eine CSV-Datei hoch. Jede Zeile entspricht einem Teilnehmer. Unterstützt ",", ";", "|" und Tab als Trennzeichen.</p>
        </div>
        <div class="form-group">
            <label for="winner_count">Anzahl der Gewinner</label>
            <input type="number" name="winner_count" id="winner_count" min="1" required value="<?= htmlspecialchars($_POST['winner_count'] ?? '') ?>">
        </div>
        <button type="submit">Gewinner ermitteln</button>
    </form>

    <?php if (!empty($result['errors'])): ?>
        <div class="errors">
            <ul>
                <?php foreach ($result['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($result['errors']) && !empty($result['winners'])): ?>
        <h2>Ermittelte Gewinner</h2>
        <?php foreach ($result['winners'] as $winner): ?>
            <div class="result-card">
                <div class="winner-label"><?= htmlspecialchars($winner['label']) ?></div>
                <div><?= $winner['value'] ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($result['participants'])): ?>
        <div class="participants-summary">
            <h3>Teilnehmerliste (<?= count($result['participants']) ?> Einträge)</h3>
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Daten</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['participants'] as $index => $participantRow): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= implode(' | ', array_map('htmlspecialchars', $participantRow)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
