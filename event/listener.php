<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
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
	* Remplace le marqueur par la séance du sujet, dans le premier message qui le
	* contient (première occurrence) ; les autres marqueurs sont retirés.
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
		$marker_post = $this->renderer->marker_post($row['topic_id']);
		if ($marker_post && $marker_post['post_id'] === (int) $row['post_id'])
		{
			$html = $this->renderer->render((int) $row['topic_id'], $marker_post['poster_id']);
			$post_row['MESSAGE'] = substr_replace($post_row['MESSAGE'], $html, $pos, strlen(self::MARKER));
		}
		$post_row['MESSAGE'] = str_replace(self::MARKER, '', $post_row['MESSAGE']);
		$event['post_row'] = $post_row;
	}
}
