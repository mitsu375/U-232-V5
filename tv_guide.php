<?php
/**
 |--------------------------------------------------------------------------|
 |   https://github.com/Bigjoos/                                            |
 |--------------------------------------------------------------------------|
 |   Licence Info: WTFPL                                                    |
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
require_once __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php';
require_once INCL_DIR . 'user_functions.php';
require_once (INCL_DIR . 'bbcode_functions.php');
require_once INCL_DIR . 'html_functions.php';
require_once INCL_DIR . 'getpre.php';
dbconn(false);
loggedinorreturn();
$lang = load_language('global');
$lcountry = (isset($_REQUEST['country']))? $_REQUEST["country"]:"US";
if (($tvsched = $mc1->get_value('schedule_'.$lcountry)) === false) {
    $tvmaze = file_get_contents('https://api.tvmaze.com/schedule?country='.$lcountry);
    $tvsched = json_decode($tvmaze, true);
if (count($tvsched) > 0)
    $mc1->cache_value('schedule_'.$lcountry, $tvsched, 60 * 60);
}
    switch ($lcountry) {
        case "US":
            $dcountry = "USA";
            break;
        case "GB":
            $dcountry = "United Kingdom";
            break;
        case "CA":
            $dcountry = "Canada";
            break;
        case "AU":
            $dcountry = "Australia";
            break;
    }
$HTMLOUT = '';
$HTMLOUT .= "<div class='panel panel-default'><div class='panel-heading'>
			<label for='checkbox_4' class='text-left'>
            	Todays ".$dcountry ." TV Schedule</label><span class='navbar-nav navbar-right'>
				   <a class='btn btn-primary btn-xs'href='tv_schedule.php?country=US'><img src='pic/flag/usa.gif' height='18px'>&nbsp;&nbsp;&nbsp;Schedule</a>&nbsp;&nbsp;
				   <a class='btn btn-primary btn-xs'href='tv_schedule.php?country=GB'><img src='pic/flag/uk.gif' height='18px'>&nbsp;&nbsp;&nbsp;Schedule</a>&nbsp;&nbsp;
				   <a class='btn btn-primary btn-xs'href='tv_schedule.php?country=CA'><img src='pic/flag/canada.gif' height='18px'>&nbsp;&nbsp;&nbsp;Schedule</a>&nbsp;&nbsp;
				   <a class='btn btn-primary btn-xs'href='tv_schedule.php?country=AU'><img src='pic/flag/australia.gif' height='18px'>&nbsp;&nbsp;&nbsp;Schedule</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </div><div class='panel-body'>";

foreach ($tvsched as $key => $item){
    if (strtotime($item['airstamp']) > TIME_NOW) {
        $HTMLOUT .= "<table align='center' class='table table-bordered'><tr><td class='heading' colspan='2'>
		<b><font color='#79c5c5'>{$item['show']['name']} Season {$item['season']}</font> / <font color='orange'>Episode {$item['number']}</font></b><div class='pull-right'><font color='#79c5c5'>Airs in " . get_pretime(strtotime($item['airstamp']) - TIME_NOW) . " on {$item['show']['network']['name']}</font>
	    </td></tr></div>
        <th class=' col-md-1 text-center'><div style='display:block; height:10px;'></div><img height='240px' src='" . (is_null($item['show']['image']['medium']) ? "pic/noposter.jpg" : $item['show']['image']['medium']) . "'></img></div><div style='display:block; height:10px;'></div></th>
		<th class=' col-md-8 text-left'><div style='display:block; height:7px;'></div><b><font color='#79c5c5'>Episode Title:</font></b>&nbsp;<font color='orange'>" . $item['name'] . "</font><div style='display:block; height:7px;'></div>
		<b><font color='#79c5c5'>Classification:</font></b>&nbsp;<font color='orange'>" . $item['show']['type'] . "</font><div style='display:block; height:7px;'></div>
		<b><font color='#79c5c5'>Rating:</font></b>&nbsp;<font color='orange'>" . (is_null($item['show']['rating']['average']) ? "No Rating Available." : $item['show']['rating']['average']) . "</font><div style='display:block; height:7px;'></div>
		<b><font color='#79c5c5'>Runtime:</font></b>&nbsp;<font color='orange'>" . $item['show']['runtime'] . "&nbsp;mins</font><div style='display:block; height:7px;'></div>
		<b><font color='#79c5c5'>Description:</font></b>&nbsp;<font color='orange'>" . (is_null($item['summary']) ? "No Description Found." : $item['summary']) . "</font></th>
        </table>";
    }
}
$HTMLOUT .= "</div></div>";
echo stdhead("Upcoming TV Episodes") . $HTMLOUT . stdfoot();
