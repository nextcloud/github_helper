<?php

require_once 'vendor/autoload.php';

$isHelp = in_array('--help', $argv) || in_array('-h', $argv);
$isDryRun = in_array('--dry-run', $argv);
$isResume = in_array('--resume', $argv);

if ($argc < 3 || $isHelp) {
    echo "Usage: php moveMilestoneIssues.php <source-milestone> <destination-milestone> [--help|-h] [--dry-run] [--resume]\n";
    echo "Example: php moveMilestoneIssues.php 'Nextcloud 31.0.8' 'Nextcloud 31.0.9'\n";
    echo "The source of the repositories and organization is taken from the milestoneupdater's config.json\n";
    echo "The destination milestone must already exist.\n";
    echo "\n";
    echo "Options:\n";
    echo "  --dry-run    Show what would be moved without making changes\n";
    echo "  --resume     Resume from where the last run left off\n";
    exit(1);
}

$sourceMilestoneTitle = $argv[1];
$destMilestoneTitle   = $argv[2];

// Progress tracking file
$progressFile = "milestone_move_progress.json";

$client = new \Github\Client();
$cache = new \Stash\Pool();
$client->addCache($cache);

if (!file_exists(__DIR__ . '/../credentials.json')) {
    echo "Please create the file ../credentials.json and provide your apikey.\n";
    echo "  cp credentials.dist.json credentials.json\n";
    exit(1);
}

$authentication = json_decode(file_get_contents(__DIR__ . '/../credentials.json'));
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: Invalid JSON in credentials.json: " . json_last_error_msg() . "\n";
    exit(1);
}

if (!isset($authentication->apikey)) {
    echo "Error: credentials.json must contain 'apikey' field\n";
    exit(1);
}

$client->authenticate($authentication->apikey, \Github\AuthMethod::ACCESS_TOKEN);

$paginator = new Github\ResultPager($client);

// Validate config exists
if (!file_exists('../milestoneupdater/config.json')) {
    echo "Error: config.json not found\n";
    exit(1);
}

$config = json_decode(file_get_contents('../milestoneupdater/config.json'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: Invalid JSON in config.json: " . json_last_error_msg() . "\n";
    exit(1);
}

if (!isset($config['org']) || !isset($config['repos']) || !is_array($config['repos'])) {
    echo "Error: config.json must contain 'org' and 'repos' (array) keys\n";
    exit(1);
}

$org = $config['org'];
$repos = $config['repos'];

// Progress tracking
$progress = [
    'source_milestone' => $sourceMilestoneTitle,
    'dest_milestone' => $destMilestoneTitle,
    'completed_repos' => [],
    'completed_issues' => [],
    'total_moved' => 0,
    'started_at' => date('Y-m-d H:i:s'),
    'last_updated' => date('Y-m-d H:i:s')
];

// Load existing progress if resuming
if ($isResume && file_exists($progressFile)) {
    $existingProgress = json_decode(file_get_contents($progressFile), true);
    if ($existingProgress && 
        $existingProgress['source_milestone'] === $sourceMilestoneTitle && 
        $existingProgress['dest_milestone'] === $destMilestoneTitle) {
        $progress = $existingProgress;
        echo "Resuming previous run started at {$progress['started_at']}\n";
        echo "Already moved {$progress['total_moved']} issues\n\n";
    } else {
        echo "Warning: Progress file exists but doesn't match current milestones. Starting fresh.\n\n";
    }
} elseif ($isResume) {
    echo "No progress file found. Starting fresh.\n\n";
}

function saveProgress($progress, $progressFile) {
    $progress['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
}

function checkRateLimit($client) {
    try {
        $response = $client->getHttpClient()->get("/rate_limit");
        $rateData = \Github\HttpClient\Message\ResponseMediator::getContent($response);
        $remaining = $rateData['rate']['remaining'];
        $resetTime = $rateData['rate']['reset'];
        
        if ($remaining < 50) {
            $waitTime = max(0, $resetTime - time() + 60); // Add 1 minute buffer
            echo "Rate limit low ($remaining remaining). Waiting " . ceil($waitTime/60) . " minutes until reset...\n";
            sleep($waitTime);
        }
        return $remaining;
    } catch (Exception $e) {
        echo "Warning: Could not check rate limit: " . $e->getMessage() . "\n";
        return 1000; // Assume we're okay if we can't check
    }
}

// Process each repository
echo "Starting to process " . count($repos) . " repositories...\n\n";
if ($isDryRun) {
	echo "Running in dry-run mode. No changes will be made.\n\n";
}


$cachedMilestones = [];
$totalReposProcessed = 0;
foreach ($repos as $index => $repo) {
    // Skip if already completed
    if (in_array($repo, $progress['completed_repos'])) {
        echo "Skipping already completed $org/$repo (" . ($index + 1) . "/" . count($repos) . ")\n";
        continue;
    }

    $remaining = checkRateLimit($client);
    echo "Processing $org/$repo (" . ($index + 1) . "/" . count($repos) . ") - $remaining requests remaining\n";

    try {
        // Get both milestones in one go (fetch all pages)
        if (!isset($cachedMilestones["$org/$repo"])) {
            $milestones = $paginator->fetchAll($client->api('issue')->milestones(), 'all', [$org, $repo, ['state' => 'all']]);
            $cachedMilestones["$org/$repo"] = $milestones;
        } else {
            $milestones = $cachedMilestones["$org/$repo"];
        }

        $sourceMilestoneNumber = null;
        $destMilestoneNumber = null;
        foreach ($milestones as $m) {
            if ($m['title'] === $sourceMilestoneTitle) $sourceMilestoneNumber = $m['number'];
            if ($m['title'] === $destMilestoneTitle) $destMilestoneNumber = $m['number'];
            if ($sourceMilestoneNumber && $destMilestoneNumber) break;
        }

        if (!$sourceMilestoneNumber) {
            echo "  Source milestone \"$sourceMilestoneTitle\" not found in $repo\n";
            $progress['completed_repos'][] = $repo;
            saveProgress($progress, $progressFile);
            continue;
        }
        if (!$destMilestoneNumber) {
            echo "  Destination milestone \"$destMilestoneTitle\" not found in $repo\n";
            $progress['completed_repos'][] = $repo;
            saveProgress($progress, $progressFile);
            continue;
        }

        // Get all issues in the source milestone
        $issues = $paginator->fetchAll($client->api('issue'), 'all', [$org, $repo, [
            'milestone' => $sourceMilestoneNumber,
            'state' => 'open',
        ]]);

        if (empty($issues)) {
            echo "  No issues/PRs in $sourceMilestoneTitle for $repo\n";
            $progress['completed_repos'][] = $repo;
            saveProgress($progress, $progressFile);
            continue;
        }

        $repoIssuesMoved = 0;
        foreach ($issues as $issue) {
            $issueKey = "$org/$repo#{$issue['number']}";
            
            // Skip if already processed
            if (in_array($issueKey, $progress['completed_issues'])) {
                continue;
            }

            if ($isDryRun) {
                echo "  [Dry Run] Would move #{$issue['number']} to \"$destMilestoneTitle\"\n";
                $progress['completed_issues'][] = $issueKey;
                $repoIssuesMoved++;
                continue;
            }

            // Check rate limit before each API call
            checkRateLimit($client);

            try {
                $client->api('issue')->update($org, $repo, $issue['number'], [
                    'milestone' => $destMilestoneNumber,
                ]);
                echo "  Moved #{$issue['number']} to \"$destMilestoneTitle\"\n";
                $progress['completed_issues'][] = $issueKey;
                $progress['total_moved']++;
                $repoIssuesMoved++;
                
                // Save progress every 5 issues
                if (count($progress['completed_issues']) % 5 === 0) {
                    saveProgress($progress, $progressFile);
                }
                
            } catch (Exception $e) {
                echo "  Failed to move #{$issue['number']}: " . $e->getMessage() . "\n";
                
                // If it's a rate limit error, wait and retry
                if (strpos($e->getMessage(), 'rate limit') !== false || 
                    strpos($e->getMessage(), '403') !== false) {
                    echo "  Rate limit hit, waiting 1 hour...\n";
                    sleep(3600);
                    // Don't mark as completed, will retry on next iteration
                    continue;
                }
                
                // Mark as completed even if failed (to avoid infinite retries)
                $progress['completed_issues'][] = $issueKey;
            }
        }

		if ($isDryRun) {
			echo "  [Dry Run] Would have moved $repoIssuesMoved issues from $repo\n";
		} else {
        	echo "  Moved $repoIssuesMoved issues from $repo\n";
		}
        $progress['completed_repos'][] = $repo;
        $totalReposProcessed++;
        saveProgress($progress, $progressFile);

    } catch (Exception $e) {
        echo "  Error processing repository $repo: " . $e->getMessage() . "\n";
        // Don't mark repo as completed if there was an error
        saveProgress($progress, $progressFile);
    }
}

try {
    $response = $client->getHttpClient()->get("/rate_limit");
    $remaining = \Github\HttpClient\Message\ResponseMediator::getContent($response)['rate']['remaining'];
    echo "\nRemaining requests to GitHub this hour: $remaining\n";
} catch (Exception $e) {
    echo "\nCould not fetch final rate limit info\n";
}

echo "\nSummary:\n";
echo "- Total issues moved: {$progress['total_moved']}\n";
echo "- Repositories processed: $totalReposProcessed/" . count($repos) . "\n";
echo "- Started at: {$progress['started_at']}\n";
echo "- Completed at: " . date('Y-m-d H:i:s') . "\n";

// Clean up progress file if everything completed successfully
if (count($progress['completed_repos']) === count($repos)) {
    echo "\nAll repositories completed successfully!\n";
    if (!$isDryRun) {
        echo "Cleaning up progress file...\n";
        unlink($progressFile);
    }
} else {
    echo "\nProgress saved. Use --resume to continue from where you left off.\n";
}
