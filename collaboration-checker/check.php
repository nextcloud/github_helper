<?php

declare(strict_types=1);

if(count($argv) < 2) {
	die("check.php [--verbose] github_user\n");
}

require_once 'vendor/autoload.php';

$isVerbose = $argv[1] === '--verbose';
$githubUser = $isVerbose ? $argv[2] : $argv[1];

const ORGANIZATIONS = ['nextcloud', 'nextcloud-releases', 'nextcloud-gmbh', 'nextcloud-deps', 'nextcloud-libraries'];
const CODEOWNERS_MAIN_BRANCHES = ['master', 'main'];
const CODEOWNERS_STABLE_BRANCHES = ['stable35', 'stable34', 'stable33'];
const CODEOWNERS_PATHS = ['.github/CODEOWNERS', 'CODEOWNERS', 'docs/CODEOWNERS'];
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
			if (preg_match('/.*-ghsa(-[0-9a-z]{4}){3}$/', $repo['name'])) {
				// Can not report permissions on GitHub Security Advisory forks
				printVerbose('S');
				continue;
			}
			printVerbose('.');
			$entry = ['repo' => $repo['name']];
			$codeownerBranches = getCodeownerBranches($ghClient, $organization, $repo['name'], $githubUser);
			if (!empty($codeownerBranches)) {
				$entry['codeowners'] = $codeownerBranches;
			}
			try {
				$collaborator = $ghClient->repository()->collaborators()->permission($organization, $repo['name'], $githubUser);
				if (isReportablePermission($collaborator, $repo, $organization)) {
					$entry['permissions'] = $collaborator['permission'];
					$entry['role'] = $collaborator['role_name'];
				}
			} catch (\Github\Exception\RuntimeException $e) {
				if ($e->getMessage() !== 'Not Found') {
					throw $e;
				}
				printVerbose(PHP_EOL . 'No permissions reported on ' . $repo['name'] . PHP_EOL);
			}
			if (count($entry) > 1) {
				$results[$organization][] = $entry;
			}
		}
	} while (!empty($repos));
	printVerbose(PHP_EOL . PHP_EOL);
}

print(\json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL);

function isReportablePermission(array $collaborator, array $repo, string $organization): bool {
	if ($collaborator['permission'] === 'none') {
		return false;
	}
	$isAdminOrMaintain = in_array($collaborator['role_name'], ['admin', 'maintain'], true);
	// ignore read access on public organizations
	if (!$isAdminOrMaintain && $collaborator['permission'] === 'read' && $repo['private'] === false) {
		return false;
	}
	// ignore simple write access on public main organization
	if (!$isAdminOrMaintain && $collaborator['permission'] === 'write' && $organization === 'nextcloud') {
		return false;
	}
	return true;
}

/**
 * Only repos where the user is listed on master/main are affected,
 * and the stable branches use the CODEOWNERS file at the same path.
 *
 * @return list<string> Branches on which the user is listed in a CODEOWNERS file
 */
function getCodeownerBranches(\Github\Client $client, string $organization, string $repo, string $githubUser): array {
	$candidates = [];
	foreach (CODEOWNERS_MAIN_BRANCHES as $branch) {
		foreach (CODEOWNERS_PATHS as $path) {
			$candidates[] = [$branch, $path];
		}
	}

	foreach (fetchFiles($client, $organization, $repo, $candidates) as $i => $text) {
		if ($text === null) {
			continue;
		}
		[$branch, $path] = $candidates[$i];
		if (!isListedInCodeowners($text, $githubUser)) {
			return [];
		}

		$branches = [$branch];
		$stable = array_map(static fn (string $b): array => [$b, $path], CODEOWNERS_STABLE_BRANCHES);
		foreach (fetchFiles($client, $organization, $repo, $stable) as $j => $stableText) {
			if ($stableText !== null && isListedInCodeowners($stableText, $githubUser)) {
				$branches[] = CODEOWNERS_STABLE_BRANCHES[$j];
			}
		}
		return $branches;
	}
	return [];
}

/**
 * @param list<array{string, string}> $files List of [branch, path]
 * @return list<?string> File contents, null when the branch or file does not exist
 */
function fetchFiles(\Github\Client $client, string $organization, string $repo, array $files): array {
	$fields = '';
	foreach ($files as $i => [$branch, $path]) {
		$fields .= sprintf('f%d: object(expression: "%s:%s") { ... on Blob { text } } ', $i, $branch, $path);
	}
	$response = $client->graphql()->execute(
		'query($owner: String!, $name: String!) { repository(owner: $owner, name: $name) { ' . $fields . '} }',
		['owner' => $organization, 'name' => $repo],
	);
	return array_map(static fn (int $i): ?string => $response['data']['repository']["f$i"]['text'] ?? null, array_keys($files));
}

function isListedInCodeowners(string $codeowners, string $githubUser): bool {
	$pattern = '/(^|\s)@' . preg_quote($githubUser, '/') . '(\s|$)/i';
	foreach (explode("\n", $codeowners) as $line) {
		$line = trim(explode('#', $line, 2)[0]);
		if ($line !== '' && preg_match($pattern, $line)) {
			return true;
		}
	}
	return false;
}

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
