<?php
if(count($argv) < 3) {
	die("tag.php \$branchname \$tag [\$historic_date]\n");
}
$branch = $argv[1];
$tag = $argv[2];

// to the rescue, when tagging a release was forgotten
// call it with the date of the release tar ball. This can be received from the download server via, e.g.
//   ls --full-time /var/www/html/server/prereleases | grep 25.0.3rc1
// or use the datetime of the merge commit of the version bump PR
$historic = null;
if (isset($argv[3])) {
	if (1 !== preg_match('/^[0-9]{4}-[0-1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9] UTC$/', $argv[3])) {
		die("Invalid historic date $argv[3], expected format: '2023-01-21 09:42:23 UTC', UTC is required");
	}
	$historic = $argv[3];
}

switch($branch) {
	case 'stable19':
	case 'stable20':
	case 'stable21':
		// keep them in sync with the ones from brancher/branch.php
		$repositories = [
			'nextcloud/server',
			'nextcloud/3rdparty',
			'nextcloud/activity',
			'nextcloud/example-files',
			'nextcloud/files_pdfviewer',
			'nextcloud/files_rightclick',
			'nextcloud/files_videoplayer',
			'nextcloud/firstrunwizard',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
			'nextcloud/photos',
			'nextcloud/privacy',
			'nextcloud/recommendations',
			'nextcloud/serverinfo',
			'nextcloud/survey_client',
			'nextcloud/text',
			'nextcloud/updater',
			'nextcloud/viewer',
			'nextcloud-gmbh/support',
		];
		break;
	case 'stable22':
	case 'stable23':
	case 'stable24':
		// keep them in sync with the ones from brancher/branch.php
		$repositories = [
			'nextcloud/server',
			'nextcloud/3rdparty',
			'nextcloud/activity',
			'nextcloud/circles',
			'nextcloud/example-files',
			'nextcloud/files_pdfviewer',
			'nextcloud/files_rightclick',
			'nextcloud/files_videoplayer',
			'nextcloud/firstrunwizard',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
			'nextcloud/photos',
			'nextcloud/privacy',
			'nextcloud/recommendations',
			'nextcloud/serverinfo',
			'nextcloud/survey_client',
			'nextcloud/text',
			'nextcloud/updater',
			'nextcloud/viewer',
			'nextcloud-gmbh/support',
		];
		break;
	case 'stable25':
	case 'stable26':
	case 'stable27':
		$repositories = [
			'nextcloud/server',
			'nextcloud/3rdparty',
			'nextcloud/activity',
			'nextcloud/bruteforcesettings',
			'nextcloud/circles',
			'nextcloud/example-files',
			'nextcloud/files_pdfviewer',
			'nextcloud/files_rightclick',
			'nextcloud/firstrunwizard',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
			'nextcloud/photos',
			'nextcloud/privacy',
			'nextcloud/recommendations',
			'nextcloud/related_resources',
			'nextcloud/serverinfo',
			'nextcloud/survey_client',
			'nextcloud/suspicious_login',
			'nextcloud/text',
			'nextcloud/twofactor_totp',
			'nextcloud/updater',
			'nextcloud/viewer',
			'nextcloud-gmbh/support',
		];
		break;
	case 'stable28':
		$repositories = [
			'nextcloud/server',
			'nextcloud/3rdparty',
			'nextcloud/activity',
			'nextcloud/bruteforcesettings',
			'nextcloud/circles',
			'nextcloud/example-files',
			'nextcloud/files_pdfviewer',
			'nextcloud/firstrunwizard',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
			'nextcloud/photos',
			'nextcloud/privacy',
			'nextcloud/recommendations',
			'nextcloud/related_resources',
			'nextcloud/serverinfo',
			'nextcloud/survey_client',
			'nextcloud/suspicious_login',
			'nextcloud/text',
			'nextcloud/twofactor_totp',
			'nextcloud/updater',
			'nextcloud/viewer',
			'nextcloud-gmbh/support',
		];
		break;
	case 'stable29':
	case 'stable30':
	case 'master':
		$repositories = [
			'nextcloud/server',
			'nextcloud/3rdparty',
			'nextcloud/activity',
			'nextcloud/bruteforcesettings',
			'nextcloud/circles',
			'nextcloud/example-files',
			'nextcloud/files_downloadlimit',
			'nextcloud/files_pdfviewer',
			'nextcloud/firstrunwizard',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
			'nextcloud/photos',
			'nextcloud/privacy',
			'nextcloud/recommendations',
			'nextcloud/related_resources',
			'nextcloud/serverinfo',
			'nextcloud/survey_client',
			'nextcloud/suspicious_login',
			'nextcloud/text',
			'nextcloud/twofactor_nextcloud_notification',
			'nextcloud/twofactor_totp',
			'nextcloud/updater',
			'nextcloud/viewer',
			'nextcloud-gmbh/support',
		];
		break;
	default:
		die("Branch not found :(\n");
}

// use proper temp location on dev machines, assuming it's memdisc, to not wear out physical storage
$workDir = gethostname() === 'client-builder' ? __DIR__ : trim(shell_exec('mktemp -d'));
fwrite(STDERR, '[Debug] Work dir is: ' . $workDir . PHP_EOL);

// for historic commits we need to pull some more history to be able to find the relevant commit
$depthMode = '--depth=1';
$committerDate = '';
if ($historic) {
	$historicDateTime = new DateTime($historic);
	$sixWeeks = new DateInterval('P6W');
	$historicDateTime->sub($sixWeeks);
	$depthMode = sprintf('--shallow-since="%s"', $historicDateTime->format('Y-m-d H:i:s e'));
	unset($historicDateTime);
	unset($sixWeeks);
}

foreach($repositories as $repo) {
	$name = explode('/', $repo)[1];
	$SSH_OPTIONS = '';
	if ($name === 'support' && gethostname() === 'client-builder') {
		$SSH_OPTIONS = "GIT_SSH_COMMAND='ssh -i ~/.ssh/id_rsa.support-app -o IdentitiesOnly=yes'";
	}
	// Clone the repository and checkout the required branch
	fwrite(STDERR, '[Debug] cd ' . $workDir . ' && ' . $SSH_OPTIONS . ' git clone ' . $depthMode . ' --branch="' . $branch . '" git@github.com:' . $repo . PHP_EOL);
	shell_exec('cd ' . $workDir . ' && ' . $SSH_OPTIONS . ' git clone ' . $depthMode . ' --branch="' . $branch . '" git@github.com:' . $repo);

	// checkout historic commit if applicable
	if ($historic) {
		if (!is_dir($workDir . '/' . $name)) {
			// we end up here, with a failed clone, when there were no commits in our time range. We redo with depth=42.
			// 42 for good luck. 1 Might bring too new commits.
			shell_exec('cd ' . $workDir . ' && ' . $SSH_OPTIONS . ' git clone --depth=42 --branch="' . $branch . '" git@github.com:' . $repo);
		}

		$commitHash = trim(shell_exec('cd ' . $workDir . '/' . $name . ' && git log -n1 --format=%H --until="' . $historic . '"'));
		if ($commitHash === "") {
			shell_exec('cd ' . $workDir . ' && rm -rf ' . $name);
			// we end up here, when there were no old enough commits in our time range! We redo with depth=42.
			// 42 for good luck.
			shell_exec('cd ' . $workDir . ' && ' . $SSH_OPTIONS . ' git clone --depth=42 --branch="' . $branch . '" git@github.com:' . $repo);
			$commitHash = trim(shell_exec('cd ' . $workDir . '/' . $name . ' && git log -n1 --format=%H --until="' . $historic . '"'));
		}
		if (strlen($commitHash) !== 40) {
			fwrite(STDERR, '[Error] unexpected commit length, aborting. Hash was ' . $commitHash . PHP_EOL);
			exit(1);
		}
		shell_exec('cd ' . $workDir . '/' . $name . '/$repo && git checkout ' . $commitHash);

		// use the date of the commit
		if ($committerDate === '') {
			// utilize commit date of the latest server commit, which should be the merge commit of the version bump.
			// Requires that server repo is always tagged first!
			$commitDate = trim(shell_exec('cd ' . $workDir . '/' . $name . ' && git show --format=%aD | head -1'));
			$committerDate = sprintf('GIT_COMMITTER_DATE="%s"', $commitDate);
		}
	}
	// Create a signed tag
	shell_exec('cd ' . $workDir . '/' . $name . ' && ' . $committerDate . ' git tag -s ' . $tag . ' -m \'' . $tag . '\'');
	// Push the signed tag
	shell_exec('cd ' . $workDir . '/' . $name . ' && ' . $SSH_OPTIONS . ' git push origin ' . $tag);
	// Delete repository
	shell_exec('cd ' . $workDir . ' && rm -rf ' . $name);
}
