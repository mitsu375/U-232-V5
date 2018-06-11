<?php
/**
 |--------------------------------------------------------------------------|
 |   https://github.com/Bigjoos/                                            |
 |--------------------------------------------------------------------------|
 |   Licence Info: GPL                                                      |
 |--------------------------------------------------------------------------|
 |   Copyright (C) 2010 U-232 V5                                            |
 |--------------------------------------------------------------------------|
 |   A bittorrent tracker source based on TBDev.net/tbsource/bytemonsoon.   |
 |--------------------------------------------------------------------------|
 |   Project Leaders: Mindless, Autotron, whocares, Swizzles.               |
 |--------------------------------------------------------------------------|
   _   _   _   _   _     _   _   _   _   _   _     _   _   _   _
  / \ / \ / \ / \ / \   / \ / \ / \ / \ / \ / \   / \ / \ / \ / \
 ( U | - | 2 | 3 | 2 )-( S | o | u | r | c | e )-( C | o | d | e )
  \_/ \_/ \_/ \_/ \_/   \_/ \_/ \_/ \_/ \_/ \_/   \_/ \_/ \_/ \_/
 */
require_once (__DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php');
require_once (INCL_DIR . 'user_functions.php');
require_once (INCL_DIR . 'bbcode_functions.php');
require_once (INCL_DIR . 'pager_functions.php');
require_once (INCL_DIR . 'comment_functions.php');
require_once (INCL_DIR . 'add_functions.php');
require_once (INCL_DIR . 'html_functions.php');
require_once (INCL_DIR . 'function_rating.php');
//require_once (INCL_DIR . 'tvrage_functions.php');
require_once (INCL_DIR . 'tvmaze_functions.php');// uncomment to use tvmaze
require_once (IMDB_DIR . 'imdb.class.php');
require_once (INCL_DIR.'getpre.php');
dbconn(false);
loggedinorreturn();
$lang = array_merge(load_language('global'), load_language('details'));
parked();


$stdhead = array(
    /** include css **/
    'css' => array(
    'bbcode',
    'details',
    'rating_style'
    )
);
$stdfoot = array(
    /** include js **/
    'js' => array(
    'popup',
    'jquery.thanks',
    'wz_tooltip',
    'java_klappe',
    'balloontip',
    'shout',
    'thumbs',
    'sack'
    )
);
$HTMLOUT = $torrent_cache = '';
if (!isset($_GET['id']) || !is_valid_id($_GET['id'])) stderr("{$lang['details_user_error']}", "{$lang['details_bad_id']}");
$id = (int)$_GET["id"];
//==pdq memcache slots
$slot = make_freeslots($CURUSER['id'], 'fllslot_');
$torrent['addedfree'] = $torrent['addedup'] = $free_slot = $double_slot = '';
if (!empty($slot)) foreach ($slot as $sl) {
    if ($sl['torrentid'] == $id && $sl['free'] == 'yes') {
        $free_slot = 1;
        $torrent['addedfree'] = $sl['addedfree'];
    }
    if ($sl['torrentid'] == $id && $sl['doubleup'] == 'yes') {
        $double_slot = 1;
        $torrent['addedup'] = $sl['addedup'];
    }
    if ($free_slot && $double_slot) break;
}
$categorie = genrelist();
foreach ($categorie as $key => $value) $change[$value['id']] = array(
    'id' => $value['id'],
    'name' => $value['name'],
    'image' => $value['image'],
    'min_class' => $value['min_class']
);
//$mc1->delete_value('torrent_details_' . $id);
if (($torrents = $mc1->get_value('torrent_details_' . $id)) === false) {
    $tor_fields_ar_int = array(
    'id',
    'leechers',
    'seeders',
    'thanks',
    'comments',
    'owner',
    'size',
    'added',
    'views',
    'hits',
    'numfiles',
    'times_completed',
    'points',
    'last_reseed',
    'category',
    'free',
    'freetorrent',
    'silver',
    'rating_sum',
    'checked_when',
    'num_ratings',
    'mtime',
    'checked_when'
    );
    $tor_fields_ar_str = array(
    'banned',
    'info_hash',
    'checked_by',
    'filename',
    'search_text',
    'name',
    'save_as',
    'visible',
    'type',
    'poster',
    'url',
    'anonymous',
    'allow_comments',
    'description',
    'nuked',
    'nukereason',
    'vip',
    'subs',
    'username',
    'newgenre',
    'release_group',
    'youtube',
    'tags',
    'user_likes'
    );
    $tor_fields = implode(', ', array_merge($tor_fields_ar_int, $tor_fields_ar_str));
    $result = sql_query("SELECT " . $tor_fields . ", (SELECT MAX(id) FROM torrents ) as max_id, (SELECT MIN(id) FROM torrents) as min_id, LENGTH(nfo) AS nfosz, IF(num_ratings < {$INSTALLER09['minvotes']}, NULL, ROUND(rating_sum / num_ratings, 1)) AS rating FROM torrents WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
    $torrents = mysqli_fetch_assoc($result);
    foreach ($tor_fields_ar_int as $i) $torrents[$i] = (int)$torrents[$i];
    foreach ($tor_fields_ar_str as $i) $torrents[$i] = $torrents[$i];
    $mc1->cache_value('torrent_details_' . $id, $torrents, $INSTALLER09['expires']['torrent_details']);
}
   if ($change[$torrents['category']]['min_class'] > $CURUSER['class']) stderr("{$lang['details_user_error']}", "{$lang['details_bad_id']}");
//==
if (($torrents_xbt = $mc1->get_value('torrent_xbt_data_' . $id)) === false && XBT_TRACKER == true) {
    $torrents_xbt = mysqli_fetch_assoc(sql_query("SELECT seeders, leechers, times_completed FROM torrents WHERE id =" . sqlesc($id))) or sqlerr(__FILE__, __LINE__);
    $mc1->cache_value('torrent_xbt_data_' . $id, $torrents_xbt, $INSTALLER09['expires']['torrent_xbt_data']);
}
//==
if (($torrents_txt = $mc1->get_value('torrent_details_txt' . $id)) === false) {
    $torrents_txt = mysqli_fetch_assoc(sql_query("SELECT descr FROM torrents WHERE id =" . sqlesc($id))) or sqlerr(__FILE__, __LINE__);
    $mc1->cache_value('torrent_details_txt' . $id, $torrents_txt, $INSTALLER09['expires']['torrent_details_text']);
}
//Memcache Pretime
if (($pretime = $mc1->get_value('torrent_pretime_'.$id)) === false) {
    $prename = htmlsafechars($torrents['name']);
    $pre_q = sql_query("SELECT time FROM releases WHERE releasename = " . sqlesc($prename)) or sqlerr(__FILE__, __LINE__);
    $pret = mysqli_fetch_assoc($pre_q);
    $pretime['time'] = strtotime($pret['time']);
    $mc1->cache_value('torrent_pretime_'.$id, $pretime, $INSTALLER09['expires']['torrent_pretime']);
}
//==
if (isset($_GET["hit"])) {
    sql_query("UPDATE torrents SET views = views + 1 WHERE id =" . sqlesc($id));
    $update['views'] = ($torrents['views'] + 1);
    $mc1->begin_transaction('torrent_details_' . $id);
    $mc1->update_row(false, array(
        'views' => $update['views']
    ));
    $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
    header("Location: {$INSTALLER09['baseurl']}/details.php?id=$id");
    exit();
}
$What_String = (XBT_TRACKER == true ? 'mtime' : 'last_action');
$What_String_Key = (XBT_TRACKER == true ? 'last_action_xbt_' : 'last_action_');
if (($l_a = $mc1->get_value($What_String_Key.$id)) === false) {
    $l_a = mysqli_fetch_assoc(sql_query('SELECT '.$What_String.' AS lastseed ' . 'FROM torrents ' . 'WHERE id = ' . sqlesc($id))) or sqlerr(__FILE__, __LINE__);
    $l_a['lastseed'] = (int)$l_a['lastseed'];
    $mc1->add_value('last_action_' . $id, $l_a, 1800);
}
/** seeders/leechers/completed caches pdq**/
$torrent_cache['seeders'] = $mc1->get_value('torrents::seeds:::' . $id);
$torrent_cache['leechers'] = $mc1->get_value('torrents::leechs:::' . $id);
$torrent_cache['times_completed'] = $mc1->get_value('torrents::comps:::' . $id);
$torrents['seeders'] = ((XBT_TRACKER === false || $torrent_cache['seeders'] === false || $torrent_cache['seeders'] === 0 || $torrent_cache['seeders'] === false) ? $torrents['seeders'] : $torrent_cache['seeders']);
$torrents['leechers'] = ((XBT_TRACKER === false || $torrent_cache['leechers'] === false || $torrent_cache['leechers'] === 0 || $torrent_cache['leechers'] === false) ? $torrents['leechers'] : $torrent_cache['leechers']);
$torrents['times_completed'] = ((XBT_TRACKER === false || $torrent_cache['times_completed'] === false || $torrent_cache['times_completed'] === 0 || $torrent_cache['times_completed'] === false) ? $torrents['times_completed'] : $torrent_cache['times_completed']);
//==slots by pdq
$torrent['addup'] = get_date($torrent['addedup'], 'DATE');
$torrent['addfree'] = get_date($torrent['addedfree'], 'DATE');
$torrent['idk'] = (TIME_NOW + 14 * 86400);
$torrent['freeimg'] = '<img src="' . $INSTALLER09['pic_base_url'] . 'freedownload.gif" alt="" />';
$torrent['doubleimg'] = '<img src="' . $INSTALLER09['pic_base_url'] . 'doubleseed.gif" alt="" />';
$torrent['free_color'] = '#FF0000';
$torrent['silver_color'] = 'silver';
//==rep user query by pdq
if (($torrent_cache['rep'] = $mc1->get_value('user_rep_' . $torrents['owner'])) === false) {
    $torrent_cache['rep'] = array();
    $us = sql_query("SELECT reputation FROM users WHERE id =" . sqlesc($torrents['owner'])) or sqlerr(__FILE__, __LINE__);
    if (mysqli_num_rows($us)) {
        $torrent_cache['rep'] = mysqli_fetch_assoc($us);
        $mc1->add_value('user_rep_' . $torrents['owner'], $torrent_cache['rep'], 14 * 86400);
    }
}
$HTMLOUT.= "<script type='text/javascript'>
    /*<![CDATA[*/
    //var e = new sack();
function do_rate(rate,id,what) {
        var box = document.getElementById('rate_'+id);
        e.setVar('rate',rate);
        e.setVar('id',id);
        e.setVar('ajax','1');
        e.setVar('what',what);
        e.requestFile = 'rating.php';
        e.method = 'GET';
        e.element = 'rate_'+id;
        e.onloading = function () {
            box.innerHTML = 'Loading ...'
        }
        e.onCompletion = function() {
            if(e.responseStatus)
                box.innerHTML = e.response();
        }
        e.onerror = function () {
            alert('That was something wrong with the request!');
        }
        e.runAJAX();
}
/*]]>*/
</script>";
$owned = $moderator = 0;
if ($CURUSER["class"] >= UC_STAFF) $owned = $moderator = 1;
elseif ($CURUSER["id"] == $torrents["owner"]) $owned = 1;
if ($torrents["vip"] == "1" && $CURUSER["class"] < UC_VIP) stderr("VIP Access Required", "You must be a VIP In order to view details or download this torrent! You may become a Vip By Donating to our site. Donating ensures we stay online to provide you more Vip-Only Torrents!");
if (!$torrents || ($torrents["banned"] == "yes" && !$moderator)) stderr("{$lang['details_error']}", "{$lang['details_torrent_id']}");
if ($CURUSER["id"] == $torrents["owner"] || $CURUSER["class"] >= UC_STAFF) $owned = 1;
else $owned = 0;
$spacer = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
if (empty($torrents["tags"])) {
    $keywords = "No Keywords Specified.";
} else {
    $tags = explode(",", $torrents['tags']);
    $keywords = "";
    foreach ($tags as $tag) {
        $keywords.= "<a href='browse.php?search=$tag&amp;searchin=all&amp;incldead=1'>" . htmlsafechars($tag) . "</a>,";
    }
    $keywords = substr($keywords, 0, (strlen($keywords) - 1));
}
if (isset($_GET["uploaded"])) {
    $HTMLOUT.= "<div class='alert alert-success col-md-11' align='center'><strong>{$lang['details_success']}</strong><br />\n";
    $HTMLOUT.= "<p>{$lang['details_start_seeding']}</p></div>\n";
    $HTMLOUT.= '<meta http-equiv="refresh" content="1;url=download.php?torrent=' . $id . '' . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . '" />';
} elseif (isset($_GET["edited"])) {
    $HTMLOUT.= "<div class='alert alert-success span11' align='center'><strong>{$lang['details_success_edit']}</strong></div>\n";
    if (isset($_GET["returnto"])) $HTMLOUT.= "<p><b>{$lang['details_go_back']}<a href='" . htmlsafechars($_GET["returnto"]) . "'>{$lang['details_whence']}</a>.</b></p>\n";
} elseif (isset($_GET["reseed"])) {
    $HTMLOUT.= "<div class='alert alert-success col-md-11' align='center'><strong>PM was sent! Now wait for a seeder</strong></div>\n";
}
//==pdq's Torrent Moderation
if ($CURUSER['class'] >= UC_STAFF) {
    if (isset($_GET["checked"]) && $_GET["checked"] == 1) {
        sql_query("UPDATE torrents SET checked_by = " . sqlesc($CURUSER['username']) . ",checked_when = ".TIME_NOW." WHERE id =" . sqlesc($id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $mc1->begin_transaction('torrent_details_' . $id);
        $mc1->update_row(false, array(
            'checked_by' => $CURUSER['username'],
            'checked_when' => TIME_NOW
        ));
        $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
        $mc1->delete_value('checked_by_' . $id);
        write_log("Torrent <a href={$INSTALLER09['baseurl']}/details.php?id=$id>(" . htmlsafechars($torrents['name']) . ")</a> was checked by {$CURUSER['username']}");
        header("Location: {$INSTALLER09["baseurl"]}/details.php?id=$id&checked=done#Success");
    } elseif (isset($_GET["rechecked"]) && $_GET["rechecked"] == 1) {
        sql_query("UPDATE torrents SET checked_by = " . sqlesc($CURUSER['username']) . ",checked_when = ".TIME_NOW." WHERE id =" . sqlesc($id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $mc1->begin_transaction('torrent_details_' . $id);
        $mc1->update_row(false, array(
            'checked_by' => $CURUSER['username'],
            'checked_when' => TIME_NOW
        ));
        $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
        $mc1->delete_value('checked_by_' . $id);
        write_log("Torrent <a href={$INSTALLER09['baseurl']}/details.php?id=$id>(" . htmlsafechars($torrents['name']) . ")</a> was re-checked by {$CURUSER['username']}");
        header("Location: {$INSTALLER09["baseurl"]}/details.php?id=$id&rechecked=done#Success");
    } elseif (isset($_GET["clearchecked"]) && $_GET["clearchecked"] == 1) {
        sql_query("UPDATE torrents SET checked_by = '', checked_when='' WHERE id =" . sqlesc($id) . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
        $mc1->begin_transaction('torrent_details_' . $id);
        $mc1->update_row(false, array(
            'checked_by' => '',
            'checked_when' => ''
        ));
        $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
        $mc1->delete_value('checked_by_' . $id);
        write_log("Torrent <a href={$INSTALLER09["baseurl"]}/details.php?id=$id>(" . htmlsafechars($torrents['name']) . ")</a> was un-checked by {$CURUSER['username']}");
        header("Location: {$INSTALLER09["baseurl"]}/details.php?id=$id&clearchecked=done#Success");
    }
    if (isset($_GET["checked"]) && $_GET["checked"] == 'done') $HTMLOUT.= "<div class='alert alert-success span11' align='center'><h4><a name='Success'>Successfully checked {$CURUSER['username']}!</a></h4></div>";
    if (isset($_GET["rechecked"]) && $_GET["rechecked"] == 'done') $HTMLOUT.= "<div class='alert alert-success span11' align='center'><h4><a name='Success'>Successfully re-checked {$CURUSER['username']}!</a></h4></div>";
    if (isset($_GET["clearchecked"]) && $_GET["clearchecked"] == 'done') $HTMLOUT.= "<div class='alert alert-success span11' align='center'><h4><a name='Success'>Successfully un-checked {$CURUSER['username']}!</a></h4></div>";
}
// end
$prev_id = ($id - 1);
$next_id = ($id + 1);
$s = htmlsafechars($torrents["name"], ENT_QUOTES);
$HTMLOUT.= "<div class='container' ><h4 align='center'>$s</h4><br />";
$HTMLOUT .= "<a class='btn btn-primary btn-sm' href='random.php'>" . (!isset($_GET['random']) ? 'Random Any' : '<span>Random Any</span>') . "</a>";
if($torrents["id"] != $torrents["min_id"])
$HTMLOUT .= "&nbsp;&nbsp;<a class='btn btn-primary btn-sm'href='details.php?id={$prev_id}'><b>Prev Torrent</b></a>";
$HTMLOUT .= "&nbsp;&nbsp;<a class='btn btn-primary btn-sm'href='browse.php'><b>Return</b></a>";
if($torrents["id"] != $torrents["max_id"])
$HTMLOUT .= "&nbsp;&nbsp;<a class='btn btn-primary btn-sm'href='details.php?id={$next_id}'><b>Next Torrent</b></a>";
//Thumbs Up
if (($thumbs = $mc1->get_value('thumbs_up_' . $id)) === false) {
    $thumbs = mysqli_num_rows(sql_query("SELECT id, type, torrentid, userid FROM thumbsup WHERE torrentid = " . sqlesc($torrents['id'])));
    $thumbs = (int)$thumbs;
    $mc1->add_value('thumbs_up_' . $id, $thumbs, 0);
}
$HTMLOUT.= "
    <div class='pull-right'>
    {$lang['details_thumbs']}
    <div id='thumbsup'>
    <a href=\"javascript:ThumbsUp('" . (int)$torrents['id'] . "')\">
    <img src='{$INSTALLER09['pic_base_url']}thumb_up.png' alt='Thumbs Up' title='Thumbs Up' width='12' height='12' /></a>&nbsp;&nbsp;&nbsp;(" . $thumbs . ")</div><!-- closing thumbs up --></div><!-- closing pull right --></div><!-- closing container -->\n";
//==
/** free mod pdq **/
$HTMLOUT.= '<div id="balloon1" class="balloonstyle">
            Once chosen this torrent will be Freeleech ' . $torrent['freeimg'] . ' until ' . get_date($torrent['idk'], 'DATE') . ' and can be resumed or started over using the
            regular download link. Doing so will result in one Freeleech Slot being taken away from your total.
        </div>
        <div id="balloon2" class="balloonstyle">
            Once chosen this torrent will be Doubleseed ' . $torrent['doubleimg'] . ' until ' . get_date($torrent['idk'], 'DATE') . ' and can be resumed or started over using the
            regular download link. Doing so will result in one Freeleech Slot being taken away from your total.
        </div>
        <div id="balloon3" class="balloonstyle">
            Remember to show your gratitude and Thank the Uploader. <img src="' . $INSTALLER09['pic_base_url'] . 'smilies/smile1.gif" alt="" />
        </div>';
/** end **/
$HTMLOUT.= "<div class='container'>";
$url = "edit.php?id=" . (int)$torrents["id"];
if (isset($_GET["returnto"])) {
    $addthis = "&amp;returnto=" . urlencode($_GET["returnto"]);
    $url.= $addthis;
    $keepget = $addthis;
}
$editlink = "a href=\"$url\" class=\"btn btn-primary btn-xs\"";
if (!($CURUSER["downloadpos"] == 0 && $CURUSER["id"] != $torrents["owner"] OR $CURUSER["downloadpos"] > 1)) {
    /** free mod by pdq **/
    //== Display the freeslots links etc.
    if ($free_slot && !$double_slot) {
        $HTMLOUT.= '<tr>
        <td align="right" class="heading">Slots</td>
        <td align="left">' . $torrent['freeimg'] . ' <b><font color="' . $torrent['free_color'] . '">Freeleech Slot In Use!</font></b> (only upload stats are recorded) - Expires: 12:01AM ' . $torrent['addfree'] . '</td></tr>';
        $freeslot = ((!XBT_TRACKER && $CURUSER['freeslots'] >= 1) ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
        $freeslot_zip = ((!XBT_TRACKER && $CURUSER['freeslots'] >= 1) ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double&amp;zip=1\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
        $freeslot_text = ((!XBT_TRACKER && $CURUSER['freeslots'] >= 1) ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double&amp;text=1\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
    } elseif (!$free_slot && $double_slot) {
        $HTMLOUT.= '<tr>
        <td align="right" class="heading">Slots</td>
        <td align="left">' . $torrent['doubleimg'] . ' <b><font color="' . $torrent['free_color'] . '">Doubleseed Slot In Use!</font></b> (upload stats x2) - Expires: 12:01AM ' . $torrent['addup'] . '</td></tr>';
        $freeslot = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
        $freeslot_zip = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free&amp;zip=1\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
        $freeslot_text = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free&amp;text=1\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a>&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
    } elseif ($free_slot && $double_slot) {
        $HTMLOUT.= '<tr>
        <td align="right" class="heading">Slots</td>
        <td align="left">' . $torrent['freeimg'] . ' ' . $torrent['doubleimg'] . ' <b><font color="' . $torrent['free_color'] . '">Freeleech and Doubleseed Slots In Use!</font></b> (upload stats x2 and no download stats are recorded)<p>Freeleech Expires: 12:01AM ' . $torrent['addfree'] . ' and Doubleseed Expires: 12:01AM ' . $torrent['addup'] . '</p></td></tr>';
        $freeslot = $freeslot_zip = $freeslot_text = '';
    } else $freeslot = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a> " . (!XBT_TRACKER ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>" : "" ) . "&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
    $freeslot_zip = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free&amp;zip=1\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a>" . (!XBT_TRACKER ? " &nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double&amp;zip=1\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>" : "") . "&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
    $freeslot_text = ($CURUSER['freeslots'] >= 1 ? "&nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=free&amp;text=1\" rel='balloon1' onclick=\"return confirm('Are you sure you want to use a freeleech slot?')\"><font color='" . $torrent['free_color'] . "'><b>Freeleech Slot</b></font></a>" . (!XBT_TRACKER ? " &nbsp;&nbsp;<b>Use: </b><a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;slot=double&amp;text=1\" rel='balloon2' onclick=\"return confirm('Are you sure you want to use a doubleseed slot?')\"><font color='" . $torrent['free_color'] . "'><b>Doubleseed Slot</b></font></a>" : "") . "&nbsp;- " . htmlsafechars($CURUSER['freeslots']) . " Slots Remaining. " : "");
    //==
    require_once MODS_DIR . 'free_details.php';
$HTMLOUT.= "</div>";
$HTMLOUT.= "<br>";
/*Tab selector begins*/

$HTMLOUT.=" <div class='row'>
<div class='col-md-12'>
<table align='center' class='table'>\n";
  /*  if ((in_array($torrents['category'], $INSTALLER09['music_cats']))) {
        if (empty($torrents['poster']) && isset($music['poster'])) {
            $torrents["poster"] = $music['poster'];
            sql_query('UPDATE torrents set poster = ' . sqlesc($music['poster']) . ' WHERE id = ' . $torrents['id']) or sqlerr(__FILE__, __LINE__);
            $mc1->begin_transaction('torrent_details_' . $torrents['id']);
            $mc1->update_row(false, array(
                'poster' => $music['poster'],
            ));
            $mc1->commit_transaction(0);
            $poster_url = ((empty($torrents['poster'])) ? $INSTALLER09['pic_base_url'] .'music.jpg' : htmlsafechars($torrents["poster"]));
        }


    }*/
    $poster_url = ((empty($torrents['poster'])) ? $INSTALLER09['pic_base_url'] . 'noposter.png' : htmlsafechars($torrents["poster"]));
//== tvrage by pdq/putyn
//if (in_array($torrents['category'], $INSTALLER09['tv_cats'])) {
//    require_once (INCL_DIR . 'tvrage_functions.php');
//    $tvrage_info = tvrage($torrents);
//   if ($tvrage_info) $HTMLOUT.= tr($lang['details_tvrage'], $tvrage_info, 1);
//}
//== tvmaze by whocares converted from former tvrage functions by pdq/putyn  //uncomment the following to use tvmaze auto-completion
if (in_array($torrents['category'], $INSTALLER09['tv_cats'])) {
   $tvmaze_info = tvmaze($torrents);
   if ($tvmaze_info) $HTMLOUT.="<tr><th class=' col-md-1 text-center'><div class='details-poster' style='background-image:url({$torrents["poster"]}); width:214px; height:315px;'></div></th><th class=' col-md-5 text-left'>".$tvmaze_info."</th></tr>";
}
//== end tvmaze
/*if ((in_array($torrents['category'], $INSTALLER09['movie_cats'])) && $torrents['url'] != '') {
$imdb = '';
$imdb_info['id'] = $imdb_info['title'] = $imdb_info['orig_title'] = $imdb_info['year'] = $imdb_info['cast'] = $imdb_info['rating'] = $imdb_info['votes'] = $imdb_info['gen'] = $imdb_info['runtime'] = $imdb_info['country'] = $imdb_info['lanuage'] = $imdb_info['director'] = $imdb_info['produce'] = $imdb_info['write'] = $imdb_info['compose'] = $imdb_info['plotoutline'] = $imdb_info['plot'] = $imdb_info['trailers'] = $imdb_info['comment'] = "";
$imdb_info = get_imdb($torrents['url']);
if (empty($torrents['poster']) && isset($imdb_info['poster'])) {
                $torrents["poster"] = $imdb_info['poster'];
                sql_query('UPDATE torrents set poster = ' . sqlesc($imdb_info['poster']) . ' WHERE id = ' . $torrents['id']) or sqlerr(__FILE__, __LINE__);
                $mc1->begin_transaction('torrent_details_' . $torrents['id']);
                $mc1->update_row(false, array(
                    'poster' => $imdb_info['poster'],
                ));
                $mc1->commit_transaction(0);
                $poster_url = ((empty($torrents['poster'])) ? $INSTALLER09['pic_base_url'] .'noposter.png' : htmlsafechars($torrents["poster"]));
            }
$imdb .= "</div></div><div class='imdb'>
<div class='imdb_info'>
<strong><font color=\"#dbe4eb\">Title: </font></strong>".$imdb_info['title']."<br />
<strong><font color=\"#dbe4eb\">Year: </font></strong> ".$imdb_info['year']."<br />
<strong><font color=\"#dbe4eb\">Genre: </font></strong> ".$imdb_info['gen']."<br />
<strong><font color=\"#dbe4eb\">Runtime: </font></strong> ".$imdb_info['runtime']." Mins<br />
<strong><font color=\"#dbe4eb\">Rating: </font></strong>".$imdb_info['rating']."<br />
<strong><font color=\"#dbe4eb\">Director: </font></strong>".$imdb_info['director']."<br />
<strong><font color=\"#dbe4eb\">Producers: </font></strong> ".$imdb_info['produce']."<br />
<strong><font color=\"#dbe4eb\">Writters: </font></strong>".$imdb_info['write']."<br />
<strong><font color=\"#dbe4eb\">Music: </font></strong>".$imdb_info['compose']."<br />
</div>
<br />";
$imdb.= "
<div class='imdb_cast'>
<div style=\"background-color:transparent; border: none; width:100%;\"><div style=\"text-transform: uppercase; border-bottom: 1px solid #CCCCCC; margin-bottom: 3px; font-size: 0.8em; color: #dbe4eb; font-weight: bold; display: block;\"><span onclick=\"if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '<strong>Cast:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>hide</a>'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '<strong>Cast:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>show</a>'; }\" ><font color='#dbe4eb'><b>Cast:&nbsp;</b></font><a href=\"#\" onclick=\"return false;\">show</a></span></div><div class=\"quotecontent\"><div style=\"display: none;\"><div style='background-color:transparent;width:100%;overflow: auto'>";
$imdb.= "".$imdb_info['cast']."";
$imdb.="</div></div></div></div></div>";
//$imdb.= "<div class='imdb_plot'>
//<div style=\"background-color:transparent; border: none; width:100%;\"><div style=\"text-transform: uppercase; border-bottom: 1px solid #CCCCCC; margin-bottom: 3px; font-size: 0.8em; color: #dbe4eb; font-weight: bold; display: block;\"><span onclick=\"if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '<strong>Plot:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>hide</a>'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '<strong>Plot:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>show</a>'; }\" ><font color='#dbe4eb'><b>Plot:&nbsp;</b></font><a href=\"#\" onclick=\"return false;\">show</a></span></div><div class=\"quotecontent\"><div style=\"display: none;\"><div style='background-color:transparent;width:100%;overflow: auto'>";
//$imdb.= "".strip_tags($imdb_info['plot'])."";
//$imdb.="</div></div></div></div></div>";
//Below was added here, but thought better in bittorrent.php where the IMDB function run.  Making sure variables are set right there seems much more sane
isset($imdb_info['comment']) ?: $imdb_info['comment'] = 'None Available';
$imdb.= "<div class='imdb_comments'>
<div style=\"background-color:transparent; border: none; width:100%;\"><div style=\"text-transform: uppercase; border-bottom: 1px solid #CCCCCC; margin-bottom: 3px; font-size: 0.8em; color: #dbe4eb; font-weight: bold; display: block;\"><span onclick=\"if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '<strong>Comments:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>hide</a>'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '<strong>Comments:&nbsp;</strong><a href=\'#\' onclick=\'return false;\'>show</a>'; }\" ><font color='#dbe4eb'><b>Comments:&nbsp;</b></font><a href=\"#\" onclick=\"return false;\">show</a></span></div><div class=\"quotecontent\"><div style=\"display: none;\"><div style='background-color:transparent;width:100%;overflow: auto'>";
$imdb.= "".strip_tags($imdb_info['comment'])."";
$imdb.="</div></div></div></div></div>";
$imdb .="</div>";
$HTMLOUT.="<tr><th class=' col-md-1 text-center'><div class='details-poster' style='background-image:url({$poster_url});'></div></th><th class=' col-md-5 text-left'><b>".$imdb."</th></tr>";
}*/
//if (empty($tvrage_info) && empty($imdb) && in_array($torrents['category'], array_merge($INSTALLER09['movie_cats'], $INSTALLER09['tv_cats']))) $HTMLOUT.= "<tr><td colspan='2'>No Imdb or Tvrage info.</td></tr>";
//if (empty($tvmaze_info) && empty($imdb)) $HTMLOUT.= "<tr><td colspan='2'>No Imdb or TVMaze info</td></tr>";

   /* if ((in_array($torrents['category'], $INSTALLER09['movie_cats']))) {

        $omdbapi_key = '*******';      //Change me to your omdbapi key get @ https://www.omdbapi.com/apikey.aspx
        if ($torrents['url'] != '') {
//==auto imdb rewritten putyn 28/06/2011
            $imdb = '';
            $omdb['Title'] = $omdb['Orig_title'] = $omdb['Year'] = $omdb['Actors'] = $omdb['Rating'] = $omdb['Votes'] = $omdb['Gen'] = $omdb['Runtime'] = $omdb['Country'] = $omdb['Lanuage'] = $omdb['Director'] = $omdb['Producer'] = $omdb['Writer'] = $omdb['Compose'] = $omdb['Plotoutline'] = $omdb['Plot'] = $omdb['Trailers'] = $omdb['Comment'] = "";
            $imdb_info['id'] = $imdb_info['title'] = $imdb_info['orig_title'] = $imdb_info['year'] = $imdb_info['cast'] = $imdb_info['rating'] = $imdb_info['votes'] = $imdb_info['gen'] = $imdb_info['runtime'] = $imdb_info['country'] = $imdb_info['lanuage'] = $imdb_info['director'] = $imdb_info['produce'] = $imdb_info['write'] = $imdb_info['compose'] = $imdb_info['plotoutline'] = $imdb_info['plot'] = $imdb_info['trailers'] = $imdb_info['comment'] = "";

                        $imdb_info = get_imdb($torrents['url']);

            $imdb_id = $imdb_info['id'];

            $url = file_get_contents("https://www.omdbapi.com/?i=tt" . $imdb_info['id'] . "&plot=full&r=json&apikey=*******");
            $omdb = json_decode($url, true);
            $poster = $omdb['Poster'];
            if ($poster != "N/A") {
                $omdb['Poster'] = "/imdb/images/" . $imdb_id . ".jpg";
                if (!file_exists('./imdb/images/' . $imdb_id . '.jpg')) {
                    @copy("$poster", "./imdb/images/" . $imdb_id . ".jpg");
                } //!file_exists('./imdb/images/' . $imdb_id . '.jpg')
            } //$poster != "N/A"
            else {
                $omdb['Poster'] = "./pic/noposter.jpg";
            }


//displaying results
            if ($omdb['Title'] != '') {
                $HTMLOUT .= "<tr><th class=' col-md-1 text-center'><div class='details-poster' style='background-image:url({$omdb['Poster']});'></div>
	</th><th class=' col-md-5 text-left'>
	<strong><span style='color:#ffffff;'>Title:</strong></span> " . $omdb['Title'] . "<br/>
	<strong><span style='color:#ffffff;'>Year:</strong></span> " . $omdb['Year'] . "<br/>
	<strong><span style='color:#ffffff;'>Rating:</strong></span> " . $omdb['Rating'] . "/10<br/>
	<strong><span style='color:#ffffff;'>Runtime:</strong></span> " . $omdb['Runtime'] . " mins<br/>
	<strong><span style='color:#ffffff;'>Cast:</strong></span> " . $omdb['Actors'] . "<br/>
	<strong><span style='color:#ffffff;'>Director:</strong></span> " . $omdb['Director'] . "<br/>
	<strong><span style='color:#ffffff;'>Writters: </strong></span> " . $omdb['Writer'] . "<br />
	<div class='imdb_comments'>
<div style=\"background-color:transparent; border: none; width:100%;\"><div style=\" border-bottom: 0px solid #CCCCCC; margin-bottom: 3px; font-weight: bold; display: block;\"><span onclick=\"if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '<b>Comments: </b><a href=\'#\' onclick=\'return false;\'>Hide</a>'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '<b>Comments: </b><a href=\'#\' onclick=\'return false;\'>Show</a>'; }\" ><font color='#ffffff'><b>Comments: </b></font><a href=\"#\" onclick=\"return false;\">Show</a></span></div><div class=\"quotecontent\"><div style=\"display: none;\"><div style='background-color:transparent;width:100%;overflow: auto'> " . $imdb_info['comment'] . "</div></div></div></div></div></tr>";
if (empty($torrents["poster"]) or ($torrents["poster"] == "./pic/noposter.jpg") && $omdb['Poster'] != "./pic/noposter.jpg") {
                    sql_query("UPDATE torrents SET poster = " . sqlesc($omdb['Poster']) . " WHERE id = $id LIMIT 1") or
                    sqlerr(__file__, __line__);
                    $torrents["poster"] = $omdb['Poster'];
                    $torrent_cache['poster'] = $omdb['Poster'];
                    if ($torrent_cache) {
                        $mc1->update_row(false, $torrent_cache);
                        $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
                        $mc1->delete_value('top5_tor_');
                        $mc1->delete_value('last5_tor_');
                        $mc1->delete_value('scroll_tor_');
                    }
                }

            }}}


    if (empty($tvmaze_info) && (empty($torrents['url']) || empty($omdb['Title']))) $HTMLOUT .= "<tr><td colspan='2'>No Imdb or TVMaze info</td></tr>";*/

    $omdbapi_key = '*******'; //Change me to your omdbapi key get @ https://www.omdbapi.com/apikey.aspx
    if ((in_array($torrents['category'], $INSTALLER09['movie_cats']))) {
        $imdb_id_new = 0;
        if (preg_match('(.com/title/tt\d+)', $torrents_txt['descr'], $im_match_imdb) && $torrents['url'] == '') {
            $imdb_id_new     = str_replace(".com/title/tt", "", $im_match_imdb[0]);
            $torrents['url'] = 'https://www.imdb.com/title/tt' . $imdb_id_new;
        } //preg_match('(.com/title/tt\d+)', $torrents_txt['descr'], $im_match_imdb) && $torrents['url'] == ''
        elseif (preg_match('(.com/title/tt\d+)', $torrents['url'], $im_match_imdb) && $torrents['url'] != '') {
            $imdb_id_new = str_replace(".com/title/tt", "", $im_match_imdb[0]);
        } //preg_match('(.com/title/tt\d+)', $torrents['url'], $im_match_imdb) && $torrents['url'] != ''

        if ($torrents['url'] != '') {
	    $omdbkey = "omdb_".$imdb_id_new;
	    if (($omdb = $mc1->get_value($omdbkey)) === false) {
                //==auto imdb rewritten putyn 28/06/2011
                $imdb          = '';
                $omdb['Title'] = $omdb['Orig_title'] = $omdb['Year'] = $omdb['Actors'] = $omdb['Rating'] = $omdb['Votes'] = $omdb['Gen'] = $omdb['Runtime'] = $omdb['Country'] = $omdb['Lanuage'] = $omdb['Director'] = $omdb['Producer'] = $omdb['Writer'] = $omdb['Compose'] = $omdb['Plotoutline'] = $omdb['Plot'] = $omdb['Trailers'] = $omdb['Comment'] = $omdb['imdbRating'] = $omdb['imdbVotes'] = "";
                //            $imdb_info = get_imdb($torrents['url']);

                $imdb_id = $imdb_id_new;

                $url  = file_get_contents("https://www.omdbapi.com/?i=tt" . $imdb_id . "&plot=short&r=json&apikey=" . $omdbapi_key);
                $omdb = json_decode($url, true);

                $poster = $omdb['Poster'];
                if ($poster !== "N/A") {
                    $poster = $omdb['Poster'];
                    $omdb['Poster'] = "/imdb/images/" . $imdb_id . ".jpg";
                    if (!file_exists('./imdb/images/' . $imdb_id . '.jpg')) {
                        @copy("$poster", "./imdb/images/" . $imdb_id . ".jpg");
                    } //!file_exists('./imdb/images/' . $imdb_id . '.jpg')
                } //$poster != "N/A"
                else {
                    $omdb['Poster'] = "./pic/noposter.jpg";
                }
		$mc1->cache_value($omdbkey, $omdb, 10080); // 7 Days
	    }

            //displaying results
            if ($omdb['Title'] != '') {

                $HTMLOUT .= "<tr><th class=' col-md-1 text-center'><div><img src='$poster'  style='width:214px; height:315px;' alt='Poster' title='Poster' /><br/><br/></td></div>
	</th><th class=' col-md-5 text-left'>
        <strong><span style='color:#79c5c5;'>Title:</span></strong><font color='orange'> " . $omdb['Title'] . "</font><br/><br/>
        <strong><span style='color:#79c5c5;'>Released:</span></strong><font color='orange'> " . $omdb['Released'] . "</font><br/><br/>
        <strong><span style='color:#79c5c5;'>Genre:</span></strong><font color='orange'> " . $omdb['Genre'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Rated:</span></strong><font color='orange'> " . $omdb['Rated'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Director:</span></strong><font color='orange'> " . $omdb['Director'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Cast:</span></strong><font color='orange'> " . $omdb['Actors'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Description:</spn></strong><font color='orange'> " . $omdb['Plot'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Runtime:</span></strong><font color='orange'> " . $omdb['Runtime'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Rating:</span></strong><font color='orange'> " . $omdb['imdbRating'] . "/10</font><br/><br/>
	<strong><span style='color:#79c5c5;'>Votes:</span></strong><font color='orange'> " . $omdb['imdbVotes'] . "</font><br/><br/>
	<strong><span style='color:#79c5c5;'>URL:</span></strong><font color='orange'><a href='" . $torrents['url'] . "'> " . $torrents['url'] . "</a></font></td></tr>";
	          if (empty($torrents["poster"]) or ($torrents["poster"] == "./pic/noposter.jpg") && $omdb['Poster'] != "./pic/noposter.jpg") {
                    sql_query("UPDATE torrents SET poster = " . sqlesc($omdb['Poster']) . " WHERE id = $id LIMIT 1") or sqlerr(__file__, __line__);
                    $torrents["poster"]      = $omdb['Poster'];
                    $torrent_cache['poster'] = $omdb['Poster'];
                    if ($torrent_cache) {
                        $mc1->update_row(false, $torrent_cache);
                        $mc1->commit_transaction($INSTALLER09['expires']['torrent_details']);
                        $mc1->delete_value('top5_tor_');
                        $mc1->delete_value('last5_tor_');
                        $mc1->delete_value('scroll_tor_');
                    } //$torrent_cache
                } //empty($torrents["poster"]) or ($torrents["poster"] == "./pic/noposter.jpg") && $omdb['Poster'] != "./pic/noposter.jpg"

            } //$omdb['Title'] != ''
        } //$torrents['url'] != ''
    } //(in_array($torrents['category'], $INSTALLER09['movie_cats']))
    if (empty($tvmaze_info) && (empty($torrents['url']) || empty($omdb['Title'])))
	$HTMLOUT .= "";
//        $HTMLOUT .= "<tr><td colspan='2'>No Imdb or TVMaze info</td></tr>";


   /* if ((in_array($torrents['category'], $INSTALLER09['music_cats']))) {

        $music = ((empty($torrents['poster'])) ? $INSTALLER09['pic_base_url'] . 'music.jpg' : htmlsafechars($torrents["poster"]));
        $HTMLOUT .= "<tr><th class=' col-md-1 text-center'><div><img src='$poster'  style='width:180px;' alt='Poster' title='Poster' /><br/><br/></td></div>";

        empty($torrents["poster"]) or ($torrents["poster"] == "./pic/music.jpg");
    }*/


$HTMLOUT.= "</table>";
 // $Free_Slot = $freeslot;
 // $Free_Slot_Zip = $freeslot_zip;
 // $Free_Slot_Text = $freeslot_text;
$HTMLOUT.= "<table class='table table-bordered'><tr><td colspan='4' class='heading'><strong>{$lang['details_download']}</strong></td></tr>
          <tr>
          <td class='row-fluid col-sm-1' align='left'>Torrent</td>
          <td colspan='3' class='text-left'>
          <a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "\" rel='balloon3' \">&nbsp;<u>" . htmlsafechars($torrents["filename"]) . "</u></a>{$Free_Slot}
          </td>
        </tr>";
    //==Torrent as zip by putyn
    $HTMLOUT.= "<tr>
        <td class='row-fluid col-sm-1' align='left'>{$lang['details_zip']}</td>
        <td colspan='3' class='text-left'>
        <a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;zip=1\" rel='balloon3' \">&nbsp;<u>" . htmlsafechars($torrents["filename"]) . "</u></a>{$Free_Slot_Zip}</td></tr>";
    //==Torrent as text by putyn
    $HTMLOUT.= "<tr>
        <td class='row-fluid col-sm-1' align='left'>{$lang['details_text']}</td>
        <td colspan='3' class='text-left'>
        <a class=\"index\" href=\"download.php?torrent={$id}" . ($CURUSER['ssluse'] == 3 ? "&amp;ssl=1" : "") . "&amp;text=1\" rel='balloon3' \">&nbsp;<u>" . htmlsafechars($torrents["filename"]) . "</u></a>{$Free_Slot_Text}</td></tr></table>";
    /** end **/
    $HTMLOUT .= "<table class='table  table-bordered'><tr><td colspan='4' class='heading'><strong>{$lang['details_description']}</strong></td></tr>
            ";
if (!empty($torrents_txt["descr"])) {
    $HTMLOUT.= "<tr>
    <td colspan='4'><br />
    " . str_replace(array("\n","  ") , array("\n","&nbsp; ") , format_comment($torrents_txt["descr"])) . "<!--</div>--></td></tr>";
} else {
$HTMLOUT.= "<td colspan='4' class='text-center'>".$tvmaze_info."</td></tr></table>";
}

    if (!empty($torrents['youtube'])) {
        $HTMLOUT.= '<table class="table  table-bordered"><tr><td align="center"><img width="150px" height="150px" src="' . $INSTALLER09['pic_base_url'] . 'trailer.png" /></td><td><object type="application/x-shockwave-flash" style="width:560px; height:340px;" data="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '"><parm name="movie" value="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '"></object><br /><a href="' . htmlsafechars($torrents['youtube']) . '" target="_blank">' . $lang['details_youtube_link'] . '</a></div></td></tr></table>';
//	$HTMLOUT.= tr(Trailer, '<object type="application/x-shockwave-flash" style="width:560px; height:340px;" data="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '"><param name="movie" value="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '" /></object><br /><a href=\'' . htmlsafechars($torrents['youtube']) . '\' target=\'_blank\'>' . $lang['details_youtube_link'] . '</a>', 1);
//$HTMLOUT.= tr(Trailer, '<object type="application/x-shockwave-flash" style="width:560px; height:340px;" data="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '"><param name="movie" value="' . str_replace('watch?v=', 'v/', $torrents['youtube']) . '" /></object><br /><a href=\'' . htmlsafechars($torrents['youtube']) . '\' target=\'_blank\'>' . $lang['details_youtube_link'] . '</a>', 1);
    } else {
        $HTMLOUT.= "&nbsp;";
    }
/*
if (!empty($torrents["description"])) {
$HTMLOUT.= "</table><table class='table table-bordered'><tr><td colspan='4' class='heading'><strong>{$lang['details_small_descr']}</strong></td></tr>
        <tr><td colspan='4' class='text-left'>" . htmlsafechars($torrents['description']) . "</td></tr>";
} else {
$HTMLOUT.= "<tr><td colspan='4' class='heading'><strong>{$lang['details_small_descr']}</strong></td></tr><tr><td colspan='4' class='text-left'>No small description found</td></tr>";
}
 $HTMLOUT.= "<tr><td colspan='4' class='heading'><strong>{$lang['details_tags']}</strong></td></tr>
        <tr><td colspan='4' class='text-left'>" . $keywords . "</td>
        </tr>";
*/
//== Similar Torrents mod
$searchname = substr($torrents['name'], 0, 6);
$query1 = str_replace(" ", ".", sqlesc("%" . $searchname . "%"));
$query2 = str_replace(".", " ", sqlesc("%" . $searchname . "%"));
if (($sim_torrents = $mc1->get_value('similiar_tor_' . $id)) === false) {
    $r = sql_query("SELECT id, name, size, added, seeders, leechers, category FROM torrents WHERE name LIKE {$query1} AND id <> " . sqlesc($id) . " OR name LIKE {$query2} AND id <> " . sqlesc($id) . " ORDER BY name LIMIT 6") or sqlerr(__FILE__, __LINE__);
    while ($sim_torrent = mysqli_fetch_assoc($r)) $sim_torrents[] = $sim_torrent;
    $mc1->cache_value('similiar_tor_' . $id, $sim_torrents, 86400);
}
if (count($sim_torrents) > 0) {
    $sim_torrent = "<table class='table  table-bordered'>\n" . "
        <thead>
        <tr>
        <th>Type</th>
        <th>Name</th>
        <th>Size</th>
        <th>Added</th>
        <th>Seeders</th>
        <th>Leechers</th>
        </tr>
        </thead>\n";
    if ($sim_torrents) {
        foreach ($sim_torrents as $a) {
            $sim_tor['cat_name'] = htmlsafechars($change[$a['category']]['name']);
            $sim_tor['cat_pic'] = htmlsafechars($change[$a['category']]['image']);
            $cat = "<img src=\"pic/caticons/{$CURUSER['categorie_icon']}/{$sim_tor['cat_pic']}\" alt=\"{$sim_tor['cat_name']}\" title=\"{$sim_tor['cat_name']}\" />";
            $name = htmlsafechars($a["name"]);
            $seeders = (int)$a["seeders"];
            $leechers = (int)$a["leechers"];
            $added = get_date($a["added"], 'DATE', 0, 1);
            $sim_torrent.= "<tr>
            <td class='one' style='padding: 0px; border: none' width='40px'>{$cat}</td>
            <td class='one'><a href='details.php?id=" . (int)$a["id"] . "&amp;hit=1'>{$name}</a></td>
            <td class='one' style='padding: 1px' align='center'>" . mksize($a['size']) . "</td>
            <td class='one' style='padding: 1px' align='center'>{$added}</td>
            <td class='one' style='padding: 1px' align='center'><span style='color:green;'>{$seeders}</span></td>
            <td class='one' style='padding: 1px' align='center'><span style='color:red;'>{$leechers}</span></td></tr>\n";
        }
        $sim_torrent.= "</table>";
    }
if (empty($sim_torrents)) $HTMLOUT.= "";
else {
 $HTMLOUT.= "<table class='table  table-bordered'><tr><td colspan='4' class='heading'><strong>Related Torrents</strong></td></tr>";
 $HTMLOUT.= "<tr><td align='left' class='heading'>$sim_torrent</td></tr></table>";
    }
}
$HTMLOUT .= '</div></div></div></table>';
$HTMLOUT.= "<div class='panel-heading'>
        <label for='checkbox_4' class='text-left'>{$lang['details_info']}</label></div><div class='row'>
<div class='col-md-12'><table class='table table-bordered'>";
 /** pdq's ratio afer d/load **/
    $downl = ($CURUSER["downloaded"] + $torrents["size"]);
    $sr = $CURUSER["uploaded"] / $downl;
    switch (true) {
    case ($sr >= 4):
        $s = "w00t";
        break;

    case ($sr >= 2):
        $s = "grin";
        break;

    case ($sr >= 1):
        $s = "smile1";
        break;

    case ($sr >= 0.5):
        $s = "noexpression";
        break;

    case ($sr >= 0.25):
        $s = "sad";
        break;

    case ($sr > 0.00):
        $s = "cry";
        break;

    default;
    $s = "w00t";
    break;
}
$sr = floor($sr * 1000) / 1000;
$sr = "<font color='" . get_ratio_color($sr) . "'>" . number_format($sr, 3) . "</font>&nbsp;&nbsp;<img src=\"pic/smilies/{$s}.gif\" alt=\"\" />";
if ($torrents['free'] >= 1 || $torrents['freetorrent'] >= 1 || $isfree['yep'] || $free_slot OR $double_slot != 0 || $CURUSER['free_switch'] != 0) {
    $HTMLOUT.= "
        <td class='heading' valign='top' align='left' width='8%'>Ratio After Download</td>
        <td class='details-text-ellipsis'><del>{$sr}&nbsp;&nbsp;Your new ratio if you download this torrent.</del><br /><b><font size='' color='#FF0000'>FREE</font></b>&nbsp;(Only upload stats are recorded)</td>";
} else {
    $HTMLOUT.= "
        <td class='heading' valign='top' align='left' width='8%'>Ratio After Download</td>
        <td>{$sr}&nbsp;&nbsp;Your new ratio if you download this torrent.</td>";
}
$rowuser = (isset($torrents['username']) ? ("<a href='userdetails.php?id=" . (int)$torrents['owner'] . "'><b>" . htmlsafechars($torrents['username']) . "</b></a>") : "{$lang['details_unknown']}");
$uprow = (($torrents['anonymous'] == 'yes') ? ($CURUSER['class'] < UC_STAFF && $torrents['owner'] != $CURUSER['id'] ? '' : $rowuser . ' - ') . "<i>{$lang['details_anon']}</i>" : $rowuser);
if ($owned) $uprow.= " $spacer<$editlink><b>{$lang['details_edit']}</b></a>";
$HTMLOUT.="<td class='heading' valign='top' align='left' width='8%'>Uploader</td><td>" . $uprow . "</td></tr>";
//==End
/**  Mod by dokty, rewrote by pdq  **/
/*
$my_points = 0;
    if (($torrent['torrent_points_'] = $mc1->get('coin_points_' . $id)) === false) {
        $sql_points = sql_query('SELECT userid, points FROM coins WHERE torrentid=' . sqlesc($id));
        $torrent['torrent_points_'] = array();
        if (mysqli_num_rows($sql_points) !== 0) {
            while ($points_cache = mysqli_fetch_assoc($sql_points)) $torrent['torrent_points_'][$points_cache['userid']] = $points_cache['points'];
        }
        $mc1->add('coin_points_' . $id, $torrent['torrent_points_'], 0);
    }
    $my_points = (isset($torrent['torrent_points_'][$CURUSER['id']]) ? (int)$torrent['torrent_points_'][$CURUSER['id']] : 0);
    $HTMLOUT.= '<tr>
        <td class="heading" valign="top" align="left" width="7%">Bonus Points</td>
        <td valign="top" align="left" width="42%"><b>In total ' . (int)$torrents['points'] . ' Karma Points given to this torrent of which ' . $my_points . ' from you.<br />
        <a href="coins.php?id=' . $id . '&amp;points=10"><img src="' . $INSTALLER09['pic_base_url'] . '10coin.png" alt="10" title="10 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=20"><img src="' . $INSTALLER09['pic_base_url'] . '20coin.png" alt="20" title="20 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=50"><img src="' . $INSTALLER09['pic_base_url'] . '50coin.png" alt="50" title="50 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=100"><img src="' . $INSTALLER09['pic_base_url'] . '100coin.png" alt="100" title="100 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=200"><img src="' . $INSTALLER09['pic_base_url'] . '200coin.png" alt="200" title="200 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=500"><img src="' . $INSTALLER09['pic_base_url'] . '500coin.png" alt="500" title="500 Points" width="40" /></a>&nbsp;&nbsp;
        <a href="coins.php?id=' . $id . '&amp;points=1000"><img src="' . $INSTALLER09['pic_base_url'] . '1000coin.png" alt="1000" title="1000 Points" width="40" /></a></b>
        <br />By clicking on the coins you can give Karma Points to the uploader.</td>';
//== Tor Reputation by pdq
if ($torrent_cache['rep']) {
    $torrents = array_merge($torrents, $torrent_cache['rep']);
    $member_reputation = get_reputation($torrents, 'torrents', $torrents['anonymous']);
    $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>Reputation</td>
        <td>" . $member_reputation . " (counts towards uploaders Reputation)</td></tr>";
}
*/
function hex_esc($matches) {
    return sprintf("%02x", ord($matches[0]));
}
//==End
$HTMLOUT .= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_info_hash']}</td><td>" .preg_replace_callback('/./s', "hex_esc", hash_pad($torrents["info_hash"])) . "</td>";
} else {
    $HTMLOUT.="<td class='heading' valign='top' align='left' width='8%'>Download Disabled!!</td><td>Your not allowed to download presently !!</td>";
}
//==Report Torrent Link
$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>Report This</td><td><form action='report.php?type=Torrent&amp;id=$id' method='post'><input class='btn btn-primary btn-xs' type='submit' name='submit' value='Report This Torrent' />&nbsp;&nbsp;<a class='btn btn-warning btn-xs'href='rules.php'><font color='#000000'>For breaking the rules</font></a></form></td></tr>";
$torrents['cat_name'] = htmlsafechars($change[$torrents['category']]['name']);
if (isset($torrents["cat_name"]))
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_type']}</td><td>" .htmlsafechars($torrents["cat_name"]). "</td>";
else $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_type']}</td><td>None</td>";
if ($CURUSER["class"] >= UC_USER && $torrents["nfosz"] > 0) $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_nfo']}</td><td><a href= 'viewnfo.php?id=" . (int)$torrents['id'] . "'><input class='btn btn-primary btn-xs' type='submit' name='submit' value='view nfo' /></a> (" . mksize($torrents["nfosz"]) . ")</td></tr>";
else $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_nfo']}</td><td>No NFO Available</td></tr>";
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>Rating</td><td>" .getRate($id, "torrent"). "</td>";
$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_size']}</td><td>" . mksize($torrents["size"]) . " (" . number_format($torrents["size"]) . " {$lang['details_bytes']})</td></tr>";
/*
//==subs by putyn
if (in_array($torrents["category"], $INSTALLER09['movie_cats']) && !empty($torrents["subs"])) {
    $HTMLOUT.= "<tr>
        <td class='heading' valign='top' align='left' width='8%'>Subtitles</td>
        <td>";
    $subs_array = explode(",", $torrents["subs"]);
    foreach ($subs_array as $k => $sid) {
        require_once (CACHE_DIR . 'subs.php');
        foreach ($subs as $sub) {
            if ($sub["id"] == $sid) $HTMLOUT.= "<img border=\"0\" width=\"25px\" style=\"padding:3px;\"src=\"" . htmlsafechars($sub["pic"]) . "\" alt=\"" . htmlsafechars($sub["name"]) . "\" title=\"" . htmlsafechars($sub["name"]) . "\" />";
        }
    }
    $HTMLOUT.= "</td>";
    }else {
            $HTMLOUT.= "<tr>
            <td class='heading' valign='top' align='left' width='8%'>Subtitles</td>
            <td>No Subtitles Supplied</td>";
    }*/
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_added']}</td><td>" . get_date($torrents['added'], "{$lang['details_long']}"). "</td>";
//Display pretime
    if ($pretime['time'] == '0') {
    $prestatement = "No pretime found";
    } else {
    $prestatement = get_pretime(time() -  $pretime['time']) . " ago<br />Uploaded " . get_pretime($torrents['added'] - $pretime['time']) . " after pre.";
    }
$HTMLOUT.="<td class='heading' valign='top' align='left' width='8%'>Pre Time</td><td>". $prestatement."</td></tr>";
//==
    if (!isset($_GET["filelist"])) $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_num_files']}</td><td>" . (int)$torrents["numfiles"] . " files&nbsp;&nbsp;<a href=\"./filelist.php?id=$id\" class=\"btn btn-primary btn-xs\">{$lang['details_list']}</a></td>";
    else {

 $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_num-files']}</td><td>" . (int)$torrents["numfiles"] . "{$lang['details_files']}</td>";
    }
//$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_views']}</td><td>" . (int)$torrents["views"] . "</td>";
//$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_hits']}</td><td>" . (int)$torrents["hits"] . "</td></tr>";
$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_last_seeder']}</td><td>{$lang['details_last_activity']}" . get_date($l_a['lastseed'], '', 0, 1) . "</td></tr>";
if(XBT_TRACKER == true) {
//$HTMLOUT.= tr("{$lang['details_peers']}", (int)$torrents_xbt["seeders"] . " seeder(s), " . (int)$torrents_xbt["leechers"] . " leecher(s) = " . ((int)$torrents_xbt["seeders"] + (int)$torrents_xbt["leechers"]) . "{$lang['details_peer_total']}", 1);
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_peers']}</td><td> " . (int)$torrents_xbt["seeders"] . " seeder(s), " . (int)$torrents_xbt["leechers"] . " leecher(s) = " . ((int)$torrents_xbt["seeders"] + (int)$torrents_xbt["leechers"]) . "{$lang['details_peer_total']}&nbsp;<a href=\"./peerlist_xbt.php?id=$id#seeders\" class=\"btn btn-primary btn-xs\">{$lang['details_list']}</a></td>";
} else {
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_peers']}</td><td> " . (int)$torrents["seeders"] . " seeder(s), " . (int)$torrents["leechers"] . " leecher(s) = " . ((int)$torrents["seeders"] + (int)$torrents["leechers"]) . "{$lang['details_peer_total']}&nbsp;&nbsp;<a href=\"./peerlist.php?id=$id#seeders\" class=\"btn btn-primary btn-xs\">&nbsp;{$lang['details_list']}</a></td>";
}
$XBT_Or_Default = (XBT_TRACKER == true ? 'snatches_xbt.php?id=' : 'snatches.php?id=');
$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_snatched']}</td><td>{$torrents['times_completed']} {$lang['details_times']}&nbsp;&nbsp;<a class='btn btn-primary btn-xs' href='{$INSTALLER09["baseurl"]}/{$XBT_Or_Default}{$id}'>{$lang['details_list']}</a></td></tr>";
//==09 Reseed by putyn
$next_reseed = 0;
if ($torrents["last_reseed"] > 0) $next_reseed = ($torrents["last_reseed"] + 172800); //add 2 days
$reseed = "<form method=\"post\" action=\"./takereseed.php\">
      <select name=\"pm_what\">
      <option value=\"last10\">last10</option>
      <option value=\"owner\">uploader</option>
      </select>&nbsp;&nbsp;
      <input class=\"btn btn-primary btn-xs\" type=\"submit\"  " . (($next_reseed > TIME_NOW) ? "disabled='disabled'" : "") . " value=\"Send PM\" />
      <input type=\"hidden\" name=\"uploader\" value=\"" . (int)$torrents["owner"] . "\" />
      <input type=\"hidden\" name=\"reseedid\" value=\"$id\" />
      </form>";
$HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>Request reseed</td><td>" . $reseed . "</td>";
$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>Bookmark</td><td><a class='btn btn-primary btn-xs' href='bookmark.php?torrent=" . $id . "&amp;action=add' name='" . $id . "'>[Bookmark It!]</a></td></tr></table></div></div>";
//$XBT_Or_Default = (XBT_TRACKER == true ? 'snatches_xbt.php?id=' : 'snatches.php?id=');
//$HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_snatched']}</td><td>{$torrents['times_completed']} {$lang['details_times']}&nbsp;&nbsp;<a class='btn btn-primary btn-xs' href='{$INSTALLER09["baseurl"]}/{$XBT_Or_Default}{$id}'>{$lang['details_list']}</a></td></tr></table></div></div>";
//==End
//==putyns thanks mod
      //==End
if ($CURUSER['class'] >= UC_STAFF) {
    $HTMLOUT.= "<div class='panel-heading'>
        <label for='checkbox_4' class='text-left'>Staff Info</label></div><div class='row'>
<div class='col-md-12'><table class='table table-bordered'>";
if ($torrents["visible"] == "no") $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_visible']}</td><td width='42%'><b>{$lang['details_no']}</b>{$lang['details_dead']}</td>";
else $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>{$lang['details_visible']}</td><td width='42%'>Yes!</td>";
if ($moderator) $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>{$lang['details_banned']}</td><td> " . $torrents["banned"] . " </td></tr>";
if ($torrents["nuked"] == "yes") $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>Nuked</td><td><img src='{$INSTALLER09['pic_base_url']}nuked.gif' alt='Nuked' title='Nuked' /></td>";
else $HTMLOUT.= "<tr><td class='heading' valign='top' align='left' width='8%'>Nuked</td><td width='42%'>{$lang['details_no']}</td>";
//==pdq's Torrent Moderation
    if (!empty($torrents['checked_by'])) {
        if (($checked_by = $mc1->get_value('checked_by_' . $id)) === false) {
            $checked_by = mysqli_fetch_assoc(sql_query("SELECT id FROM users WHERE username=" . sqlesc($torrents['checked_by']))) or sqlerr(__FILE__, __LINE__);
            $mc1->add_value('checked_by_' . $id, $checked_by, 30 * 86400);
        }
        $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>Checked by</td>
    <td>
<a href='{$INSTALLER09["baseurl"]}/userdetails.php?id=" . (int)$checked_by['id'] . "'>
    <strong>" . htmlsafechars($torrents['checked_by']) . "</strong></a>
    <a href='{$INSTALLER09["baseurl"]}/details.php?id=" . (int)$torrents['id'] . "&amp;rechecked=1'>
    <em class='label label-warning'><strong>Re-Check this torrent</strong></em></a>
    <a href='{$INSTALLER09["baseurl"]}/details.php?id=" . (int)$torrents['id'] . "&amp;clearchecked=1'>
    <em class='label label-danger'><strong>Un-Check this torrent</strong></em></a>
    &nbsp;<em class='label label-primary'>* STAFF Eyes Only *</em>
    ".(isset($torrents["checked_when"]) && $torrents["checked_when"] > 0 ? "<strong>Checked When : ".get_date($torrents["checked_when"],'DATE',0,1)."</strong>":'' )."</td></tr>";
    } else {
        $HTMLOUT.= "<td class='heading' valign='top' align='left' width='8%'>Checked by</td><td><em class='label label-danger'><strong>NOT CHECKED!</strong></em>
       <a href='{$INSTALLER09["baseurl"]}/details.php?id=" . (int)$torrents['id'] . "&amp;checked=1'>
       <em class='label label-success'><strong>Check this torrent</strong></em></a>&nbsp;<em class='label label-primary'><strong>* STAFF Eyes Only *</strong></em></p></td></tr>";
    }
}
if (!empty($torrents["nukereason"])) $HTMLOUT.= "<tr><td class='rowhead'><b>Nuke-Reason</b></td><td align='left'>" . htmlsafechars($torrents["nukereason"]) . "</td></tr>\n";
//==
$HTMLOUT.= "</table>
        </div>
     </div>";
$HTMLOUT.= "
<div align='center'>";
$HTMLOUT .="</div></div>";
$HTMLOUT .="</div>";

$HTMLOUT.= "<div class='panel-heading'>
    <label for='checkbox_4' class='text-center'>Post a comment</label></div>";
$HTMLOUT.= "<div class='row'><div class='col-md-12'>
    <form name='comment' method='post' action='comment.php?action=add&amp;tid=$id'>
    <table class='table table-bordered'>
    <tr><td align='center'><br />
    <textarea class='form-control' name='body' cols='280' rows='4'></textarea>
    <input type='hidden' name='tid' value='" . htmlsafechars($id) . "' />
    <a href=\"javascript:SmileIT(':-)','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/smile1.gif' alt='Smile' title='Smile' /></a>
    <a href=\"javascript:SmileIT(':smile:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/smile2.gif' alt='Smiling' title='Smiling' /></a>
    <a href=\"javascript:SmileIT(':-D','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/grin.gif' alt='Grin' title='Grin' /></a>
    <a href=\"javascript:SmileIT(':lol:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/laugh.gif' alt='Laughing' title='Laughing' /></a>
    <a href=\"javascript:SmileIT(':w00t:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/w00t.gif' alt='W00t' title='W00t' /></a>
    <a href=\"javascript:SmileIT(':blum:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/blum.gif' alt='Rasp' title='Rasp' /></a>
    <a href=\"javascript:SmileIT(';-)','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/wink.gif' alt='Wink' title='Wink' /></a>
    <a href=\"javascript:SmileIT(':devil:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/devil.gif' alt='Devil' title='Devil' /></a>
    <a href=\"javascript:SmileIT(':yawn:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/yawn.gif' alt='Yawn' title='Yawn' /></a>
    <a href=\"javascript:SmileIT(':-/','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/confused.gif' alt='Confused' title='Confused' /></a>
    <a href=\"javascript:SmileIT(':o)','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/clown.gif' alt='Clown' title='Clown' /></a>
    <a href=\"javascript:SmileIT(':innocent:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/innocent.gif' alt='Innocent' title='innocent' /></a>
    <a href=\"javascript:SmileIT(':whistle:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/whistle.gif' alt='Whistle' title='Whistle' /></a>
    <a href=\"javascript:SmileIT(':unsure:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/unsure.gif' alt='Unsure' title='Unsure' /></a>
    <a href=\"javascript:SmileIT(':blush:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/blush.gif' alt='Blush' title='Blush' /></a>
    <a href=\"javascript:SmileIT(':hmm:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/hmm.gif' alt='Hmm' title='Hmm' /></a>
    <a href=\"javascript:SmileIT(':hmmm:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/hmmm.gif' alt='Hmmm' title='Hmmm' /></a>
    <a href=\"javascript:SmileIT(':huh:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/huh.gif' alt='Huh' title='Huh' /></a>
    <a href=\"javascript:SmileIT(':look:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/look.gif' alt='Look' title='Look' /></a>
    <a href=\"javascript:SmileIT(':rolleyes:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/rolleyes.gif' alt='Roll Eyes' title='Roll Eyes' /></a>
    <a href=\"javascript:SmileIT(':kiss:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/kiss.gif' alt='Kiss' title='Kiss' /></a>
    <a href=\"javascript:SmileIT(':blink:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/blink.gif' alt='Blink' title='Blink' /></a>
    <a href=\"javascript:SmileIT(':baby:','comment','body')\"><img border='0' src='{$INSTALLER09['pic_base_url']}smilies/baby.gif' alt='Baby' title='Baby' /></a><br /><br />
    <input class='btn btn-primary btn-xs' type='submit' value='Submit' /></td></tr></table></form></div></div>";
if ($torrents["allow_comments"] == "yes" || $CURUSER['class'] >= UC_STAFF && $CURUSER['class'] <= UC_MAX) {
    $HTMLOUT.= "<a name=\"startcomments\"></a>\n";
} else {
    $HTMLOUT.= "<div class='panel-heading'>
    <label for='checkbox_4' class='text-center'>Start comments &nbsp;</a><b>{$lang['details_com_disabled']}</label></div>";
    echo stdhead("{$lang['details_details']}\"" . htmlsafechars($torrents["name"], ENT_QUOTES) . "\"", true, $stdhead) . $HTMLOUT . stdfoot($stdfoot);
    die();
}
$HTMLOUT.= "<!-- accordion collapse going here -->
<script type='text/javascript'>
/*<![CDATA[*/
jQuery(document).ready(function() {
  jQuery('.content').hide();
  //toggle the componenet with class msg_body
  jQuery('.h1').click(function()
  {
    jQuery(this).next('.content').slideToggle(500);
  });
});
/*]]>*/
</script>";
$commentbar = "<p class='h1 btn btn-primary' style='margin-left: 290px;'>Comments Open/Close</p>
    <div class='content'><h4 align='center' ><br /><a class='btn btn-primary' href='comment.php?action=add&amp;tid=$id'>{$lang['details_add_comment']}</a>
    <br /><a class='index' href='{$INSTALLER09['baseurl']}/takethankyou.php?id=" . $id . "'>
    <br /><img src='{$INSTALLER09['pic_base_url']}smilies/thankyou.gif' alt='Thanks' title='Thank You' border='0' /></a></h4>";
$count = (int)$torrents['comments'];
if (!$count) {
    $HTMLOUT.= "<table align='center' class='table table-bordered'><td align='center'>{$lang['details_no_comment']}</td></table>";
} else {
    $perpage = 15;
    $pager = pager($perpage, $count, "details.php?id=$id&amp;", array(
        'lastpagedefault' => 1
    ));
    $subres = sql_query("SELECT comments.id, comments.text, comments.user_likes, comments.user, comments.torrent, comments.added, comments.anonymous, comments.editedby, comments.editedat, users.avatar, users.av_w, users.av_h, users.offavatar, users.warned, users.reputation, users.opt1, users.opt2, users.mood, users.username, users.title, users.class, users.donor FROM comments LEFT JOIN users ON comments.user = users.id WHERE torrent = " . sqlesc($id) . " ORDER BY comments.id " . $pager['limit']) or sqlerr(__FILE__, __LINE__);
    $allrows = array();
    while ($subrow = mysqli_fetch_assoc($subres)) $allrows[] = $subrow;
    $HTMLOUT.= "<br />";
    $HTMLOUT.= $pager['pagertop'];
    $HTMLOUT.= "<br />";
    $HTMLOUT.= commenttable($allrows);
    $HTMLOUT.= $pager['pagerbottom'];
    $HTMLOUT.= "<br /></div></div></table>";
}

$HTMLOUT.= "</div>
</div>
</div>
</div>
</div>";
$HTMLOUT.="</div></div><br /><br /><div style='margin-left:13%;width:89%;' class='panel-default'><div class='col-md-4'></div><div class='col-md-10'>";
//////////////////////// HTML OUTPUT ////////////////////////////
echo stdhead("{$lang['details_details']}" . htmlsafechars($torrents["name"], ENT_QUOTES) . "", true, $stdhead) .$HTMLOUT . stdfoot($stdfoot);
?>
