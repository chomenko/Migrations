<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */

namespace Chomenko\Migrations;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class DataGenerator
{

	const MIGRATIONS_PREFIX = "Data";

	/**
	 * @var string
	 */
	private $version;

	/**
	 * @var string
	 */
	private $className;

	/**
	 * @var PhpNamespace
	 */
	private $object;

	/**
	 * @var string
	 */
	private $fileName;

	/**
	 * @var Configuration
	 */
	private $configuration;

	/**
	 * @param Configuration $configuration
	 * @param string $version
	 */
	public function __construct(Configuration $configuration, $version)
	{
		$this->configuration = $configuration;
		$this->version = $version;
		$this->className = $this->createClassName();
		$this->object = $this->createObject();
		$this->fileName = $this->className . ".php";

		$this->createInit();

	}

	/**
	 * @return ClassType
	 */
	public function getClass(): ClassType
	{
		return $this->object->getClasses()[$this->className];
	}


	/**
	 * @return string
	 */
	protected function createClassName(): string
	{
		return self::MIGRATIONS_PREFIX . $this->version;
	}

	/**
	 * @return PhpNamespace
	 */
	protected function createObject(): PhpNamespace
	{
		$namespace = new PhpNamespace($this->configuration->getDataMigrationsNamespace());
		$namespace->addUse(AbstractMigrationData::class);

		$class = $namespace->addClass($this->createClassName());
		$class->addExtend(AbstractMigrationData::class);

		return $namespace;
	}

	protected function createInit()
	{
		$class = $this->getClass();
		$class->addMethod("execute");
	}

	/**
	 * @param string $directory
	 * @return string
	 */
	public function saveIntoDir(string $directory): string
	{
		$content = "<?php declare(strict_types=1); \n\n";
		$content .= $this->__toString();

		$fileSrc =  realpath($directory) . "/" . $this->fileName;
		file_put_contents($fileSrc, $content);
		return $fileSrc;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return (string)$this->object;
	}

}
