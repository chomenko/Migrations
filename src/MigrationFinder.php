<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */

namespace Chomenko\Migrations;

use Doctrine\DBAL\Migrations\Finder\AbstractFinder;

class MigrationFinder extends AbstractFinder
{

	/**
	 * @var string
	 */
	protected $prefix;


	public function __construct(string $prefix = "Version")
	{
		$this->prefix = $prefix;
	}

	/**
	 * {@inheritdoc}
	 */
	public function findMigrations($directory, $namespace = null)
	{
		$dir = $this->getRealPath($directory);

		return $this->loadMigrations($this->getMatches($this->createIterator($dir)), $namespace);
	}

	/**
	 * Create a recursive iterator to find all the migrations in the subdirectories.
	 * @param string $dir
	 * @return \RegexIterator
	 */
	private function createIterator($dir)
	{
		return new \RegexIterator(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
				\RecursiveIteratorIterator::LEAVES_ONLY
			),
			$this->getPattern(),
			\RegexIterator::GET_MATCH
		);
	}

	private function getPattern()
	{
		return sprintf('#^.+\\%s' . $this->prefix . '[^\\%s]{1,255}\\.php$#i', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
	}

	/**
	 * Transform the recursiveIterator result array of array into the expected array of migration file
	 * @param iterable $iteratorFilesMatch
	 * @return array
	 */
	private function getMatches($iteratorFilesMatch)
	{
		$files = [];
		foreach ($iteratorFilesMatch as $file) {
			$files[] = $file[0];
		}

		return $files;
	}

	/**
	 * Load the migrations and return an array of thoses loaded migrations
	 * @param array $files array of migration filename found
	 * @param string $namespace namespace of thoses migrations
	 * @return array constructed with the migration name as key and the value is the fully qualified name of the migration
	 */
	protected function loadMigrations($files, $namespace)
	{
		$migrations = [];

		uasort($files, $this->getFileSortCallback());

		foreach ($files as $file) {
			static::requireOnce($file);
			$className = basename($file, '.php');
			$version   = (string) substr($className, strlen($this->prefix));
			if ($version === '0') {
				throw new \InvalidArgumentException(sprintf(
					'Cannot load a migrations with the name "%s" because it is a reserved number by doctrine migrations' . PHP_EOL .
					'It\'s used to revert all migrations including the first one.',
					$version
				));
			}
			$migrations[$version] = sprintf('%s\\%s', $namespace, $className);
		}

		return $migrations;
	}

}
