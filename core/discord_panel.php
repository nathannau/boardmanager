<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\core;

/**
* Tableau d'une séance pour Discord : premier message de sa publication dans le salon Forum
* (un embed avec un champ par table, et les boutons des joueurs).
*/
class discord_panel
{
	const COLOR_OPEN = 0xF2B134;
	const COLOR_PAST = 0x7A7A7A;
	/** Au-delà, les tables sont proposées dans un menu de sélection plutôt qu'en boutons */
	const MAX_TABLE_BUTTONS = 20;

	// Préfixes des custom_id des composants (lus par controller/discord.php)
	const ID_REGISTER = 'bm2:r';
	const ID_SELECT = 'bm2:s';
	const ID_UNREGISTER = 'bm2:u';

	/** @var rules */
	protected $rules;

	/** @var renderer */
	protected $renderer;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public function __construct(rules $rules, renderer $renderer, \phpbb\language\language $language, \phpbb\db\driver\driver_interface $db)
	{
		$this->rules = $rules;
		$this->renderer = $renderer;
		$this->language = $language;
		$this->db = $db;
	}

	/**
	* Titre de la publication
	*/
	public function title(array $session)
	{
		$this->language->add_lang('common', 'nathannau/boardmanager2');
		return self::plain($this->language->lang('BM2_DISCORD_POST_TITLE', $this->renderer->format_session_date($session['session_date'])));
	}

	/**
	* Nom de l'étiquette de la publication dans le salon Forum
	*/
	public function tag_name($past)
	{
		$this->language->add_lang('common', 'nathannau/boardmanager2');
		return $this->language->lang($past ? 'BM2_DISCORD_TAG_PAST' : 'BM2_DISCORD_TAG_OPEN');
	}

	/**
	* Contenu du premier message de la publication
	*
	* @return array message Discord (embeds, components, allowed_mentions)
	*/
	public function message(array $session)
	{
		$this->language->add_lang('common', 'nathannau/boardmanager2');
		$state = $this->rules->load($session);
		$past = $this->rules->is_past($session);
		$users = $this->load_usernames($state);
		$late = false;

		$fields = array();
		foreach ($state['tables'] as $table_id => $table)
		{
			$fields[] = $this->table_field($state, $table, $state['players'][$table_id], $users, $late);
		}
		$fields[] = $this->no_table_field($state, $users, $late);

		list($min, $max) = $this->rules->remaining_places($state);
		$description = array();
		if ($past)
		{
			$description[] = '**' . $this->language->lang('BM2_DISCORD_PAST') . '**';
		}
		else
		{
			$description[] = $this->language->lang('BM2_DISCORD_HOWTO');
			$description[] = self::plain($this->language->lang('BM2_REMAINING_MIN', $min)) . ' · ' . self::plain($this->language->lang('BM2_REMAINING_MAX', $max));
		}

		$embed = array(
			'title'			=> $this->title($session),
			'url'			=> $this->topic_url($session['topic_id']),
			'color'			=> $past ? self::COLOR_PAST : self::COLOR_OPEN,
			'description'	=> implode("\n", $description),
			'fields'		=> array_slice($fields, 0, 25),
		);
		$legend = array($this->language->lang('BM2_DISCORD_LEGEND_TEMPORARY'));
		if ($late)
		{
			$legend[] = $this->language->lang('BM2_DISCORD_LEGEND_LATE');
		}
		$embed['footer'] = array('text' => implode(' · ', $legend));

		return array(
			'embeds'			=> array($embed),
			'components'		=> $past ? array() : $this->components($state, $users),
			'allowed_mentions'	=> array('parse' => array()),
		);
	}

	protected function table_field(array $state, array $table, array $players, array $users, &$late)
	{
		$type = in_array($table['type'], rules::TYPES, true) ? $this->language->lang('BM2_TYPE_' . strtoupper($table['type'])) : '';
		$name = '🎲 ' . self::plain($table['name']) . ($type !== '' ? ' — ' . $type : '') . ' · ' . self::plain($table['start_time']);

		$lines = array();
		$mj = $table['mj_user_id'] && isset($users[(int) $table['mj_user_id']]) ? $users[(int) $table['mj_user_id']] : $table['mj_username'];
		if ($mj !== '')
		{
			$lines[] = $this->language->lang('BM2_DISCORD_MJ', self::markdown($mj));
		}
		if (trim($table['description']) !== '')
		{
			$lines[] = '*' . self::markdown($table['description']) . '*';
		}
		if (trim($table['trigger_warning']) !== '')
		{
			$lines[] = '⚠️ ' . self::markdown($table['trigger_warning']);
		}
		$lines = array_merge($lines, $this->player_lines($state, $players, $users, $late));

		$count = $this->rules->count_players($players);
		$missing = max(0, $table['min_players'] - $count);
		$lines[] = $this->language->lang('BM2_DISCORD_PLACES', $count, $table['max_players'])
			. ($missing ? ' · ' . $this->language->lang('BM2_DISCORD_MISSING', $missing) : '');

		return array('name' => self::cut($name, 256), 'value' => self::cut(implode("\n", $lines), 1024));
	}

	protected function no_table_field(array $state, array $users, &$late)
	{
		$players = $state['players'][0];
		$lines = $this->player_lines($state, $players, $users, $late);
		return array(
			'name'	=> $this->language->lang('BM2_NO_TABLE') . ' — ' . $this->language->lang('BM2_NB_PLAYERS', $this->rules->count_players($players)),
			'value'	=> self::cut($lines ? implode("\n", $lines) : '—', 1024),
		);
	}

	/**
	* Joueurs présents (les désinscrits, barrés sur le forum, ne sont pas affichés),
	* pré-inscriptions « * », inscrits tardifs ⏰
	*/
	protected function player_lines(array $state, array $players, array $users, &$late)
	{
		$lines = array();
		foreach ($players as $player)
		{
			if ($player['removed'])
			{
				continue;
			}
			$name = $player['user_id'] && isset($users[(int) $player['user_id']]) ? $users[(int) $player['user_id']] : $player['username'];
			$line = self::markdown($name) . ($player['temporary'] ? ' \\*' : '');
			if ($this->rules->is_late($player, $state['session']))
			{
				$line .= ' ⏰';
				$late = true;
			}
			$lines[] = '• ' . $line;
		}
		return $lines;
	}

	/**
	* Boutons des joueurs : une inscription par table, complète ou non (ou un menu au-delà de
	* MAX_TABLE_BUTTONS tables), puis sans table et annuler sa présence. Le forum gère les cas
	* particuliers (déjà inscrit, table complète, pré-inscription à confirmer ou à annuler).
	*/
	protected function components(array $state, array $users)
	{
		$session_id = (int) $state['session']['session_id'];
		$rows = array();
		$buttons = array();

		if (count($state['tables']) <= self::MAX_TABLE_BUTTONS)
		{
			foreach ($state['tables'] as $table_id => $table)
			{
				$buttons[] = array(
					'type'		=> 2,
					'style'		=> 1,
					'label'		=> self::cut($this->register_label($table, $users), 80),
					'custom_id'	=> self::ID_REGISTER . ':' . $session_id . ':' . $table_id,
				);
			}
			foreach (array_chunk($buttons, 5) as $chunk)
			{
				$rows[] = array('type' => 1, 'components' => $chunk);
			}
		}
		else
		{
			$options = array();
			foreach (array_slice($state['tables'], 0, 25, true) as $table_id => $table)
			{
				$options[] = array(
					'label'			=> self::cut($this->register_label($table, $users), 100),
					'value'			=> (string) $table_id,
					'description'	=> self::cut($this->language->lang('BM2_DISCORD_PLACES', $this->rules->count_players($state['players'][$table_id]), $table['max_players']), 100),
				);
			}
			$rows[] = array('type' => 1, 'components' => array(array(
				'type'			=> 3,
				'custom_id'		=> self::ID_SELECT . ':' . $session_id,
				'placeholder'	=> $this->language->lang('BM2_DISCORD_SELECT'),
				'options'		=> $options,
			)));
		}

		$rows[] = array('type' => 1, 'components' => array(
			array('type' => 2, 'style' => 2, 'label' => $this->language->lang('BM2_DISCORD_BTN_NO_TABLE'), 'custom_id' => self::ID_REGISTER . ':' . $session_id . ':0'),
			array('type' => 2, 'style' => 4, 'label' => $this->language->lang('BM2_DISCORD_BTN_UNREGISTER'), 'custom_id' => self::ID_UNREGISTER . ':' . $session_id),
		));
		return $rows;
	}

	/**
	* « M'inscrire : jeu de MJ »
	*/
	protected function register_label(array $table, array $users)
	{
		$mj = $table['mj_user_id'] && isset($users[(int) $table['mj_user_id']]) ? $users[(int) $table['mj_user_id']] : $table['mj_username'];
		return $mj !== ''
			? $this->language->lang('BM2_DISCORD_BTN_REGISTER', self::plain($table['name']), self::plain($mj))
			: $this->language->lang('BM2_DISCORD_BTN_REGISTER_NO_MJ', self::plain($table['name']));
	}

	/**
	* Pseudos actuels des membres (pseudo du forum)
	*/
	protected function load_usernames(array $state)
	{
		$ids = array();
		foreach ($state['tables'] as $table)
		{
			$ids[] = (int) $table['mj_user_id'];
		}
		foreach ($state['players'] as $players)
		{
			foreach ($players as $player)
			{
				$ids[] = (int) $player['user_id'];
			}
		}
		$ids = array_filter(array_unique($ids));
		if (!$ids)
		{
			return array();
		}
		$users = array();
		$result = $this->db->sql_query('SELECT user_id, username FROM ' . USERS_TABLE . ' WHERE ' . $this->db->sql_in_set('user_id', $ids));
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[(int) $row['user_id']] = $row['username'];
		}
		$this->db->sql_freeresult($result);
		return $users;
	}

	/**
	* Lien vers le message du forum qui porte la séance
	*/
	public function topic_url($topic_id)
	{
		$marker_post = $this->renderer->marker_post($topic_id);
		return generate_board_url() . '/viewtopic.php?' . ($marker_post ? 'p=' . $marker_post['post_id'] . '#p' . $marker_post['post_id'] : 't=' . (int) $topic_id);
	}

	/**
	* Texte stocké par phpBB (entités HTML) vers texte brut
	*/
	public static function plain($text)
	{
		return html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
	}

	/**
	* Texte stocké par phpBB vers texte Discord, sans mise en forme involontaire
	*/
	public static function markdown($text)
	{
		return preg_replace('/([\\\\*_~`|>#\[\]()-])/', '\\\\$1', self::plain($text));
	}

	public static function cut($text, $length)
	{
		return utf8_strlen($text) > $length ? utf8_substr($text, 0, $length - 1) . '…' : $text;
	}
}
