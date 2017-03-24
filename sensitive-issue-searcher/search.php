<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once __DIR__ . '/vendor/autoload.php';

if(count($argv) !== 4) {
	die(__FILE__ . ' TOKEN OWNER REPOSITORY');
}

$token = $argv[1];
$owner = $argv[2];
$repository = $argv[3];

$searchTerms = [
	"'passwordsalt' =>",
	"'secret' =>",
	"'dbpassword' =>",
	"'mail_smtppassword' =>",
];


$client = new \Github\Client();
$client->authenticate($token, '', \Github\Client::AUTH_HTTP_TOKEN);


$paginator  = new Github\ResultPager($client);
/** @var \Github\Api\Issue $issues */
$issueApi = $client->api('issue');
$issueApi->setPerPage(100);
/** @var array $issues */
$issues = $paginator->fetchAll($issueApi, 'all', [$owner, $repository , ['state' => 'all']]);
foreach($issues as $issue) {
	$stringsToSearch = [];
	/** @var string $issueText */
	$stringsToSearch[] = $issue['body'];
	/** @var int $issueNumber */
	$issueNumber = $issue['number'];
	/** @var array $comments */
	$comments = $issueApi->comments()->all($owner, $repository, $issueNumber);
	foreach($comments as $comment) {
		$stringsToSearch[] = $comment['body'];
	}

	foreach($stringsToSearch as $string) {
		foreach($searchTerms as $term) {
			$pattern = "/$term '(.*)',/";

			preg_match_all($pattern,
				$string,
				$result, PREG_PATTERN_ORDER);

			if (count($result) === 2 && isset($result[1][0])) {
				$leakedSecret = $result[1][0];
				$acceptableResults = [
					'***',
					'****',
					'hidden',
					'redacted',
					'xxx',
					'xxxx',
					'***removed***',
					'replaced',
					'****removed****',
					'blanked',
					'[removed]',
					'[redacted]',
					'',
				];
				if(!in_array(strtolower($leakedSecret), $acceptableResults, true)) {
					echo($issueNumber . ':' . $result[1][0] . "\n");
				}
			}
		}
	}
}
