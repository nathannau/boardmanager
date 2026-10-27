<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\cron;

/**
* Rattrapage des publications Discord : création des manquantes, mises à jour
* échouées, clôture (étiquette « Terminée » et verrouillage) des séances passées
*/
class discord_sync extends \phpbb\cron\task\base
{
	const INTERVAL = 3600;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \nathannau\boardmanager2\core\discord_sync */
	protected $sync;

	public function __construct(\phpbb\config\config $config, \nathannau\boardmanager2\core\discord_sync $sync)
	{
		$this->config = $config;
		$this->sync = $sync;
	}

	public function is_runnable()
	{
		return $this->sync->enabled();
	}

	public function should_run()
	{
		return (int) $this->config['bm2_discord_sync_last'] < time() - self::INTERVAL;
	}

	public function run()
	{
		$this->config->set('bm2_discord_sync_last', time(), false);
		$this->sync->sync_due();
	}
}
