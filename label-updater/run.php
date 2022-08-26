<?php

require_once 'vendor/autoload.php';

$COLOR_GRAY = "\033[0;37m";
$COLOR_RED = "\033[0;31m";
$NO_COLOR = "\033[0m";
$STRIKE_THROUGH = "\033[9m";
$BOLD = "\033[1m";

$client = new \Github\Client(
	new \Github\HttpClient\CachedHttpClient([
		'cache_dir' => '/tmp/github-api-cache'
	])
);

if(!file_exists('credentials.json')) {
	print 'Please create the file credentials.json and provide your apikey.' . PHP_EOL;
	print '  cp credentials.dist.json credentials.json' . PHP_EOL;
	exit(1);
}

$authentication = json_decode(file_get_contents('credentials.json'));

$client->authenticate($authentication->apikey, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new Github\ResultPager($client);

$response = $client->getHttpClient()->get("rate_limit");
print("Remaining requests to GitHub this hour: " . \Github\HttpClient\Message\ResponseMediator::getContent($response)['rate']['remaining'] . PHP_EOL);

$repos = $argv;
$options = getopt('', ['init']);
$config = json_decode(file_get_contents('config.json'), true);

$init = isset($options['init']);
unset($repos[0]);
if ($init) {
	unset($repos[array_search('--init', $repos, true)]);
}

if (empty($repos)) {
	$repos = $config['repos'];
}

/** @var \Github\Api\Issue\Labels $api */
$api = $client->api('issue')->labels();

$organizationApi = $client->api('organization');

function getAllLabels($client, $owner, $repo) {
	$paginator = new Github\ResultPager($client);
	return $paginator->fetchAll($client->api('issue')->labels(), 'all', [$owner, $repo]);
}

$masterLabels = getAllLabels($client, $config['org'], $config['master']);

foreach ($repos as $repo) {
	$org = $config['org'];

	if (strpos($repo, '/') !== false) {
		[$org, $repo] = explode('/', $repo);
	}

	$labels = getAllLabels($client, $org, $repo);
	if ($init) {
		foreach ($labels as $label) {
			$api->deleteLabel($org, $repo, $label['name']);
		}
		$labels = [];
	}
	print($BOLD . $org . '/' . $repo . $NO_COLOR . PHP_EOL);

	foreach ($masterLabels as $masterLabel) {
		foreach ($config['exclude'] as $exclude) {
			if (preg_match($exclude, $masterLabel['name'])) {
				print(' - ' . $org . '/' . $repo . ': ' . $STRIKE_THROUGH . $masterLabel['name'] . $NO_COLOR . ' ignoring because of patter ' . $exclude . PHP_EOL);
				continue 2;
			}
		}

		foreach ($labels as $label) {
			if ($label['name'] === $masterLabel['name']) {
				if ($label['color'] !== $masterLabel['color']) {
					print(' - ' . $org . '/' . $repo . ': Updating color of ' . $masterLabel['name'] . $NO_COLOR . PHP_EOL);
					$api->update($org, $repo, $label['name'], $masterLabel['name'], $masterLabel['color']);
				} else {
					print(' - ' . $org . '/' . $repo . ': Skipping ' . $masterLabel['name'] . $NO_COLOR . PHP_EOL);
				}
				continue 2;
			}
		}


		print(' - ' . $org . '/' . $repo . ': Adding ' . $masterLabel['name'] . $NO_COLOR . PHP_EOL);
		$api->create($org, $repo, [
			'name' => $masterLabel['name'],
			'color' => $masterLabel['color'],
		]);
	}
}
