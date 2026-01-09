<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager\controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use nathannau\boardmanager\core\BoardManager;
use nathannau\boardmanager\core\Board;
use nathannau\boardmanager\core\Player;
use \phpbb\request\request_interface;

/**
* 
*/
class main
{
	protected $db;
	protected $user;
//	protected $auth;
	protected $request;
	protected $boardManager;
	protected $template;
	
	public function __construct(BoardManager $boardManager, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\request\request $request, \phpbb\template\template $template)
	{
		$this->boardManager = $boardManager;
		$this->db = $db;
		$this->user = $user;
		$this->request = $request;
		$this->template = $template;
	}
	
	public function registerAction($post, $hash)
	{		
		global $phpbb_root_path, $phpEx;
		
		$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = '.$post.';';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$row['topic_id'].';';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		//echo $row['post_text'],$row['bbcode_uid'],$row['bbcode_bitfield']."<br>";
		
		$allBoards = $this->boardManager->ExtractBBCode32($row['post_text'],$row['bbcode_uid']);
		foreach($allBoards as $boards)
		{
			$board = $boards->GetBoardWithHash($hash);
			if ($board==null) continue;

			$date = date("dmY");
			if (!$board->isWithoutTable)
			{
				$woBoard = $boards->GetWithoutBoard();
				//$fromWob = false;
				foreach($woBoard->players as &$player)
				{
					if ($player->name != $this->user->data['username'] || $player->removed) continue;
					$player->removed = true;
					$date = $player->date;
					break;
				}
			}
			
			for($i=count($board->players)-1; $i>=0; $i--)
				if ($board->players[$i]->name == $this->user->data['username'] && $board->players[$i]->temporary)
					unset($board->players[$i]);
				
			$board->players[] = new Player($this->user->data['username'].';'.$date.';');
			
			$message = $this->boardManager->ReplaceBBCode32($row['post_text'], $row['bbcode_uid'], $allBoards);
			$this->EditMessage($post, $message);
			
			$message = $board->isWithoutTable ?
				"Message auto : Inscription en sans table":
				"Message auto : Inscription sur la table '" . $board->name . "' de '".$board->mj."'";
			$redirect = $this->ReplyMessage($row['topic_id'], $message);
			
			redirect($redirect);
			die();
		}

		$redirect = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $post . '#p' . $post);
		redirect($redirect);
		//return new Response('<pre>Oki registerAction '.$post.' '.$hash.'<br/>'.$redirect);
	}
	public function unregisterAction($post, $hash)
	{
		global $phpbb_root_path, $phpEx;

		$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = '.$post.';';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$row['topic_id'].';';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$allBoards = $this->boardManager->ExtractBBCode32($row['post_text'],$row['bbcode_uid']);

		foreach($allBoards as $index=>$boards)
		{
			$board = $boards->GetBoardWithHash($hash);
			if ($board==null) continue;
			
			foreach($board->players as $player)
			{
				if ($player->name != $this->user->data['username'] || $player->removed) continue;
				$player->removed = true;
			
				$message = $this->boardManager->ReplaceBBCode32($row['post_text'], $row['bbcode_uid'], $allBoards);
				
				$this->EditMessage($post, $message);
			
				$message = $board->isWithoutTable ?
					"Message auto : Désinscription de sans table":
					"Message auto : Désinscription de la table '" . $board->name . "' de '".$board->mj."'";
				$redirect = $this->ReplyMessage($row['topic_id'], $message);
			
				redirect($redirect);
				die();
			}
		}

		$redirect = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $post . '#p' . $post);
		redirect($redirect);
		//return new Response('<pre>Oki registerAction '.$post.' '.$hash.'<br/>'.$redirect);
		return new Response('Oki unregisterAction '.$post.' '.$hash);
	}
	public function openAction($post)
	{
		global $phpbb_root_path, $phpEx;

		$page_title = 'Ouverture de table';
		page_header($page_title);
		
		$board = new Board();
		$board->isWithoutTable = false;
		$board->name = $this->request->variable('name', '', true, request_interface::POST);
		$board->type = $this->request->variable('type', '', true, request_interface::POST);
		$board->url = $this->request->variable('url', '', true, request_interface::POST);
		$board->time = $this->request->variable('time', '21h', true, request_interface::POST);
		$board->minPlayer = $this->request->variable('min', '3', true, request_interface::POST);
		$board->maxPlayer = $this->request->variable('max', '5', true, request_interface::POST);
		$board->trigger_warning = $this->request->variable('trigger_warning', '', true, request_interface::POST);
		$board->description = $this->request->variable('description', '', true, request_interface::POST);
		$board->mj = $this->user->data['username'];
		$preinscription = $this->request->variable('preinscription', '', true, request_interface::POST);

		$date = date("dmY");
		foreach(explode("\n", $preinscription) as $player)
		{
			$player = trim($player);
			if ($player!="")
				$board->players[] = new Player($player.';'.$date.';*');
		}

		if ($board->IsValid())
		{
			$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = '.$post.';';
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow();
			$this->db->sql_freeresult($result);
			
			$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$row['topic_id'].';';
			$result = $this->db->sql_query($sql);
			$topic = $this->db->sql_fetchrow();
			$this->db->sql_freeresult($result);
			
			$allBoards = $this->boardManager->ExtractBBCode32($row['post_text'],$row['bbcode_uid']);

			foreach($allBoards as $boards)
			{
				$boards->AddBoard($board);
				$woBoard = $boards->GetWithoutBoard();
				if ($woBoard!=null)
					foreach($woBoard->players as &$player)
						if ($player->name==$this->user->data['username'])
							$player->removed = true;
			}
			
			$message = $this->boardManager->ReplaceBBCode32($row['post_text'], $row['bbcode_uid'], $allBoards);
			
			$this->EditMessage($post, $message);
			
			$message = "Message auto : Ajout de la table '".$board->name."' à ".$board->time." pour ".$board->minPlayer." à ".$board->maxPlayer;
			$redirect = $this->ReplyMessage($row['topic_id'], $message);
			redirect($redirect);
			die();
		}
		
		$this->template->assign_vars(array('board' => $board, 'preinscription' => $preinscription));
		$this->template->set_filenames(array(
			'body'	=> "@nathannau_boardmanager/open.html",
		));
		return new Response($this->template->assign_display('body'), 200, array('Content-Type' => 'text/html'));
	}
	public function cancelAction($post, $hash)
	{
		global $phpbb_root_path, $phpEx;

		$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = '.$post.';';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$row['topic_id'].';';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$allBoards = $this->boardManager->ExtractBBCode32($row['post_text'],$row['bbcode_uid']);

		foreach($allBoards as $boards)
		{
			$board = $boards->GetBoardWithHash($hash);
			if ($board==null) continue;
			
			$woBoard = $boards->GetWithoutBoard();
			if ($woBoard==null)
			{
				$woBoard = new Board();
				$boards->AddBoard($woBoard);
			}
			
			$message2 = "Message auto : Annule la table '".$board->name."' à ".$board->time." pour ".$board->minPlayer." à ".$board->maxPlayer."\n";
			foreach($board->players as $player)
			{
				if (!$player->temporary)
				{
					$woBoard->players[] = $player;
					$message2 .= "Message auto : '".$player->name."' passe en sans table\n";
				}
			}
			$boards->RemoveBoard($board);

			$message1 = $this->boardManager->ReplaceBBCode32($row['post_text'], $row['bbcode_uid'], $allBoards);
			
			$this->EditMessage($post, $message1);
			
			$redirect = $this->ReplyMessage($row['topic_id'], $message2);
			
			redirect($redirect);
			die();
		}
		
		$redirect = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'p=' . $post . '#p' . $post);
		redirect($redirect);
		//return new Response('Oki cancelAction '.$post.' '.$hash);
	}
	
	private function EditMessage($idPost, $message)
	{
		global $phpbb_root_path, $phpEx;
		include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		
		$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE post_id = '.$idPost.';';
		$result = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$post['topic_id'].';';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$data = array(
			'post_id' => $post['post_id'],
		//	'poster_id' => $this->user->data['user_id'],
			'poster_id' => $post['poster_id'],
			// General Posting Settings
			'forum_id' => $post['forum_id'],    // The forum ID in which the post will be placed. (int)
			'topic_id' => $post['topic_id'],    // Post a new topic or in an existing one? Set to 0 to create a new one, if not, specify your topic ID here instead.
			'icon_id' => $post['icon_id'],    // The Icon ID in which the post will be displayed with on the viewforum, set to false for icon_id. (int)

			// Defining Post Options
			'enable_bbcode' => true,    // Enable BBcode in this post. (bool)
			'enable_smilies' => (bool)$post['enable_smilies'],    // Enabe smilies in this post. (bool)
			'enable_urls' => (bool)$post['enable_magic_url'],    // Enable self-parsing URL links in this post. (bool)
			'enable_sig' => (bool)$post['enable_sig'],    // Enable the signature of the poster to be displayed in the post. (bool)

			// Message Body
			'message' => $message,        // Your text you wish to have submitted. It should pass through generate_text_for_storage() before this. (string)
			'message_md5' => md5($message),// The md5 hash of your message

			// Values from generate_text_for_storage()
			'bbcode_bitfield' => $post['bbcode_bitfield'],    // Value created from the generate_text_for_storage() function.
			'bbcode_uid' => $post['bbcode_uid'],        // Value created from the generate_text_for_storage() function.

			// Other Options
			'post_edit_reason'	=> '',
			'post_edit_locked'    => 0,        // Disallow post editing? 1 = Yes, 0 = No
			//'topic_title'        => $subject,    // Subject/Title of the topic. (string)

			// Email Notification Settings
			'notify_set'        => true,        // (bool)
			'notify'            => false,        // (bool)
			'post_time'         => 0,        // Set a specific time, use 0 to let submit_post() take care of getting the proper time (int)
			//'forum_name'        => '',        // For identifying the name of the forum in a notification email. (string)

			// Indexing
			'enable_indexing'    => true,        // Allow indexing the post? (bool)

			// 3.0.6
			'force_approved_state'    => true, // Allow the post to be submitted without going into unapproved queue

			// 3.1-dev, overwrites force_approve_state
			'force_visibility'            => true, // Allow the post to be submitted without going into unapproved queue, or make it be deleted
		);
		return submit_post('edit',  $post['post_subject'],  $this->user->data['username'],  $topic['topic_type'], $poll, $data, true);
	}
	
	private function ReplyMessage($idTopic, $message)
	{
		global $phpbb_root_path, $phpEx;
		include_once($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
		
		$sql = 'SELECT * FROM ' . POSTS_TABLE . ' WHERE topic_id = '.$idTopic.' ORDER BY post_time DESC LIMIT 1;';
		$result = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$sql = 'SELECT * FROM ' . TOPICS_TABLE . ' WHERE topic_id = '.$idTopic.';';
		$result = $this->db->sql_query($sql);
		$topic = $this->db->sql_fetchrow();
		$this->db->sql_freeresult($result);
		
		$isLast = $post['poster_id']==$this->user->data['user_id'];
		
		$data = array(
//			'post_id' => $row['post_id'],
			'poster_id' => $this->user->data['user_id'],
			
			// General Posting Settings
			'forum_id' => $post['forum_id'],    // The forum ID in which the post will be placed. (int)
			'topic_id' => $post['topic_id'],    // Post a new topic or in an existing one? Set to 0 to create a new one, if not, specify your topic ID here instead.
			'icon_id' => $post['icon_id'],    // The Icon ID in which the post will be displayed with on the viewforum, set to false for icon_id. (int)

			// Defining Post Options
			'enable_bbcode' => true,    // Enable BBcode in this post. (bool)
			'enable_smilies' => (bool)$post['enable_smilies'],    // Enabe smilies in this post. (bool)
			'enable_urls' => (bool)$post['enable_magic_url'],    // Enable self-parsing URL links in this post. (bool)
			'enable_sig' => (bool)$post['enable_sig'],    // Enable the signature of the poster to be displayed in the post. (bool)
			
			// Message Body
//			'message' => $row['post_text'],        // Your text you wish to have submitted. It should pass through generate_text_for_storage() before this. (string)
//			'message_md5' => md5($row['post_text']),// The md5 hash of your message

			// Values from generate_text_for_storage()
			'bbcode_bitfield' => $post['bbcode_bitfield'],    // Value created from the generate_text_for_storage() function.
			'bbcode_uid' => $post['bbcode_uid'],        // Value created from the generate_text_for_storage() function.

			// Other Options
			'post_edit_reason'	=> '',
			'post_edit_locked'    => 0,        // Disallow post editing? 1 = Yes, 0 = No
			//'topic_title'        => $subject,    // Subject/Title of the topic. (string)
			
			// Email Notification Settings
			'notify_set'        => true,        // (bool)
			'notify'            => false,        // (bool)
			'post_time'         => 0,        // Set a specific time, use 0 to let submit_post() take care of getting the proper time (int)
			//'forum_name'        => '',        // For identifying the name of the forum in a notification email. (string)

			// Indexing
			'enable_indexing'    => true,        // Allow indexing the post? (bool)

			// 3.0.6
			'force_approved_state'    => true, // Allow the post to be submitted without going into unapproved queue

			// 3.1-dev, overwrites force_approve_state
			'force_visibility'            => true, // Allow the post to be submitted without going into unapproved queue, or make it be deleted
		);
		
		$subject = $post['post_subject'];
		if ($isLast)
		{
			$data['post_id'] = $post['post_id'];
			if (preg_match('/^<.>/', $post['post_text']))
				$data['message'] = substr_replace($post['post_text'], "<br/>\r\n<br/>\r\n".$message, -4, 0);
			else
				$data['message'] = $post['post_text']."\n\n".$message;
		}
		else 
		{
			$data['message'] = $message;
			if (strtolower(substr($subject,0,3))!="re:")
				$subject = 'Re: '.$subject;
		}
		$data['message_md5'] = md5($message);
		
		return submit_post($isLast?'edit':'reply', $subject,  $this->user->data['username'],  $topic['topic_type'], $poll, $data, true);
	}
	
	
}
