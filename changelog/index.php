<?php

// This file is generated by Composer
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
# TODO
#use Cache\Adapter\Redis\RedisCachePool;

class GenerateChangelogCommand extends Command
{
	const ORG_NAME = 'nextcloud';
	const REPO_SERVER = 'server';

    private $skipLabels = [];

	protected function configure()
	{
		$this
			->setName('generate:changelog')
			->setDescription('Generates the changelog.')
			->addArgument('repo', InputArgument::REQUIRED, 'The repo name, default is server. Other options e.g. desktop, android.')
			->addArgument('base', InputArgument::REQUIRED, 'The base version.')
			->addArgument('head', InputArgument::REQUIRED, 'The head version.')
			->addOption(
				'format',
				'f',
				InputOption::VALUE_REQUIRED,
				'What format should the output have? (markdown, forum, html)',
				'markdown'
			)
			->addOption(
				'no-bots',
				null,
				InputOption::VALUE_NONE,
				'Remove automated PRs and commits from all results'
			)
			->addOption(
				'skip-label',
				null,
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'Skip pull requests with the given label'
			);
	}

	protected function cleanTitle($title)
	{
		$title = preg_replace('!(\[|\()(stable)? ?\d\d(\]|\))?\W*!i', '', $title);
		$title = preg_replace('!^\[security\]!i', '', $title);
		$title = trim($title);
		return strtoupper(substr($title, 0, 1)) . substr($title, 1);
	}

	protected function processPR($repoName, $pr)
	{
		$title = $this->cleanTitle($pr['title']);
		$id = '#' . $pr['number'];
		if ($repoName !== 'server') {
			$id = $repoName . $id;
		}
		$data = [
			'repoName' => $repoName,
			'number' => $pr['number'],
			'title' => $title,
		];

		if (isset($pr['author']['login'])) {
			$data['author'] = $pr['author']['login'];
		}

		return [$id, $data];
	}

	protected function shouldPRBeSkipped(array $pr)
	{
		if (preg_match('!^\d+(\.\d+(\.\d+))? ?(rc|beta|alpha)? ?(\d+)?$!i', $pr['title'])) {
			return true;
		}
        if (isset($pr['labels'], $pr['labels']['nodes'])) {
            foreach ($pr['labels']['nodes'] as $label) {
                if (in_array($label['id'], $this->skipLabels, false) || in_array(strtolower($label['name']), $this->skipLabels, false) ) {
                    return true;
                }
            }
        }
		return false;
	}

	/**
	 * Get the list of shipped apps from server head
	 * Then compare for existing repos to check against
	 *
	 * @param string $head the server head, master, stable26, stable19...
	 * @return string[]
	 */
	protected function getReposToIterate($head = 'master')
	{
		$client = new \GuzzleHttp\Client();
		$ghClient = new \Github\Client();
		$this->authenticateGithubClient($ghClient);

		// TODO iterate over all repos
		$shippedApps = [];
		$orgRepositories = [];
		$reposToIterate = [
			"server",
			"3rdparty",
		];

		try {
			$res = $client->request('GET', "https://raw.githubusercontent.com/nextcloud/server/$head/core/shipped.json");
			$shippedApps = json_decode($res->getBody()->getContents(), true)['shippedApps'] ?? [];
		} catch (\Exception $e) {
			throw new Exception('Unable to fetch the shipped apps list.');
		}

		try {
			/** @var \Github\Api\Organization $organizationApi */
			$organizationApi = $ghClient->api('organization');
			$paginator = new Github\ResultPager($ghClient, 50);
			$parameters = array(self::ORG_NAME);
			$repos = $paginator->fetchAll($organizationApi, 'repositories', $parameters);

			// Filter out archived and disabled repos
			$results = array_filter($repos, function($repo): bool {
				return $repo['archived'] === false
					&& $repo['disabled'] === false;
			});

			// Return repos names
			$orgRepositories = array_map(fn($repo): string => $repo['name'], $results);
		} catch (\Exception $e) {
			throw new Exception('Unable to fetch the github repositories list.');
		}

		return [...$reposToIterate, ...array_intersect($orgRepositories, $shippedApps)];
	}

	protected function authenticateGithubClient(\Github\Client $client) {
		if (!file_exists(__DIR__ . '/../credentials.json')) {
			throw new Exception('Credentials file is missing - please provide your credentials in credentials.json in the root folder.');
		}

		$credentialsData = json_decode(file_get_contents(__DIR__ . '/../credentials.json'), true);
		if (!is_array($credentialsData) || !isset($credentialsData['apikey'])) {
			throw new Exception('Credentials file can not be read or does not provide "apikey".');
		}

		$client->authenticate($credentialsData['apikey'], Github\Client::AUTH_ACCESS_TOKEN);
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$repoName = $input->getArgument('repo');
		$base = $input->getArgument('base');
		$head = $input->getArgument('head');

		$orgName = self::ORG_NAME;

		if (!file_exists(__DIR__ . '/../credentials.json')) {
			throw new Exception('Credentials file is missing - please provide your credentials in credentials.json in the root folder.');
		}

		$credentialsData = json_decode(file_get_contents(__DIR__ . '/../credentials.json'), true);
		if (!is_array($credentialsData) || !isset($credentialsData['apikey'])) {
			throw new Exception('Credentials file can not be read or does not provide "apikey".');
		}

		$format = $input->getOption('format');
		if (!in_array($format, ['markdown', 'forum', 'html'])) {
			throw new \Symfony\Component\Console\Exception\InvalidOptionException(
				"The provided format is invalid (should be one of markdown, forum, html but was '$format')"
			);
		}

        $this->skipLabels = array_map('strtolower', $input->getOption('skip-label'));

		if ($output->isVerbose()) {
			$output->writeln("repo: $repoName");
			$output->writeln("base: $base");
			$output->writeln("head: $head");
		}

		// Android overriding
		$milestoneToCheck = null;
		$substring = 'v';
		$subStringNum = 1;
		if ($repoName !== self::REPO_SERVER) {
			$reposToIterate = [$repoName];
			$substring = 'stable-';
			$subStringNum = 7;
		} else {
			// else we are checking the server changelog
			$reposToIterate = $this->getReposToIterate($head);
		}

		if (substr($base, 0, $subStringNum) === $substring) {
			$version = explode('.', strtolower(substr($base, $subStringNum)));
			if (count($version) !== 3) {
				$output->writeln('<error>Detected version does not have exactly 3 numbers separated by a dot.</error>');
			} else {
				if (strpos($version[2], 'rc') !== false || strpos($version[2], 'beta') !== false) {
					$version[2] = (string)((int)$version[2]); // this basically removes the beta/RC part
					$milestoneToCheck = join('.', $version);

					if (strpos($milestoneToCheck, '.0.0') !== false) {
						$milestoneToCheck = str_replace('.0.0', '', $milestoneToCheck);
					}
				} else {
					$version[2] = (string)((int)$version[2] + 1);
					$milestoneToCheck = join('.', $version);
				}
				if ($output->isVerbose()) {
					$output->writeln("Checking milestone $milestoneToCheck for pending PRs ...");
				}
			}
		} else {
			$output->writeln('<error>No version detected - the output will not contain any pending PRs. Use a git tag starting with "v" like "v13.0.5".</error>');
		}

		$prTitles = ['closed' => [], 'pending' => []];

		# TODO
		#$client = new \Redis();
		#$client->connect('127.0.0.1', 6379);
		// Create a PSR6 cache pool
		#$pool = new RedisCachePool($client);

		$client = new \Github\Client();
		# TODO
		#$client->addCache($pool);
		$this->authenticateGithubClient($client);

		$factor = 2;
		if ($milestoneToCheck !== null) {
			$factor = 3;
		}

		$progressBar = new ProgressBar($output, count($reposToIterate) * $factor);
		$progressBar->setFormat(
			implode(
				"\n",
				[
					' %message%',
					' %current%/%max% [%bar%] %percent:3s%%',
					' Remaining: %remaining:6s%',
				]
			)
		);
		$progressBar->setMessage('Starting ...');
		$progressBar->start();

		$isBetaNull = strpos($base, 'beta0') !== false;

		foreach ($reposToIterate as $repoName) {
			$pullRequests = [];
			/** @var \Github\Api\Repo $repo */
			$repo = $client->api('repo');
			if (!$isBetaNull) {
				try {
					$progressBar->setMessage("Fetching git history for $repoName between $base and $head...");
					$paginator = new Github\ResultPager($client);
					$parameters = array(self::ORG_NAME, $repoName, $base, $head);
					$commitsApi = $repo->commits();
					$commits = $paginator->fetch($commitsApi, 'compare', $parameters)['commits'];
					while ($paginator->hasNext()) {
						$commits = array_merge($commits, $paginator->fetchNext()['commits']);
					}
				} catch (\Github\Exception\RuntimeException $e) {
					if ($e->getMessage() === 'Not Found') {
						$output->writeln('<error>Could not find base or head reference on ' . $repoName . '.</error>');
						// print 3 empty lines to not overwrite the error message with the progress bar
						$output->writeln('');
						$output->writeln('');
						$output->writeln('');
						continue;
					}
					throw $e;
				}

				foreach ($commits as $commit) {
					$noBots = $input->getOption('no-bots');
					$name = $commit['commit']['author']['name'];
					if ($noBots && (str_contains($name, '[bot]') || str_contains($name, 'nextcloud-'))) {
						// ignore this bot-created commit
						continue;
					}
					$fullMessage = $commit['commit']['message'];
					list($firstLine,) = explode("\n", $fullMessage, 2);
					if (substr($firstLine, 0, 20) === 'Merge pull request #') {
						$firstLine = substr($firstLine, 20);
						list($number,) = explode(" ", $firstLine, 2);
						$pullRequests[] = $number;
					}
				}
			}
			$progressBar->advance();

			if ($milestoneToCheck !== null) {
				$progressBar->setMessage("Fetching pending PRs for $repoName $milestoneToCheck ...");

				$query = "query{
	repository(owner: \"$orgName\", name: \"$repoName\") {
		milestones(first: 40, states: [OPEN]) {
			nodes {
				title
				number
				pullRequests(states: [OPEN], first: 40) {
					nodes {
						number
						title
						author {
							login
						}
					}
					pageInfo {
						endCursor
						hasNextPage
					}
				}
			}
		}
	}
}";

				$response = $client->api('graphql')->execute($query);
				foreach ($response['data']['repository']['milestones']['nodes'] as $milestone) {
					if (strpos($milestone['title'], $milestoneToCheck) !== false) {
						foreach ($milestone['pullRequests']['nodes'] as $pr) {
							if ($this->shouldPRBeSkipped($pr)) {
								continue;
							}
							list($id, $data) = $this->processPR($repoName, $pr);
							$prTitles['pending'][$id] = $data;
						}
						while ($milestone['pullRequests']['pageInfo']['hasNextPage']) {
							$query = "query{
	repository(owner: \"$orgName\", name: \"$repoName\") {
		milestone(number: {$milestone['number']}) {
			title
			number
			pullRequests(states: [OPEN], first: 40, after: \"{$milestone['pullRequests']['pageInfo']['endCursor']}\") {
				nodes {
					number
					title
					author {
						login
					}
				}
				pageInfo {
					endCursor
					hasNextPage
				}
			}
		}
	}
}";

							$response = $client->api('graphql')->execute($query);

							$milestone = $response['data']['repository']['milestone'];

							foreach ($milestone['pullRequests']['nodes'] as $pr) {
								if ($this->shouldPRBeSkipped($pr)) {
									continue;
								}
								list($id, $data) = $this->processPR($repoName, $pr);
								$prTitles['pending'][$id] = $data;
							}
						}
					}
				}
				$progressBar->advance();
			}


			$query = <<<'QUERY'
query {
QUERY;
			$query .= '		repository(owner: "' . $orgName . '", name: "' . $repoName . '") {';

			foreach ($pullRequests as $pullRequest) {
				$query .= "pr$pullRequest: pullRequest(number: $pullRequest) { number, title, labels(first: 10) { nodes { id, name } } },";
			}

			$query .= <<<'QUERY'
		}
}
QUERY;

			$progressBar->setMessage("Fetching PR titles for $repoName ...");

			if (count($pullRequests) === 0) {
				$progressBar->advance();
				continue;
			}

			$response = $client->api('graphql')->execute($query);
			if (!isset($response['data']['repository'])) {
				$progressBar->advance();
				continue;
			}

			foreach ($response['data']['repository'] as $pr) {
				if ($this->shouldPRBeSkipped($pr)) {
					continue;
				}
				list($id, $data) = $this->processPR($repoName, $pr);
				$prTitles['closed'][$id] = $data;
			}
			$progressBar->advance();
		}
		$progressBar->finish();

		$output->writeln('');

		ksort($prTitles['closed']);
		ksort($prTitles['pending']);

		switch ($format) {
			case 'html':
				$version = $milestoneToCheck;
				$versionDashed = str_replace('.', '-', $version);
				$date = new \DateTime('now');
				$date = $date->add(new \DateInterval('P1D'));
				$date = $date->format('F j Y');

				$output->writeln('<h3 id="' . $versionDashed . '">Version ' . $version . ' <small>' . $date . '</small></h3>');
				$output->writeln('<p>Download: <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.tar.bz2">nextcloud-' . $version . '.tar.bz2</a> or <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip">nextcloud-' . $version . '.zip</a></br>');
				$output->writeln('Check the file integrity with:</br>');
				$output->writeln('MD5: <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.tar.bz2.md5">nextcloud-' . $version . '.tar.bz2.md5</a> or <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip.md5">nextcloud-' . $version . '.zip.md5</a></br>');
				$output->writeln('SHA256: <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.tar.bz2.sha256">nextcloud-' . $version . '.tar.bz2.sha256</a> or <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip.sha256">nextcloud-' . $version . '.zip.sha256</a></br>');
				$output->writeln('SHA512: <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.tar.bz2.sha512">nextcloud-' . $version . '.tar.bz2.sha512</a> or <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip.sha512">nextcloud-' . $version . '.zip.sha512</a></br>');
				$output->writeln('PGP (<a href="https://nextcloud.com/nextcloud.asc">Key</a>): <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.tar.bz2.asc">nextcloud-' . $version . '.tar.bz2.asc</a> or <a href="https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip.asc">nextcloud-' . $version . '.zip.asc</a></p>');
				$output->writeln("");
				$output->writeln("<h4>Changes</h4>");
				$output->writeln("<ul>");
				foreach ($prTitles['closed'] as $id => $data) {
					$repoName = $data['repoName'];
					$number = $data['number'];
					$title = $data['title'];
					$output->writeln("\t<li><a href=\"https://github.com/$orgName/$repoName/pull/$number\">$title ($repoName#$number)</a></li>");
				}
				$output->writeln("</ul>");
				$count = count($prTitles['pending']);
				if ($count > 0) {
					$output->writeln("<error>$count pending PRs not printed - maybe the release is not ready yet</error>");
				}
				break;
			case 'forum':
				foreach ($prTitles['closed'] as $id => $data) {
					$repoName = $data['repoName'];
					$number = $data['number'];
					$title = $data['title'];
					$output->writeln("* [$title ($repoName#$number)](https://github.com/$orgName/$repoName/pull/$number)");
				}
				$count = count($prTitles['pending']);
				if ($count > 0) {
					$output->writeln("<error>$count pending PRs not printed - maybe the release is not ready yet</error>");
				}
				break;
			case 'markdown':
			default:
				foreach ($prTitles['closed'] as $id => $data) {
					$repoName = $data['repoName'];
					$number = $data['number'];
					$title = $data['title'];
					if ($repoName === 'server') {
						$output->writeln("* #$number");
					} else {
						$output->writeln("* $orgName/$repoName#$number");
					}
				}

				// Do we have pending PRs?
				if (count($prTitles['pending'])) {
					$output->writeln("\n\n## Pending PRs:");

					// Group PR by authors
					$pendingPRs = $prTitles['pending'];
					function cmp($a, $b) {
						return strnatcasecmp($a['author'], $b['author']);
					}
					usort($pendingPRs, "cmp");

					$prevAuthor = '';
					foreach ($pendingPRs as $id => $data) {
						$repoName = $data['repoName'];
						$number = $data['number'];
						$title = $data['title'];
						$author = array_key_exists('author', $data) ? '@' . $data['author'] : '';
						if ($author === '@backportbot-nextcloud') {
							$author = '';
						}
						if ($author === '@dependabot-preview') {
							$author = '';
						}
						if ($author === '@dependabot') {
							$author = '';
						}
						if ($author === '@dependabot[bot]') {
							$author = '';
						}
						if ($prevAuthor !== $author) {
							$output->writeln("* $author");
							$prevAuthor = $author;
						}
						if ($repoName === 'server') {
							$output->writeln("  * [ ] #$number");
						} else {
							$output->writeln("  * [ ] $orgName/$repoName#$number");
						}
					}
				}
				break;
		}

		// Stop using cache
		# TODO
		#$client->removeCache();
	}
}

$application = new Application();

$application->add(new GenerateChangelogCommand());
$application->run();
