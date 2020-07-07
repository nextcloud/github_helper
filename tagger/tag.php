<?php 
if(count($argv) !== 3) {
	die("tag.php \$branchname \$tag\n");
}
$branch = $argv[1];
$tag = $argv[2];

switch($branch) {
	case 'stable16':
	case 'stable17':
		// keep them in sync with the ones from brancher/branch.php
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
			'recommendations',
			'viewer',
			'privacy',
		];
		break;
	case 'stable18':
	case 'stable19':
	case 'master':
		// keep them in sync with the ones from brancher/branch.php
		$repositories = [
			'server',
			'3rdparty',
			'apps',
			'files_pdfviewer',
			'files_texteditor',
			'files_videoplayer',
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
			'recommendations',
			'viewer',
			'privacy',
			'photos',
			'text',
		];
		break;
	default:
		die("Branch not found :(\n");
}

foreach($repositories as $repo) {
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && git clone git@github.com:nextcloud/' . $repo . '.git');
	// Checkout the required branch
	shell_exec('cd ' . __DIR__ . '/'. $repo . ' && git checkout ' . $branch);
	// Create a signed tag
	shell_exec('cd ' . __DIR__ . '/' . $repo . ' && git tag -s ' . $tag . ' -m \'' . $tag . '\'');
	// Push the signed tag
	shell_exec('cd ' . __DIR__ . '/' . $repo . ' && git push origin ' . $tag);
	// Delete repository
	shell_exec('cd ' . __DIR__ . ' && rm -rf ' . $repo);
}
