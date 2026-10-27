<?php
/**
 *
 * BoardManager extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Nathan Nau
 * @license MIT (voir le fichier LICENSE)
 *
 */

namespace nathannau\boardmanager2\ucp;

/**
* Liaison du compte Discord du membre : génération du code à saisir avec /lier, déliaison
*/
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');
		/** @var \phpbb\request\request $request */
		$request = $phpbb_container->get('request');
		/** @var \phpbb\template\template $template */
		$template = $phpbb_container->get('template');
		/** @var \phpbb\user $user */
		$user = $phpbb_container->get('user');
		/** @var \phpbb\log\log_interface $log */
		$log = $phpbb_container->get('log');
		/** @var \nathannau\boardmanager2\core\discord_links $links */
		$links = $phpbb_container->get('nathannau.boardmanager2.discord_links');

		$language->add_lang('common', 'nathannau/boardmanager2');
		$this->tpl_name = '@nathannau_boardmanager2/ucp_boardmanager2_discord';
		$this->page_title = 'UCP_BOARDMANAGER2_DISCORD';
		$form_key = 'nathannau_boardmanager2_ucp_discord';
		add_form_key($form_key);

		$user_id = (int) $user->data['user_id'];
		$code = '';

		if ($request->is_set_post('generate') || $request->is_set_post('unlink'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . '<br /><br />' . $language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'), E_USER_WARNING);
			}
		}

		if ($request->is_set_post('unlink'))
		{
			$link = $links->get_by_user($user_id);
			$links->unlink($user_id);
			if ($link)
			{
				$log->add('admin', $user_id, $user->ip, 'LOG_BM2_DISCORD_UNLINKED', false, array($user->data['username'], $link['discord_username']));
			}
			meta_refresh(3, $this->u_action);
			trigger_error($language->lang('UCP_BM2_DISCORD_UNLINKED') . '<br /><br />' . $language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>'));
		}

		$link = $links->get_by_user($user_id);
		if (!$link && $request->is_set_post('generate'))
		{
			$code = $links->create_code($user_id);
		}

		$template->assign_vars(array(
			'BM2_LINKED'			=> (bool) $link,
			'BM2_DISCORD_USERNAME'	=> $link ? $link['discord_username'] : '',
			'BM2_LINKED_TIME'		=> $link ? $user->format_date($link['linked_time']) : '',
			'BM2_CODE'				=> $code,
			'BM2_CODE_MINUTES'		=> (int) (\nathannau\boardmanager2\core\discord_links::CODE_TTL / 60),
			'BM2_PENDING'			=> !$link && !$code && $links->get_pending_code($user_id),
			'U_ACTION'				=> $this->u_action,
		));
	}
}
