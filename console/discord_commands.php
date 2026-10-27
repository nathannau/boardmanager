<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\console;

use nathannau\boardmanager2\core\discord_api;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
* Déclare les commandes slash du bot auprès de Discord, sur un serveur
*/
class discord_commands extends \phpbb\console\command\command
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var discord_api */
	protected $api;

	public function __construct(\phpbb\user $user, \phpbb\config\config $config, discord_api $api)
	{
		$this->config = $config;
		$this->api = $api;
		parent::__construct($user);
	}

	/**
	* Commandes du bot (le texte est vu par les membres dans Discord)
	*/
	public static function definitions()
	{
		return array(
			array(
				'name'			=> 'lier',
				'type'			=> 1,
				'description'	=> 'Lier ton compte Discord à ton compte du forum de la Guilde',
				'options'		=> array(
					array(
						'type'			=> 3,
						'name'			=> 'code',
						'description'	=> 'Code affiché dans ton profil du forum (Profil → Discord)',
						'required'		=> true,
						'min_length'	=> 6,
						'max_length'	=> 12,
					),
				),
			),
		);
	}

	protected function configure()
	{
		$this
			->setName('boardmanager2:discord-commands')
			->setDescription('Déclare les commandes slash du bot sur un serveur Discord.')
			->addOption('guild', null, InputOption::VALUE_REQUIRED, 'Identifiant du serveur (par défaut : celui de la configuration).')
			->addOption('remove', null, InputOption::VALUE_NONE, 'Retire toutes les commandes du bot de ce serveur.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		$guild_id = $input->getOption('guild') ?: $this->config['bm2_discord_guild_id'];
		if (!$this->api->is_configured() || !ctype_digit((string) $guild_id))
		{
			$io->error('Configurer d\'abord l\'application Discord (ACP → Board Manager → Discord) et l\'identifiant du serveur.');
			return 1;
		}

		$commands = $input->getOption('remove') ? array() : self::definitions();
		try
		{
			$result = $this->api->put_guild_commands($guild_id, $commands);
		}
		catch (\RuntimeException $e)
		{
			$io->error($e->getMessage());
			return 1;
		}

		$names = array_map(function ($command) {
			return '/' . $command['name'];
		}, $result);
		$io->success($names ? 'Commandes déclarées sur le serveur ' . $guild_id . ' : ' . implode(', ', $names) : 'Commandes retirées du serveur ' . $guild_id . '.');
		return 0;
	}
}
