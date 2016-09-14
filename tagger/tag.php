<?php 

$branch = 'stable10';
$tag = 'v10.0.1RC1';

$repositories = [
	'server',
	'3rdparty',
	'apps',
	'files_pdfviewer',
	'files_texteditor',
	'files_videoplayer',
	'gallery',
	'apps',
	'firstrunwizard',
	'activity',
	'templateeditor',
	'notifications',
	'password_policy',
	'user_saml',
	'files_accesscontrol',
	'files_automatedtagging',
	'files_retention',
	'serverinfo',
	'survey_client',
	'example-files',
];

foreach($repositories as $repo) {
	// Clone the repository
	shell_exec('cd ' . __DIR__ . ' && git clone https://github.com/nextcloud/' . $repo . '.git');
	// Checkout the required branch
	shell_exec('cd ' . __DIR__ . '/'. $repo . ' && git checkout ' . $branch);
	// Create a signed tag
	shell_exec('cd ' . __DIR__ . '/' . $repo . ' && git tag -s ' . $tag . ' -m \'' . $tag . '\'');
	// Push the signed tag
	shell_exec('cd ' . __DIR__ . '/' . $repo . ' && git push origin ' . $tag);
}
