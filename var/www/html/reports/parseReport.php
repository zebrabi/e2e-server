<?php

function processSuite(array $suite, array &$statusCounts) {
    // Process the suite's specs
    if (!empty($suite['specs'])) {
        foreach ($suite['specs'] as $spec) {
            foreach ($spec['tests'] as $test) {
                $statusToCount = "other"; // Default to "other"
                
                // Process the test results
                if (!empty($test['results'])) {
                    $actualStatus = $test['status'];
                    $expectedStatus = $test['expectedStatus'] ?? ''; // Default to empty if not set

                    if ($actualStatus === 'expected') {
                        $statusToCount = $expectedStatus;
                    } elseif ($actualStatus === 'unexpected') {
                        $statusToCount = 'failed';
                    } else {
                        $statusToCount = $actualStatus;
                    }
                } else {
                    $statusToCount = 'skipped';
                }

                // Only count known statuses
                if (!array_key_exists($statusToCount, $statusCounts)) {
                    $statusToCount = 'other';
                }
                
                // Increment the count for the status
                $statusCounts[$statusToCount]++;
            }
        }
    }

    // Process sub-suites recursively
    if (!empty($suite['suites'])) {
        foreach ($suite['suites'] as $subSuite) {
            processSuite($subSuite, $statusCounts);
        }
    }
}

function countTestStatuses(array $testResults): array {
    // Initialize the status counts
    $statusCounts = [
        'passed' => 0,
        'failed' => 0,
        'flaky' => 0,
        'skipped' => 0,
        'other' => 0,
    ];

    // Process the suites
    foreach ($testResults['suites'] as $suite) {
        processSuite($suite, $statusCounts); // Pass the statusCounts by reference
    }

    return $statusCounts;
}

function parsePwReport(string $pwReport): string {
    $summaryFilePath = $pwReport . DIRECTORY_SEPARATOR . 'summary.html';
    $pwReportPath = $pwReport . DIRECTORY_SEPARATOR . 'test-results.json';

    // Check if summary.html already exists
    if (file_exists($summaryFilePath)) {
        return file_get_contents($summaryFilePath);
    }

    // Check if test-results.json exists and is readable
    if (!file_exists($pwReportPath)) {
        return "File not found: $pwReportPath\n";
    }

    $pwReportString = file_get_contents($pwReportPath);
    $testResults = json_decode($pwReportString, true);
    
    if ($testResults === null) {
        return "Error parsing JSON file.\n";
    }
    
    // Get the status counts
    $statusCounts = countTestStatuses($testResults);
    
    // Start building the summary string
    $summary = "<div style='display: flex; gap: 10px; font-family: Arial, sans-serif;'>";
    
    foreach ($statusCounts as $status => $count) {
        if ($count > 0) {
            switch ($status) {
                case 'passed':
                    $icon = "&#9989;"; // ✅
                    break;
                case 'failed':
                    $icon = "&#10060;"; // ❌
                    break;
                case 'flaky':
                    $icon = "&#9888;"; // ⚠️
                    break;
                case 'skipped':
                    $icon = "&#10134;"; // ➖
                    break;
                default:
                    $icon = "&#10067;"; // ❓
            }
            
            $summary .= "<div style='padding: 5px 10px; border: 1px solid #ddd; border-radius: 5px;'>";
            $summary .= "$icon <strong>" . ucfirst($status) . "</strong>: $count";
            $summary .= "</div>";
        }
    }
    
    $summary .= "</div>";
    
    // Save summary to file
    //$summaryFilePath = getcwd() . DIRECTORY_SEPARATOR . $summaryFilePath;
    file_put_contents($summaryFilePath, $summary);
    
    return $summary;
}

?>