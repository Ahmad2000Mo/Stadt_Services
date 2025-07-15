<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Parameter einlesen und validieren
$category  = $_GET['category'] ?? null;
$state     = $_GET['state'] ?? null;
$startYear = isset($_GET['startYear']) ? (int)$_GET['startYear'] : (int)(date('Y') - 5);
$endYear   = isset($_GET['endYear']) ? (int)$_GET['endYear'] : (int)date('Y');

if (!$category || !$state) {
    echo json_encode(['error' => 'Fehlende Parameter: category und state erforderlich.']);
    exit;
}

if ($startYear > $endYear) {
    echo json_encode(['error' => 'startYear darf nicht größer als endYear sein.']);
    exit;
}

// Kategorie-zu-Tabelle Mapping
$tables = [
    'population' => '12521-0020',
    'births'     => '12612-0100',
    'deaths'     => '12613-0011',
    'marriages'  => '12611-0010'
];

if (!isset($tables[$category])) {
    echo json_encode(['error' => 'Ungültige Kategorie']);
    exit;
}

$table = $tables[$category];

// Zeitraum für Genesis API: Komma-separierte Jahre
$zeitraum = implode(',', range($startYear, $endYear));

// API Request zusammenbauen
$token   = '4e22c3bb8d284e8a97ce79ea53d04d20';
$baseUrl = 'https://www-genesis.destatis.de/genesisWS/rest/2020';

$endpoint = $baseUrl . '/data/table?' . http_build_query([
    'username' => $token,
    'name'     => $table,
    'bereich'  => $state,
    'zeit'     => $zeitraum,
    'format'   => 'json',
    'language' => 'de'
]);

// cURL Request ausführen
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$error    = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error || $httpCode !== 200) {
    echo json_encode(['error' => 'Fehler beim Abruf der Genesis API', 'details' => $error ?: $httpCode]);
    exit;
}

// JSON-Antwort parsen
$data = json_decode($response, true);
if (!$data || !isset($data['Object']['Content'])) {
    echo json_encode(['error' => 'Ungültige Antwort von der API']);
    exit;
}

$rawText = $data['Object']['Content'];

// Funktion zum Parsen der Rohdaten (flexibel für mit/ohne Geschlecht)
function parseGenesisFlexible(string $raw): array {
    $lines = explode("\n", $raw);
    $data = [];
    $years = [];
    $hasGender = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Jahreszeile erkennen (beginnend mit ;)
        if (preg_match('/^\s*;[\d;]/', $line)) {
            $parts = explode(';', $line);
            $years = array_slice($parts, 1);
            continue;
        }

        if (strpos($line, ';') === false) continue;
        if (stripos($line, 'Statistik') !== false || stripos($line, 'Tabelle:') !== false) continue;

        $parts = explode(';', $line);
        $count = count($parts);

        if (count($years) === ($count - 2)) {
            // Mit Geschlechtsspalte
            $hasGender = true;
            $stateName = $parts[0];
            $gender = $parts[1];
            $values = array_slice($parts, 2);
        } elseif (count($years) === ($count - 1)) {
            // Ohne Geschlechtsspalte
            $stateName = $parts[0];
            $gender = null;
            $values = array_slice($parts, 1);
        } else {
            continue;
        }

        foreach ($years as $i => $year) {
            $year = trim($year);
            $val = $values[$i] ?? null;
            $val = str_replace(',', '.', trim($val));
            $val = is_numeric($val) ? (float)$val : null;

            if ($year !== '') {
                if ($gender !== null) {
                    $data[$stateName][$year][$gender] = $val;
                } else {
                    $data[$stateName][$year] = $val;
                }
            }
        }
    }
    return $data;
}

// Hilfsfunktion: Jahr aus "TT.MM.JJJJ" extrahieren
function extractYear(string $dateString): ?int {
    if (preg_match('/\d{4}$/', $dateString, $matches)) {
        return (int)$matches[0];
    }
    return null;
}

// Filterfunktion nach Bundesland und Zeitraum
function filterDataByStateAndYear(array $data, string $stateFilter, int $startYear, int $endYear): array {
    if (!isset($data[$stateFilter])) return [];

    $result = [];
    foreach ($data[$stateFilter] as $dateKey => $value) {
        $year = extractYear($dateKey);
        if ($year !== null && $year >= $startYear && $year <= $endYear) {
            $result[$dateKey] = $value;
        }
    }

    return [$stateFilter => $result];
}

// Parsen und Filtern
$parsedData = parseGenesisFlexible($rawText);
$filteredData = filterDataByStateAndYear($parsedData, $state, $startYear, $endYear);

// Ergebnis ausgeben
echo json_encode($filteredData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
