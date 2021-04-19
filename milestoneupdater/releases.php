<?php

require_once 'vendor/autoload.php';

$COLOR_GRAY = "\033[0;37m";
$COLOR_RED = "\033[0;31m";
$NO_COLOR = "\033[0m";
$STRIKE_THROUGH = "\033[9m";
$BOLD = "\033[1m";

$client = new \Github\Client();
$cache = new Stash\Pool();

$client->addCache($cache);

if(!file_exists(__DIR__ . '/../credentials.json')) {
	print 'Please create the file ../credentials.json and provide your apikey.' . PHP_EOL;
	print '  cp credentials.dist.json credentials.json' . PHP_EOL;
	exit(1);
}

function milestoneSort($a, $b) {
	return strnatcasecmp($a['title'], $b['title']);
}
function skipBecauseOfVersionConstraint($versionAdded, $milestoneOrLabelName) {
	$version = explode(' ', $milestoneOrLabelName)[1];
	return version_compare($versionAdded, $version) === 1;
}
function getDateTime($date) {
	$dateObject = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', new DateTimeZone('US/Pacific'));

	$dateObject->setTimeZone(new DateTimeZone('UTC'));

	return $dateObject->format('Y-m-d\TH:i:s\Z');
}

$authentication = json_decode(file_get_contents(__DIR__ . '/../credentials.json'));

$client->authenticate($authentication->apikey, Github\Client::AUTH_ACCESS_TOKEN);
$paginator = new Github\ResultPager($client);

$config = json_decode(file_get_contents('config.json'), true);
$repositories = [];

$updateDueDate = [];

$SHOW_MILESTONE = true;

foreach($config['repos'] as $repo) {
	$repositories[$repo] = [
		'milestones' => [],
	];

	print('Repo ' . $config['org'] . '/' . $repo . PHP_EOL);
	if($SHOW_MILESTONE) print("  Milestones" . PHP_EOL);
	$milestones = $client->api('issue')->milestones()->all($config['org'], $repo);
	uasort($milestones, 'milestoneSort');
	foreach($milestones as $milestone) {
		$repositories[$repo]['milestones'][$milestone['title']] = $milestone;

		if($SHOW_MILESTONE) print("    ");
		if($milestone['open_issues'] !== 0) {
			if($SHOW_MILESTONE) print($milestone['title'] . ' ' . $milestone['open_issues']);
		} else {
			if($SHOW_MILESTONE) print($COLOR_GRAY. $milestone['title']);
		}
		if(array_key_exists($milestone['title'], $config['dueDates']) &&
			$milestone['due_on'] !== getDateTime($config['dueDates'][$milestone['title']])) {
			if($SHOW_MILESTONE) print($COLOR_RED . ' update due date');
			$updateDueDate[] = [
				'org' => $config['org'],
				'repo' => $repo,
				'number' => $milestone['number'],
				'milestone' => $milestone['title'],
				'state' => $milestone['state'],
				'title' => $milestone['title'],
				'description' => $milestone['description'],
				'oldDueDate' => $milestone['due_on'],
				'newDueDate' => getDateTime($config['dueDates'][$milestone['title']]),
			];
		}
		if($SHOW_MILESTONE) print($NO_COLOR . PHP_EOL);
	}
}

$response = $client->getHttpClient()->get("rate_limit");
print("Remaining requests to GitHub this hour: " . \Github\HttpClient\Message\ResponseMediator::getContent($response)['rate']['remaining'] . PHP_EOL);

foreach($repositories as $name => $repository) {
	foreach($repository['milestones'] as $milestone => $info) {
		if(in_array($milestone, $config['closeMilestones'])) {
			$data = [
				"title" => $milestone,
				"state" => $info['state'],
				"description" => $info['description'] ,
				"due_on" => $info['due_on']
			];
			if($info['open_issues'] === 0) {
				$data['state'] = 'closed';
			}
			$textStyle = $data['state'] === 'open' ? $BOLD : '';
			$textColor = $data['state'] === 'open' ? $COLOR_RED : '';
			print($textColor . $config['org'] . '/' . $name . ': close milestone ' . $milestone . ' - state: ' . $textStyle . $data['state'] . $NO_COLOR . PHP_EOL);

			if(array_key_exists($milestone, $config['dueDates'])) {
				$data['due_on'] = getDateTime($config['dueDates'][$milestone]);
			}
			$client->api('issue')->milestones()->update($config['org'], $name, $info['number'], $data);
		}
	}

	foreach($config['addMilestones'] as $milestone) {
		if(!array_key_exists($milestone, $repository['milestones'])) {
			if(isset($config['versionAdded'][$name]) && skipBecauseOfVersionConstraint($config['versionAdded'][$name], $milestone)) {
				print($COLOR_GRAY . $config['org'] . '/' . $name . ': skipped milestone ' . $milestone . $NO_COLOR . PHP_EOL);
				continue;
			}

			print($config['org'] . '/' . $name . ': add milestone ' . $milestone . $NO_COLOR . PHP_EOL);
			$data = [
				"title" => $milestone
			];
			if(array_key_exists($milestone, $config['dueDates'])) {
				$data['due_on'] = getDateTime($config['dueDates'][$milestone]);
			}
			$client->api('issue')->milestones()->create($config['org'], $name, $data);

		}
	}
}

if(count($updateDueDate)) {
	print('Following due dates need to be updated:' . PHP_EOL);

	foreach($updateDueDate as $date) {
		print($COLOR_RED . $date['org'] . '/' . $date['repo'] . ' ' . $date['title'] . ' from ' . $date['oldDueDate'] . ' to ' . $date['newDueDate'] . $NO_COLOR . PHP_EOL);
		if(in_array($date['title'], $config['closeMilestones'])) {
			continue; // no need to change the due date of a milestone that is meant to be closed
		}
		$client->api('issue')->milestones()->update($date['org'], $date['repo'], $date['number'], [
			'title' => $date['title'],
			'state' => $date['state'],
			'description' => $date['description'],
			'due_on' => $date['newDueDate']
		]);
	}
}
