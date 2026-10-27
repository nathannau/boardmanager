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
* « Messages auto » postés dans le sujet d'une séance, au nom du membre qui agit
*/
class poster
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	protected $root_path;
	protected $php_ext;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\auth\auth $auth, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Poste un message auto dans le sujet. Si le membre est l'auteur du dernier message
	* (autre que le premier), le texte y est ajouté plutôt que de créer une réponse.
	*
	* @param string $message texte sous la forme stockée par phpBB (entités HTML échappées),
	*                        comme les noms lus en base
	* @return string url du message
	*/
	public function reply($topic_id, $message, actor $actor)
	{
		// submit_post() prend l'auteur dans l'utilisateur courant : on se met à la place du membre
		$saved = null;
		if ((int) $this->user->data['user_id'] !== $actor->user_id)
		{
			$saved = $this->user->data;
			$this->user->data = array_merge($this->user->data, $actor->data);
			$this->auth->acl($this->user->data);
		}

		try
		{
			return $this->submit($topic_id, $message, $actor);
		}
		finally
		{
			if ($saved !== null)
			{
				$this->user->data = $saved;
				$this->auth->acl($this->user->data);
			}
		}
	}

	protected function submit($topic_id, $message, actor $actor)
	{
		if (!function_exists('submit_post'))
		{
			include($this->root_path . 'includes/functions_posting.' . $this->php_ext);
		}

		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql = 'SELECT forum_name FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $topic['forum_id'];
		$result = $this->db->sql_query($sql);
		$forum_name = $this->db->sql_fetchfield('forum_name');
		$this->db->sql_freeresult($result);

		$sql = 'SELECT * FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . (int) $topic_id . '
			ORDER BY post_time DESC, post_id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$last = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$merge = $last
			&& (int) $last['poster_id'] === $actor->user_id
			&& (int) $last['post_id'] !== (int) $topic['topic_first_post_id'];

		$uid = $bitfield = '';
		$flags = 0;
		if ($merge)
		{
			$options = ($last['enable_bbcode'] ? OPTION_FLAG_BBCODE : 0) | ($last['enable_smilies'] ? OPTION_FLAG_SMILIES : 0) | ($last['enable_magic_url'] ? OPTION_FLAG_LINKS : 0);
			$text = generate_text_for_edit($last['post_text'], $last['bbcode_uid'], $options);
			$text = $text['text'] . "\n\n" . $message;
			generate_text_for_storage($text, $uid, $bitfield, $flags, (bool) $last['enable_bbcode'], (bool) $last['enable_magic_url'], (bool) $last['enable_smilies']);
		}
		else
		{
			$text = $message;
			generate_text_for_storage($text, $uid, $bitfield, $flags, false, false, false);
		}

		$data = array(
			'poster_id'			=> $actor->user_id,
			'forum_id'			=> (int) $topic['forum_id'],
			'topic_id'			=> (int) $topic_id,
			'icon_id'			=> $merge ? (int) $last['icon_id'] : 0,
			'enable_bbcode'		=> $merge ? (bool) $last['enable_bbcode'] : false,
			'enable_smilies'	=> $merge ? (bool) $last['enable_smilies'] : false,
			'enable_urls'		=> $merge ? (bool) $last['enable_magic_url'] : false,
			'enable_sig'		=> $merge ? (bool) $last['enable_sig'] : true,
			'message'			=> $text,
			'message_md5'		=> md5($text),
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid,
			'post_edit_reason'	=> '',
			'post_edit_locked'	=> 0,
			'notify_set'		=> true,
			'notify'			=> false,
			'post_time'			=> 0,
			'enable_indexing'	=> true,
			'force_approved_state'	=> true,
			'force_visibility'	=> true,
			'topic_posts_approved'		=> $topic['topic_posts_approved'],
			'topic_posts_unapproved'	=> $topic['topic_posts_unapproved'],
			'topic_posts_softdeleted'	=> $topic['topic_posts_softdeleted'],
			'topic_first_post_id'		=> $topic['topic_first_post_id'],
			'topic_last_post_id'		=> $topic['topic_last_post_id'],
			'topic_title'		=> $topic['topic_title'],
			'forum_name'		=> $forum_name,
		);

		$subject = $topic['topic_title'];
		if ($merge)
		{
			$data['post_id'] = (int) $last['post_id'];
			$data['poster_ip'] = $last['poster_ip'];
			$data['post_visibility'] = $last['post_visibility'];
			$data['post_subject'] = $last['post_subject'];
			$subject = $last['post_subject'];
		}
		else if (strtolower(substr($subject, 0, 3)) !== 're:')
		{
			$subject = 'Re: ' . $subject;
		}

		$poll = array();
		return submit_post($merge ? 'edit' : 'reply', $subject, $actor->username, $topic['topic_type'], $poll, $data, true);
	}
}
