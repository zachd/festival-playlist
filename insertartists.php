<?php 

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

include("playlists.php");

$db = new PDO("sqlite:sql/artists.processing.sqlite3");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS `artists` (
  `name` varchar(255),
  `id` varchar(255),
  `festival` varchar(255),
  `day` int(11)
)");
?>
<html>
<head>
<title>Artists Import</title>
</head>
<body>
<?php

$stmt = $db->prepare("INSERT INTO artists (name, id, festival, day) VALUES (:name, :id, :festival, :day)");
$stmt->bindParam(':name', $ins_name);
$stmt->bindParam(':id', $ins_id);
$stmt->bindParam(':festival', $ins_festival);
$stmt->bindParam(':day', $ins_day);

foreach($festivals as $festival => $days){
    foreach($days as $day => $acts){
        foreach($acts as $artist => $playlist){
            $searchres = $db->query("SELECT * FROM artists WHERE name = '".$artist."' LIMIT 1");
            $found = $searchres->fetch();
            if(!$found){
                $ins_name = $artist;
                $ins_id = $playlist;
                $ins_festival = $festival;
                $day = $day;
                $stmt->execute();
            echo "<b>Inserted</b> <i>".$artist."</i> for ".$festival.".<br /><br />";
            } else
            echo "<b>Skipped</b> <i>".$artist."</i> for ".$festival." (Already inserted)<br /><br />";
        }
    }
}

rename("/home/zach/festivals/sql/artists.sqlite3", "/home/zach/festivals/sql/artists.old.sqlite3");
rename("/home/zach/festivals/sql/artists.processing.sqlite3", "/home/zach/sql/festivals/artists.sqlite3");
?>
</body>
</html>