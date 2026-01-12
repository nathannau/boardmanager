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
class Board
{
    const NAME = 'table';
    const TYPE = 'type';
    const URL = 'url';
    const TIME = 'heure';
    const MIN = 'min';
    const MAX = 'max';
    const MJ = 'mj';
    const PLAYER = 'joueur';
	const TRIGGER_WARNING = 'tg';
	const DESCRIPTION = 'description';
    
    var $isWithoutTable;
    var $name;
    var $type;
    var $url;
    var $time;
    var $minPlayer;
    var $maxPlayer;
    var $mj;
    var $players;
	var $trigger_warning;
	var $description;
    
    public function __construct($from='')
    {
        $this->isWithoutTable = true;
        $this->players = array();
        
        $from = str_replace("\r","", $from);
        $lines = explode("\n", $from);
        foreach($lines as $line)
        {
            $detail = explode(':', $line, 2);
            switch($detail[0])
            {
                case board::NAME:
                    $this->name = $detail[1];
                    $this->isWithoutTable = $this->name=="";
                    break;
                case board::TYPE:
                    $this->type = $detail[1];
                    break;
                case board::URL:
                    $this->url = $detail[1];
					if ($this->url!='' && preg_match('/<a href="([^"]*)"/i', $this->url, $match))
						$this->url = $match[1];
                    break;
                case board::TIME:
                    $this->time = $detail[1];
                    break;
                case board::MIN:
                    $this->minPlayer = $detail[1];
                    break;
                case board::MAX:
                    $this->maxPlayer = $detail[1];
                    break;
                case board::MJ:
                    $this->mj = $detail[1];
                    break;
                case board::TRIGGER_WARNING:
                    $this->trigger_warning = $detail[1];
                    break;
                case board::DESCRIPTION:
                    $this->description = $detail[1];
                    break;
                case board::PLAYER:
                    $this->players[] = new Player($detail[1]);
                    break;
            }
        }
    }

	public function Hash()
	{
		$ps = "";
		foreach($this->players as $player) $ps .= $player->Hash();
		$k = ($this->isWithoutTable?'0':'1').'|'.
			$this->name.'|'.
			$this->time.'|'.
			$this->minPlayer.'|'.
			$this->maxPlayer.'|'.
			$this->mj.'|'.
			$ps;
		return md5($k);
	}
	
    public function ToHTML(\phpbb\user $user, $allreadyInBoard, $canAdd, $postId, $endDate)
    {
		global $phpbb_container;
		$helper = $phpbb_container->get('controller.helper');
		
		$username = $user->data['username'];
		$isAnomymous = $user->data['user_id'] == ANONYMOUS;
		$isBot = $user->data['is_bot'];
		$hash = $this->Hash();

        $ret = '';
        $ret .= '<div class="board" style="border: 1px solid #ccc; padding: 10px">';
        if ($this->isWithoutTable)
		{
			$nbPlayer = 0;
			foreach($this->players as $player)
				if (!$player->removed)
					$nbPlayer++;
			
            $ret .= '<span style="font-weight: bold">Sans table</span> ' . $nbPlayer . ' joueur' . (($nbPlayer>1)?'s':'');
			
			if (!$isBot && !$isAnomymous && !$allreadyInBoard && !$this->IsInBoard($user))
				$ret .= ' <a href="'.
					$helper->route('nathannau_boardmanager_register', array('post'=>$postId, 'hash'=>$hash))
					.'">m\'inscrire en sans table</a>';
            $ret .= '<br/>';
		}
        else
        {
            $name = $this->name;
            if ($this->url!='')
                $name = '<a href="'.$this->url.'">'.$name.'</a>';

			$types = ["os"=>"One shot","co"=>"Campagne ouverte","cf"=>"Campagne fermée","ts"=>"Table de secours",];
			$type = isset($types[$this->type]) ? '('. $types[$this->type] .')' :'';
            $ret .= '<span style="font-weight: bold"><span style="text-decoration: underline">'.
				$name.
				'</span> '.
				'<span class="type">'.$type.'</span> '.
				$this->minPlayer
				.'-'.
				$this->maxPlayer
				.' joueurs '.
				$this->time
				.'</span>';
			
			if ($this->NbPlayer()<$this->maxPlayer && !$isBot && !$isAnomymous && !$allreadyInBoard && $canAdd)
				$ret .= ' <a href="'.
					$helper->route('nathannau_boardmanager_register', array('post'=>$postId, 'hash'=>$hash))
					.'">m\'inscrire a la table</a>';
			$ret .= '<br/>';

			if (trim($this->description)!="")
				$ret .= '<i>'.$this->description.'</i><br/>';

			if (trim($this->trigger_warning)!="")
			{
				$ret .= '<b style="color:#800; display: flex; align-items: center;"><svg height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f"><path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/></svg> '.
				$this->trigger_warning.'</b>';
			}

			$ret .= '<span style="font-weight: bold">'.
				$this->mj.
				'</span>';
			if ($username == $this->mj)
				$ret .= ' <a href="'.
					$helper->route('nathannau_boardmanager_cancel', array('post'=>$postId, 'hash'=>$hash))
					.'">annuler ma table</a>';
			$ret .= '<br/>';
        }
            
        foreach($this->players as $player)
		{
			$inRed = $player->DayTo($endDate) < Boards::LIMIT_DAYS;
			if ($inRed) $ret .= '<span style="color:#880000;">';
			
			if ($player->name == $username && !$player->removed && !$player->temporary)
				$ret .= $player->name.' <a href="'.
					$helper->route('nathannau_boardmanager_unregister', array('post'=>$postId, 'hash'=>$hash))
					.'">annuler ma présence</a>';
			else if ($player->name == $username && $player->temporary)
				$ret .= $player->name.' * <a href="'.
					$helper->route('nathannau_boardmanager_unregister', array('post'=>$postId, 'hash'=>$hash))
					.'">annuler ma présence</a> - <a href="'.
					$helper->route('nathannau_boardmanager_register', array('post'=>$postId, 'hash'=>$hash))
					.'">confirmer ma présence</a>';
			else if ($player->removed)
				$ret .= '<span style="text-decoration: line-through">'.$player->name.($player->temporary?' *':'').'</span>';
			else
				$ret .= $player->name.($player->temporary?' *':'');
			
			if ($inRed) $ret .= '</span>';
			$ret .= '<br/>';
		}
		$ret .= '</div>';
        return $ret;
    }
	
    public function ToBBCode()
    {
        $ret = "";
        if (!$this->isWithoutTable)
        {
			$ret .= board::NAME . ':' . $this->name . "\n";
			$ret .= board::TYPE . ':' . $this->type . "\n";
			$ret .= board::URL . ':' . $this->url . "\n";
			$ret .= board::TIME . ':' . $this->time . "\n";
			$ret .= board::MIN . ':' . $this->minPlayer . "\n";
			$ret .= board::MAX . ':' . $this->maxPlayer . "\n";
			$ret .= board::MJ . ':' . $this->mj . "\n";
			$ret .= board::TRIGGER_WARNING . ':' . $this->trigger_warning . "\n";
			$ret .= board::DESCRIPTION . ':' . $this->description . "\n";
		}
		foreach ($this->players as $player)
			$ret .= board::PLAYER . ':' . $player->ToBBCode() . "\n";

        return $ret;
    }

	public function IsInBoard(\phpbb\user $user)
	{
		$username = $user->data['username'];
		//$ret = '--'.$username.'--<br/>';
		foreach($this->players as $player)
			if ($player->name == $username && !$player->removed)
				return true;
		return !$this->isWithoutTable && $this->mj == $username;
	}

	public function NbPlayer()
	{
		$nb=0;
		foreach($this->players as $player)
			if (!$player->removed) 
				$nb++;
		return $nb++;
	}

    public function IsValid() 
	{ 
		return 
			preg_match('/^\d+$/',$this->minPlayer) && 
			preg_match('/^\d+$/',$this->minPlayer) && 
			$this->minPlayer > 0 && 
			$this->maxPlayer>=$this->minPlayer && 
			trim($this->name)!='' &&
			trim($this->time)!='' &&
			trim($this->mj)!='';
	}
}
