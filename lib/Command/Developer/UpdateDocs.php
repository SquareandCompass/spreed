<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Command\Developer;

use OC\Core\Command\Base;
use OCP\App\IAppManager;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateDocs extends Base {
	private IConfig $config;
	private IAppManager $appManager;
	private array $sections = [];

	public function __construct(IConfig $config) {
		$this->config = $config;

		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	protected function configure(): void {
		$this
			->setName('talk:developer:update-docs')
			->setDescription('Update documentation of commands')
			->addArgument(
				'name',
				InputArgument::OPTIONAL,
				"The name of command to update. When have this argument, don't will upate the documentation, only will print the markdown output."
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->appManager = \OC::$server->get(IAppManager::class);

		$info = $this->appManager->getAppInfo('spreed');
		$commandName = $input->getArgument('name');
		$documentation = '';
		foreach ($info['commands'] as $namespace) {
			$command = $this->getCommand($namespace);
			if ($commandName && $commandName !== $command->getName()) {
				continue;
			}
			$this->sections['documentation'][] = $this->getDocumentation($command);
		}

		if ($commandName) {
			$output->writeln(implode("\n", $this->sections['documentation']));
		} else {
			$documentation =
				"# Talk occ commands\n\n" .
				implode("\n", $this->sections['documentation']);

			$handle = fopen(__DIR__ . '/../../../docs/occ.md', 'w');
			fwrite($handle, $documentation);
			fclose($handle);
		}
		return 0;
	}

	protected function getCommand(string $namespace): Command {
		$command = \OC::$server->get($namespace);
		// Clean full definition of command that have the default Symfony options
		$command->setApplication($this->getApplication());
		return $command;
	}

	protected function getDocumentation(Command $command): string {
		$doc = '## ' . $command->getName() . "\n\n";
		$doc .= $command->getDescription() . "\n\n";
		$doc .=
			'### Usage' . "\n\n" .
			array_reduce(
				array_merge(
					[$command->getSynopsis()],
					$command->getAliases(),
					$command->getUsages()
				),
				function ($carry, $usage) {
					return $carry.'* `'.$usage.'`'."\n";
				}
			) . "\n";
		$doc .= $this->describeInputDefinition($command);
		$doc .= "\n";

		return $doc;
	}

	protected function describeInputDefinition(Command $command): string {
		$definition = $command->getDefinition();
		$text = '';
		if ($showArguments = \count($definition->getArguments()) > 0) {
			$text .= "\n";
			$text .= "| Arguments | Description | Is required | Is array | Default |\n";
			$text .= '|---|---|---|---|---|';
			foreach ($definition->getArguments() as $argument) {
				$text .= "\n";
				if (null !== $describeInputArgument = $this->describeInputArgument($argument)) {
					$text .= $describeInputArgument;
				}
			}
		}

		if (\count($definition->getOptions()) > 0) {
			if ($showArguments) {
				$text .= "\n\n";
			}

			$text .= "| Options | Accept value | Is value required | Is multiple | is nagatable | Default |\n";
			$text .= '|---|---|---|---|---|---|';
			foreach ($definition->getOptions() as $option) {
				$text .= "\n";
				if (null !== $describeInputOption = $this->describeInputOption($option)) {
					$text .= $describeInputOption;
				}
			}
		}
		return $text;
	}

	protected function describeInputArgument(InputArgument $argument): string {
		$description = $argument->getDescription();

		return
			'| `'.($argument->getName() ?: '<none>')."` | " .
			($description ? preg_replace('/\s*[\r\n]\s*/', " ", $description) : '') . ' | ' .
			($argument->isRequired() ? 'yes' : 'no')." | " .
			($argument->isArray() ? 'yes' : 'no')." | " .
			'`' . str_replace("\n", '', var_export($argument->getDefault(), true)) . "` |";
	}

	protected function describeInputOption(InputOption $option): string {
		$name = '--'.$option->getName();
		if ($option->isNegatable()) {
			$name .= '|--no-'.$option->getName();
		}
		if ($option->getShortcut()) {
			$name .= '|-'.str_replace('|', '|-', $option->getShortcut()).'';
		}
		$description = $option->getDescription();

		return
			'| `'.$name.'` | ' .
			($description ? preg_replace('/\s*[\r\n]\s*/', " ", $description) : '') . ' | '.
			($option->acceptValue() ? 'yes' : 'no')." | " .
			($option->isValueRequired() ? 'yes' : 'no')." | " .
			($option->isArray() ? 'yes' : 'no')." | " .
			($option->isNegatable() ? 'yes' : 'no')." | " .
			str_replace("\n", '', var_export($option->getDefault(), true)).'` |';
	}
}
