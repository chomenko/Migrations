<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\Command;

use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Kdyby\Console\Application;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Tools\CacheCleaner;
use Nette\DI\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationContinue extends AbstractCommand
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
		$this->setName('migrations:continue')
			->setDescription("Update tables. Migration is not required");
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @throws \Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		$configuration = $this->getMigrationConfiguration($input, $output);

		$configuration->setName("Update scheme and data");
		$this->outputHeader($configuration, $output);

		$availableMigrations = count($configuration->getAvailableVersions());
		$application = $this->container->getByType(Application::class);

		if ($availableMigrations > 0) {
			$command = $application->find("migration:migrate");
			$arguments = ["--allow-no-migration" => TRUE, "--no-interaction" => TRUE];
			$greetInput = new ArrayInput($arguments);
			$greetInput->setInteractive(FALSE);
			$command->run($greetInput, $output);
		} else {
			$command = $application->find("orm:schema-tool:update");
			$command->cacheCleaner = $this->cacheCleaner;
			$arguments = ["--force" => TRUE, "--no-interaction" => TRUE];
			$greetInput = new ArrayInput($arguments);
			$greetInput->setInteractive(FALSE);
			$command->run($greetInput, $output);
		}

		$command = $application->find("migrations:data:migrate");
		$arguments = ["--no-interaction" => TRUE];
		$greetInput = new ArrayInput($arguments);
		$greetInput->setInteractive(FALSE);
		$command->run($greetInput, $output);
	}

}
