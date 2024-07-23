<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/vendor/autoload.php';

if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
	echo "\033[0;30m\033[43m                                                                 \033[0m\n";
	echo "\033[0;30m\033[43m Run the script with --dry-run until all files can be converted. \033[0m\n";
	echo "\033[0;30m\033[43m Otherwise the author list can not be generated correctly.       \033[0m\n";
	echo "\033[0;30m\033[43m                                                                 \033[0m\n";
	echo "\n";
	die("convert.php [--dry-run] [--ignore-js-dir] <path>\n");
}

$args = $argv;
// Remove script
array_shift($args);

$isDryRun = $args[0] === '--dry-run';
if ($isDryRun) {
	// Remove option
	array_shift($args);
}
$ignoreJSDir = $args[0] === '--ignore-js-dir';
if ($ignoreJSDir) {
	// Remove option
	array_shift($args);

	// Maybe wrong order?
	if (!$isDryRun) {
		$isDryRun = $args[0] === '--dry-run';
		if ($isDryRun) {
			// Remove option
			array_shift($args);
		}
	}
}
$path = realpath($args[0]) . '/';

function abortFurtherAnalysing(): void {
	global $isDryRun;

	echo "\n\n\n";
	echo "\033[0;37m\033[41m                                                                       \033[0m\n";
	echo "\033[0;37m\033[41m                            ❌ ABORTING ❌                             \033[0m\n";
	echo "\033[0;37m\033[41m Please manually fix the error pointed out above and rerun the script. \033[0m\n";
	echo "\033[0;37m\033[41m                                                                       \033[0m\n";
	echo "\n\n\n";

	if (!$isDryRun) {
		echo "\033[0;30m\033[43m                                                                 \033[0m\n";
		echo "\033[0;30m\033[43m Run the script with --dry-run until all files can be converted. \033[0m\n";
		echo "\033[0;30m\033[43m Otherwise the author list can not be generated correctly.       \033[0m\n";
		echo "\033[0;30m\033[43m                                                                 \033[0m\n";
		echo "\n\n\n";
	}

	exit(1);
}

function generateSpdxContent(string $originalHeader, string $file): array {
	$nextcloudersCopyrightYear = 3000;
	$newHeaderLines = [];
	$authors = [];
	$license = null;

	foreach (explode("\n", $originalHeader) as $line) {
		// @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
		if (preg_match('/@copyright Copyright \(c\) (\d{4}),? ([^<]+) <([^>]+)>/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} elseif ($nextcloudersCopyrightYear > $m[1]) {
				$nextcloudersCopyrightYear = (int) $m[1];
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright 2023 Joas Schilling <coding@schilljs.com>
		} elseif (preg_match('/@copyright (\d{4}),? ([^<]+) <([^>]+)>/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} elseif ($nextcloudersCopyrightYear > $m[1]) {
				$nextcloudersCopyrightYear = (int) $m[1];
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright Copyright (c) 2023 Joas Schilling (coding@schilljs.com)
		} elseif (preg_match('/@copyright Copyright \(c\) (\d{4}),? ([^<]+) \(([^>]+)\)/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} elseif ($nextcloudersCopyrightYear > $m[1]) {
				$nextcloudersCopyrightYear = (int) $m[1];
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright Copyright (c) 2023 Joas Schilling Nextcloud GmbH, https://nextcloud.com
		} elseif (preg_match('/@copyright Copyright \(c\) (\d{4}),? ([^\n]+)/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]}";
			} elseif ($nextcloudersCopyrightYear > $m[1]) {
				$nextcloudersCopyrightYear = (int) $m[1];
			}
			$authors[] = $m[2];

		// Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
		} elseif (preg_match('/Copyright \(c\) (\d{4}),? ([^<]+) <([^>]+)>/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} elseif ($nextcloudersCopyrightYear > $m[1]) {
				$nextcloudersCopyrightYear = (int) $m[1];
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright 2023
		} elseif (preg_match('/@copyright (\d{4})/', $line, $m)) {
		} elseif (preg_match('/@author ([^\n]+)/', $line, $m)) {
			$authors[] = $m[1];
		} elseif (str_contains($line, '@license AGPL-3.0-or-later')) {
			$license = 'SPDX-License-Identifier: AGPL-3.0-or-later';
		} elseif (str_contains($line, '@license AGPL-3.0-or-later')) {
			$license = 'SPDX-License-Identifier: AGPL-3.0-or-later';
		} elseif (str_contains($line, '@license GNU AGPL version 3 or any later version')) {
			$license = 'SPDX-License-Identifier: AGPL-3.0-or-later';
		} elseif (str_contains($line, '@license AGPL-3.0')) {
			$license = 'SPDX-License-Identifier: AGPL-3.0-only';
		} elseif (str_contains($line, 'This file is licensed under the Affero General Public License version 3 or')) {
			$license = 'SPDX-License-Identifier: AGPL-3.0-or-later';
		} elseif (str_contains($line, '// GNU GPL version 3 or any later version')) {
			$license = 'SPDX-License-Identifier: GPL-3.0-or-later';
		} elseif (str_contains($line, 'it under the terms of the GNU General Public License as published by')) {
		} elseif (str_contains($line, 'it under the terms of the GNU Affero General Public License as')) {
		} elseif (str_contains($line, 'it under the terms of the GNU Afferoq General Public License as')) {
		} elseif (str_contains($line, 'it under the terms of the GNU Affero General Public License, version 3,')) {
		} elseif (str_contains($line, 'License, or (at your option) any later version.')) {
		} elseif (str_contains($line, 'GNU General Public License for more details.')) {
		} elseif (str_contains($line, 'GNU Affero General Public License for more details.')) {
		} elseif (str_contains($line, 'You should have received a copy of the GNU General Public License')) {
		} elseif (str_contains($line, 'You should have received a copy of the GNU Affero General Public License')) {
		} elseif (str_contains($line, 'the Free Software Foundation, either version 3 of the License, or')) {
		} elseif (str_contains($line, 'along with this program.  If not, see <http://www.gnu.org/licenses/>')) {
		} elseif (str_contains($line, 'along with this program. If not, see <http://www.gnu.org/licenses/>')) {
		} elseif (str_contains(strtolower($line), 'license')) {
			echo " ├─ ❌ \033[0;31m" . $file . ' Unrecognized license:' . "\033[0m\n";
			echo '    └─ ' . $line . "\n";
			abortFurtherAnalysing();
		} elseif (str_contains(strtolower($line), 'copyright')) {
			echo " ├─ ❌ \033[0;31m" . $file . ' Unrecognized copyright:' . "\033[0m\n";
			echo '    └─ ' . $line . "\n";
			abortFurtherAnalysing();
		}
	}

	if ($nextcloudersCopyrightYear !== 3000) {
		array_unshift($newHeaderLines, "SPDX-FileCopyrightText: $nextcloudersCopyrightYear Nextcloud GmbH and Nextcloud contributors");
	}

	if ($license === null) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No license found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$newHeaderLines = array_unique($newHeaderLines);
	$newHeaderLines[] = $license;

	return [$authors, $newHeaderLines];
}

function replacePhpOrCSSCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '/*') ? 0 : strpos($content, "\n/*");
	if ($headerStart === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$headerEnd = strpos($content, '*/', $headerStart);
	if ($headerEnd === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment END found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('*/'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo " ├─ ✅ \033[0;32m" . $file . ' SPDX' . "\033[0m\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSPDXcontent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "/**\n * " . implode("\n * ", $newHeaderLines) . "\n */";

	if ($isDryRun) {
		echo " ├─ ☑️  \033[0;36m" . $file . ' would replace' . "\033[0m\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo " ├─ ☑️  \033[0;36m" . $file . ' replaced' . "\033[0m\n";
	}
	return $authors;
}

function replaceJavaScriptCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '/*') ? 0 : strpos($content, "\n/*");
	if ($headerStart === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$headerEnd = strpos($content, '*/', $headerStart);
	if ($headerEnd === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment END found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('*/'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo " ├─ ✅ \033[0;32m" . $file . ' SPDX' . "\033[0m\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSpdxContent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "/**\n * " . implode("\n * ", $newHeaderLines) . "\n */";

	if ($isDryRun) {
		echo " ├─ ☑️  \033[0;36m" . $file . ' would replace' . "\033[0m\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo " ├─ ☑️  \033[0;36m" . $file . ' replaced' . "\033[0m\n";
	}
	return $authors;
}

function replaceVueCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '<!--') ? 0 : strpos($content, "\n<!--");
	if ($headerStart === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$headerEnd = strpos($content, '-->', $headerStart);
	if ($headerEnd === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment END found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('-->'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo " ├─ ✅ \033[0;32m" . $file . ' SPDX' . "\033[0m\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSpdxContent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "<!--\n  - " . implode("\n  - ", $newHeaderLines) . "\n-->";

	if ($isDryRun) {
		echo " ├─ ☑️  \033[0;36m" . $file . ' would replace' . "\033[0m\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo " ├─ ☑️  \033[0;36m" . $file . ' replaced' . "\033[0m\n";
	}
	return $authors;
}

function replaceSwiftCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '//') ? 0 : strpos($content, "\n//");
	if ($headerStart === false) {
		echo " ├─ ❌ \033[0;31m" . $file . ' No header comment found' . "\033[0m\n";
		abortFurtherAnalysing();
	}

	$headerEndToken = "import";
	$headerEnd = strpos($content, $headerEndToken, $headerStart);
	if ($headerEnd === false) {
		$headerEndToken = "extension";
		$headerEnd = strpos($content, $headerEndToken, $headerStart);
		if ($headerEnd === false) {
			$headerEndToken = "protocol";
			$headerEnd = strpos($content, $headerEndToken, $headerStart);
			if ($headerEnd === false) {
				$headerEndToken = "class";
				$headerEnd = strpos($content, $headerEndToken, $headerStart);
				if ($headerEnd === false) {
					echo " ├─ ❌ \033[0;31m" . $file . ' No header comment END found' . "\033[0m\n";
					abortFurtherAnalysing();
				}
			}
		}
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen($headerEndToken));
	if (str_contains($originalHeader, 'SPDX')) {
		echo " ├─ ✅ \033[0;32m" . $file . ' SPDX' . "\033[0m\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSpdxContent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "//\n// " . implode("\n// ", $newHeaderLines) . "\n//\n\n" . $headerEndToken;

	if ($isDryRun) {
		echo " ├─ ☑️  \033[0;36m" . $file . ' would replace' . "\033[0m\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo " ├─ ☑️  \033[0;36m" . $file . ' replaced' . "\033[0m\n";
	}
	return $authors;
}

$finder = new \Symfony\Component\Finder\Finder();
$finder->ignoreVCSIgnored(true)
	->sortByName();

$exclude = [];
if (file_exists($path . '.reuse/dep5')) {
	$dep5 = file_get_contents($path . '.reuse/dep5');
	$lines = explode("\n", $dep5);
	$lines = array_filter($lines, static fn(string $line) => str_starts_with($line, 'Files: '));

	foreach ($lines as $line) {
		$line = preg_replace('/\s+/', ' ', trim($line));
		$files = explode(' ', $line);
		array_shift($files);

		foreach ($files as $file) {
			$pathFilter = $file;
			if (str_contains($file, '*')) {
				$pathFilter = '/'. str_replace(['/', '.', '*'], ['\/', '\.', '(.+)'], $file) . '$/i';
			}
			$finder->notPath($pathFilter);
		}
	}
}
$finder->in($path);

$notHandled = '';
$authors = [];
foreach ($finder->getIterator() as $file) {
	if ($ignoreJSDir && str_starts_with($file->getRealPath(), $path . 'js/')) {
		echo ' ├─ ◽ ' . $file->getRealPath() . ' skipped' . "\n";
		continue;
	}
	if (str_starts_with($file->getRealPath(), $path . 'l10n/')) {
		echo ' ├─ ◽ ' . $file->getRealPath() . ' skipped' . "\n";
		continue;
	}

	if ($file->getExtension() === 'php' || $file->getExtension() === 'css' || $file->getExtension() === 'scss') {
		if (!str_contains($file->getRealPath(), '/lib/Vendor/')
			&& !str_contains($file->getRealPath(), '/vendor/')
			&& !str_contains($file->getRealPath(), '/tests/stubs/')) {
			$authors[] = replacePhpOrCSSCopyright($file->getRealPath(), $isDryRun);
		} else {
			echo " ├─ 🔶 \033[0;33m" . $file->getRealPath() . ' skipped' . "\033[0m\n";
		}
	} elseif (preg_match('/^[mc]?[tj]s$/', $file->getExtension())) {
		if (
			!str_contains($file->getRealPath(), '/vendor/')
		) {
			$authors[] = replaceJavaScriptCopyright($file->getRealPath(), $isDryRun);
		} else {
			echo " ├─ 🔶 \033[0;33m" . $file->getRealPath() . ' skipped' . "\033[0m\n";
		}
	} elseif ($file->getExtension() === 'vue' || $file->getExtension() === 'html') {
		if (
			!str_contains($file->getRealPath(), '/vendor/')
		) {
			$authors[] = replaceVueCopyright($file->getRealPath(), $isDryRun);
		} else {
			echo " ├─ 🔶 \033[0;33m" . $file->getRealPath() . ' skipped' . "\033[0m\n";
		}
	} elseif ($file->getExtension() === 'swift') {
		$authors[] = replaceSwiftCopyright($file->getRealPath(), $isDryRun);
	} elseif (!$file->isDir()) {
		if (
			str_ends_with($file->getRealPath(), 'composer.json')
			|| str_ends_with($file->getRealPath(), 'composer.lock')
			|| str_ends_with($file->getRealPath(), '.md')
			|| str_ends_with($file->getRealPath(), '.png')
			|| str_ends_with($file->getRealPath(), '.svg')
			|| str_ends_with($file->getRealPath(), '.xml')
			|| str_ends_with($file->getRealPath(), '.json')
		) {
			echo ' ├─ ◽ ' . $file->getRealPath() . ' skipped' . "\n";
		} elseif (
			!str_contains($file->getRealPath(), '/tests/integration/vendor/')
			&& !(str_starts_with($file->getRealPath(), $path . 'l10n/') && str_ends_with($file->getRealPath(), '.json'))
			&& !str_contains($file->getRealPath(), '/tests/integration/phpserver.log')
			&& !str_contains($file->getRealPath(), '/tests/integration/phpserver_fed.log')
		) {
			$notHandled .= " ├─ ❌ \033[0;31m" . $file . ' Not handled' . "\033[0m\n";
		}
	}
}

echo $notHandled;

$authorList = array_merge(...$authors);
sort($authorList);
$authorList = array_unique($authorList);

$authorsContent = "# Authors\n\n- " . implode("\n- ", $authorList) . "\n";
if ($isDryRun) {
	echo " └─ ✅ \033[0;32mCan generate AUTHORS.md" . "\033[0m\n\n";
	echo $authorsContent;
} else {
	file_put_contents($path . 'AUTHORS.md', $authorsContent, FILE_APPEND);
	echo " └─ ✅ \033[0;32mAppended AUTHORS.md" . "\033[0m\n";
}
