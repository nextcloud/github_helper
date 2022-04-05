<?php

declare(strict_types=1);

if(count($argv) < 2) {
	die("check.php [--verbose] github_user\n");
}

require_once 'vendor/autoload.php';

$isVerbose = $argv[1] === '--verbose';
$githubUser = $isVerbose ? $argv[2] : $argv[1];;

const ORGANIZATIONS = ['nextcloud', 'nextcloud-releases', 'nextcloud-gmbh'];
$ghClient = initGithubClient();

$results = [];
foreach (ORGANIZATIONS as $organization) {
	$results[$organization] = [];
	$page = 1;

	printVerbose('Checking ' . $organization);
	do {
		try {
			printVerbose(PHP_EOL . 'Page ' . $page);
			$repos = $ghClient->organization()->repositories($organization, 'all', $page);
		} catch (\Github\Exception\RuntimeException $e) {
			if ($e->getMessage() === 'Not Found') {
				$repos = [];
			} else {
				throw $e;
			}
		}
		$page++;
		foreach ($repos as $repo) {
			printVerbose('.');
			try {
				$collaborator = $ghClient->repository()->collaborators()->permission($organization, $repo['name'], $githubUser);
			} catch (\Github\Exception\RuntimeException $e) {
				if ($e->getMessage() === 'Not Found') {
					printVerbose(PHP_EOL . 'No permissions reported on ' . $repo['name'] . PHP_EOL);
					continue;
				}
				throw $e;
			}
			if ($collaborator['permission'] === 'none') {
				continue;
			}
			// ignore read access on public organizations
			if ($collaborator['permission'] === 'read' && $repo['private'] === false) {
				continue;
			}
			// ignore simple write access on public main organization
			if ($collaborator['permission'] === 'write' && $organization === 'nextcloud') {
				continue;
			}
			$results[$organization][] = [ 'repo' => $repo['name'], 'permissions' => $collaborator['permission'], 'role' => $collaborator['role_name'] ] ;
		}
	} while (!empty($repos));
	printVerbose(PHP_EOL . PHP_EOL);
}

print(\json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL);

function initGithubClient(): \Github\Client {
	$client = $client = new \Github\Client();
	$authentication = \json_decode(file_get_contents(__DIR__ . '/../credentials.json'));
	$client->authenticate($authentication->apikey, Github\AuthMethod::ACCESS_TOKEN);
	return $client;
}

function printVerbose(string $msg) {
	global $isVerbose;
	if (!$isVerbose) {
		return;
	}
	print($msg);
}
