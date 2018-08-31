<?php
if(count($argv) !== 2) {
	die("branch.php \$branchname\n");
}
$branch = $argv[1];

// keep them in sync with the ones from tagger/tag.php
$repositories = [
	'server',
	'3rdparty',
	'apps',
	'files_pdfviewer',
	'files_texteditor',
	'files_videoplayer',
	'gallery',
	'firstrunwizard',
	'activity',
	'notifications',
	'password_policy',
	'serverinfo',
	'survey_client',
	'example-files',
	'logreader',
	'updater',
	'nextcloud_announcements',
];

foreach($repositories as $repo) {
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && git clone git@github.com:nextcloud/' . $repo);
	// Checkout the new branch
	shell_exec('cd ' . __DIR__ . '/'. $repo . ' && git checkout -b ' . $branch);
	// Push the branch
	shell_exec('cd ' . __DIR__ . '/' . $repo . ' && git push origin ' . $branch);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $repo);
}
