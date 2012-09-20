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

class log extends infernoshout_engine
{
	function log_action($message, $type)
	{
		$this->vbulletin->db->query("
			insert into " . TABLE_PREFIX . "infernoshoutlog
			(l_time, l_ip, l_message, l_user, l_type)
			values
			(" . time() . ", '" . $_SERVER['REMOTE_ADDR'] . "', '" . addslashes($message) . "', {$this->vbulletin->userinfo['userid']}, '{$type}')
		");

		return $this->vbulletin->db->insert_id();
	}

	function snapshot($type, $prunefor = false)
	{
		$snapshot = array();
		$shouts = $this->vbulletin->db->query("select s.*, u.username from " . TABLE_PREFIX . "infernoshout s left join " . TABLE_PREFIX . "user u on(u.userid = s.s_user) where s.s_private = '-1' order by s.s_time desc");
		while ($shout = $this->vbulletin->db->fetch_array($shouts))
		{
			$snapshot[] = $shout;
		}

		$snapshot = addslashes(serialize($snapshot));
		$snapshot = $this->log_action($snapshot, 'snapshot');

		$this->log_action(
			"{$this->vbulletin->userinfo['username']} " . (($type == 'prune') ? 'pruned the shoutbox' : ' pruned shouts by ' . $prunefor) . '<snapshot>' . $snapshot . '</snapshot>',
			$type
		);
	}
}
?>