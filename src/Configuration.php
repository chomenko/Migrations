<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Migrations\Configuration\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\Migrations\Finder\MigrationFinderInterface;
use Doctrine\DBAL\Migrations\MigrationException;
use Doctrine\DBAL\Migrations\OutputWriter;
use Doctrine\DBAL\Migrations\QueryWriter;
use Doctrine\DBAL\Migrations\Version;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Nette\DI\Container;

class Configuration extends DoctrineConfiguration
{

	/**
	 * @var Container
	 */
	private $container;

	/**
	 * Flag for whether or not the migration table has been created
	 *
	 * @var boolean
	 */
	private $migrationTableCreated = false;

	/**
	 * Prevent write queries.
	 *
	 * @var bool
	 */
	private $isDryRun = false;

	/**
	 * @var string
	 */
	private $migrationsTypeColumnName = "type";

	/**
	 * @var Version[]
	 */
	private $dataMigrations;

	/**
	 * @var string
	 */
	private $dataMigrationsNamespace = "MigrationsData";

	/**
	 * The migration finder implementation -- used to load migrations from a
	 * directory.
	 *
	 * @var MigrationFinderInterface
	 */
	private $migrationFinder;

	/**
	 * @var array
	 */
	private $dataDirs = [];

	/**
	 * Construct a migration configuration object.
	 *
	 * @param Connection               $connection   A Connection instance
	 * @param OutputWriter             $outputWriter A OutputWriter instance
	 * @param MigrationFinderInterface $finder       Migration files finder
	 * @param QueryWriter|null         $queryWriter
	 */
	public function __construct(
		Connection $connection,
		OutputWriter $outputWriter = null,
		MigrationFinderInterface $finder = null,
		QueryWriter $queryWriter = null
	) {
		$finder = $finder ?? new MigrationFinder();
		$this->migrationFinder = new MigrationFinder(DataGenerator::MIGRATIONS_PREFIX);
		parent::__construct($connection, $outputWriter, $finder, $queryWriter);
	}

	/**
	 * @return array
	 */
	public function getDataDirs(): array
	{
		return $this->dataDirs;
	}

	/**
	 * @param string $path
	 * @return $this
	 */
	public function addDataDir(string $path)
	{
		$path = realpath($path);
		if ($path) {
			$this->dataDirs[] = $path;
		}
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMigrationsTypeColumnName(): string
	{
		return $this->migrationsTypeColumnName;
	}

	/**
	 * @param Container $container
	 * @return void
	 */
	public function setContainer(Container $container): void
	{
		$this->container = $container;
	}

	/**
	 * @param string $dataMigrationsNamespace
	 */
	public function setDataMigrationsNamespace($dataMigrationsNamespace)
	{
		$this->dataMigrationsNamespace = $dataMigrationsNamespace;
	}

	/**
	 * @return string
	 */
	public function getDataMigrationsNamespace(): string
	{
		return $this->dataMigrationsNamespace;
	}

	/**
	 * @param string $direction
	 * @param string $to
	 * @return array
	 */
	public function getMigrationsToExecute($direction, $to): array
	{
		$versions = parent::getMigrationsToExecute($direction, $to);
		if ($this->container) {
			foreach ($versions as $version) {
				$this->container->callInjects($version->getMigration());
			}
		}
		return $versions;
	}

	/**
	 * @param string $version
	 * @return Version|string
	 * @throws \Doctrine\DBAL\Migrations\MigrationException
	 */
	public function getVersion($version)
	{
		$version = parent::getVersion($version);

		if ($this->container)
			$this->container->callInjects($version->getMigration());

		return $version;
	}

	/**
	 * @return bool
	 * @throws \Doctrine\DBAL\DBALException
	 * @throws \Doctrine\DBAL\Migrations\MigrationException
	 */
	public function createMigrationTable()
	{

		$this->validate();

		if ($this->migrationTableCreated) {
			return false;
		}

		$this->connect();
		$connection = $this->getConnection();
		$schemaManager = $connection->getSchemaManager();

		if ($schemaManager->tablesExist([$this->getMigrationsTableName()])) {
			$this->updateColumn();
			$this->migrationTableCreated = true;
			return false;
		}

		if ($this->isDryRun) {
			return false;
		}

		$columns = [
			$this->getMigrationsColumnName() => $this->getMigrationsColumn(),
			$this->migrationsTypeColumnName => $this->getMigrationsDataColumn(),
		];

		$table = new Table($this->getMigrationsTableName(), $columns);
		$table->setPrimaryKey([$this->getMigrationsColumnName()]);
		$connection->getSchemaManager()->createTable($table);

		$this->migrationTableCreated = true;

		return true;
	}

	/**
	 * @return Column
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function getMigrationsColumn() : Column
	{
		return new Column($this->getMigrationsColumnName(), Type::getType('string'), ['length' => 255]);
	}

	/**
	 * @return Column
	 * @throws \Doctrine\DBAL\DBALException
	 */
	private function getMigrationsDataColumn() : Column
	{
		return new Column($this->migrationsTypeColumnName, Type::getType('string'), ['length' => 255, 'default' => "scheme"]);
	}

	private function updateColumn()
	{

		$connection = $this->getConnection();
		$schemaManager = $connection->getSchemaManager();
		$originalScheme = $schemaManager->createSchema();
		$newSchema = clone $originalScheme;

		$table = $originalScheme->getTable($this->getMigrationsTableName());

		if (!$table->hasColumn($this->migrationsTypeColumnName)) {
			$table->addColumn($this->migrationsTypeColumnName, 'string', ['length' => 255, 'default' => "scheme"]);
			$queries = $originalScheme->getMigrateFromSql($newSchema, $connection->getDatabasePlatform());
			foreach ($queries as $query) {
				$connection->query($query);
			}
		}
	}

	/**
	 * @param Version $version
	 * @return bool
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function hasDataMigrated(Version $version): bool
	{
		return $this->hasVersionMigrated($version, "data");
	}

	/**
	 * @param Version $version
	 * @param string $type
	 * @return bool
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function hasVersionMigrated(Version $version, string $type = "scheme")
	{
		$this->connect();
		$this->createMigrationTable();

		$version = $this->getConnection()->fetchColumn(
			"SELECT " . $this->getQuotedMigrationsColumnName() . " FROM " . $this->getMigrationsTableName() . " WHERE " . $this->getQuotedMigrationsColumnName() . " = ?"
			. " AND " . $this->migrationsTypeColumnName . " = ?",
			[$version->getVersion(), $type]
		);

		return $version !== false;
	}

	/**
	 * @return array|Version[]
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getMigratedData()
	{
		return $this->getMigratedVersions("data");
	}

	/**
	 * @param string $type
	 * @return array|Version[]
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getMigratedVersions(string $type = "scheme")
	{
		$this->createMigrationTable();

		if ( ! $this->migrationTableCreated && $this->isDryRun) {
			return [];
		}

		$this->connect();

		$ret = $this->getConnection()->fetchAll(
			"SELECT " . $this->getQuotedMigrationsColumnName() . " FROM " . $this->getMigrationsTableName()
			. " WHERE " . $this->migrationsTypeColumnName . " = ?",
			[$type]
		);

		return array_map('current', $ret);
	}

	/**
	 * @return null
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getPrevData()
	{
		return $this->getRelativeData($this->getCurrentData(), -1);
	}

	/**
	 * @return null|string
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getNextData()
	{
		return $this->getRelativeData($this->getCurrentData(), 1);
	}

	/**
	 * @return string
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getCurrentData()
	{
		return $this->getCurrentVersion("data");
	}

	/**
	 * @param string $type
	 * @return string
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getCurrentVersion(string $type = "scheme")
	{
		$this->createMigrationTable();

		if ( ! $this->migrationTableCreated && $this->isDryRun) {
			return '0';
		}

		$this->connect();

		if (empty($this->getMigrations())) {
			$this->registerMigrationsFromDirectory($this->getMigrationsDirectory());
		}

		$where = null;
		if ( ! empty($this->getMigrations())) {
			$migratedVersions = [];
			foreach ($this->getMigrations() as $migration) {
				$migratedVersions[] = sprintf("'%s'", $migration->getVersion());
			}
			$where = " WHERE " . $this->getQuotedMigrationsColumnName() . " IN (" . implode(', ', $migratedVersions) . ")";
			$where .= " AND " . $this->migrationsTypeColumnName . " = '".$type."'";
		}

		$sql = sprintf(
			"SELECT %s FROM %s%s ORDER BY %s DESC",
			$this->getQuotedMigrationsColumnName(),
			$this->getMigrationsTableName(),
			$where,
			$this->getQuotedMigrationsColumnName()
		);

		$sql    = $this->getConnection()->getDatabasePlatform()->modifyLimitQuery($sql, 1);
		$result = $this->getConnection()->fetchColumn($sql);

		return $result !== false ? (string) $result : '0';
	}

	/**
	 * @return false|int|mixed
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getNumberOfExecutedDataMigrations()
	{
		return $this->getNumberOfExecutedMigrations("data");
	}

	/**
	 * @param string $type
	 * @return false|int|mixed
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getNumberOfExecutedMigrations(string $type = "scheme")
	{
		$this->connect();
		$this->createMigrationTable();

		$result = $this->getConnection()->fetchColumn(
			"SELECT COUNT(" . $this->getQuotedMigrationsColumnName() . ") FROM " . $this->getMigrationsTableName()
			. "WHERE " . $this->migrationsTypeColumnName . " = ?",
			[$type]
		);

		return $result !== false ? $result : 0;
	}

	protected function loadDataMigrations()
	{
		if (empty($this->dataMigrations)) {

			$dirs = $this->dataDirs;
			$dirs[] = $this->getMigrationsDirectory();
			foreach ($dirs as $dir) {
				$this->registerDataMigrationsFromDirectory($dir);
			}
		}
	}

	/**
	 * @return array
	 */
	public function getAvailableData()
	{
		$availableVersions = [];
		$this->loadDataMigrations();

		foreach ($this->dataMigrations as $migration) {
			$availableVersions[] = $migration->getVersion();
		}

		return $availableVersions;
	}

	/**
	 * @param $to
	 * @return Version[]
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getDataMigrationsToExecute($to): array
	{
		$this->loadDataMigrations();
		$versions = [];
		$migrated = $this->getMigratedData();
		foreach ($this->dataMigrations as $version) {
			if ($this->shouldExecuteMigrationData($version, $to, $migrated)) {
				$versions[$version->getVersion()] = $version;
			}
		}

		return $versions;
	}

	/**
	 * @param Version $version
	 * @param $to
	 * @param $migrated
	 * @return bool
	 */
	private function shouldExecuteMigrationData(Version $version, $to, $migrated)
	{
		if (in_array($version->getVersion(), $migrated, true)) {
			return false;
		}
		return $version->getVersion() <= $to;
	}


	/**
	 * @param $path
	 * @return array|string[]
	 */
	protected function findDataMigrations($path)
	{
		return $this->migrationFinder->findMigrations($path, $this->dataMigrationsNamespace);
	}

	/**
	 * @param $path
	 * @return array
	 * @throws MigrationException
	 */
	public function registerDataMigrationsFromDirectory($path)
	{
		return $this->registerMigrationsData($this->findDataMigrations($path));
	}

	/**
	 * @param array $migrations
	 * @return array
	 * @throws MigrationException
	 */
	public function registerMigrationsData(array $migrations)
	{
		$versions = [];
		foreach ($migrations as $version => $class) {
			$versions[] = $this->registerMigrationData($version, $class);
		}

		return $versions;
	}

	/**
	 * Returns the latest available migration version.
	 *
	 * @return string The version string in the format YYYYMMDDHHMMSS.
	 */
	public function getLatestData()
	{
		$this->loadDataMigrations();
		$versions = array_keys($this->dataMigrations);
		$latest   = end($versions);
		return $latest !== false ? (string) $latest : '0';
	}

	/**
	 * @param $version
	 * @param $class
	 * @return Version|string
	 * @throws MigrationException
	 */
	public function registerMigrationData($version, $class)
	{
		$this->ensureDataMigrationClassExists($class);

		$version = (string) $version;
		$class   = (string) $class;
		if (isset($this->dataMigrations[$version])) {
			throw MigrationException::duplicateMigrationVersion($version, get_class($this->dataMigrations[$version]));
		}
		$version = new Version($this, $version, $class);
		$this->dataMigrations[$version->getVersion()] = $version;
		ksort($this->dataMigrations, SORT_STRING);

		return $version;
	}

	/**
	 * @param $class
	 * @throws MigrationException
	 */
	private function ensureDataMigrationClassExists($class)
	{
		if ( ! class_exists($class)) {
			throw MigrationException::migrationClassNotFound($class, $this->getMigrationsNamespace());
		}
	}

	/**
	 * @param bool $isDryRun
	 */
	public function setIsDryRun($isDryRun)
	{
		parent::setIsDryRun($isDryRun);
		$this->isDryRun = $isDryRun;
	}

	/**
	 * @return bool
	 */
	public function isDryRun(): bool
	{
		return $this->isDryRun;
	}

	/**
	 * Returns the datetime of a migration
	 *
	 * @param string $version
	 * @return string
	 */
	public function getDataDateTime($version)
	{
		$datetime = str_replace('Data', '', $version);
		$datetime = \DateTime::createFromFormat('YmdHis', $datetime);

		if ($datetime === false) {
			return '';
		}

		return $datetime->format('Y-m-d H:i:s');
	}

	/**
	 * @return Version[]
	 */
	public function getDataMigrations(): array
	{
		return $this->dataMigrations;
	}

	/**
	 * @param $version
	 * @return Version
	 */
	public function getDataMigration($version): Version
	{
		return $this->dataMigrations[$version];
	}

	/**
	 * @param $alias
	 * @return null|string
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function resolveDataAlias($alias)
	{
		if ($this->hasData($alias)) {
			return $alias;
		}
		switch ($alias) {
			case 'first':
				return '0';
			case 'current':
				return $this->getCurrentData();
			case 'prev':
				return $this->getPrevData();
			case 'next':
				return $this->getNextData();
			case 'latest':
				return $this->getLatestData();
			default:
				if (substr($alias, 0, 7) == 'current') {
					return $this->getDeltaData(substr($alias, 7));
				}
				return null;
		}
	}

	/**
	 * @param string $version
	 * @param int $delta
	 * @return null
	 */
	public function getRelativeData($version, $delta)
	{
		$this->loadDataMigrations();

		$versions = array_map('strval', array_keys($this->dataMigrations));

		array_unshift($versions, '0');
		$offset = array_search((string) $version, $versions, true);
		if ($offset === false || ! isset($versions[$offset + $delta])) {
			// Unknown version or delta out of bounds.
			return null;
		}

		return $versions[$offset + $delta];
	}

	/**
	 * @param $delta
	 * @return null
	 * @throws MigrationException
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function getDeltaData($delta)
	{
		$symbol = substr($delta, 0, 1);
		$number = (int) substr($delta, 1);

		if ($number <= 0) {
			return null;
		}

		if ($symbol == "+" || $symbol == "-") {
			return $this->getRelativeData($this->getCurrentData(), (int) $delta);
		}

		return null;
	}


	/**
	 * @param string $version
	 * @return bool
	 */
	public function hasData($version): bool
	{
		$this->loadDataMigrations();
		return isset($this->dataMigrations[$version]);
	}

}
