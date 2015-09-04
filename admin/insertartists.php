<?php 

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

include("../playlists.php");

$key = file_get_contents("api.key");
$db = new PDO("sqlite:../sql/artists.sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS `artists` (
  `name` varchar(255),
  `festival` varchar(255)
)");
$db->exec("CREATE TABLE IF NOT EXISTS `playlists` (
  `name` varchar(255),
  `id` varchar(255),
  `playlist` varchar(255)
)");
?>
<html>
<head>
<title>Artists Import</title>
</head>
<body>
<?php

function check_playlist($string, $artist) {
  if(stristr($string, "popular") && stristr($string, "videos")){
    $new1 = preg_replace("~popular~i", "", $string);
    $new2 = preg_replace("~videos~i", "", $new1);
    $new3 = preg_replace("~[^\w]~iu", "", $new2);
    $art = preg_replace("~[^\w]~iu", "", $artist); 
    if(strcasecmp($new3, $art) == 0)
      return true;
  }
  return false;
}

if(!isset($_GET['festival'])){
    echo "<div style=\"text-align:center;\">";
    foreach($festivals as $festival => $array){
        echo "<a href=\"?festival=".$festival."\">Load ".$festival." Festival artists</a><br />";
    }    
    echo "</div>";
} else {
  $festival = $_GET['festival'];

  $check = $db->prepare("SELECT * FROM artists WHERE name = :artist LIMIT 1");
  $check->bindParam(':artist', $check_artist);


  $check_ins = $db->prepare("SELECT * FROM artists WHERE name = :artist AND festival = :festival LIMIT 1");
  $check_ins->bindParam(':artist', $check_ins_artist);
  $check_ins->bindParam(':festival', $check_ins_festival);


  $ins_ar = $db->prepare("INSERT INTO artists (name, festival) VALUES (:name, :festival)");
  $ins_ar->bindParam(':name', $ins_name_ar);
  $ins_ar->bindParam(':festival', $ins_festival);

  $ins_pl = $db->prepare("INSERT INTO playlists (name, id, playlist) VALUES (:name, :id, :playlist)");
  $ins_pl->bindParam(':name', $ins_name_pl);
  $ins_pl->bindParam(':id', $ins_id);
  $ins_pl->bindParam(':playlist', $ins_playlist);

  $days = $festivals[$festival];
      foreach($days as $day => $acts){
          foreach($acts as $artist){
              $ins_name_ar = $artist;
              $ins_festival = $festival;
              $check_artist = $artist;
              $check->execute();
              $found = $check->fetch();
              if(!$found){
                  $artistfixed = str_replace("&", "And", str_replace("+", "And", str_replace("-", "", str_replace("$$", "ss", str_replace("'", "", $artist)))));
                  $curl = curl_init();
                  curl_setopt_array($curl, array(
                      CURLOPT_RETURNTRANSFER => 1,
                      CURLOPT_URL => "https://www.googleapis.com/youtube/v3/search?part=snippet&q=%23".str_replace(" ", "", $artistfixed)."&alt=json&start-index=1&maxResults=50&key=".$key,
                  ));
                  $resp = json_decode(curl_exec($curl), true);
                  curl_close($curl); 
                  
                  if(!$resp || array_key_exists('error', $resp)){
                    echo "<b>Skipped</b> <i>".$artist."</i> for ".$festival." (<font color=\"red\">ERROR".$resp['error']['code'].": ".$resp['error']['message']."</font>)<br />";
                    continue;
                  }
                  if(!array_key_exists('items', $resp)){
                    echo "<b>Skipped</b> <i>".$artist."</i> for ".$festival." (<a href=\"http://gdata.youtube.com/feeds/api/channels?q=%23".str_replace(" ", "", $artistfixed)."&alt=json&start-index=1&maxResults=50&v=2\">Channel not found</a>)<br /><br />";
                    continue;
                  }

                  $foundchannel = false;
                  $foundplaylist = false;
                  $start = 1;
                  foreach($resp['items'] as $result){
                    if(strcasecmp($result['snippet']['title'], "#".str_replace(" ", "", $artistfixed)) == 0){
                      $pageToken = "";
                      echo "Using channel: <b>".$result['snippet']['title']."</b><br />";
                      $foundchannel = true;
                      do {
                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_RETURNTRANSFER => 1,
                            CURLOPT_URL => "https://www.googleapis.com/youtube/v3/playlists?part=snippet&channelId=".$result['snippet']['channelId']."&alt=json&start-index=".$start."&maxResults=50&pageToken=".$pageToken."&key=".$key,
                        ));
                        $resptwo = json_decode(curl_exec($curl), true);
                        curl_close($curl); 
                        if(!array_key_exists('items', $resptwo)){
                          break;
                        }
                        foreach($resptwo['items'] as $entry){
                          if(check_playlist($entry['snippet']['title'], str_replace("$$", "ss", $artist))){
                            echo "Using playlist: <b>".$entry['snippet']['title']."</b><br />";
                            $foundplaylist = $entry['id'];
                            $foundplaylistname = $entry['snippet']['title'];
                          }
                        }
                        $start += 50;
                        if(!array_key_exists('nextPageToken', $resptwo)){
                          break;
                        }
                        $pageToken = $resptwo['nextPageToken'];
                        echo "Checking page " . $pageToken."<br />";
                      } while (!$foundplaylist);
                      break;
                    }
                  }
                  if($foundplaylist){
                    // Insert to playlists database
                    $ins_name_pl = $artist;
                    $ins_id = $foundplaylist;
                    $ins_playlist = $foundplaylistname;
                    $ins_pl->execute();
                    $ins_ar->execute();
                    echo "<b>Inserted & Processed</b> <i>".$artist."</i> for ".$festival.".<br /><br />";
                  } else {
                    echo "<b>Skipped</b> <i>".$artist."</i> for ".$festival." (<a href=\"https://www.googleapis.com/youtube/v3/playlists?part=snippet&channelId=".$result['snippet']['channelId']."&alt=json&start-index=".$start."&maxResults=50&key=".$key."\">Playlist not found</a>)<br /><br />";
                  }
              } else {

                // Check if already inserted in artists database
                $check_ins_artist = $artist;
                $check_ins_festival = $festival;
                $check_ins->execute();
                $ins_found = $check_ins->fetch();
                if(!$ins_found){
                  $ins_ar->execute();
                  echo "<b>Inserted</b> <i>".$artist."</i> for ".$festival.".<br />";
                }
                echo "<b>Skipped</b> <i>".$artist."</i> processing (Already inserted)<br /><br />";
              }
          }
      }
  }

//rename("/home/zach/festivals/sql/artists.sqlite3", "/home/zach/festivals/sql/artists.old.sqlite3");
//rename("/home/zach/festivals/sql/artists.processing.sqlite3", "/home/zach/festivals/sql/artists.sqlite3");
?>
</body>
</html>
