<?php 
if(count($argv) !== 3) {
	die("tag.php \$branchname \$tag\n");
}
$branch = $argv[1];
$tag = $argv[2];

switch($branch) {
	case 'stable17':
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
			'nextcloud/gallery',
			'nextcloud/logreader',
			'nextcloud/nextcloud_announcements',
			'nextcloud/notifications',
			'nextcloud/password_policy',
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
	case 'stable18':
	case 'stable19':
	case 'master':
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
	default:
		die("Branch not found :(\n");
}

foreach($repositories as $repo) {
	$name = explode('/', $repo)[1];
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && git clone git@github.com:' . $repo . '.git');
	// Checkout the required branch
	shell_exec('cd ' . __DIR__ . '/'. $name . ' && git checkout ' . $branch);
	// Create a signed tag
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && git tag -s ' . $tag . ' -m \'' . $tag . '\'');
	// Push the signed tag
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && git push origin ' . $tag);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $name);
}
