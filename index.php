<?php 
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

include("playlists.php");
date_default_timezone_set("Europe/Dublin");

$param = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$question_pos = strpos($_SERVER['REQUEST_URI'], '?');
if ($question_pos !== false) {
        $question_pos++; // don't want the ?
        $query = substr($_SERVER['REQUEST_URI'], $question_pos);
        parse_str($query, $_GET);
}

if(isset($param)){
    if (!empty($param) && strcspn($param, '0123456789') == strlen($param))
        $param = $param . date("y");
    if(empty($param)) $param = "electricpicnic16"; // Default to EP 2016
    if(empty($param) || !ctype_alnum($param) && strpos($param, "_") === FALSE || !file_exists("sql/".$param.".sqlite3")){
        echo '<h2><b><center>Festival not found: <br /><br /><i>'.$param.'</i><br /><br /><input action="action" type="button" value="Go Back" onclick="window.history.go(-1); return false;" /></center></b></h2>';
        die();
    }
    $festival = ucwords(substr($param, 0, -2));
    if(array_key_exists($festival, $names))
        $festival = $names[$festival];
    $festivallower = strtolower($param);
    $year = "20".substr($festivallower, -2);
}


$db = new PDO("sqlite:sql/".$param.".sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dba = new PDO("sqlite:sql/artists.sqlite3");
$dba->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$check = $dba->prepare("SELECT a.name, p.id FROM artists a INNER JOIN playlists p ON p.name = a.name WHERE a.festival = :festival");
$check->bindParam(':festival', $chk_festival);
$chk_festival = strtolower($param);
$check->execute();
$playlists = $check->fetchAll();

function getartists($a, $d = 0){
	global $dba;
	$resultartists = $dba->prepare("SELECT * FROM artists WHERE festival = '".strtolower($a)."'".($d ? "AND day = '".$d."'" : ""));
    $resultartists->execute();
	return $resultartists->fetchAll();
}

function showrating($r){
    if($r > 0){
        $p = ($r / 5.0) * 100.0;
        return "<span style=\"font-weight:bold;color:".($p > 90 ? '#138900' : ($p > 80 ? '#FF9700' : ($p > 70 ? '#FF5C00' : '#FF0D00'))).";\">".number_format($p, 1)."%</span>";
    } else
        return "<span style=\"font-weight:bold;font-style:italic;\">?? %</span>";
}

function showlikesdislikes($l, $d){
    if(($l + $d) > 0){
        $p = ($l / ($l + $d)) * 100.0;
        return "<span style=\"font-weight:bold;color:".($p > 90 ? '#138900' : ($p > 80 ? '#FF9700' : ($p > 70 ? '#FF5C00' : '#FF0D00'))).";\">".number_format($p, 1)."%</span>";
    } else
        return "<span style=\"font-weight:bold;font-style:italic;\">?? %</span>";
}

function gettitle($title, $name){
    return addslashes(str_replace('"', '', $title));
}

$res = $db->prepare("SELECT count(*) FROM videos");$res->execute(); $rows = $res->fetchColumn(); 
$resa = $dba->prepare("SELECT count(*) FROM artists WHERE festival = '".$festivallower."'");$resa->execute(); $totart = $resa->fetchColumn(); 
$resv = $db->prepare("SELECT sum(views) FROM videos");$resv->execute(); $totviews = $resv->fetchColumn(); 

if(isset($_GET['a']) && strcasecmp($_GET['a'], "all") == 0){
    $result = $db->prepare("SELECT v.* FROM videos v NATURAL JOIN ( SELECT name, MAX(views) AS views FROM videos GROUP BY name )  ORDER BY views DESC");$result->execute();
} else if(isset($_GET['a'])){
    $result = $db->prepare("SELECT * FROM videos WHERE name = '".$_GET['a']."' ORDER BY views DESC");$result->execute();
} else {
    $result = $db->prepare("SELECT * FROM videos ".(isset($_GET['nogroove']) ? 'WHERE name != \'Groove Armada\' ' : '').(isset($_GET['nolana']) ? 'WHERE name != \'Lana Del Rey\' ' : '')."ORDER BY views DESC".(isset($_GET['n']) 
    && is_numeric($_GET['n']) && $_GET['n'] <= $rows ? " LIMIT " . $_GET['n'] : " LIMIT 150"));$result->execute();
}

$count = 1;
$tablestring = "";
$scriptstring = "";
while($row = $result->fetch()){
    $stringtoadd = "{'title':'".gettitle($row['title'], $row['name'])."','url':'".$row['link']."'}";
    $playlistidforartist = array_values(array_filter($playlists, function($ar) {global $row; return ($ar['name'] == $row['name']);}));
    $tablestring = $tablestring . 
        '<div id="'.$row['id'].'" class="row" onclick="SCMPlay(\''.$row['id'].'\', '.$stringtoadd.');"><div class="index">'.$count++.'</div>
            <div class="image"><img src="//i.ytimg.com/vi/'.$row['id'].'/mqdefault.jpg" /></div>
            <div class="description">
                <span class="title">'.$row['title'].'</span>
                <span class="artist"><a href="?a='.urlencode($row['name']).'">'.$row['name'].'</a></span>
                <span class="views">'.number_format($row['views']).' views</span>
            </div>
        </div>';
    $scriptstring = $scriptstring . $stringtoadd . ",";
}
$singlepage = false;
if(isset($_GET['a']) && strtolower($_GET['a']) != "all")
    $singlepage = true;
?>
<html>
<head>
<title><?php echo isset($_GET['a']) ? (strtolower($_GET['a']) == "all" ? 'Top Tracks' : $_GET['a']) . ' | ' : ''; ?><?php echo $festival; ?> Festival <?php echo $year; ?></title>
<link rel="SHORTCUT ICON" href="//festivals.zach.ie/img/<?php echo $festivallower; ?>-favicon.png" id="favicon">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
<meta property="fb:admins" content="1434685963"/>
<meta property="og:site_name" content="<?php echo $festival; ?> Festival <?php echo $year; ?>"/>
<meta property="og:title" content="Top Tracks Playlist - <?php echo $festival; ?> Festival <?php echo $year; ?>"/>
<meta property="og:type" content="website"/>
<meta property="og:url" content="https://festivals.zach.ie/<?php echo $festivallower; ?>"/>
<meta property="og:image" content="https://festivals.zach.ie/img/<?php echo $festivallower; ?>-favicon.png" />
<meta property="og:image:width" content="64" />
<meta property="og:image:height" content="64" />
<meta property="og:description" content="Acts from <?php echo $festival; ?> Festival <?php echo $year; ?> shown sorted by their top tracks on YouTube. Videos are from the auto playlist 'Popular Videos' for each artist." />
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<link rel="stylesheet" href="scm/style.css" type="text/css" id="" media="print, projection, screen" />
<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" />

<!-- SCM Music Player https://scmplayer.net -->
<script type="text/javascript" src="https://festivals.zach.ie/scm/script.js" 
data-config="{'skin':'skins/simpleBlack/skin.css','volume':50,'autoplay':true,'shuffle':false,'repeat':1,'placement':'bottom','showplaylist':false,'playlist':[]}" ></script>
<!-- SCM Music Player script end -->
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ga('create', 'UA-49530760-1', 'zach.ie');
  ga('send', 'pageview');

  var loadPlayer = function() {
    SCM.loadPlaylist([<?php echo rtrim($scriptstring, ","); ?>]);
    if (window.top.SCM.lastVideo && document.getElementById(window.top.SCM.lastVideo)) {
      document.getElementById(window.top.SCM.lastVideo).className = "row playing";
    }
  }

  var SCMPlay = function(video, content) {
    if (window.top.SCM.lastVideo !== video) {
        SCM.play(content);
    }
  }

  document.addEventListener("player-change", function(e) {
    if (window.top.SCM.lastVideo && document.getElementById(window.top.SCM.lastVideo)) {
      document.getElementById(window.top.SCM.lastVideo).className = "row";
    }
    document.getElementById(e.detail).className = "row playing";
    window.top.SCM.lastVideo = e.detail;
  });

  $(document).keypress(function(e) {
    console.log(e.which);
    if(e.which == 32){
      e.preventDefault();
      SCM.togglePlaying();
    } else if(e.which == 39){
      e.preventDefault();
      SCM.next();
    } else if(e.which == 37){
      e.preventDefault();
      SCM.previous();
    }
  });
</script>
</head>
<body onload="loadPlayer();">
<div class="container">
    <div class="content">
        <h1><a href="//festivals.zach.ie/<?php echo $festivallower; ?>" title="<?php echo $festival; ?> Festival <?php echo $year; ?>"><img src="img/<?php echo $festivallower; ?>.png" alt="<?php echo $festival; ?> Festival <?php echo $year; ?>"/></a></h1>
        <h2 class="options">
            <!--<?php if(isset($_GET['a']) && strcasecmp($_GET['a'], "all") !== 0) echo "Displaying results for <a target=\"_blank\" href=\"https://www.google.com/search?q=".urlencode(strtolower($_GET['a']))."\" title=\"View ".$_GET['a']." on Google\"><img style=\"width:15px;vertical-align:top;margin-right:2px;\" src=\"img/".$festivallower."-favicon.png\"><b>".$_GET['a']
            ."</b></a>.<br /><br />"; ?>-->Displaying acts sorted by their top YouTube tracks. <br />Click an artist's name to view their playlist page. <br />
            <div class="settings"><span class="show">Show: </span>
            <span class="button left<?php echo isset($_GET['a']) ? '' : ' selected'; ?>"><a href="/<?php echo $festivallower; ?>">All Tracks</a></span><span class="button <?php echo $singlepage ? 'middle' : 'middle right'; ?><?php echo isset($_GET['a']) && strtolower($_GET['a']) == "all" ? ' selected' : ''; ?>"><a href="?a=all">Top Track per Artist</a></span><?php if($singlepage) { ?><span class="button right selected"><img style="width:15px;vertical-align:top;margin-right:3px;" src="img/<?php echo $festivallower; ?>-favicon.png">Artist</span>
            <?php } ?>
            </div>
        </h2>

        <h2 class="festivals">
        <b>2016:</b>
        <a href="/electricpicnic16" title="Electric Picnic Festival 2016" <?php echo ($festivallower == "electricpicnic16" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/electricpicnic16-favicon.png" class="mini-favicon"/>Electric Picnic</a> | 
        <a href="/forbiddenfruit16" title="Forbidden Fruit Festival 2016" <?php echo ($festivallower == "forbiddenfruit16" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/forbiddenfruit16-favicon.png" class="mini-favicon"/>Forbidden Fruit</a> | 
        <a href="/life16" title="Life Festival 2016" <?php echo ($festivallower == "life16" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/life16-favicon.png" class="mini-favicon"/>Life</a> | 
        <a href="/trinityball16" title="Trinity Ball 2016" <?php echo ($festivallower == "trinityball16" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/trinityball16-favicon.png" class="mini-favicon"/>Trinity Ball</a><br />
        <b>2015:</b>
        <a href="/electricpicnic15" title="Electric Picnic Festival 2015" <?php echo ($festivallower == "electricpicnic15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/electricpicnic15-favicon.png" class="mini-favicon"/>Electric Picnic</a> | 
        <a href="/forbiddenfruit15" title="Forbidden Fruit Festival 2015"<?php echo ($festivallower == "forbiddenfruit15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/forbiddenfruit15-favicon.png" class="mini-favicon"/>Forbidden Fruit</a> | 
        <a href="/longitude15" title="Longitude Festival 2015"<?php echo ($festivallower == "longitude15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/longitude15-favicon.png" class="mini-favicon"/>Longitude</a> | 
        <a href="/indiependence15" title="Indiependence Festival 2015"<?php echo ($festivallower == "indiependence15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/indiependence15-favicon.png" class="mini-favicon"/>Indiependence</a> | 
        <a href="/splendourinthegrass15" title="Splendour In The Grass Festival 2015" <?php echo ($festivallower == "splendourinthegrass15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/splendourinthegrass15-favicon.png" class="mini-favicon"/>SITG</a> | 
        <a href="/groovinthemoo15" title="Groovin' The Moo Festival 2015" <?php echo ($festivallower == "groovinthemoo15" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/groovinthemoo15-favicon.png" class="mini-favicon"/>GITM</a><br />
        <b>2014:</b>
        <a href="/electricpicnic14" title="Electric Picnic Festival 2014" <?php echo ($festivallower == "electricpicnic14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/electricpicnic14-favicon.png" class="mini-favicon"/>Electric Picnic</a> | 
        <a href="/forbiddenfruit14" title="Forbidden Fruit Festival 2014"<?php echo ($festivallower == "forbiddenfruit14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/forbiddenfruit14-favicon.png" class="mini-favicon"/>Forbidden Fruit</a> | 
        <a href="/longitude14" title="Longitude Festival 2014"<?php echo ($festivallower == "longitude14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/longitude14-favicon.png" class="mini-favicon"/>Longitude</a> | 
        <a href="/trinityball14" title="Trinity Ball Festival 2014"<?php echo ($festivallower == "trinityball14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/trinityball14-favicon.png" class="mini-favicon"/>Trinity Ball</a> | 
        <a href="/latitude14" title="Latitude Festival 2014"<?php echo ($festivallower == "latitude14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/latitude14-favicon.png" class="mini-favicon"/>Latitude</a> | 
        <a href="/life14" title="Life Festival 2014"<?php echo ($festivallower == "life14" ? "style=\"font-weight:bold;\"" : ""); ?>>
        <img src="img/life14-favicon.png" class="mini-favicon"/>Life</a>
        <br /><!--<?php if(isset($_GET['a']) && strtolower($_GET['a']) != "all") echo "<br /><a onclick=\"SCM.loadPlaylist([".rtrim($scriptstring, ",")."]);\">Click to refresh</a> the playlist for ".($_GET['a'] == "" ? "Top Tracks" : $_GET['a'])."."; ?>-->
        </h2>
    </div>
    <div id="video-container" onclick="SCM.togglePlaying();"><div></div></div>
    <div class="sidebar"> 
        <h1><img src="//festivals.zach.ie/img/<?php echo $festivallower; ?>-favicon.png" /><?php echo (isset($_GET['a']) ? (strtolower($_GET['a']) == "all" ? $festival.' - Top Tracks' : 'Artist - '.$_GET['a']) : $festival.' - All Tracks'); ?></h1>
        <?php 
            echo $tablestring;
        ?>
        <div class="footer">
            <!--Artists: <?php foreach($playlists as $result) echo '<a href="?a='.urlencode($result['name']).'">'.$result['name'].'</a>'
            .($result['name'] === 'Godfathers' ? '.' : ($result['name'] === '2ManyDJs' ? '/' : ', ')).($result['name'] === 'Original Rudeboys' ? '<br />' : ''); ?><br /><br />-->
            No Copyright Assumed. Site is Unofficial.<br />Artists: <?php echo number_format($totart); ?> - Videos: <?php echo number_format($rows); ?> - <a href="?a=all" title="Show the Top Song for each Artist">Show Uniques</a><br />
            <a href="https://github.com/zachd/festival-playlist" title="View on GitHub" target="_blank">View on GitHub</a> (Show <a href="?n=500">500</a>, <a href="?n=1000">1000</a>, <a href="?n=<?php echo $rows; ?>">All</a>)<br /><br />
        </div>
    </div> 
</div>

<br />
</body></html>
