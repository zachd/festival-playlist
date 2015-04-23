<?php 
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
error_reporting(0);

include("playlists.php");

if(isset($_GET['params'])){
    $params = explode("/", $_GET['params']);
    if (!empty($params[0]) && strcspn($params[0], '0123456789') == strlen($params[0]))
        $params[0] = $params[0] . "14";
    if(empty($params[0])) $params[0] = "electricpicnic15"; // Default to EP 2015
    if(empty($params[0]) || !ctype_alnum($params[0]) && strpos($params[0], "_") === FALSE || !file_exists("sql/".$params[0].".sqlite3")){
        echo "<b><center>Festival not found: ".$params[0]."</center></b>";
        die();
    }
    $festival = ucwords(substr($params[0], 0, -2));
    if(array_key_exists($festival, $names))
        $festival = $names[$festival];
    $festivallower = strtolower($params[0]);
    $year = "20".substr($festivallower, -2);
}


$db = new PDO("sqlite:sql/".$params[0].".sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dba = new PDO("sqlite:sql/artists.sqlite3");
$dba->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$check = $dba->prepare("SELECT a.name, p.id FROM artists a INNER JOIN playlists p ON p.name = a.name WHERE a.festival = :festival");
$check->bindParam(':festival', $chk_festival);
$chk_festival = strtolower($params[0]);
$check->execute();
$playlists = $check->fetchAll();

function getartists($a, $d = 0){
	global $dba;
	$result = $dba->query("SELECT * FROM artists WHERE festival = '".mysql_real_escape_string(strtolower($a))."'".($d ? "AND day = '".$d."'" : ""));
	return $result->fetchAll();
}

function showrating($r){
    if($r > 0){
        $p = ($r / 5.0) * 100.0;
        return "<span style=\"font-weight:bold;color:".($p > 90 ? '#138900' : ($p > 80 ? '#FF9700' : ($p > 70 ? '#FF5C00' : '#FF0D00'))).";\">".number_format($p, 1)."%</span>";
    } else
        return "<span style=\"font-weight:bold;font-style:italic;\">?? %</span>";
}

function gettitle($title, $name){
    $titletext = addslashes(str_replace('"', '', preg_replace("~^".$name." ?-? ?~i", "", $title)));
    if(preg_match("~^".$name." ?-? ?~i", $title))
        $titletext = $name."&nbsp;-&nbsp;".$titletext;
    return $titletext;
}

$res = $db->query("SELECT count(*) FROM videos"); $rows = $res->fetchColumn(); 
$resa = $dba->query("SELECT count(*) FROM artists WHERE festival = '".mysql_real_escape_string($festivallower)."'"); $totart = $resa->fetchColumn(); 
$resv = $db->query("SELECT sum(views) FROM videos"); $totviews = $resv->fetchColumn(); 
$resd = $db->query("SELECT sum(duration) FROM videos"); $totdur = $resd->fetchColumn(); 

if(isset($_GET['a']) && strcasecmp($_GET['a'], "all") == 0){
    $result = $db->query("SELECT v.* FROM videos v NATURAL JOIN ( SELECT name, MAX(views) AS views FROM videos GROUP BY name )  ORDER BY views DESC");
}else if(isset($_GET['a']) && in_array($_GET['a'], $festivals[strtolower($params[0])][1]))
    $result = $db->query("SELECT * FROM videos WHERE name = '".mysql_real_escape_string($_GET['a'])."' ORDER BY views DESC");
else 
    $result = $db->query("SELECT * FROM videos ".(isset($_GET['nogroove']) ? 'WHERE name != \'Groove Armada\' ' : (isset($_GET['noboiler']) ? 'WHERE duration <= 3600 ' : ''))."ORDER BY views DESC".(isset($_GET['n']) 
    && is_numeric(mysql_real_escape_string($_GET['n'])) && mysql_real_escape_string($_GET['n']) <= $rows ? " LIMIT " . mysql_real_escape_string($_GET['n']) : " LIMIT 100"));

$count = 1;
$tablestring = "";
$scriptstring = "";
while($row = $result->fetch()){
    $stringtoadd = "{'title':'".gettitle($row['title'], $row['name'])."','url':'".$row['link']."'}";
    $playlistidforartist = array_values(array_filter($playlists, function($ar) {global $row; return ($ar['name'] == $row['name']);}));
    $tablestring = $tablestring . "<tr><td>".$count++."</td><td><img src=\"http://i.ytimg.com/vi/".$row['id']."/default.jpg\" style=\"height:40px;\" /></td><td><a href=\"?a=".urlencode($row['name'])."\">".$row['name']."</a></td>
    <td>".number_format($row['views'])."</td><td><a onclick=\"SCM.play(".$stringtoadd.");\" target=\"_blank\" class=\"link\">".$row['title']."</a></td>
    <td>".number_format($row['duration']/60, 1)."m</td><td>".showrating($row['rating'])."</td><td><a href=\"".$row['link']."&list=".$playlistidforartist[0]['id']/*$playlists[$row['name']]href=\"http://youtube.com/playlist?list=".$playlists[$row['name']]*/."\" target=\"_blank\" class=\"playlist\">&#9654; YouTube</a></td></tr>";
    $scriptstring = $scriptstring . $stringtoadd . ",";
}
?>
<html>
<head>
<title><?php echo $festival; ?> Festival <?php echo $year; ?></title>
<link rel="SHORTCUT ICON" href="http://festivals.zach.ie/img/<?php echo $festivallower; ?>-favicon.png">
<meta property="fb:admins" content="1434685963"/>
<meta property="og:site_name" content="<?php echo $festival; ?> Festival <?php echo $year; ?>"/>
<meta property="og:title" content="Top Tracks Playlist - <?php echo $festival; ?> Festival <?php echo $year; ?>"/>
<meta property="og:type" content="website"/>
<meta property="og:url" content="http://festivals.zach.ie/<?php echo $festivallower; ?>"/>
<meta property="og:image" content="http://festivals.zach.ie/img/<?php echo $festivallower; ?>-fb.png" />
<meta property="og:description" content="Acts from <?php echo $festival; ?> Festival <?php echo $year; ?> shown sorted by their top tracks on YouTube. Videos are from the auto playlist 'Popular Videos' for each artist." />
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script type="text/javascript" src="scm/jquery.tablesorter.min.js"></script> 
<link rel="stylesheet" href="scm/style.css" type="text/css" id="" media="print, projection, screen" />
<script type="text/javascript" id="js">
$(document).ready(function () {
        $('.link').hover(function() {
            $('.both').remove()
            $(this).append('<span class="both">&#9654; Play</span>')
            $('.both').animate({opacity: 1.0}) 
            }, function(){
            $('.both').fadeOut(100, function(){
            $(this).remove()
            })
        });
        jQuery.tablesorter.addParser({
            id: "views",
            is: function (s) {
                return /^[0-9]?[0-9,\.]*$/.test(s);
            },
            format: function (s) {
                return jQuery.tablesorter.formatFloat(s.replace(/,/g, ''));
            },
            type: "numeric"
        });
        jQuery.tablesorter.addParser({
            id: "length",
            is: function (s) {
                return /^[0-9]?[0-9,\.]*m$/.test(s);
            },
            format: function (s) {
                return jQuery.tablesorter.formatFloat(s.replace(/m/g, ''));
            },
            type: "numeric"
        });
        $("#table").tablesorter({
            sortList: [3,0],
            headers: { 3: { sorter: 'views'} }
        });
    }); 
</script>
<!-- SCM Music Player http://scmplayer.net -->
<script type="text/javascript" src="http://festivals.zach.ie/scm/script.js" 
data-config="{'skin':'skins/simpleBlack/skin.css','volume':50,'autoplay':true,'shuffle':false,'repeat':1,'placement':'top','showplaylist':false,'playlist':[<?php echo rtrim($scriptstring, ","); ?>]}" ></script>
<!-- SCM Music Player script end -->
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-49530760-1', 'netsoc.ie');
  ga('send', 'pageview');

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
<body>
<span class="headerspan">
<h1><a href="http://festivals.zach.ie/<?php echo $festivallower; ?>" title="<?php echo $festival; ?> Festival <?php echo $year; ?>"><img src="img/<?php echo $festivallower; ?>.png" alt="<?php echo $festival; ?> Festival <?php echo $year; ?>"/></a><br /><?php echo (in_array($festivallower, $noimages) ? "" : $festival." Festival ".$year); ?></h1>
<h2><?php if(isset($_GET['a']) && strcasecmp($_GET['a'], "all") !== 0) echo "Displaying results for <a target=\"_blank\" href=\"https://www.google.com/search?q=".urlencode(strtolower($_GET['a']))."\" title=\"View ".$_GET['a']." on Google\"><img style=\"width:15px;vertical-align:top;margin-right:2px;\" src=\"img/".$festivallower."-favicon.png\"><b>".$_GET['a']
."</b></a>.<br /><br />"; ?>Showing acts sorted by their top YouTube tracks. <br />Click an artist's name to view their playlist page. <br /><br /><b>2015:</b>
<a href="/electricpicnic15" title="Electric Picnic Festival 2015" <?php echo ($festivallower == "electricpicnic15" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Electric Picnic</a> | 
<a href="/splendourinthegrass15" title="Splendour In The Grass Festival 2015" <?php echo ($festivallower == "splendourinthegrass15" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Splendour In The Grass</a><br />
<b>2014:</b>
<a href="/electricpicnic14" title="Electric Picnic Festival 2014" <?php echo ($festivallower == "electricpicnic14" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Electric Picnic</a> | 
<a href="/forbiddenfruit14" title="Forbidden Fruit Festival 2014"<?php echo ($festivallower == "forbiddenfruit14" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Forbidden Fruit</a> | 
<a href="/life14" title="Life Festival 2014"<?php echo ($festivallower == "life14" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Life</a> | 
<a href="/trinityball14" title="Trinity Ball Festival 2014"<?php echo ($festivallower == "trinityball14" ? "style=\"font-weight:bold; color:black;\"" : ""); ?>>Trinity Ball</a> 
<br /><?php if(isset($_GET['a'])) echo "<br /><a onclick=\"SCM.loadPlaylist([".rtrim($scriptstring, ",")."]);\">Click to refresh</a> the playlist for ".($_GET['a'] == "" ? "Top Tracks" : $_GET['a'])."."; ?>
</h2>
</span>
<table id="table" class="tablesorter"> 
<thead> 
<tr> 
    <th>#</th>
    <th> </th>
    <th>Artist</th> 
    <th>Views</th> 
    <th>Title</th> 
    <th>Length</th> 
    <th>Rating</th> 
    <th>Link</th> 
</tr> 
</thead> 
<tbody> 
<?php 
    echo $tablestring;
?>
</tbody> 
</table> 
<div id="footer">Artists: <?php foreach($playlists as $result) echo '<a href="?a='.urlencode($result['name']).'">'.$result['name'].'</a>'
    .($result['name'] === 'Godfathers' ? '.' : ($result['name'] === '2ManyDJs' ? '/' : ', ')).($result['name'] === 'Original Rudeboys' ? '<br />' : ''); ?><br /><br />
<div id="footer">No Copyright Assumed. Site is Unofficial.<br />Artists: <?php echo number_format($totart); ?> - Videos: <?php echo number_format($rows); ?> - <a href="?a=all" title="Show the Top Song for each Artist">Show Uniques</a><br />Created by Zachary (Show <a href="?n=500">500</a>, <a href="?n=1000">1000</a>, <a href="?n=<?php echo $rows; ?>">All</a>)<br /><br /></div>
</body></html>
