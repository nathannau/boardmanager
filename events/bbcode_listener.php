<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace nathannau\boardmanager\events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use nathannau\boardmanager\core\BoardManager;

/**
* Extends EventSubscriberInterface
*/
class bbcode_listener implements EventSubscriberInterface
{
    protected $boardManager;

    public function __construct(BoardManager $boardManager)
    {
        $this->boardManager = $boardManager;
    }

    static public function getSubscribedEvents()
    {
        return array(
            'core.modify_text_for_display_before'               => 'modify_text_for_display_before',
            'core.modify_text_for_display_after'                => 'modify_text_for_display_after',
//            'core.text_formatter_s9e_render_before'             => 'text_formatter_s9e_render_before',
//            'core.text_formatter_s9e_render_after'              => 'text_formatter_s9e_render_after',
//            'core.modify_submit_post_data'                      => 'modify_submit_post_data',
//            'core.viewtopic_modify_post_row'                    => 'viewtopic_modify_post_row',
//            'core.text_formatter_s9e_parser_setup'              => 's9e_allow_custom_bbcodes',

/*
            'core.modify_text_for_display_before'               => 'parse_bbcodes_before',
            'core.user_setup'                                   => 'load_language_on_setup',
            'core.modify_text_for_display_after'                => 'parse_bbcodes_after',
            'core.display_custom_bbcodes'                       => 'setup_custom_bbcodes',
            'core.display_custom_bbcodes_modify_sql'            => 'custom_bbcode_modify_sql',
            'core.display_custom_bbcodes_modify_row'            => 'display_custom_bbcodes',
            'core.modify_format_display_text_after'             => 'parse_bbcodes_after2',
            'core.modify_bbcode_init'                           => 'allow_custom_bbcodes',
*/
        );
    }
/*
//public function parse_bbcodes_before() { echo "parse_bbcodes_before<br/>"; }
public function load_language_on_setup() { echo "load_language_on_setup<br/>"; }
public function parse_bbcodes_after($event) { echo "parse_bbcodes_after<br/>"; var_dump(($event->get_data())); }
public function setup_custom_bbcodes() { echo "setup_custom_bbcodes<br/>"; }
public function custom_bbcode_modify_sql() { echo "custom_bbcode_modify_sql<br/>"; }
public function display_custom_bbcodes() { echo "display_custom_bbcodes<br/>"; }
public function parse_bbcodes_after2() { echo "parse_bbcodes_after<br/>"; }
public function allow_custom_bbcodes() { echo "allow_custom_bbcodes<br/>"; }
public function text_formatter_s9e_render_before() { echo "text_formatter_s9e_render_before<br/>"; }
public function text_formatter_s9e_render_after() { echo "text_formatter_s9e_render_after<br/>"; }
*/

    public function modify_text_for_display_before($event) {
//        echo "modify_text_for_display_before<br/>";
        $uid = $event['uid'];
        if (!$uid) return;
        //$flags = $event['flags'];
        //$bitfield = $event['bitfield'];
        $text = $event['text'];
        $first_pass_replace = str_replace('$uid', $uid, $this->boardManager->get_first_pass_replace());
//        echo 'first_pass_replace : '.$first_pass_replace.'<hr/>'.$text.'<hr/>';
//echo '<pre>';debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);die();
        $event['text'] = preg_replace($this->boardManager->get_first_pass_match(), $first_pass_replace, $text);
//$event['text'] = 'prout';
//        echo 'text : '.$event['text'].'<hr/>';
    }

    public function modify_text_for_display_after($event) {
//        echo "modify_text_for_display_after<br/>";
        $uid = $event['uid'];
        if (!$uid) return;
        $text = $event['text'];
        $second_pass_match = str_replace('$uid', $uid, $this->boardManager->get_second_pass_match());
//echo 'uid : '.$uid.'<hr/>';
//echo 'second_pass_match : '.$second_pass_match.'<hr/>';
        $boardManager = $this->boardManager;
//echo '<pre>';
//echo $text.'<hr/>';
//print_r($boardManager); die();
        $event['text'] = preg_replace_callback(
            $second_pass_match, 
            function($matches) use($boardManager, $uid) {
//die('la<br/>');
//print_r([$matches, $boardManager, $uid]);
return $boardManager->ExecuteBBCode32($matches[1], $matches[2], $uid); }, 
            $text);
        //$text;
// $event['text'] = 'prout';
//echo $event['text'].'<hr/>';
//die();
    }

    public function modify_submit_post_data($event)
    {
//	$vars = array(
//		'mode',
//		'subject',
//		'username',
//		'topic_type',
//		'poll',
//		'data',
//		'update_message',
//		'update_search_index',
//	);
        $data = $event->get_data();
        $data['data']['message']='...';
        echo '<pre>';
//		print_r(get_class($event)).'<br/>';
//		print_r($event['mode']).'<br/>';
        foreach ($data as $k=>$v)
            echo $k.' : '.print_r($v, true).'<br/>';
        //print_r($event);
        die();
//		function submit_post($mode, $subject, $username, $topic_type, &$poll, &$data, $update_message = true, $update_search_index = true)
    }

	/*
	public function s9e_allow_custom_bbcodes($event)
	{
		die();
		echo '<pre>'; var_dump('lala'); echo '</pre>'; 
		if (defined('IN_CRON'))
		{
			return; // do no apply bbcode permissions if in a cron job (for 3.1 to 3.2 update reparsing)
		}

		/** @var $service \phpbb\textformatter\s9e\parser object from the text_formatter.parser service * /
		$service = $event['parser'];
		$parser = $service->get_parser();
		/*
		foreach ($parser->registeredVars['abbc3.bbcode_groups'] as $bbcode_name => $groups)
		{
			if (!$this->bbcodes_display->user_in_bbcode_group($groups))
			{
				$bbcode_name = rtrim($bbcode_name, '=');
				$service->disable_bbcode($bbcode_name);
			}
		}* /
	}
	*/

}
