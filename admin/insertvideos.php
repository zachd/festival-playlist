<?php 

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

set_time_limit(0);

include("../playlists.php");

$data = new PDO("sqlite:../sql/artists.sqlite3");
$data->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$key = file_get_contents("api.key");
$db = new PDO("sqlite:../sql/processing.sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS `videos` (
  `name` varchar(255),
  `id` varchar(20),
  `tag` varchar(255),
  `position` int(11),
  `published` varchar(25),
  `link` varchar(255),
  `title` varchar(255),
  `cleantitle` varchar(255),
  `views` int(11),
  `likes` int(11),
  `dislikes` int(11)
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



    $check = $data->prepare("SELECT a.name, p.id FROM artists a INNER JOIN playlists p ON p.name = a.name WHERE a.festival = :festival");
    $check->bindParam(':festival', $chk_festival);
    $chk_festival = $_GET['festival'];
    $check->execute();
    $found = $check->fetchAll();

    $checktwo = $db->prepare("SELECT * FROM videos WHERE name = :act AND cleantitle LIKE :cleantitle LIMIT 1");
    $checktwo->bindParam(':act', $chk2_act);
    $checktwo->bindParam(':cleantitle', $chk2_cleantitle);

    $stmt = $db->prepare("INSERT INTO videos (name, id, tag, position, published, link, title, cleantitle, views, likes, dislikes) VALUES (:name, :id, :tag, :position, :published, :link, :title, :cleantitle, :views, :likes, :dislikes)");
    $stmt->bindParam(':name', $ins_name);
    $stmt->bindParam(':id', $ins_id);
    $stmt->bindParam(':tag', $ins_tag);
    $stmt->bindParam(':position', $ins_position);
    $stmt->bindParam(':published', $ins_published);
    $stmt->bindParam(':link', $ins_link);
    $stmt->bindParam(':title', $ins_title);
    $stmt->bindParam(':cleantitle', $ins_cleantitle);
    $stmt->bindParam(':views', $ins_views);
    $stmt->bindParam(':likes', $ins_likes);
    $stmt->bindParam(':dislikes', $ins_dislikes);
    $totalprocessed = 0;
    $totalinserted = 0;
    $totalskipped = 0;
    $totaldeleted = 0;

    foreach($found as $result){
        $playlist = $result['id'];
        $act = $result['name'];
        $videos = array();

        $pageToken = "";
        echo "<br /><b>Fetching playlist videos for " . $act."</b><br />";
        // Loop through result pages of the current playlist
        do {
            $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=".$playlist."&maxResults=50&pageToken=".$pageToken."&key=".$key;
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
            ));
            $resp = json_decode(curl_exec($curl), true);
            curl_close($curl); 

            if(array_key_exists('error', $resp) || !array_key_exists('items', $resp)){
                break;
            }

            // Loop through each video in the current page
            foreach($resp['items'] as $entry){
                $totalprocessed++;
                if(!(stristr($entry['snippet']['title'], $act) === FALSE)){
                    $id = $entry['snippet']['resourceId']['videoId'];
                    $videos[$id]['name'] = $act;
                    $videos[$id]['tag'] = strtolower($act);
                    $videos[$id]['position'] = $entry['snippet']['position'];
                    $videos[$id]['published'] = $entry['snippet']['publishedAt'];
                    $videos[$id]['link'] = "http://youtube.com/watch?v=".$id;
                    $videos[$id]['title'] = $entry['snippet']['title'];
                    $videos[$id]['cleantitle'] = preg_replace("~[^A-Za-z0-9]+~", "", preg_replace("~\[[^\]]+\]~", "", preg_replace("~\([^\)]+\)~", "", preg_replace("~".$act."~i", "", strtolower($videos[$id]['title'])))));
                } else {
                    echo "Skipping <b>#".$entry['snippet']['position']."</b> ";
                    echo "<span title=\"".$entry['snippet']['title']." does not contain ".$act."\">(act name not in title)</span>";
                    echo "<br />";
                    $totalskipped++;
                }
            }
            if(array_key_exists('nextPageToken', $resp))
                $pageToken = $resp['nextPageToken'];
        } while (!array_key_exists('error', $resp) && array_key_exists('items', $resp) && array_key_exists('nextPageToken', $resp));
        echo "<br />";

        echo "<b>Inserting playlist videos for " . $act."</b><br />";
        $pageToken = "";
        for($n = 1; true; $n += 49){
            $ids = ""; $idcount = 1;
            foreach($videos as $id => $video){
                if($idcount >= $n && $idcount < $n + 49)
                    $ids .= ($id . ",");
                $idcount++;
            }

            if($ids === "")
                break;

            $url = "https://www.googleapis.com/youtube/v3/videos?part=statistics&id=".$ids."&maxResults=50&pageToken=".$pageToken."&key=".$key;
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
            ));
            $resptwo = json_decode(curl_exec($curl), true);
            curl_close($curl); 

            // Break if we are at the end
            if(array_key_exists('error', $resptwo) || !array_key_exists('items', $resptwo)){
                break;
            }

            foreach($resptwo['items'] as $entry){
                $ins_id = $entry['id'];
                $ins_name = $videos[$ins_id]['name'];
                $ins_tag = $videos[$ins_id]['tag'];
                $ins_position = $videos[$ins_id]['position'];
                $ins_published = $videos[$ins_id]['published'];
                $ins_link = $videos[$ins_id]['link'];
                $ins_title = $videos[$ins_id]['title'];
                $ins_cleantitle = $videos[$ins_id]['cleantitle'];
                $ins_views = $entry['statistics']['viewCount'];
                $ins_likes = $entry['statistics']['likeCount'];
                $ins_dislikes = $entry['statistics']['dislikeCount'];

                // Clean Title
                echo "Inserting <b>#".$ins_position."</b>: ";
                $chk2_cleantitle = "%" . $ins_cleantitle . "%";
                $chk2_act = $act;
                $checktwo->execute();
                $foundtwo = $checktwo->fetch();
                if($foundtwo){
                    if($foundtwo['views'] < $ins_views){
                        $delete = $db->prepare("DELETE FROM videos WHERE name =? AND id = ? AND cleantitle = ? AND views = ?");
                        $delete->execute(array($act, $foundtwo['id'], $foundtwo['cleantitle'], $foundtwo['views']));
                        $totaldeleted++;
                        echo "<i>True (deleted duplicate with ".number_format($ins_views - $foundtwo['views'])." less views)</i><br />";
                        // Now insert
                        $stmt->execute();
                        $totalinserted++;
                    } else {
                        echo "<span title=\"Skipped Duplicate: ".$ins_title."\"><b>False</b> (already in database)</span><br />";
                        $totalskipped++;
                    }
                } else {
                    $stmt->execute();
                    echo "<i>True</i><br />";
                    $totalinserted++;
                }
            }
        }
    }
    echo "<h2>Total Processed: $totalprocessed</h2>";
    echo "<h3>Total Inserted: $totalinserted (Skipped: $totalskipped, Deleted: $totaldeleted)</h3>";
    rename("/home/zach/festivals/sql/".$_GET['festival'].".sqlite3", "/home/zach/festivals/sql/".$_GET['festival'].".old.sqlite3");
    rename("/home/zach/festivals/sql/processing.sqlite3", "/home/zach/festivals/sql/".$_GET['festival'].".sqlite3");
}
?>
</body>
</html>
