<?php
/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
|| + Inferno Technologies Software
|| +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
|| + Product: Inferno vBShout Pro
|| +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
|| + Website: http://infernotechnologies.net
|| + Email: webmaster@infernotechnologies.net
|| + Copyright 2004 - 2006 Inferno Technologies
|| + All Rights Reserved
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */



/**
 * Extension to shoutbox engine
 * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */

class shout extends infernoshout_engine
{
	var $userid;
	var $me = 0;
	var $taglist = false;
	var $parser = false;
	var $silent = false;
	var $doshout = true;
	var $admincom = false;
	var $private = -1;

	function update_aop_file()
	{
		$fp = fopen(DIR . '/infernoshout/aop/aop.php', 'w+');
		@fwrite($fp, TIMENOW);
		@fclose($fp);
	}

	function update_activity()
	{
		if ($this->vbulletin->options['ishout_aop'])
		{
			$this->update_aop_file();
		}

		$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutsessions set s_activity='" . TIMENOW . "' where s_user='{$this->vbulletin->userinfo['userid']}'");

		if ($this->vbulletin->db->affected_rows() < 1)
		{
			$this->vbulletin->db->query("insert into " . TABLE_PREFIX . "infernoshoutsessions (s_activity, s_user) values ('" . TIMENOW . "', '{$this->vbulletin->userinfo['userid']}')");
		}
	}

	function process($message = '', $userid = -1, $perms = -1)
	{
		if (($this->vbulletin->options['ishout_lockdown'] > 0 && $this->vbulletin->userinfo['userid'] != $this->vbulletin->options['ishout_lockdown']) || $this->is_banned())
		{
			echo 'completed';
			exit;
		}

		$this->fetch_data($userid, $perms);
		$this->is_action_code($message);

		if ($this->vbulletin->options['ishout_autodelete'] > 0)
		{
			$this->vbulletin->db->query("delete from " . TABLE_PREFIX . "infernoshout where s_time < " . (TIMENOW - $this->vbulletin->options['ishout_autodelete']));
		}

		if ($this->admincom && $this->vbulletin->options['ishout_disable_acom'])
		{
			$this->doshout = false;
		}

		if (!$this->doshout)
		{
			echo 'completed';
			exit;
		}

		if ($this->vbulletin->options['ishout_flood'] > 0 && VB_AREA != 'AdminCP' && THIS_SCRIPT != 'cron')
		{
			$last = $this->vbulletin->db->query_first("select s_time from " . TABLE_PREFIX . "infernoshout where s_user='{$this->userid}' order by s_time desc limit 1");

			if ($last['s_time'] > 0 && !(TIMENOW >= ($last['s_time'] + $this->vbulletin->options['ishout_flood'])))
			{
				echo 'flood!';
				exit;	
			}
		}

		if ($this->vbulletin->options['ishout_maxbbsize'] > 0 && ($this->vbulletin->options['ishout_bbcodes'] & 4))
		{
			$this->limit_sizebb($message);
		}

		$message = addslashes(convert_urlencoded_unicode($message));

		$this->vbulletin->db->query("
			insert into " . TABLE_PREFIX . "infernoshout
			(s_user, s_time, s_shout, s_me, s_private)
			values
			({$this->userid}, " . TIMENOW . ", '$message', '{$this->me}', {$this->private})
		");

		$this->update_activity();

		if ($this->silent)
		{
			return true;
		}

		echo 'completed';
		exit;
	}

	function size_bb($num)
	{
		if (intval($num) > $this->vbulletin->options['ishout_maxbbsize'])
		{
			$num = $this->vbulletin->options['ishout_maxbbsize'];
		}

		return '[size=' . $num . ']';
	}

	function limit_sizebb(&$message)
	{
		$message = preg_replace("#\[size=(\d+)\]#ie", "\$this->size_bb('\\1')", $message);
	}

	function is_action_code(&$message)
	{
		if (preg_match("#^(/me\s+?)#i", $message, $matches))
		{
			$this->me = 1;

			$message = trim(str_replace($matches[0], '', $message));

			return true;
		}

		if (trim($message) == '/prune' && $this->can_do_admin())
		{
			if ($this->vbulletin->options['ishout_logging_high'])
			{
				$this->load_engine('log');

				$log = new log;
				$log->snapshot('prune');
			}

			$this->vbulletin->db->query("delete from " . TABLE_PREFIX . "infernoshout");
			$this->me = 1;

			$message = 'удалил все сообщения чата';
			$this->admincom = true;

			return true;
		}

		if (preg_match("#^(/prune\s+?)#i", $message, $matches) && $this->can_do_admin())
		{
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($pruneuser = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				if ($this->vbulletin->options['ishout_logging_high'])
				{
					$this->load_engine('log');

					$log = new log;
					$log->snapshot('pruneuser', $pruneuser['username']);
				}

				$this->vbulletin->db->query("delete from " . TABLE_PREFIX . "infernoshout where s_user='{$pruneuser['userid']}'");
				$this->me = 1;

				$message = 'админ удалил сообщения от ' . $pruneuser['username'];
				$this->admincom = true;

				return true;
			}
		}

		if (preg_match("#^(/pm\s+)([^;]+?);(.+?)$#i", $message, $matches) && !$this->vbulletin->options['ishout_disable_pm'])
		{
			$this->doshout = true;

			$user = htmlspecialchars_uni(addslashes(trim($matches[2])));

			if ($pmuser = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$this->doshout = true;
				$this->private = $pmuser['userid'];
				$message = trim($matches[3]);
			}
		}

		if (preg_match("#^(/ignore\s+?)#i", $message, $matches))
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$settings =& $this->fetch_user_settings($this->vbulletin->userinfo['userid']);

				$ignored = explode(',', $settings['s_ignored']);

				if (is_array($ignored))
				{
					if (!in_array($user['userid'], $ignored))
					{
						$ignored[] = $user['userid'];
					}
				}

				$ignored = addslashes(implode(',', $ignored));

				$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_ignored='{$ignored}' where s_user='{$this->vbulletin->userinfo['userid']}'");
			}

			return true;
		}

		if (preg_match("#^(/unignore\s+?)#i", $message, $matches))
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$settings =& $this->fetch_user_settings($this->vbulletin->userinfo['userid']);

				$ignored = explode(',', $settings['s_ignored']);

				if (is_array($ignored))
				{
					foreach ($ignored as $key => $userid)
					{
						if ($userid == $user['userid']) 
						{
							unset($ignored[$key]);
						}
					}
				}

				$ignored = addslashes(implode(',', $ignored));

				$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_ignored='{$ignored}' where s_user='{$this->vbulletin->userinfo['userid']}'");
			}

			return true;
		}

		if ((preg_match("#^(/notice\s+?)#i", $message, $matches) || trim($message) == '/removenotice') && $this->can_do_admin())
		{
			if (trim($message) != '/removenotice')
			{
				$message = addslashes(convert_urlencoded_unicode(trim(str_replace($matches[0], '', $message))));
			}
			else
			{
				$message = '';
			}

			if ($this->vbulletin->options['ishout_logging'])
			{
				$this->load_engine('log');

				$log = new log;
				$log->log_action(
					trim($message) != '' ? "Уведомление было изменено. <box>Старое: " . (($this->vbulletin->options['ishout_notice']) ? $this->vbulletin->options['ishout_notice'] : 'отсутсвовало') . "</box><box>Новое: {$message}</box>" : 'очищено',
					'notice'
				);
			}

			$this->vbulletin->db->query("update " . TABLE_PREFIX . "setting set value='{$message}' where varname='ishout_notice'");

			$this->doshout = false;

			$this->update_activity();
			$this->build_options();

			return true;
		}

		if (preg_match("#^(/ban\s+?)#i", $message, $matches) && $this->can_do_admin())
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username, usergroupid, membergroupids from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$banned = $this->fetch_banned();

				if (!in_array($user['userid'], $banned) && !$this->is_in_ug_list($this->vbulletin->options['ishout_protectedugs'], $user))
				{
					$banned[] = $user['userid'];
					$banned = addslashes(implode(',', $banned));

					$this->doshout = true;
					$this->me = true;

					$message = 'забанил пользователя ' . $user['username'] . ' в чате';
					$this->admincom = true;

					$this->vbulletin->db->query("update " . TABLE_PREFIX . "setting set value='{$banned}' where varname='ishout_banned'");

					$this->build_options();

					if ($this->vbulletin->options['ishout_logging'])
					{
						$this->load_engine('log');

						$log = new log;
						$log->log_action(
							"Пользователь {$user['username']} забанен",
							'ban'
						);
					}
				}
			}

			return true;
		}

		if (preg_match("#^(/unban\s+?)#i", $message, $matches) && $this->can_do_admin())
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$banned = $this->fetch_banned();

				if (in_array($user['userid'], $banned))
				{
					foreach ($banned as $key => $userid)
					{
						if ($userid == $user['userid'] || trim($userid) == '')
						{
							unset($banned[$key]);
						}
					}

					$banned = addslashes(implode(',', $banned));

					$this->doshout = true;
					$this->me = true;

					$message = 'Разбанить пользователя ' . $user['username'] . ' в чате';
					$this->admincom = true;

					$this->vbulletin->db->query("update " . TABLE_PREFIX . "setting set value='{$banned}' where varname='ishout_banned'");

					$this->build_options();

					if ($this->vbulletin->options['ishout_logging'])
					{
						$this->load_engine('log');

						$log = new log;
						$log->log_action(
							"Пользователь {$user['username']} разбанен",
							'unban'
						);
					}
				}
			}

			return true;
		}

		if (preg_match("#^(/silence\s+?)#i", $message, $matches) && $this->can_do_admin())
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_silenced='1' where s_user='{$user['userid']}'");

				if ($this->vbulletin->db->affected_rows() < 1 && !$entry = $this->vbulletin->db->query_first("select s_user from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$user['userid']}'"))
				{
					$this->vbulletin->db->query("
						insert into " . TABLE_PREFIX . "infernoshoutusers
						(s_user, s_silenced)
						values
						('{$user['userid']}', '1')
					");
				}

				$this->doshout = true;
				$this->me = true;
				$this->admincom = true;

				$message = 'заткнул пользователя ' . $user['username'] . ' в чате';

				if ($this->vbulletin->options['ishout_logging'])
				{
					$this->load_engine('log');

					$log = new log;
					$log->log_action(
						"Пользователь {$user['username']} был заткнут",
						'silence'
					);
				}
			}

			return true;
		}

		if (preg_match("#^(/unsilence\s+?)#i", $message, $matches) && $this->can_do_admin())
		{
			$this->doshout = false;
			$user = htmlspecialchars_uni(addslashes(trim(str_replace($matches[0], '', $message))));

			if ($user = $this->vbulletin->db->query_first("select userid, username from " . TABLE_PREFIX . "user where userid='$user' or username='$user'"))
			{
				$this->vbulletin->db->query("update " . TABLE_PREFIX . "infernoshoutusers set s_silenced='0' where s_user='{$user['userid']}'");

				$this->doshout = true;
				$this->me = true;
				$this->admincom = true;

				$message = 'has unsilenced the user ' . $user['username'] . ' from the shoutbox';

				if ($this->vbulletin->options['ishout_logging'])
				{
					$this->load_engine('log');

					$log = new log;
					$log->log_action(
						"User {$user['username']} has been unsilenced",
						'unsilence'
					);
				}
			}

			return true;
		}

		if ($message == '/banlist' && $this->can_do_admin())
		{
			$this->doshout = true;
			$this->private = $this->vbulletin->userinfo['userid'];

			$banlist = $this->fetch_banned();
			$list = array();

			if (!empty($banlist))
			{
				$banlist = $this->vbulletin->db->query("select username, userid from " . TABLE_PREFIX . "user where userid in (" . implode(',', $banlist) . ")");
				while ($userban = $this->vbulletin->db->fetch_array($banlist))
				{
					if ($this->vbulletin->options['ishout_bbcodes'] & 64)
					{
						$list[] = "[url={$this->vbulletin->options['bburl']}/member.php?{$this->vbulletin->session->vars['sessionurl']}u={$userban[userid]}]{$userban[username]}[/url]";
					}
					else
					{
						$list[] = $userban['username'];
					}
				}

				$message = 'Currently banned users: ' . implode(', ', $list);
			}
			else
			{
				$message = 'No users are currently banned within the shoutbox.';
			}
		}

		// let's query custom commands
		$commands = $this->vbulletin->db->query_first("select s_commands from " . TABLE_PREFIX . "infernoshoutusers where s_user='{$this->vbulletin->userinfo['userid']}'");

		if ($commands['s_commands'])
		{
			$commands = unserialize($commands['s_commands']);

			if (is_array($commands))
			{
				foreach ($commands as $command)
				{
					$lookfor = explode(' ', $command['input']);
					$lookfor = $lookfor[0];

					if (preg_match("#^(" . preg_quote($lookfor) .")(.*)?$#i", $message, $matches))
					{
						$thisinput = trim($matches[2]);

						$message = str_replace('{input}', $thisinput, $command['output']);

						$this->is_action_code($message);

						break;
					}
				}
			}
		}
	}

	function build_options()
	{
		require_once(DIR . '/includes/adminfunctions.php');

		build_options();
	}

	function fetch_data($userid, $perms)
	{
		if ($userid == -1)
		{
			$this->userid =& $this->vbulletin->userinfo['userid'];
		}
		else
		{
			// We haven't got this far yet...
		}

		if ($perms == -1)
		{
			// load default perms
		}
		else
		{
			// input custom perms
		}
	}

	function parse(&$text)
	{
		if (!class_exists('vB_BbCodeParser'))
		{
			require_once(DIR . '/includes/class_bbcode.php');
			require_once(DIR . '/includes/functions_newpost.php');
		}

		if (!$this->taglist)
		{
			$this->fetch_tag_list();
		}

		if (!$this->parser)
		{
			$this->parser =& new vB_BbCodeParser($this->vbulletin, $this->taglist);

			$this->vbulletin->options['allowhtml'] = false;
			$this->vbulletin->options['allowbbcode'] = true;
			$this->vbulletin->options['allowbbimagecode'] =& $this->vbulletin->options['ishout_images'];
			$this->vbulletin->options['allowsmilies'] =& $this->vbulletin->options['ishout_smilies'];
		}

		$text = $this->parser->parse(trim($text), 'nonforum');
	}

	function fetch_tag_list()
	{
		$this->vbulletin->options['allowedbbcodes'] =& $this->vbulletin->options['ishout_bbcodes'];
		$this->taglist =& fetch_tag_list();
	}

	function can_do_admin()
	{
		return $this->is_in_ug_list($this->vbulletin->options['ishout_admincommands']);
	}
}
?>