<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Smoke tests guarding against breaking changes in Symfony Console (and other
 * dependencies) that only surface when the CLI is actually bootstrapped.
 *
 * Requiring index.php declares GenerateChangelogCommand, which fails at
 * declaration time if configure()/execute() no longer match the parent
 * signatures. Registering it on an Application exercises the command
 * registration API (Application::add() was removed in favour of addCommand()
 * in Symfony Console 8). These are exactly the failures that reached a release
 * run because nothing here was executed in CI.
 */
final class GenerateChangelogCommandTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		// The bootstrap at the bottom of index.php is guarded so requiring the
		// file only declares the command class instead of running the app.
		require_once dirname(__DIR__) . '/index.php';
	}

	public function testCommandClassIsDeclared(): void
	{
		$this->assertTrue(
			class_exists(GenerateChangelogCommand::class),
			'GenerateChangelogCommand must be declared by index.php',
		);
		$this->assertInstanceOf(Command::class, new GenerateChangelogCommand());
	}

	public function testCommandCanBeRegisteredAndResolved(): void
	{
		$application = new Application();
		$application->addCommand(new GenerateChangelogCommand());

		$command = $application->find('generate:changelog');
		$this->assertInstanceOf(GenerateChangelogCommand::class, $command);
	}

	/**
	 * Runs the real entrypoint the release workflow invokes. This is the only
	 * test that exercises index.php's own bootstrap (Application creation and
	 * command registration), so it catches a removed/renamed Console API used
	 * there, not just in this test's own setup.
	 */
	public function testEntrypointBootstrapsWithoutFatalError(): void
	{
		$index = escapeshellarg(dirname(__DIR__) . '/index.php');
		exec(PHP_BINARY . " $index generate:changelog --help 2>&1", $output, $exitCode);
		$out = implode("\n", $output);

		$this->assertSame(0, $exitCode, "index.php exited non-zero:\n$out");
		$this->assertStringContainsString('generate:changelog', $out);
	}

	public function testCommandDefinitionMatchesConfigure(): void
	{
		$command = new GenerateChangelogCommand();
		$definition = $command->getDefinition();

		foreach (['repo', 'base', 'head'] as $argument) {
			$this->assertTrue(
				$definition->hasArgument($argument),
				"Missing expected argument: $argument",
			);
		}

		foreach (['format', 'no-bots', 'skip-label', 'skip-drafts'] as $option) {
			$this->assertTrue(
				$definition->hasOption($option),
				"Missing expected option: $option",
			);
		}
	}
}
