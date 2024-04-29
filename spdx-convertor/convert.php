<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Joas Schilling <coding@schilljs.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/vendor/autoload.php';

if ($argc < 2 || in_array('--help', $argv) || in_array('-h', $argv)) {
	die("convert.php [--dry-run] <path>\n");
}

$isDryRun = $argv[1] === '--dry-run';
$path = rtrim($isDryRun ? $argv[2] : $argv[1], '/') . '/';

function generateSpdxContent(string $originalHeader, string $file): array {
	$newHeaderLines = [];
	$authors = [];
	$license = null;

	foreach (explode("\n", $originalHeader) as $line) {
		// @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
		if (preg_match('/@copyright Copyright \(c\) (\d+),? ([^<]+) <([^>]+)>/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} else {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} Nextcloud GmbH and Nextcloud contributors";
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright 2023 Joas Schilling <coding@schilljs.com>
		} elseif (preg_match('/@copyright (\d+),? ([^<]+) <([^>]+)>/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} else {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} Nextcloud GmbH and Nextcloud contributors";
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright Copyright (c) 2023 Joas Schilling (coding@schilljs.com)
		} elseif (preg_match('/@copyright Copyright \(c\) (\d+),? ([^<]+) \(([^>]+)\)/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]} <{$m[3]}>";
			} else {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} Nextcloud GmbH and Nextcloud contributors";
			}
			$authors[] = "{$m[2]} <{$m[3]}>";

		// @copyright Copyright (c) 2023 Joas Schilling Nextcloud GmbH, https://nextcloud.com
		} elseif (preg_match('/@copyright Copyright \(c\) (\d+),? ([^\n]+)/', $line, $m)) {
			if (str_contains(strtolower($m[2]), 'owncloud')) {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} {$m[2]}";
			} else {
				$newHeaderLines[] = "SPDX-FileCopyrightText: {$m[1]} Nextcloud GmbH and Nextcloud contributors";
			}
			$authors[] = $m[2];

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
		} elseif (str_contains($line, 'it under the terms of the GNU Affero General Public License as')) {
		} elseif (str_contains($line, 'it under the terms of the GNU Afferoq General Public License as')) {
		} elseif (str_contains($line, 'it under the terms of the GNU Affero General Public License, version 3,')) {
		} elseif (str_contains($line, 'License, or (at your option) any later version.')) {
		} elseif (str_contains($line, 'GNU Affero General Public License for more details.')) {
		} elseif (str_contains($line, 'You should have received a copy of the GNU Affero General Public License')) {
		} elseif (str_contains($line, 'the Free Software Foundation, either version 3 of the License, or')) {
		} elseif (str_contains($line, 'along with this program.  If not, see <http://www.gnu.org/licenses/>')) {
		} elseif (str_contains($line, 'along with this program. If not, see <http://www.gnu.org/licenses/>')) {
		} elseif (str_contains(strtolower($line), 'license')) {
			echo ' ├─ ❌ ' . $file . ' Unrecognized license:' . "\n";
			echo '    └─ ' . $line . "\n";
			exit(1);
		} elseif (str_contains(strtolower($line), 'copyright')) {
			echo ' ├─ ❌ ' . $file . ' Unrecognized copyright:' . "\n";
			echo '    └─ ' . $line . "\n";
			exit(1);
		}
	}

	if ($license === null) {
		echo ' ├─ ❌ ' . $file . ' No license found' . "\n";
		exit(1);
	}

	$newHeaderLines = array_unique($newHeaderLines);
	$newHeaderLines[] = $license;

	return [$authors, $newHeaderLines];
}

function replacePhpOrCSSCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '/*') ? 0 : strpos($content, "\n/*");
	if ($headerStart === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment found' . "\n";
		exit(1);
	}

	$headerEnd = strpos($content, '*/', $headerStart);
	if ($headerEnd === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment END found' . "\n";
		exit(1);
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('*/'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo ' ├─ 🔸 ' . $file . ' skipped' . "\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSPDXcontent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "/**\n * " . implode("\n * ", $newHeaderLines) . "\n */";

	if ($isDryRun) {
		echo ' ├─ ✅ ' . $file . ' OK' . "\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo ' ├─ ✅ ' . $file . ' replaced' . "\n";
	}
	return $authors;
}

function replaceJavaScriptCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '/*') ? 0 : strpos($content, "\n/*");
	if ($headerStart === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment found' . "\n";
		exit(1);
	}

	$headerEnd = strpos($content, '*/', $headerStart);
	if ($headerEnd === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment END found' . "\n";
		exit(1);
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('*/'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo ' ├─ 🔸 ' . $file . ' skipped' . "\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSpdxContent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "/**\n * " . implode("\n * ", $newHeaderLines) . "\n */";

	if ($isDryRun) {
		echo ' ├─ ✅ ' . $file . ' OK' . "\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo ' ├─ ✅ ' . $file . ' replaced' . "\n";
	}
	return $authors;
}

function replaceVueCopyright(string $file, bool $isDryRun): array {
	$content = file_get_contents($file);

	$headerStart = str_starts_with($content, '<!--') ? 0 : strpos($content, "\n<!--");
	if ($headerStart === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment found' . "\n";
		exit(1);
	}

	$headerEnd = strpos($content, '-->', $headerStart);
	if ($headerEnd === false) {
		echo ' ├─ ❌ ' . $file . ' No header comment END found' . "\n";
		exit(1);
	}

	$originalHeader = substr($content, $headerStart, $headerEnd - $headerStart + strlen('-->'));
	if (str_contains($originalHeader, 'SPDX')) {
		echo ' ├─ 🔸 ' . $file . ' skipped' . "\n";
		return [];
	}

	[$authors, $newHeaderLines] = generateSpdxContent($originalHeader, $file);
	$newHeader = (($headerStart === 0) ? '' : "\n") . "<!--\n  - " . implode("\n  - ", $newHeaderLines) . "\n-->";

	if ($isDryRun) {
		echo ' ├─ ✅ ' . $file . ' OK' . "\n";
	} else {
		file_put_contents(
			$file,
			str_replace($originalHeader, $newHeader, $content)
		);
		echo ' ├─ ✅ ' . $file . ' replaced' . "\n";
	}
	return $authors;
}

$finder = new \Symfony\Component\Finder\Finder();
$finder->ignoreVCSIgnored(true)
	->sortByName();

$exclude = [];
if (file_exists($path . '/.reuse/dep5')) {
	$dep5 = file_get_contents($path . '/.reuse/dep5');
	$lines = explode("\n", $dep5);
	$lines = array_filter($lines, static fn(string $line) => str_starts_with($line, 'Files: '));

	foreach ($lines as $line) {
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
	if ($file->getExtension() === 'php' || $file->getExtension() === 'css' || $file->getExtension() === 'scss') {
		if (!str_contains($file->getRealPath(), '/lib/Vendor/')
			&& !str_contains($file->getRealPath(), '/vendor/')
			&& !str_contains($file->getRealPath(), '/tests/stubs/')) {
			$authors[] = replacePhpOrCSSCopyright($file->getRealPath(), $isDryRun);
		}
	} elseif ($file->getExtension() === 'js' || $file->getExtension() === 'ts') {
		if (
			!str_contains($file->getRealPath(), '/vendor/')
		) {
			$authors[] = replaceJavaScriptCopyright($file->getRealPath(), $isDryRun);
		}
	} elseif ($file->getExtension() === 'vue') {
		if (
			!str_contains($file->getRealPath(), '/vendor/')
		) {
			$authors[] = replaceVueCopyright($file->getRealPath(), $isDryRun);
		}
	} elseif (!$file->isDir()) {
		if (
			!str_contains($file->getRealPath(), '/tests/integration/vendor/')
			&& !str_contains($file->getRealPath(), '/tests/integration/phpserver.log')
			&& !str_contains($file->getRealPath(), '/tests/integration/phpserver_fed.log')
		) {
			$notHandled .= ' ├─ ❌ ' . $file . ' Not handled' . "\n";
		}
	}
}

echo $notHandled;

$authorList = array_merge(...$authors);
sort($authorList);
$authorList = array_unique($authorList);

$authorsContent = "# Authors\n\n- " . implode("\n- ", $authorList) . "\n";
file_put_contents($path . 'AUTHORS.md', $authorsContent, FILE_APPEND);
echo ' └─ ✅ Appended AUTHORS.md' . "\n";
