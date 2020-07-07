<?php
if(count($argv) !== 2) {
	die("branch.php \$branchname\n");
}
$branch = $argv[1];

// keep them in sync with the ones from tagger/tag.php
$repositories = [
	'server',
	'3rdparty',
	'activity',
	'apps',
	'example-files',
	'files_pdfviewer',
	'files_rightclick',
	'files_texteditor',
	'files_videoplayer',
	'firstrunwizard',
	'gallery',
	'logreader',
	'nextcloud_announcements',
	'notifications',
	'password_policy',
	'privacy',
	'recommendations',
	'serverinfo',
	'survey_client',
	'updater',
	'viewer',
	'photos',
	'text',
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
