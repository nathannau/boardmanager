<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use nathannau\boardmanager2\core\renderer;

class listener implements EventSubscriberInterface
{
	/** Rendu du BBCode marqueur, remplacé par la séance dans viewtopic */
	const MARKER = '<div class="bm2-session"></div>';

	/** @var renderer */
	protected $renderer;

	public function __construct(renderer $renderer)
	{
		$this->renderer = $renderer;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.text_formatter_s9e_configure_after'	=> 'configure_bbcode',
			'core.viewtopic_modify_post_row'			=> 'render_session',
		);
	}

	/**
	* Déclare le BBCode [boardmanager2][/boardmanager2], sans paramètre ni contenu
	*/
	public function configure_bbcode($event)
	{
		/** @var \s9e\TextFormatter\Configurator $configurator */
		$configurator = $event['configurator'];

		// Une ancienne déclaration (table des BBCodes) aurait le même nom
		unset($configurator->BBCodes['BOARDMANAGER2']);
		unset($configurator->tags['BOARDMANAGER2']);

		$configurator->BBCodes->addCustom('[boardmanager2][/boardmanager2]', self::MARKER);
	}

	/**
	* Remplace le marqueur par la séance du message (première occurrence seulement)
	*/
	public function render_session($event)
	{
		$post_row = $event['post_row'];
		$pos = strpos($post_row['MESSAGE'], self::MARKER);
		if ($pos === false)
		{
			return;
		}

		$row = $event['row'];
		$html = $this->renderer->render((int) $row['post_id'], (int) $event['poster_id']);
		$message = substr_replace($post_row['MESSAGE'], $html, $pos, strlen(self::MARKER));
		$post_row['MESSAGE'] = str_replace(self::MARKER, '', $message);
		$event['post_row'] = $post_row;
	}
}
