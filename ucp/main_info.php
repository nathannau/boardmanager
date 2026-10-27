<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\ucp;

class main_info
{
	public function module()
	{
		return array(
			'filename'	=> '\nathannau\boardmanager2\ucp\main_module',
			'title'		=> 'UCP_BOARDMANAGER2_DISCORD',
			'modes'		=> array(
				'discord'	=> array(
					'title'	=> 'UCP_BOARDMANAGER2_DISCORD',
					// Visible seulement quand l'intégration Discord est activée dans l'ACP
					'auth'	=> 'ext_nathannau/boardmanager2 && cfg_bm2_discord_enabled',
					'cat'	=> array('UCP_PROFILE'),
				),
			),
		);
	}
}
