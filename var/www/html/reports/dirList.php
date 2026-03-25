<?php
require_once 'parseReport.php';

function currentDirectoryTitle() {
    $currentDirectory = getcwd();
    $currentDirectory = str_replace('/var/www/html', '', $currentDirectory);

    echo "<html>";
    echo "<head>";
    echo "<title>E2E Reports</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .filters {
            margin: 20px 0;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f9f9f9;
        }
        .filters label {
            margin-right: 15px;
        }
        .filters input[type='date'] {
            margin-right: 15px;
        }
        table { border-collapse: collapse; }
        td { padding: 6px 10px; vertical-align: top; }
    </style>";
    echo "</head>";
    echo "<body>";
    echo "<h1>Index of $currentDirectory</h1>";
}

function closeHtml() {
    echo "</body>";
    echo "</html>";
}

function getSize($path) {
    if (is_file($path)) {
        return filesize($path);
    } elseif (is_dir($path)) {
        $size = 0;
        $fileIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($fileIterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    return false;
}

function humanReadableSize($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return round($size, 2) . ' ' . $units[$unitIndex];
}

function renderFilters() {
    $tables = isset($_GET['tables']);
    $charts = isset($_GET['charts']);
    $cards = isset($_GET['cards']);

    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';

    echo "<form method='get' class='filters'>";

    echo "<label><input type='checkbox' name='tables' " . ($tables ? 'checked' : '') . "> tables</label>";
    echo "<label><input type='checkbox' name='charts' " . ($charts ? 'checked' : '') . "> charts</label>";
    echo "<label><input type='checkbox' name='cards' " . ($cards ? 'checked' : '') . "> cards</label>";

    echo "<label>From: <input type='date' name='from' value='" . htmlspecialchars($fromDate) . "'></label>";
    echo "<label>To: <input type='date' name='to' value='" . htmlspecialchars($toDate) . "'></label>";

    echo "<button type='submit'>Filter</button> ";
    echo "<a href='?'>Reset</a>";
    echo "</form>";
}

function matchesTypeFilter($itemName, $selectedTypes) {
    if (empty($selectedTypes)) {
        return true;
    }

    $itemNameLower = strtolower($itemName);

    foreach ($selectedTypes as $type) {
        if (strpos($itemNameLower, strtolower($type)) !== false) {
            return true;
        }
    }

    return false;
}

function matchesDateFilter($timestamp, $fromDate, $toDate) {
    if (!empty($fromDate)) {
        $fromTimestamp = strtotime($fromDate . ' 00:00:00');
        if ($timestamp < $fromTimestamp) {
            return false;
        }
    }

    if (!empty($toDate)) {
        $toTimestamp = strtotime($toDate . ' 23:59:59');
        if ($timestamp > $toTimestamp) {
            return false;
        }
    }

    return true;
}

function reportsList($dirsOnly = true) {
    $pattern = './*';
    $flags = $dirsOnly ? GLOB_ONLYDIR : 0;
    $items = glob($pattern, $flags);

    $items = array_filter($items, function($item) {
        return basename($item) !== 'index.php';
    });

    usort($items, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    echo "<table>";
    echo "<tr><td><a href='..'>../</a></td></tr>";

    foreach ($items as $item) {
        $size = getSize($item);
        $readableSize = humanReadableSize($size);
        $itemName = str_replace('./', '', $item);

        echo "<tr><td><a href='{$itemName}'>{$itemName}</a></td>";
        echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . date("Y-m-d H:i", filemtime($item)) . "</td>";
        echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[{$readableSize}]</td></tr>";
    }

    echo "</table>";
}

function reportsListWithResults($dirsOnly = true) {
    $pattern = './*';
    $flags = $dirsOnly ? GLOB_ONLYDIR : 0;
    $items = glob($pattern, $flags);

    $items = array_filter($items, function($item) {
        return basename($item) !== 'index.php';
    });

    $selectedTypes = [];

    if (isset($_GET['tables'])) $selectedTypes[] = 'tables';
    if (isset($_GET['charts'])) $selectedTypes[] = 'charts';
    if (isset($_GET['cards'])) $selectedTypes[] = 'cards';

    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';

    $items = array_filter($items, function($item) use ($selectedTypes, $fromDate, $toDate) {
        $itemName = str_replace('./', '', $item);
        $resultFile = $item . DIRECTORY_SEPARATOR . "test-results.json";

        if (!file_exists($resultFile)) {
            return false;
        }

        $createdTime = filemtime($resultFile);

        if (!matchesTypeFilter($itemName, $selectedTypes)) {
            return false;
        }

        if (!matchesDateFilter($createdTime, $fromDate, $toDate)) {
            return false;
        }

        return true;
    });

    usort($items, function($a, $b) {
        $result_a = $a . DIRECTORY_SEPARATOR . "test-results.json";
        $result_b = $b . DIRECTORY_SEPARATOR . "test-results.json";
        return filemtime($result_b) - filemtime($result_a);
    });

    renderFilters();

    echo "<table>";
    echo "<tr><td><a href='..'>../</a></td><td></td></tr>";

    flush();

    foreach ($items as $item) {
        $size = getSize($item);
        $readableSize = humanReadableSize($size);
        $itemName = str_replace('./', '', $item);

        echo "<tr><td><a href='{$itemName}/'>{$itemName}</a></td><td>";
        flush();

        $summary = parsePwReport($item);
        echo $summary;

        $result_file = $item . DIRECTORY_SEPARATOR . "test-results.json";

        echo "</td><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . date("Y-m-d H:i", filemtime($result_file)) . "</td>";
        echo "<td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[{$readableSize}]</td></tr>";
        flush();
    }

    echo "</table>";
    flush();
}
?>