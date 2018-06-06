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
    require_once INCL_DIR . 'bbcode_functions.php';
    require_once INCL_DIR . 'html_functions.php';
    dbconn(false);
    loggedinorreturn();
    $lang = load_language('global');
    $htmlout = '';
    define('IMDB_IMG_DIR', BITBUCKET_DIR . DIRECTORY_SEPARATOR . 'imdb');
    if (!is_dir(IMDB_IMG_DIR)) {
        mkdir(IMDB_IMG_DIR);
    }
    $INSTALLER09['expires']['imdb_upcoming'] = 1440; // 1440 = 1 day
    if (($imdb_upcoming = $mc1->get_value('imdb_upcoming_')) === false) {
        $cr2 = curl_init("https://www.imdb.com/movies-coming-soon/");
        curl_setopt($cr2, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
        curl_setopt($cr2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cr2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($cr2, CURLOPT_POST, 0);
        curl_setopt($cr2, CURLOPT_HTTPHEADER, array("Accept-language: en\r\n"));
        curl_setopt($cr2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($cr2, CURLOPT_SSL_VERIFYHOST, false);
        $imdbhtml = curl_exec($cr2);
        curl_close($cr2);
        preg_match_all('/<h4.*<a name=.*>(.*)&nbsp;/i', $imdbhtml, $datestemp);
        $dates = $datestemp[1];
        $regex = '';
        foreach ($dates as $date) {
            $regex .= '<a name(.*)';
        }
        $regex .= 'see-more';
        preg_match("/$regex/isU", $imdbhtml, $datemovies);
        $temp = array();
        foreach ($datemovies as $key => $value) {
            preg_match_all('/<table(.*)<\/table/isU', $value, $out);
            if ($key != 0) {
                $temp[$dates[$key - 1]] = $out[1];
            }

        }
        foreach ($dates as $date) {
            $i = 0;
            foreach ($temp[$date] as $code) {
                preg_match('/src="(.*)".*"\/title\/(tt\d+)\/.*".*title="(.*)".*itemprop="genre">(.*)<\/p>.*description">(.*)<\/div>.*itemprop=\'url\'>(.*)<\/a>.*Stars:(.*)<\/div>/isU', $code, $out);
                foreach ($out as $key => $value) {
                    if ($key != 0) {
                        $out[$key] = strip_tags($value);
                    }
                }
                $imdbout[$date][$i]['title'] = $out[3];
                $imdbout[$date][$i]['num'] = $out[2];
                get_imdbimg($out[1], $out[2]);
                $imdbout[$date][$i]['genres'] = preg_replace('/\s+/', ' ', $out[4]);
                $imdbout[$date][$i]['plot'] = preg_replace('/^\s+/', ' ', $out[5]);
                $imdbout[$date][$i]['director'] = preg_replace('/^\s+/', ' ', $out[6]);
                $imdbout[$date][$i]['stars'] = preg_replace('/\s+/', ' ', $out[7]);
                $i++;
            }
        }
        $imdb_upcoming = serialize($imdbout);
        $mc1->cache_value('imdb_upcoming_', $imdb_upcoming, $INSTALLER09['expires']['imdb_upcoming']);
    }

    $dates = unserialize($imdb_upcoming);
    $htmlout = '';
    $htmlout .= "<div class='panel panel-default'>
	            <div class='panel-heading'>
		        <label for='checkbox_4' class='text-left'>Coming To Theaters Soon</label></div><div class='panel-body'>";
    foreach ($dates as $date => $items) {
        foreach ($items as $row) {
            $htmlout .= "
     <table align='center' class='table table-bordered'><tr><td class='heading' colspan='2'>
		        <b><a href='https://www.imdb.com/title/{$row['num']}'>{$row['title']}</a></b><div class='pull-right'>Release Date: {$date}</div>
</td></tr>
<th class=' col-md-1 text-center'><div style='display:block; height:10px;'></div>
<a href=\"img.php/imdb/" . htmlsafechars($row["num"]) . ".jpg\"><img height='240px' src=\"img.php/imdb/" . htmlsafechars($row["num"]) . ".jpg\" border=\"0\" width=\"180\" height=\"250\" alt=\"{$row['title']}\" title=\"{$row['title']}\" /></a>
</div><div style='display:block; height:10px;'></div></th>
 ";
           $htmlout .= "<th class=' col-md-8 text-left'><div style='display:block; height:7px;'></div>
               <b><font color='red'>Title:</font></b>&nbsp;<font color='orange'>" . $row['title'] . "</font><div style='display:block; height:7px;'></div>
               <b><font color='red'>Genre(s):</font></b>&nbsp;<font color='orange'>" . $row['genres'] . "</font><div style='display:block; height:7px;'></div>
               <b><font color='red'>Director:</font></b>&nbsp;<font color='orange'>" . $row['director'] . "</font><div style='display:block; height:7px;'></div>
               <b><font color='red'>Starring:</font></b>&nbsp;<font color='orange'>" . $row['stars'] . "</font><div style='display:block; height:7px;'></div>
               <b><font color='red'>Plot:</font></b>&nbsp;<font color='orange'>" . $row['plot'] . "</font><br>
             </th></div>";
        }
        $htmlout .= "</div>";
    }
    $htmlout .= "</table></div></div>";
    echo stdhead("Upcoming Movies") . $htmlout . stdfoot();
    function get_imdbimg($img, $num) {
        if (!file_exists(IMDB_IMG_DIR . DIRECTORY_SEPARATOR . $num . ".jpg")) {
            $poster = str_replace('http://', 'https://', $img);
            $cr = curl_init($poster);
            curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
            $imgfile = curl_exec($cr);
            $curlResponse = curl_getinfo($cr);
            curl_close($cr);
            file_put_contents(IMDB_IMG_DIR . DIRECTORY_SEPARATOR . $num . ".jpg", $imgfile);
            if (filesize(IMDB_IMG_DIR . DIRECTORY_SEPARATOR . $num . ".jpg") == 0) {
                unlink(IMDB_IMG_DIR . DIRECTORY_SEPARATOR . $num . ".jpg");
            }
        }
    }
