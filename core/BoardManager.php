<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager\core;

/**
* 
*/
class BoardManager
{
	protected $db;
	protected $user;
//	protected $auth;
	protected $request;
	
	protected $bbcode_id;

	protected $first_pass_match;
	protected $first_pass_replace;
	protected $second_pass_match;
	protected $second_pass_replace;

	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, /*\phpbb\auth\auth $auth,*/ \phpbb\request\request $request)
	{
		$this->db = $db;
		$this->user = $user;
//		$this->auth = $auth;
		$this->request = $request;
		
		$sql = 'SELECT * FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'boardmanager'";
		$result = $this->db->sql_query($sql);
		$this->bbcode_id = $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);
		
		if (!class_exists('acp_bbcodes'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/acp/acp_bbcodes.'.$phpEx);
		}
		
		$bbcode_tool = new \acp_bbcodes();
                $match='[boardmanager {NUMBER}]{TEXT}[/boardmanager]';
//		$match='[boardmanager]{TEXT}[/boardmanager]';
		$tmp='{TEXT}';
		$data = $bbcode_tool->build_regexp($match, $tmp);
//echo '<pre>';print_r([$data, $match, $tmp]);echo '</pre>';
		$this->first_pass_match = $data['first_pass_match'];
		$this->first_pass_replace = $data['first_pass_replace'];
		$this->second_pass_match =  '/\\[boardmanager (\\d+)(?::$uid)?\\](.*?)\\[\\/boardmanager(?::$uid)?\\]/s';//$data['second_pass_match'];
		$this->second_pass_replace = '$2'; // $data['second_pass_replace'];
	}

	public function get_first_pass_match() { return $this->first_pass_match; }
	public function get_first_pass_replace() { return $this->first_pass_replace; }
	public function get_second_pass_match() { return $this->second_pass_match; }
	public function get_second_pass_replace() { return $this->second_pass_replace; }
	
	public function ExecuteBBCode($bitfield, $flags, $text, $uid)
	{

		$bbcode_infos = $this->GetBBCodeInfos($bitfield);

        if ($bbcode_infos==null) 
            return $text;

		$postId = 0;
		$topicId = 0;
		if($this->request->is_set('t'))
		{
			$topicId = $this->request->variable('t',0);
		}
		else if($this->request->is_set('p'))
		{
			$postId = $this->request->variable('p',0);
			$sql = 'SELECT * FROM ' . POSTS_TABLE . " WHERE post_id = '".$this->db->sql_escape($postId)."'";
			$result = $this->db->sql_query($sql);
			$topicId = $this->db->sql_fetchfield('topic_id');
			$this->db->sql_freeresult($result);
		}
		if (!$topicId) return $text;
		$sql = 'SELECT * FROM ' . POSTS_TABLE . " WHERE topic_id = '".$this->db->sql_escape($topicId)."' AND bbcode_uid = '".$this->db->sql_escape($uid)."' ORDER BY post_id";
		$result = $this->db->sql_query($sql);
		$postId = $this->db->sql_fetchfield('post_id');
		$this->db->sql_freeresult($result);

		if (!$postId) return $text;
		
        $bbcode_detail = $bbcode_infos['preg'];
//		echo '<pre>'; var_dump($bbcode_infos); echo '</pre>';
        list($pattern, $to) = each($bbcode_detail);
        $pattern = str_replace('$uid', $uid, $pattern);
//        echo '<pre>'.$pattern.'<br/>'.$text.'</pre>';
		$that = $this;
        return preg_replace_callback($pattern, function($match) use ($that, $postId) 
		{
			$date = $match[1];
			$value = $match[2];
			$boards = new Boards($value, $date);
			return $boards->ToHTML($that->user, $postId).'<br/>';
		}, $text);
	}
	public function ExecuteBBCode32($date, $text, $uid)
	{
		$postId = 0;
		$topicId = 0;
		if($this->request->is_set('t'))
		{
			$topicId = $this->request->variable('t',0);
		}
		else if($this->request->is_set('p'))
		{
			$postId = $this->request->variable('p',0);
			$sql = 'SELECT * FROM ' . POSTS_TABLE . " WHERE post_id = '".$this->db->sql_escape($postId)."'";
			$result = $this->db->sql_query($sql);
			$topicId = $this->db->sql_fetchfield('topic_id');
			$this->db->sql_freeresult($result);
		}
		if (!$topicId) return $text;
		$sql = 'SELECT * FROM ' . POSTS_TABLE . " WHERE topic_id = '".$this->db->sql_escape($topicId)."' AND bbcode_uid = '".$this->db->sql_escape($uid)."' ORDER BY post_id";
		$result = $this->db->sql_query($sql);
		$postId = $this->db->sql_fetchfield('post_id');
		$this->db->sql_freeresult($result);

		if (!$postId) return $text;

		$boards = new Boards($text, $date);
		return $boards->ToHTML($this->user, $postId).'<br/>';
	}

	public function ExtractBBCode($text, $uid, $bitfield) //$postrow)
	{
		/*
		$bitfield = $postrow['bbcode_bitfield'];
		$uid = $postrow['bbcode_uid'];
		$text = $postrow['post_text'];
		*/
		
		$bbcode_infos = $this->GetBBCodeInfos($bitfield);

        if ($bbcode_infos==null) 
            return array();

        $bbcode_detail = $bbcode_infos['preg'];
        list($pattern, $to) = each($bbcode_detail);
        $pattern = str_replace('$uid', $uid, $pattern);

		preg_match_all($pattern, $text, $matches);
		$matches = array_merge_all($matches[1], $matches[2]);

		return array_map(function($match) {
			return new Boards($match[1], $match[0]);
		}, $matches);
	}

	public function ExtractBBCode32($text, $uid)
	{
		$first_pass_replace = str_replace('$uid', $uid, $this->first_pass_replace);
		$text = preg_replace($this->first_pass_match, $first_pass_replace, $text);
		$second_pass_match = str_replace('$uid', $uid, $this->second_pass_match);
		preg_match_all($second_pass_match, $text, $matches);
		$matches = array_merge_all($matches[1], $matches[2]);

		return array_map(function($match) {
			return new Boards($match[1], $match[0]);
		}, $matches);
	}
	
	public function ReplaceBBCode($text, $uid, $bitfield, /*&$postrow,*/ $allBoards)
	{
		/*
		$bitfield = $postrow['bbcode_bitfield'];
		$uid = $postrow['bbcode_uid'];
		$text = $postrow['post_text'];
		*/
		$bbcode_infos = $this->GetBBCodeInfos($bitfield);
		
        if ($bbcode_infos==null) 
            return null;

        $bbcode_detail = $bbcode_infos['preg'];
        list($pattern, $to) = each($bbcode_detail);
        $pattern = str_replace('$uid', $uid, $pattern);
		
		$index = 0;
		$text = preg_replace_callback($pattern, function($match) use ($uid, &$index, $allBoards) 
		{
			$boards = $allBoards[$index++];
			return '[boardmanager '.$boards->GetDateAsString().':'.$uid.']'.$boards->ToBBCode().'[/boardmanager:'.$uid.']';
		}, $text);
		return /*$postrow['post_text'] =*/ $text;
	}

	public function ReplaceBBCode32($text, $uid, $allBoards)
	{
		/*
		$bitfield = $postrow['bbcode_bitfield'];
		$uid = $postrow['bbcode_uid'];
		$text = $postrow['post_text'];
		*/
		$first_pass_replace = str_replace('$uid', $uid, $this->first_pass_replace);
		$text = preg_replace($this->first_pass_match, $first_pass_replace, $text);
		$second_pass_match = str_replace('$uid', $uid, $this->second_pass_match);
		
		$index = 0;
		$text = preg_replace_callback($second_pass_match, function($match) use ($uid, &$index, $allBoards) 
		{
			$boards = $allBoards[$index++];
			return '[boardmanager '.$boards->GetDateAsString().':'.$uid.']'.$boards->ToBBCode().'[/boardmanager:'.$uid.']';
		}, $text);
		return $text;
	}

	public function GetBBCodeId()
	{
		$sql = 'SELECT * FROM ' . BBCODES_TABLE . " WHERE LOWER(bbcode_tag) = 'boardmanager'";
		$result = $this->db->sql_query($sql);
		$bbcode_id = $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);
		
		return $bbcode_id;
	}

	public function GetBBCodeInfos($bitfield)
	{
		static $bbcode, $bbcode_id;
		
		if (!class_exists('bbcode'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
		}
		if (empty($bbcode))
			$bbcode = new \bbcode($bitfield);
		else
			$bbcode->bbcode($bitfield);
		
		$bbcode->bbcode_cache_init();

		if (empty($bbcode_id))
			$bbcode_id = $this->GetBBCodeId();
        
		return isset($bbcode->bbcode_cache[$bbcode_id]) ? $bbcode->bbcode_cache[$bbcode_id] : null;
	}
	
}

if (!function_exists("array_merge_all"))
{
	function array_merge_all()
	{
		$ret = array();
		foreach(func_get_args() as $mi=>$ma)
		{
			foreach($ma as $si=>$sa)
			{
				if (!isset($ret[$si])) $ret[$si] = array();
				$ret[$si][$mi] = $sa;
			}
		}
		return $ret;
	}
}
