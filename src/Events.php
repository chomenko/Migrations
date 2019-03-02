<?php
/**
 * Author: Mykola Chomenko
 * Email: mykola.chomenko@dipcom.cz
 */

namespace Chomenko\Migrations;

final class Events
{
	/**
	 * Private constructor. This class cannot be instantiated.
	 */
	private function __construct()
	{
	}

	const onMigrationsDataMigrating = 'onMigrationsDataMigrating';
	const onMigrationsDataMigrated = 'onMigrationsDataMigrated';
	const onMigrationsDataExecuting = 'onMigrationsDataExecuting';
	const onMigrationsDataExecuted  = 'onMigrationsDataExecuted';
}

