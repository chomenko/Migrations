<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\DI;

use Chomenko\Migrations\Configuration;
use Chomenko\Migrations\ConfigurationHelper;
use Chomenko\Migrations\ConfigurationScheme;
use Doctrine\DBAL\Migrations\Tools\Console\Command as DoctrineCommand;
use Kdyby\Console\DI\ConsoleExtension;
use Nette\DI\Helpers;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Chomenko\Migrations\Command;
use Nette\DI\Statement;
use Symfony\Component\Console\Application;

class MigrationsExtension extends CompilerExtension
{

	const DATA_TAG = "migration-data";

	/**
	 * @var array
	 */
	private $defaults = [
		'table' => 'doctrine_migrations',
		'column' => 'version',
		'directory' => '%appDir%/../migrations',
		'dataDirs' => [],
		'namespace' => 'Migrations',
		'dataNamespace' => 'MigrationsData',
		'versionsOrganization' => NULL,
	];

	/**
	 * @var array
	 */
	private $defaultCommands = [
		"diffCommand" => DoctrineCommand\DiffCommand::class,
		"executeCommand" => DoctrineCommand\ExecuteCommand::class,
		"generateCommand" => DoctrineCommand\GenerateCommand::class,
		"latestCommand" => DoctrineCommand\LatestCommand::class,
		"migrateCommand" => DoctrineCommand\MigrateCommand::class,
		"statusCommand" => DoctrineCommand\StatusCommand::class,
		"upToDateCommand" => DoctrineCommand\UpToDateCommand::class,
		"versionCommand" => DoctrineCommand\VersionCommand::class,
	];

	/**
	 * @var array
	 */
	private $command = [
		"resetCommand" => Command\MigrationReset::class,
		"continueCommand" => Command\MigrationContinue::class,
		"migrateDataCommand" => Command\MigrationMigrateData::class,
		"migrationCreateDataCommand" => Command\MigrationNewData::class,
	];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);
		$config = Helpers::expand($config, $builder->parameters);

		$configuration = $builder->addDefinition($this->prefix('configuration'));
		$configuration
			->setFactory(Configuration::class)
			->addSetup('setContainer', [new Statement('@container')])
			->addSetup('setMigrationsTableName', [$config['table']])
			->addSetup('setMigrationsColumnName', [$config['column']])
			->addSetup('setMigrationsDirectory', [$config['directory']])
			->addSetup('setMigrationsNamespace', [$config['namespace']])
			->addSetup('setDataMigrationsNamespace', [$config['dataNamespace']]);

		foreach ($config["dataDirs"] as $path) {
			$configuration->addSetup("addDataDir", [$path]);
		}

		if ($config['versionsOrganization'] === Configuration::VERSIONS_ORGANIZATION_BY_YEAR) {
			$configuration->addSetup('setMigrationsAreOrganizedByYear');
		} elseif ($config['versionsOrganization'] === Configuration::VERSIONS_ORGANIZATION_BY_YEAR_AND_MONTH) {
			$configuration->addSetup('setMigrationsAreOrganizedByYearAndMonth');
		}

		foreach ($this->defaultCommands as $prefix => $class) {
			$builder->addDefinition($this->prefix($prefix))
				->setFactory($class)
				->addTag(ConsoleExtension::TAG_COMMAND)
				->setAutowired(FALSE);
		}

		foreach ($this->command as $prefix => $class) {
			$builder->addDefinition($this->prefix($prefix))
				->setFactory($class)
				->addTag(ConsoleExtension::TAG_COMMAND)
				->setInject(TRUE)
				->setAutowired(FALSE);
		}

		$builder->addDefinition($this->prefix('configurationHelper'))
			->setFactory(ConfigurationHelper::class)
			->setAutowired(FALSE);

	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$application = $builder->getByType(Application::class, FALSE);
		if ($application) {
			$applicationDef = $builder->getDefinition($application);
			$applicationDef->addSetup(
				new Statement('$service->getHelperSet()->set(?)', [$this->prefix('@configurationHelper')])
			);
		}
	}


	/**
	 * @param Configurator $configurator
	 */
	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('Migrations', new MigrationsExtension());
		};
	}

}