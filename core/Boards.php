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
class Boards
{
	const SEPARATOR = "----------";
	const LIMIT_DAYS = 4;
	private $boards;
	private $date;
	
	public function GetDateAsString()
	{
		return $this->date;
	}
	public function GetDate()
	{
		$d = substr($this->date, 0, 2);
		$m = substr($this->date, 2, 2);
		$y = substr($this->date, 4, 4);
		
		return mktime(0, 0, 0, $m, $d, $y);
	}
	 
	public function __construct($from, $date)
	{
		$this->boards = array();
		$this->date = $date;

		$from = preg_replace('|<br/?>|i' , '', $from);
        $board_datas = explode(Boards::SEPARATOR, $from);
        
        foreach($board_datas as $board_data)
        {
            $this->boards[] = new Board($board_data);
        }
	}

	public function IsInBoard(\phpbb\user $user, $checkWithoutBoard = false)
	{
		foreach($this->boards as $board)
			if (($checkWithoutBoard || !$board->isWithoutTable) && $board->IsInBoard($user))
				return true;
		return false;
	}

	public function ToHTML(\phpbb\user $user, /*$uid,*/ $postId)
    {
		global $phpbb_container;
		$helper = $phpbb_container->get('controller.helper');
		
		$ret = "";
		
		$userId = $user->data['user_id'];
		$username = $user->data['username'];
		$isAnomymous = $user->data['user_id'] == ANONYMOUS;
		$isBot = $user->data['is_bot'];
		
		$isInBoard = $this->IsInBoard($user);
		$userDate = $this->FindUserDateInWithoutBoard($user);
		if ($userDate!==null)
		{
			$d1 = mktime(0, 0, 0, substr($userDate, 2, 2), substr($userDate, 0, 2), substr($userDate, 4, 4));
			$d2 = mktime(0, 0, 0, substr($this->date, 2, 2), substr($this->date, 0, 2), substr($this->date, 4, 4));
			$canAdd = ($d2-$d1)/86400 >= Boards::LIMIT_DAYS -1;
		}
		else
		{
			$d1 = time(); //mktime(0,0,1, 13, 12, 2016); // 
			$d2 = mktime(0, 0, 0, substr($this->date, 2, 2), substr($this->date, 0, 2), substr($this->date, 4, 4));
			$canAdd = ($d2-$d1)/86400 >= Boards::LIMIT_DAYS -1;
		}
		
        foreach($this->boards as $board)
			$ret .= $board->ToHTML($user, $isInBoard, $canAdd, $postId, $this->date).'<br/>';
		
		if (!$isInBoard && !$isAnomymous && !$isBot)
			$ret .= '<a href="'.
				$helper->route('nathannau_boardmanager_open', array('post'=>$postId))
				.'">ouvrir une table</a><br/>';
		
		$dispoMin = 0;
		$dispoMax = 0;
        foreach($this->boards as $board)
			if (!$board->isWithoutTable)
			{
				$nbPlayer = $board->NbPlayer();
				$dispoMin += max(0, $board->minPlayer - $nbPlayer);
				$dispoMax += $board->maxPlayer - $nbPlayer;
			}
			
		$ret .= '<br/>';
		$ret .= 'Il reste '.$dispoMin.' place'.(($dispoMin>1)?'s':'').' à remplir pour lancer toutes les tables<br/>';
		$ret .= 'Il reste un maximum de '.$dispoMax.' place'.(($dispoMax>1)?'s':'').'<br/>';
				
		return $ret;
	}
	
	private function FindUserDateInWithoutBoard(\phpbb\user $user)
	{
		$board = $this->GetWithoutBoard();
		if ($board==null) return null;
		
		$username = $user->data['username'];
		foreach($board->players as $player)
			if ($player->name == $username && !$player->removed)
				return $player->date;
		return null;
	}

	public function ToBBCode()
    {
		$ret = "";
		$isFirst = true;
        foreach($this->boards as $board)
        {
			if (!$isFirst) $ret .= Boards::SEPARATOR."\n"; 
			$ret .= $board->ToBBCode();
			$isFirst = false;
        }
		return $ret;
	}

	public function GetBoardWithHash($hash)
	{
		foreach($this->boards as $board)
			if ($board->Hash()==$hash)
				return $board;
		return null;
	}

	public function GetWithoutBoard()
	{
		foreach($this->boards as $board)
			if ($board->isWithoutTable)
				return $board;
		return null;
	}
	
	public function AddBoard(Board $board)
	{
		for($i=0; $i<count($this->boards); $i++)
		{
			$tmp = $this->boards[$i];
			if ($tmp->isWithoutTable)
			{
				$this->boards[$i] = $board;
				$board = $tmp;
			}
		}
		$this->boards[] = $board;
	}
	public function RemoveBoard(Board $board)
	{
		for($i=count($this->boards)-1; $i>=0; $i--)
		{
			if ($this->boards[$i]==$board)
				unset($this->boards[$i]);
		}
	}
}