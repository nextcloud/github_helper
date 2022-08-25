<?php
if(count($argv) !== 3) {
	die("tag.php \$branchname \$tag\n");
}
$branch = $argv[1];
$tag = $argv[2];

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
	case 'master':
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
	default:
		die("Branch not found :(\n");
}

foreach($repositories as $repo) {
	$name = explode('/', $repo)[1];
	$SSH_OPTIONS = '';
	if ($name === 'support' && gethostname() === 'client-builder') {
		$SSH_OPTIONS = "GIT_SSH_COMMAND='ssh -i ~/.ssh/id_rsa.support-app -o IdentitiesOnly=yes'";
	}
	// Clone the repository and checkout the required branch
	shell_exec('cd ' . __DIR__ . ' && ' . $SSH_OPTIONS . ' git clone --depth=1 --branch="' . $branch . '" git@github.com:' . $repo);
	// Create a signed tag
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && git tag -s ' . $tag . ' -m \'' . $tag . '\'');
	// Push the signed tag
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && ' . $SSH_OPTIONS . ' git push origin ' . $tag);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $name);
}
