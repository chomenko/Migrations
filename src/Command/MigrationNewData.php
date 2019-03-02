<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */
namespace Chomenko\Migrations\Command;

use Chomenko\Migrations\Configuration;
use Chomenko\Migrations\DataGenerator;
use Doctrine\DBAL\Migrations\Tools\Console\Command\AbstractCommand;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Doctrine\Tools\CacheCleaner;
use Nette\DI\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MigrationNewData extends AbstractCommand
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
		$this->setName('migrations:data:new')
			->setDescription("Generate empty data migration file.");
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @throws \Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var Configuration $configuration */
		$configuration = $this->getMigrationConfiguration($input, $output);

		$configuration->setName("Generated new data class");
		$this->outputHeader($configuration, $output);

		$version = $configuration->generateVersionNumber();
		$data = new DataGenerator($configuration, $version);

		$helper = $this->getHelper('question');
		$paths = array_merge([realpath($configuration->getMigrationsDirectory())], $configuration->getDataDirs());
		$question = new ChoiceQuestion('Please select your save path', $paths,0);
		$question->setErrorMessage('Path %s is invalid.');
		$path = $helper->ask($input, $output, $question);

		$file = $data->saveIntoDir($path);
		$output->writeln(sprintf('Generated new migration data class to <info>%s</info>', $file));
	}

}
