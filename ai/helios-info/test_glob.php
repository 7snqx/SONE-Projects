<?php
echo "Current DIR: " . __DIR__ . "\n";
echo "Glob pattern: " . __DIR__ . '/history/*.json' . "\n";
$files = glob(__DIR__ . '/history/*.json');
print_r($files);

$dates = [];
foreach ($files as $file) {
    $dates[] = basename($file, '.json');
}
echo "Dates JSON:\n";
echo json_encode(['dates' => $dates]);
