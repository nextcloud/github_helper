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
	'nextcloud/bruteforcesettings',
	'nextcloud/circles',
	'nextcloud/documentation',
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

foreach($repositories as $repo) {
	$name = explode('/', $repo)[1];
	$SSH_OPTIONS = '';
	if ($name === 'support' && gethostname() === 'client-builder') {
		$SSH_OPTIONS = "GIT_SSH_COMMAND='ssh -i ~/.ssh/id_rsa.support-app -o IdentitiesOnly=yes'";
	}
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && ' . $SSH_OPTIONS . ' git clone git@github.com:' . $repo);
	// Checkout the new branch
	shell_exec('cd ' . __DIR__ . '/'. $name . ' && git checkout -b ' . $branch);
	// Push the branch
	shell_exec('cd ' . __DIR__ . '/' . $name . ' && ' . $SSH_OPTIONS . ' git push origin ' . $branch);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $name);
}
