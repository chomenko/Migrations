<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\Command;

use Chomenko\Migrations\Configuration;
use Chomenko\Migrations\MigrationData;
use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Tools\CacheCleaner;
use Nette\DI\Container;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationMigrateData extends AbstractCommand
{

	/**
	 * @var EntityManager @inject
	 */
	public $em;

	/**
	 * @var Container @inject
	 */
	public $container;

	/**
	 * @var CacheCleaner @inject
	 */
	public $cacheCleaner;

	protected function configure()
	{
		$this->setName('migrations:data:migrate')
			->setDescription("Execute a migration data to a specified version or the latest available version.")
			->addArgument('version', InputArgument::OPTIONAL, 'The version number (YYYYMMDDHHMMSS) or alias (first, prev, next, latest) to migrate to.', 'latest')
			->addOption('only-force', null, InputOption::VALUE_NONE, 'Execute only migration force. migrations:data:migrate YYYYMMDDHHMMSS --only-force');

	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|null
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var Configuration $configuration */
		$configuration = $this->getMigrationConfiguration($input, $output);
		$migration = $this->createMigration($configuration, $input, $output);
		$configuration->setName("Data Migrations");
		$this->outputHeader($configuration, $output);

		$executedMigrations  = $configuration->getMigratedData();
		$availableMigrations = $configuration->getAvailableData();

		$version = $input->getArgument('version');
		$onlyForce = $input->getOption('only-force');

		if (!$configuration->hasData($version) && $onlyForce) {
			$output->writeln('<error>Could not find data migration version.</error>');
			return 1;
		}

		$version = $this->getVersionNameFromAlias($input->getArgument('version'), $output, $configuration);
		if ($version === false) {
			return 1;
		}

		$executedUnavailableMigrations = array_diff($executedMigrations, $availableMigrations);
		if ( ! empty($executedUnavailableMigrations)) {
			$output->writeln(sprintf(
				'<error>WARNING! You have %s previously executed migrations'
				. ' in the database that are not registered migrations.</error>',
				count($executedUnavailableMigrations)
			));

			foreach ($executedUnavailableMigrations as $executedUnavailableMigration) {
				$output->writeln(sprintf(
					'    <comment>>></comment> %s (<comment>%s</comment>)',
					$configuration->getDataDateTime($executedUnavailableMigration),
					$executedUnavailableMigration
				));
			}

			$question = 'Are you sure you wish to continue? (y/n)';
			if ( ! $this->canExecute($question, $input, $output)) {
				$output->writeln('<error>Migration cancelled!</error>');
				return 1;
			}
		}

		$question = "\n<comment>WARNING!</comment> You are about to execute a database data migration"
			. "\nthat could result in data changes or data loss."
			. "\nAre you sure you wish to continue? (y/n): ";
		if ($this->canExecute($question, $input, $output)) {
			$output->writeln("");
			$migration->migrate($version, $onlyForce);
		} else {
			$output->writeln('<error>Migration cancelled!</error>');
		}
	}

	/**
	 * @param string $question
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	private function canExecute($question, InputInterface $input, OutputInterface $output)
	{
		if ($input->isInteractive() && ! $this->askConfirmation($question, $input, $output)) {
			return false;
		}

		return true;
	}

	/**
	 * @param Configuration $configuration
	 * @return MigrationData
	 */
	protected function createMigration(Configuration $configuration, InputInterface $input, OutputInterface $output)
	{
		return new MigrationData(
			$this->em,
			$input,
			$output,
			$configuration
		);
	}

	/**
	 * @param $versionAlias
	 * @param OutputInterface $output
	 * @param Configuration $configuration
	 * @return bool|null|string
	 * @throws \Doctrine\DBAL\DBALException
	 * @throws \Doctrine\DBAL\Migrations\MigrationException
	 */
	private function getVersionNameFromAlias($versionAlias, OutputInterface $output, Configuration $configuration)
	{
		$version = $configuration->resolveDataAlias($versionAlias);
		if ($version === null) {
			if ($versionAlias == 'prev') {
				$output->writeln('<error>Already at first version.</error>');
				return false;
			}
			if ($versionAlias == 'next') {
				$output->writeln('<error>Already at latest version.</error>');
				return false;
			}
			if (substr($versionAlias, 0, 7) == 'current') {
				$output->writeln('<error>The delta couldn\'t be reached.</error>');
				return false;
			}

			$output->writeln(sprintf(
				'<error>Unknown version: %s</error>',
				OutputFormatter::escape($versionAlias)
			));
			return false;
		}

		return $version;
	}


}
