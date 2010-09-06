<?php

# click stats per message
require_once dirname(__FILE__).'/accesscheck.php';

if (isset($_GET['id'])) {
  $id = sprintf('%d',$_GET['id']);
} else {
  $id = 0;
}
if (isset($_GET['start'])) {
  $start = sprintf('%d',$_GET['start']);
} else {
  $start = 0;
}

$addcomparison = 0;
$access = accessLevel('mviews');
#print "Access level: $access";
switch ($access) {
  case 'owner':
    $subselect = ' and owner = ' . $_SESSION["logindetails"]["id"];
    if ($id) {
      $allow = Sql_Fetch_Row_query(sprintf('select owner from %s where id = %d %s',$GLOBALS['tables']['message'],$id,$subselect));
      if ($allow[0] != $_SESSION["logindetails"]["id"]) {
        print $GLOBALS['I18N']->get('You do not have access to this page');
        return;
      }
    }
    $addcomparison = 1;
    break;
  case 'all':
    $subselect = '';
    break;
  case 'none':
  default:
    $subselect = ' where id = 0';
    print $GLOBALS['I18N']->get('You do not have access to this page');
    return;
    break;
}

$download = !empty($_GET['dl']);
if ($download) {
  ob_end_clean();
#  header("Content-type: text/plain");
  header('Content-type: text/csv');
  header('Content-disposition:  attachment; filename="phpList Message open statistics.csv"');
  ob_start();
}  

if (!$id) {
  print '<p>'.PageLinkButton('mviews&dl=true',$GLOBALS['I18N']->get('Download as CSV file')).'</p>';
#  print '<p>'.$GLOBALS['I18N']->get('Select Message to view').'</p>';
  $timerange = ' and msg.entered  > date_sub(current_timestamp,interval 12 month)';
#  $timerange = '';

  $req = Sql_Query(sprintf('select msg.id as messageid,count(um.viewed) as views, count(um.status) as total,
    subject,date_format(sent,"%%e %%b %%Y") as sent,bouncecount as bounced from %s um,%s msg
    where um.messageid = msg.id %s %s
    group by msg.id order by msg.entered desc limit 10',
    $GLOBALS['tables']['usermessage'],$GLOBALS['tables']['message'],$subselect,$timerange));
  if (!Sql_Affected_Rows()) {
    print '<p class="information">'.$GLOBALS['I18N']->get('There are currently no messages to view').'</p>';
  }

  $ls = new WebblerListing($GLOBALS['I18N']->get('Available Messages'));
  while ($row = Sql_Fetch_Array($req)) {
    $element = $row['messageid'].' '.substr($row['subject'],0,50);
    $ls->addElement($element);
    if (!empty($row['sent'])) {
      $ls->addColumn($element,$GLOBALS['I18N']->get('date'),$row['sent']);
    } else {
      $ls->addColumn($element,$GLOBALS['I18N']->get('date'),$GLOBALS['I18N']->get('in progress'));
    }
    $ls->addColumn($element,$GLOBALS['I18N']->get('sent'),$row['total']);
    $ls->addColumn($element,$GLOBALS['I18N']->get('bounced'),$row['bounced']);
    $ls->addColumn($element,$GLOBALS['I18N']->get('views'),$row['views'],$row['views'] ? PageURL2('mviews&amp;id='.$row['messageid']):'');
    $openrate = sprintf('%0.2f',($row['views'] / $row['total'] * 100));
    $ls->addColumn($element,$GLOBALS['I18N']->get('rate'),$openrate.' %');
    $bouncerate = sprintf('%0.2f',($row['bounced'] / $row['total'] * 100));
    $ls->addColumn($element,$GLOBALS['I18N']->get('bounce rate'),$bouncerate.' %');
    
  }
  if ($addcomparison) {
    $total = Sql_Fetch_Array_Query(sprintf('select count(entered) as total from %s um', $GLOBALS['tables']['usermessage']));
    $viewed = Sql_Fetch_Array_Query(sprintf('select count(viewed) as viewed from %s um', $GLOBALS['tables']['usermessage']));
    $overall = $GLOBALS['I18N']->get('Comparison to other admins');
    $ls->addElement($overall);
    $ls->addColumn($overall,$GLOBALS['I18N']->get('views'),$viewed['viewed']);
    $perc = sprintf('%0.2f',($viewed['viewed'] / $total['total'] * 100));
    $ls->addColumn($overall,$GLOBALS['I18N']->get('rate'),$perc.' %');
  }
  if ($download) {
    ob_end_clean();
    print $ls->tabDelimited();
  }

  print $ls->display();
  return;
}


print '<h3>'.$GLOBALS['I18N']->get('View Details for a Message').'</h3>';
$messagedata = Sql_Fetch_Array_query("SELECT * FROM {$tables['message']} where id = $id $subselect");
print '<table class="mviewsDetails">
<tr><td>'.$GLOBALS['I18N']->get('Subject').'<td><td>'.$messagedata['subject'].'</td></tr>
<tr><td>'.$GLOBALS['I18N']->get('Entered').'<td><td>'.$messagedata['entered'].'</td></tr>
<tr><td>'.$GLOBALS['I18N']->get('Sent').'<td><td>'.$messagedata['sent'].'</td></tr>
</table><hr/>';


$ls = new WebblerListing($GLOBALS['I18N']->get('Message Open Statistics'));

$req = Sql_Query(sprintf('select um.userid
    from %s um,%s msg where um.messageid = %d and um.messageid = msg.id and um.viewed is not null %s
    group by userid',
    $GLOBALS['tables']['usermessage'],$GLOBALS['tables']['message'],$id,$subselect));

$total = Sql_Num_Rows($req);
$offset = 0;
if (isset($start) && $start > 0) {
  $listing = sprintf($GLOBALS['I18N']->get("Listing user %d to %d"),$start,$start + MAX_USER_PP);
  $offset = $start;
  $limit = "limit $start,".MAX_USER_PP;
} else {
  $listing =  sprintf($GLOBALS['I18N']->get("Listing user %d to %d"),1,MAX_USER_PP);
  $start = 0;
  $limit = "limit 0,".MAX_USER_PP;
}
if ($id) {
  $url_keep = '&amp;id='.$id;
} else {
  $url_keep = '';
}
print $total. " ".$GLOBALS['I18N']->get("Entries")."</p>";
if ($total) {
  printf ('<table class="mviewsListing" border="1"><tr><td colspan="4" align="center">%s</td></tr><tr><td>%s</td><td>%s</td><td>
          %s</td><td>%s</td></tr></table><hr/>',
          $listing,
          PageLink2("mviews$url_keep","&lt;&lt;","start=0"),
          PageLink2("mviews$url_keep","&lt;",sprintf('start=%d',max(0,$start-MAX_USER_PP))),
          PageLink2("mviews$url_keep","&gt;",sprintf('start=%d',min($total,$start+MAX_USER_PP))),
          PageLink2("mviews$url_keep","&gt;&gt;",sprintf('start=%d',$total-MAX_USER_PP)));
}

/* @todo, fix broken Adodb query */
// $query = sprintf('select userid, email, um.entered as sent, min(um.viewed) as firstview, max(um.viewed) as lastview, count(um.viewed) as viewcount, abs(extract(epoch from um.entered) - extract(epoch from um.viewed)) as responsetime
// from %s um, %s u, %s msg
// where um.messageid = ?
//   and um.messageid = msg.id
//   and um.userid = u.id
//   and um.viewed is not null
// %s
// group by userid, email, um.entered, um.viewed
// order by firstview
// limit ' . MAX_USER_PP . ' offset %s',
//     $GLOBALS['tables']['usermessage'], $GLOBALS['tables']['user'], $GLOBALS['tables']['message'], $subselect, $offset);
// $req = Sql_Query_Params($query, array($id));

$req = Sql_Query(sprintf('select userid,email,um.entered as sent,min(um.viewed) as firstview,
    max(um.viewed) as lastview, count(um.viewed) as viewcount,
    abs(unix_timestamp(um.entered) - unix_timestamp(um.viewed)) as responsetime
    from %s um, %s user, %s msg where um.messageid = %d and um.messageid = msg.id and um.userid = user.id and um.viewed is not null %s
    group by userid %s',
    $GLOBALS['tables']['usermessage'],$GLOBALS['tables']['user'],$GLOBALS['tables']['message'],$id,$subselect,$limit));


$summary = array();
while ($row = Sql_Fetch_Array($req)) {
  $element = '<!--'.$row['userid'].'-->'.$row['email'];
  $ls->addElement($element,PageUrl2('userhistory&amp;id='.$row['userid']));
  $ls->addColumn($element,$GLOBALS['I18N']->get('sent'),formatDateTime($row['sent']));
  if ($row['viewcount'] > 1) {
    $ls->addColumn($element,$GLOBALS['I18N']->get('firstview'),formatDateTime($row['firstview'],1));
    $ls->addColumn($element,$GLOBALS['I18N']->get('lastview'),formatDateTime($row['lastview']));
    $ls->addColumn($element,$GLOBALS['I18N']->get('views'),$row['viewcount']);
  } else {
    $ls->addColumn($element,$GLOBALS['I18N']->get('firstview'),formatDateTime($row['firstview'],1));
    $ls->addColumn($element,$GLOBALS['I18N']->get('responsetime'),$row['responsetime'].' '.$GLOBALS['I18N']->get('sec'));
  }
}
print $ls->display();
?>
