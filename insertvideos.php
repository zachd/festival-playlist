<?php 

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
include("playlists.php");

$db = new PDO("sqlite:sql/processing.sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS `videos` (
  `name` varchar(255),
  `id` varchar(20),
  `tag` varchar(255),
  `position` int(11),
  `link` varchar(255),
  `title` varchar(255),
  `cleantitle` varchar(255),
  `duration` int(11),
  `views` int(11),
  `rating` double
)");
?>
<html>
<head>
<title>Insert Videos</title>
<script type="text/javascript">
function hover(x, y){
    var divs = document.getElementsByClassName(x);
    for(var i=0; i<divs.length; i++)
      divs[i].style.fontWeight = (y ? 'bold' : 'normal');
}
</script>
</head>
<body>
<?php
if(!isset($_GET['festival'])){
    echo "<div style=\"text-align:center;\">";
    foreach($festivals as $festival => $array){
        echo "<a href=\"?festival=".$festival."\">Load ".$festival." Festival videos</a><br />";
    }    
    echo "</div>";
} else {

    $days = $festivals[$_GET['festival']];

    $playlistresultslinks = array();
    $playlistresultsviews = array();

    $stmt = $db->prepare("INSERT INTO videos (name, id, tag, position, link, title, cleantitle, duration, views, rating) VALUES (:name, :id, :tag, :position, :link, :title, :cleantitle, :duration, :views, :rating)");
    $stmt->bindParam(':name', $ins_name);
    $stmt->bindParam(':id', $ins_id);
    $stmt->bindParam(':tag', $ins_tag);
    $stmt->bindParam(':position', $ins_position);
    $stmt->bindParam(':link', $ins_link);
    $stmt->bindParam(':title', $ins_title);
    $stmt->bindParam(':cleantitle', $ins_cleantitle);
    $stmt->bindParam(':duration', $ins_duration);
    $stmt->bindParam(':views', $ins_views);
    $stmt->bindParam(':rating', $ins_rating);
    $totalprocessed = 0;
    $totalinserted = 0;
    $totalskipped = 0;
    $totaldeleted = 0;

    foreach($days as $day => $playlists){
        foreach($playlists as $act => $playlist){
            /*
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => 'https://youtube.com/disco?action_search=1&query='.urlencode($act),
                CURLOPT_FOLLOWLOCATION => 1
            ));
            $resp = json_decode(curl_exec($curl), true);
            preg_match('~\&list\=([^\&]+)~i', $resp['url'], $playlistid);
            curl_close($curl); */

            $next = "http://gdata.youtube.com/feeds/api/playlists/".$playlist."?alt=json&start-index=1&max-results=25";
            echo "<b>Fetching playlist for " . $act."</b><br />";
            do {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $next,
                ));
                $resp = json_decode(curl_exec($curl), true);
                curl_close($curl); 

                foreach($resp['feed']['entry'] as $entry){
                    echo "Inserting <b>#".$entry['yt$position']['$t']."</b>: ";
                    $totalprocessed++;
                    if(!(stristr($entry['title']['$t'], $act) === FALSE)){
                        $ins_name = $act;
                        parse_str(parse_url($entry['link'][0]['href'], PHP_URL_QUERY), $idparsearray);
                        $ins_id = $idparsearray['v'];
                        $ins_tag = strtolower($act);
                        $ins_position = $entry['yt$position']['$t'];
                        $ins_link = $entry['link'][0]['href'];
                        $ins_title = $entry['title']['$t'];
                        $ins_cleantitle = preg_replace("~[^A-Za-z0-9]+~", "", preg_replace("~\[[^\]]+\]~", "", preg_replace("~\([^\)]+\)~", "", preg_replace("~".$act."~i", "", strtolower($entry['title']['$t'])))));
                        $ins_duration = $entry['media$group']['yt$duration']['seconds'];
                        $ins_views = $entry['yt$statistics']['viewCount'];
                        $ins_rating = $entry['gd$rating']['average'];
                        $cltitleres = $db->query("SELECT * FROM videos WHERE name = '".$act."' AND cleantitle LIKE '%".$ins_cleantitle."%' LIMIT 1");
                        $found = $cltitleres->fetch();
                        if($found){
                            if($found['views'] < $ins_views){
                                $db->query("DELETE FROM videos WHERE name = '".$act."' AND id = '".$found['id']."' AND cleantitle = '".$found['cleantitle']."' AND views = '".$found['views']."'");
                                $stmt->execute();
                                echo "<i>True (Deleted duplicate entry with ".number_format($ins_views - $found['views'])." less views</i>";
                                    $totaldeleted++;
                            } else {
                                echo "<span title=\"Skipped Duplicate: ".$entry['title']['$t']." != ".$act."\"><b>False</b></span>";
                                $totalskipped++;
                            }
                        } else {
                            $stmt->execute();
                            echo "<i>True</i>";
                            $totalinserted++;
                        }
                    } else {
                        echo "<span title=\"".$entry['title']['$t']." != ".$act."\"><b>False</b></span>";
                    }
                    echo "<br />";
                }
                if(array_key_exists(4, $resp['feed']['link']) && $resp['feed']['link'][4]['rel'] === "next")
                    $next = $resp['feed']['link'][4]['href'];
                else if($resp['feed']['link'][5]['rel'] === "next")
                    $next = $resp['feed']['link'][5]['href'];
                echo "<!-- " . $next . " -->";
                echo "<br />".$resp['feed']['openSearch$startIndex']['$t']." + ".$resp['feed']['openSearch$itemsPerPage']['$t']." < " . $resp['feed']['openSearch$totalResults']['$t']."<br />";
            } while (($resp['feed']['openSearch$startIndex']['$t'] + $resp['feed']['openSearch$itemsPerPage']['$t']) < $resp['feed']['openSearch$totalResults']['$t']);
            echo "<br />";
        }
    }

    arsort($playlistresultsviews);
    foreach($playlistresultsviews as $num => $views){
        /*echo  '<a style="color: black; text-decoration: none;" 
                    href="'.$entry['link'][0]['href'].'"><span onmouseover="hover(\''.$name.'\', true)" 
                    onmouseout="hover(\''.$name.'\', false)" title="'.$act.' Playlist #'.
                    $entry['yt$position']['$t'].'"><span class="'.$name.'">'.$entry['title']['$t']) .
        "</span> (". number_format($views) . " views)</span></a><br />";*/
    }
    echo "<h2>Total Processed: $totalprocessed</h2>";
    echo "<h3>Total Inserted: $totalinserted (Skipped: $totalskipped, Deleted: $totaldeleted)</h3>";
    rename("/home/zach/festivals/sql/".$_GET['festival'].".sqlite3", "/home/zach/festivals/sql/".$_GET['festival'].".old.sqlite3");
    rename("/home/zach/festivals/sql/processing.sqlite3", "/home/zach/festivals/sql/".$_GET['festival'].".sqlite3");
}
?>
</body>
</html>
