<?php
$files = [
    'c:/xampp/htdocs/foodpoint/app/Http/Controllers/Admin/ReportController.php',
    'c:/xampp/htdocs/foodpoint/routes/api.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    $output = [];
    $retval = 0;
    exec("php -l " . escapeshellarg($file), $output, $retval);
    echo implode("\n", $output) . "\n";
}
