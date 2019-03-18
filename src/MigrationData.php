<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */

namespace Chomenko\Migrations;

use Doctrine\Common\EventArgs;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Migrations\MigrationException;
use Doctrine\DBAL\Migrations\Version;
use Doctrine\ORM\EntityManager;
use Kdyby\Doctrine\Diagnostics\Panel;
use Nette\Utils\RegexpException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationData implements SQLLogger
{

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	/**
	 * @var InputInterface
	 */
	private $input;

	/**
	 * @var OutputInterface
	 */
	private $output;

	/**
	 * @var Configuration
	 */
	private $configuration;

	/**
	 * @var int
	 */
	private $sqlCount = 0;

	public function __construct(
		EntityManager $entityManager,
		InputInterface $input,
		OutputInterface $output,
		Configuration $configuration
	){
		$this->entityManager = $entityManager;
		$this->input = $input;
		$this->output = $output;
		$this->configuration = $configuration;
	}

	/**
	 * @param string|null $to
	 * @param bool $onlyForce
	 * @return array
	 * @throws DBALException
	 * @throws MigrationException
	 */
	public function migrate($to = NULL, $onlyForce = false)
	{
		$this->sqlCount = 0;
		if ($to === NULL) {
			$to = $this->configuration->getLatestData();
		}
		$to = (string)$to;

		$migrations = $this->configuration->getDataMigrations();
		if ( !isset($migrations[$to]) && $to > 0) {
			throw MigrationException::unknownMigrationVersion($to);
		}

		if (!$onlyForce) {
			$migrationsToExecute = $this->configuration->getDataMigrationsToExecute($to);
			if (empty($migrationsToExecute)) {
				$this->output->writeln('<comment>No migrations to execute.</comment>');
				return [];
			}
		} else {
			$migrationsToExecute = [$this->configuration->getDataMigration($to)];
		}

		$this->configuration->dispatchEvent(
			Events::onMigrationsDataMigrating,
			new EventArgs($this->configuration, $migrationsToExecute)
		);

		$executed = [];
		$time = 0;
		foreach ($migrationsToExecute as $version){
			/** @var AbstractMigrationData $migration */
			$migration = $version->getMigration();

			$this->configuration->dispatchEvent(
				Events::onMigrationsDataExecuting,
				new EventArgs($this->configuration, $migration)
			);

			$migration->setEntityManager($this->entityManager);
			$migration->setInput($this->input);
			$migration->setOutput($this->output);

			$conf = $this->entityManager->getConnection()->getConfiguration();
			$conf->setSQLLogger($this);
			$this->output->writeln("  <info>Run migration data {$version->getVersion()}</info>");
			$this->output->writeln("  <info>↓↓↓</info>");

			$migration->execute();

			$this->configuration->dispatchEvent(
				Events::onMigrationsDataExecuting,
				new EventArgs($this->configuration, $migration)
			);

			$time += $version->getTime();
			$executed[] = $migration;
			$this->output->writeln("");
			$conf->setSQLLogger(NULL);
			$this->markVersion($version, $onlyForce);
		}

		$this->configuration->dispatchEvent(
			Events::onMigrationsDataMigrated,
			new EventArgs($this->configuration, $executed)
		);

		$this->output->write("\n  <comment>------------------------</comment>\n");
		$this->output->write(sprintf("  <info>++</info> finished in %ss", $time));
		$this->output->write(sprintf("  <info>++</info> %s migrations executed", count($executed)));
		$this->output->write(sprintf("  <info>++</info> %s sql queries\n", $this->sqlCount));

		return $executed;
	}

	/**
	 * @param string $sql
	 * @param array|null $params
	 * @param array|null $types
	 */
	public function startQuery($sql, ?array $params = NULL, ?array $types = NULL)
	{
		$ignore = [
			'"START TRANSACTION"',
			'"COMMIT"',
			'"ROLLBACK"',
		];

		if (array_search($sql, $ignore) !== FALSE) {
			return;
		}

		try {
			$sql = Panel::formatQuery($sql, $params, $types);
		} catch (DBALException $e) {
		} catch (RegexpException $e) {
		}
		$this->sqlCount++;
		$this->output->writeln("  <comment>{$sql}</comment>");
	}

	/**
	 * @param $version
	 * @throws DBALException
	 */
	private function markVersion(Version $version, $onlyForce)
	{
		$this->configuration->createMigrationTable();

		if($onlyForce && $this->configuration->hasDataMigrated($version)){
			return;
		}

		$this->configuration->getConnection()->insert(
			$this->configuration->getMigrationsTableName(),
			[
				$this->configuration->getQuotedMigrationsColumnName() => $version->getVersion(),
				$this->configuration->getMigrationsTypeColumnName() => "data",
			]
		);
	}


	/**
	 * Marks the last started query as stopped. This can be used for timing of queries.
	 *
	 * @return void
	 */
	public function stopQuery()
	{
	}

}
