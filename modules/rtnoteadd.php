<?php

/*
 * LMS version 1.9-cvs
 *
 *  (C) Copyright 2001-2007 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

if(isset($_GET['ticketid']))
{
	$note['ticketid'] = intval($_GET['ticketid']);

	if(($LMS->GetUserRightsRT($AUTH->id, 0, $note['ticketid']) & 2) != 2)
        {
	        $SMARTY->display('noaccess.html');
	        $SESSION->close();
	        die;
	}
}
elseif(isset($_POST['note']))
{
	$note = $_POST['note'];
	
	if($note['body'] == '')
		$error['body'] = trans('Note body not specified!');

	if(!isset($note['ticketid']) || !intval($note['ticketid']))
	{
		$SESSION->redirect('?m=rtqueuelist');
	}
	
	if(!$error)
	{
		$DB->Execute('INSERT INTO rtnotes (userid, ticketid, body, createtime)
			    VALUES(?, ?, ?, ?NOW?)',
			    array($AUTH->id, $note['ticketid'], $note['body']));

		// setting status
//		if(!$DB->GetOne('SELECT owner FROM rttickets WHERE id = ?', array($note['ticketid'])))
//    			$DB->Execute('UPDATE rttickets SET owner = ? WHERE id = ?', array($AUTH->id, $note['ticketid']));
		if(!$DB->GetOne('SELECT state FROM rttickets WHERE id = ?', array($note['ticketid'])))
			$LMS->SetTicketState($note['ticketid'], 1);

		if(isset($note['notify']))
		{
			$user = $LMS->GetUserInfo($AUTH->id);
			$queue = $LMS->GetQueueByTicketId($note['ticketid']);
			$mailfname = '';
			
			if(isset($CONFIG['phpui']['helpdesk_sender_name']))
			{
				if($CONFIG['phpui']['helpdesk_sender_name'] == 'queue')
				{
					$mailfname = $$queue['name'];
				}
				elseif($CONFIG['phpui']['helpdesk_sender_name'] == 'user')
				{
					$mailfname = $user['name'];
				}
				
				$mailfname = '"'.$mailfname.'"';
			}

			$mailfrom = $user['email'] ? $user['email'] : $queue['email'];
				
			$headers['Date'] = date('r');
		        $headers['From'] = $mailfname.' <'.$mailfrom.'>';
			$headers['Subject'] = sprintf("[RT#%06d] %s", $note['ticketid'], $DB->GetOne('SELECT subject FROM rttickets WHERE id = ?', array($note['ticketid'])));
			$headers['Reply-To'] = $headers['From'];

			$body = $note['body']."\n\nhttp".(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '').'://'
				.$_SERVER['HTTP_HOST'].substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/') + 1)
				.'?m=rtticketview&id='.$note['ticketid'];

			if($recipients = $DB->GetCol('SELECT email FROM users, rtrights 
						WHERE users.id=userid AND queueid = ? AND email != \'\' 
							AND (rtrights.rights & 8) = 8 AND users.id != ?', 
							array($queue['id'], $AUTH->id)))
			{
				foreach($recipients as $email)
				{
					if(isset($CONFIG['phpui']['debug_email']) && $CONFIG['phpui']['debug_email'])
						$recip = $CONFIG['phpui']['debug_email'];
					else
						$recip = $email;
					$headers['To'] = '<'.$recip.'>';
		        
					$LMS->SendMail($recip, $headers, $body);
				}
			}
		}
				
		$SESSION->redirect('?m=rtticketview&id='.$note['ticketid']);
	}
}
else
{
	header('Locaton: ?m=rtqueuelist');
	die;
}
/*
	if(!eregi("[RT#[0-9]{6}]",$note['subject'])) 
		$note['subject'] .= sprintf(" [RT#%06d]",$note['ticketid']); 
*/

$layout['pagetitle'] = trans('New Note');

$SESSION->save('backto', $_SERVER['QUERY_STRING']);

$SMARTY->assign('note', $note);
$SMARTY->assign('error', $error);
$SMARTY->display('rtnoteadd.html');

?>