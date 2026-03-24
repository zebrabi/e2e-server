<?php
require_once 'parseReport.php';

function currentDirectoryTitle() {
	$currentDirectory = getcwd();
	$currentDirectory = str_replace('/var/www/html', '', $currentDirectory);

	echo "<html>";
	echo "<head>";
	echo "<title>E2E Reports</title>";
	echo "</head>";
	echo "<body>";
	echo "<h1>Index of $currentDirectory</h1>";
}

function closeHtml(){
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

    return false; // Return false if the path is neither a file nor a directory
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

function reportsList($dirsOnly = true){
    $pattern = './*';
    $flags = $dirsOnly ? GLOB_ONLYDIR : 0;
    $items = glob($pattern, $flags);

    // Exclude index.php from the list
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

    // Exclude index.php from the list
    $items = array_filter($items, function($item) {
        return basename($item) !== 'index.php';
    });

    usort($items, function($a, $b) {
        $result_a = $a . DIRECTORY_SEPARATOR . "test-results.json";
        $result_b = $b . DIRECTORY_SEPARATOR . "test-results.json";
        return filemtime($result_b) - filemtime($result_a);
    });


    echo "<table>";
    echo "<tr><td><a href='..'>../</a></td><td></td></tr>";

    // Ensure output is sent immediately
    //ob_end_flush();
    //echo str_pad('', 4096); // Send a buffer to push output
    flush();
    //ob_start();

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