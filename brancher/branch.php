<?php
if(count($argv) !== 2) {
	die("branch.php \$branchname\n");
}
$branch = $argv[1];

// keep them in sync with the ones from tagger/tag.php
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

foreach($repositories as $repo) {
	$name = explode('/', $repo)[1];
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && git clone git@github.com:' . $repo);
	// Checkout the new branch
	shell_exec('cd ' . __DIR__ . '/'. $name . ' && git checkout -b ' . $branch);
	// Push the branch
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && git push origin ' . $branch);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $name);
}
