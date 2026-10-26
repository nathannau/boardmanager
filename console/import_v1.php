<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager2\console;

use nathannau\boardmanager2\core\legacy\Boards;
use nathannau\boardmanager2\core\rules;
use nathannau\boardmanager2\core\store;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
* Import des séances v1 ([boardmanager JJMMAAAA]…[/boardmanager] dans le texte des messages)
*/
class import_v1 extends \phpbb\console\command\command
{
	/** Bloc v1 dans le texte stocké (uid présent) ou dans le texte d'édition (uid optionnel) */
	const BLOCK = '#\[boardmanager (\d{8})(?::(\w+))?\](.*?)\[/boardmanager(?::\2)?\]#s';

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var store */
	protected $store;

	/** @var rules */
	protected $rules;

	protected $root_path;
	protected $php_ext;

	public function __construct(\phpbb\user $user, \phpbb\db\driver\driver_interface $db, store $store, rules $rules, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->store = $store;
		$this->rules = $rules;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		parent::__construct($user);
	}

	protected function configure()
	{
		$this
			->setName('boardmanager2:import-v1')
			->setDescription('Importe les séances v1 [boardmanager] en base (tables bm2_*).')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse et rapport, sans aucune écriture.')
			->addOption('update', null, InputOption::VALUE_NONE, 'Réimporte aussi les messages déjà importés depuis la v1 (leurs tables et joueurs sont remplacés).')
			->addOption('rewrite-posts', null, InputOption::VALUE_NONE, 'Remplace le bloc v1 des messages par [boardmanager2][/boardmanager2] (bascule uniquement).')
			->addOption('post', null, InputOption::VALUE_REQUIRED, 'Limite le traitement à ce message (post_id).');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);
		$dry_run = (bool) $input->getOption('dry-run');
		$update = (bool) $input->getOption('update');
		$rewrite = (bool) $input->getOption('rewrite-posts');
		$only_post = (int) $input->getOption('post');

		if (!function_exists('generate_text_for_storage'))
		{
			include($this->root_path . 'includes/functions_content.' . $this->php_ext);
		}

		$stats = array('posts' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'tables' => 0, 'players' => 0, 'rewritten' => 0);
		$unresolved = array();
		$warnings = array();

		$sql = 'SELECT post_id, topic_id, poster_id, post_time, post_text, bbcode_uid, enable_bbcode, enable_smilies, enable_magic_url
			FROM ' . POSTS_TABLE . "
			WHERE post_text " . $this->db->sql_like_expression($this->db->get_any_char() . '[boardmanager ' . $this->db->get_any_char()) .
			($only_post ? ' AND post_id = ' . $only_post : '') . '
			ORDER BY post_id';
		$result = $this->db->sql_query($sql);
		$posts = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($posts as $post)
		{
			$stats['posts']++;
			$post_id = (int) $post['post_id'];

			if (!preg_match_all(self::BLOCK, $post['post_text'], $blocks, PREG_SET_ORDER))
			{
				$warnings[] = "Message $post_id : bloc [boardmanager] illisible, ignoré.";
				continue;
			}
			if (count($blocks) > 1)
			{
				$warnings[] = "Message $post_id : " . count($blocks) . ' blocs, seul le premier est importé.';
			}

			$session_date = $this->rules->parse_v1_date($blocks[0][1]);
			if ($session_date === false)
			{
				$warnings[] = "Message $post_id : date « {$blocks[0][1]} » invalide, ignoré.";
				continue;
			}

			$boards = new Boards($this->normalize($blocks[0][3]), $blocks[0][1]);
			$rows = $this->build_rows($boards, $session_date, (int) $post['post_time'], $unresolved, $post_id);

			$existing = $this->store->get_session_by_post($post_id);
			$import = !$existing || ($update && $existing['source'] === 'v1');
			if ($existing && !$import)
			{
				$stats['skipped']++;
			}

			if ($import)
			{
				$stats[$existing ? 'updated' : 'imported']++;
				$stats['tables'] += count($rows['tables']);
				foreach ($rows['tables'] as $table)
				{
					$stats['players'] += count($table['players']);
				}
				$stats['players'] += count($rows['no_table']);

				if (!$dry_run)
				{
					$this->store->begin();
					if ($existing)
					{
						$session_id = (int) $existing['session_id'];
						$this->store->delete_session_content($session_id);
						$this->store->update_session_date($session_id, $session_date);
					}
					else
					{
						$session_id = $this->store->create_session($post_id, $post['topic_id'], $session_date, $post['poster_id'], 'v1', $post['post_time']);
					}
					$this->write_rows($session_id, $rows);
					$this->store->commit();
				}
			}

			if ($rewrite && $this->rewrite_post($post, $dry_run, $io))
			{
				$stats['rewritten']++;
			}
		}

		$io->title('Import boardmanager v1' . ($dry_run ? ' (dry-run : aucune écriture)' : ''));
		$io->table(array('Élément', 'Nombre'), array(
			array('Messages avec un bloc v1', $stats['posts']),
			array('Séances importées', $stats['imported']),
			array('Séances réimportées (--update)', $stats['updated']),
			array('Séances déjà importées, ignorées', $stats['skipped']),
			array('Tables', $stats['tables']),
			array('Joueurs (lignes)', $stats['players']),
			array('Messages réécrits', $stats['rewritten']),
		));
		if ($unresolved)
		{
			ksort($unresolved);
			$io->section(count($unresolved) . ' pseudos sans compte (importés comme joueurs hors forum)');
			$io->listing(array_map(function ($name, $count) {
				return "$name ($count)";
			}, array_keys($unresolved), $unresolved));
		}
		if ($warnings)
		{
			$io->section('Avertissements');
			$io->listing($warnings);
		}
		return 0;
	}

	/**
	* Texte du bloc tel qu'écrit en v1 : sans balises XML/HTML, entités décodées
	*/
	protected function normalize($text)
	{
		// Liens convertis : <URL url="…">…</URL> (s9e) ou <a href="…">…</a> (ancien format)
		$text = preg_replace('#<URL url="([^"]*)">.*?</URL>#s', '$1', $text);
		$text = preg_replace('#<a [^>]*href="([^"]*)"[^>]*>.*?</a>#s', '$1', $text);
		$text = preg_replace('#<!--.*?-->#s', '', $text);
		$text = preg_replace('#<br\s*/?>#i', '', $text);
		$text = strip_tags($text);
		return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/**
	* Retour à la forme stockée par phpBB (entités HTML échappées)
	*/
	protected function escape($text)
	{
		return htmlspecialchars(trim((string) $text), ENT_COMPAT, 'UTF-8');
	}

	protected function build_rows(Boards $boards, $session_date, $post_time, array &$unresolved, $post_id)
	{
		$names = array();
		foreach ($boards->boards as $board)
		{
			$names[] = $this->escape($board->mj);
			foreach ($board->players as $player)
			{
				$names[] = $this->escape($player->name);
			}
		}
		$users = $this->rules->find_users_by_name($names);
		$resolve = function ($name) use ($users, &$unresolved) {
			$clean = utf8_clean_string($name);
			if (isset($users[$clean]))
			{
				return $users[$clean];
			}
			if ($name !== '')
			{
				$unresolved[$name] = (isset($unresolved[$name]) ? $unresolved[$name] : 0) + 1;
			}
			return array('user_id' => 0, 'username' => $name);
		};
		$players = function ($board) use ($resolve, $session_date) {
			$rows = array();
			foreach ($board->players as $player)
			{
				$name = $this->escape($player->name);
				if ($name === '')
				{
					continue;
				}
				$account = $resolve($name);
				$date = $this->rules->parse_v1_date(trim($player->date));
				$rows[] = array(
					'user_id'		=> $account['user_id'],
					'username'		=> $account['user_id'] ? $account['username'] : $name,
					'register_date'	=> $date === false ? $session_date : $date,
					'removed'		=> $player->removed ? 1 : 0,
					'temporary'		=> $player->temporary ? 1 : 0,
				);
			}
			return $rows;
		};

		$rows = array('tables' => array(), 'no_table' => array());
		foreach ($boards->boards as $board)
		{
			if ($board->isWithoutTable)
			{
				$rows['no_table'] = array_merge($rows['no_table'], $players($board));
				continue;
			}
			$mj = $resolve($this->escape($board->mj));
			$type = trim((string) $board->type);
			$url = $this->escape($board->url);
			$rows['tables'][] = array(
				'table' => array(
					'name'				=> $this->escape($board->name),
					'type'				=> in_array($type, rules::TYPES, true) ? $type : '',
					'url'				=> $this->rules->is_valid_url($url) ? $url : '',
					'start_time'		=> $this->escape($board->time),
					'min_players'		=> (int) $board->minPlayer,
					'max_players'		=> (int) $board->maxPlayer,
					'mj_user_id'		=> $mj['user_id'],
					'mj_username'		=> $mj['user_id'] ? $mj['username'] : $this->escape($board->mj),
					'trigger_warning'	=> $this->escape($board->trigger_warning),
					'description'		=> $this->escape($board->description),
					'created_time'		=> $post_time,
				),
				'players' => $players($board),
			);
		}
		return $rows;
	}

	protected function write_rows($session_id, array $rows)
	{
		foreach ($rows['tables'] as $table)
		{
			$table_id = $this->store->add_table(array_merge($table['table'], array('session_id' => $session_id)));
			foreach ($table['players'] as $player)
			{
				$this->store->add_player(array_merge($player, array('session_id' => $session_id, 'table_id' => $table_id)));
			}
		}
		foreach ($rows['no_table'] as $player)
		{
			$this->store->add_player(array_merge($player, array('session_id' => $session_id, 'table_id' => 0)));
		}
	}

	/**
	* Remplace le(s) bloc(s) v1 du message par le marqueur v2
	*
	* @return bool message modifié (ou à modifier en dry-run)
	*/
	protected function rewrite_post(array $post, $dry_run, SymfonyStyle $io)
	{
		$options = ($post['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0) | ($post['enable_smilies'] ? OPTION_FLAG_SMILIES : 0) | ($post['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);
		$edit = generate_text_for_edit($post['post_text'], $post['bbcode_uid'], $options);

		$first = true;
		$text = preg_replace_callback(self::BLOCK, function () use (&$first) {
			$marker = $first ? '[boardmanager2][/boardmanager2]' : '';
			$first = false;
			return $marker;
		}, $edit['text']);
		if ($first)
		{
			return false;
		}

		// Comme submit_post() : la somme de contrôle porte sur le texte d'édition
		$checksum = md5($text);
		$uid = $bitfield = '';
		$flags = 0;
		generate_text_for_storage($text, $uid, $bitfield, $flags, (bool) $post['enable_bbcode'], (bool) $post['enable_magic_url'], (bool) $post['enable_smilies']);

		if ($dry_run)
		{
			if ($io->isVerbose())
			{
				$io->text('Message ' . $post['post_id'] . ' réécrit :');
				$io->text($text);
			}
			return true;
		}

		$this->db->sql_query('UPDATE ' . POSTS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array(
			'post_text'			=> $text,
			'bbcode_uid'		=> $uid,
			'bbcode_bitfield'	=> $bitfield,
			'post_checksum'		=> $checksum,
		)) . ' WHERE post_id = ' . (int) $post['post_id']);
		return true;
	}
}
