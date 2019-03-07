<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\Command;

use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Kdyby\Console\Application;
use Kdyby\Doctrine\Tools\CacheCleaner;
use Nette\DI\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationReset extends AbstractCommand
{

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
		$this->setName('migrations:reset')
			->setDescription("Remove all table and run new migrate")
			->addOption(
				'force-scheme',
				null,
				InputOption::VALUE_NONE,
				'It updates the schema even if the migration is not created. migrations:continue --force-scheme'
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|null
	 * @throws \Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		$configuration = $this->getMigrationConfiguration($input, $output);
		$configuration->setName("Restart database");
		$this->outputHeader($configuration, $output);

		$question = "\n<error>WARNING! You really want to restart the database?</error> "
			. "\nYou will lose all the data."
			. "\nAre you sure you wish to continue? (y/n): ";
		if (!$this->canExecute($question, $input, $output)) {
			return 1;
		}

		$application = $this->container->getByType(Application::class);

		$command = $application->find("orm:schema-tool:drop");
		$command->cacheCleaner = $this->cacheCleaner;

		$arguments = ['--full-database' => TRUE, '--force' => TRUE];
		$greetInput = new ArrayInput($arguments);
		$command->run($greetInput, $output);

		$arguments = [];
		if ($input->getOption('force-scheme')) {
			$arguments["--force-scheme"] = TRUE;
		}

		$command = $application->find("migrations:continue");
		$greetInput = new ArrayInput($arguments);
		$greetInput->setInteractive(FALSE);
		$command->run($greetInput, $output);
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

}
