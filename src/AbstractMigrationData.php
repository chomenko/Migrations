<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */

namespace Chomenko\Migrations;

use Doctrine\DBAL\Migrations\Version;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractMigrationData
{

	/**
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * @var InputInterface
	 */
	protected $input;

	/**
	 * @var OutputInterface
	 */
	protected $output;

	/**
	 * @var Version
	 */
	protected $version;

	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $connection;

	/**
	 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
	 */
	protected $sm;

	/**
	 * @var \Doctrine\DBAL\Platforms\AbstractPlatform
	 */
	protected $platform;

	public function __construct(Version $version)
	{
		$config = $version->getConfiguration();
		$this->version = $version;
		$this->connection = $config->getConnection();
		$this->sm = $this->connection->getSchemaManager();
		$this->platform = $this->connection->getDatabasePlatform();
	}

	abstract public function execute();

	/**
	 * @return EntityManager
	 */
	public function getEntityManager(): EntityManager
	{
		return $this->em;
	}

	/**
	 * @param EntityManager $em
	 * @return $this
	 */
	public function setEntityManager(EntityManager $em)
	{
		$this->em = $em;
		return $this;
	}

	/**
	 * @return InputInterface
	 */
	public function getInput(): InputInterface
	{
		return $this->input;
	}

	/**
	 * @param InputInterface $input
	 * @return $this
	 */
	public function setInput(InputInterface $input)
	{
		$this->input = $input;
		return $this;
	}

	/**
	 * @return OutputInterface
	 */
	public function getOutput(): OutputInterface
	{
		return $this->output;
	}

	/**
	 * @param OutputInterface $output
	 * @return $this
	 */
	public function setOutput(OutputInterface $output)
	{
		$this->output = $output;
		return $this;
	}

}
