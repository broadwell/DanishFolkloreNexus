<?php

# Connect to the database
$user="dfl";
$password="Dfl#123!";
$database="folkloredb";
$dblink = mysql_connect("localhost:3306",$user,$password);
if (!$dblink) {
    die('Could not connect: ' . mysql_error());
}
$db_selected = @mysql_select_db($database, $dblink) or die( "Unable to select database" );
if (!$db_selected) {
    die('Unable to select db: ' . mysql_error());
}

setlocale(LC_COLLATE, "da_DK");

$encoding_query = "SET names \"utf8\"";
$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed ' . mysql_error());

if (!(is_dir("english_pub"))) {
        mkdir("english_pub", 0755);
}
if (!(is_dir("danish_pub"))) {
        mkdir("danish_pub", 0755);
}
if (!(is_dir("danish_man"))) {
        mkdir("danish_man", 0755);
}
if (!(is_dir("english_pub_info"))) {
        mkdir("english_pub_info", 0755);
}

$informantIDtoInitials = array("150" => "BJK", "241" => "JPP", "235" => "KMP", "90" => "AMJ", "123" => "PJ");

$story_to_informant = array();

$story_to_informant_query = "SELECT story_id, informant_id FROM story_to_informant ORDER BY story_id";
$story_to_informant_result =  mysql_query($story_to_informant_query) or die('Story to informant query failed ' . mysql_error());
while ($story_to_informant_row = mysql_fetch_assoc($story_to_informant_result)) {
    $story_id = $story_to_informant_row['story_id'];
    $informant_id = $story_to_informant_row['informant_id'];

    if (array_key_exists($story_id, $story_to_informant)) {
        if (($story_to_informant[$story_id] == 0) && ($informant_id != 0)) {
            $story_to_informant[$story_id] = $informant_id;
        }
    } else {
        $story_to_informant[$story_id] = $informant_id;
    }

}

$stories_query = "SELECT DISTINCT story_id, publication_info FROM story ORDER BY story_id";
$stories_result =  mysql_query($stories_query) or die('Story query failed ' . mysql_error());

$story_count = 0;

while ($stories_row = mysql_fetch_assoc($stories_result)) {
	$story_id = $stories_row['story_id'];
	$pub_info = $stories_row['publication_info'];
        $informant_id = $story_to_informant[$story_id];

        $formatted_story_id = str_pad($story_id, 3, "0", STR_PAD_LEFT);

        if (array_key_exists($informant_id, $informantIDtoInitials))
            $informant_initials = $informantIDtoInitials[$informant_id];
        else
            $informant_initials = "XXX";

        if (strpos($pub_info, "Unpub") === false) 
            $english_pub_filename = $formatted_story_id."-".$informant_initials."-".$pub_info;
        else
            $english_pub_filename = $formatted_story_id."-".$pub_info;

        $english_pub_dir = "english_pub_info/".$english_pub_filename.".txt";

	$english_query = 'SELECT story_object_text FROM story_object WHERE story_id='.$story_id.' AND text_type="english_publication"';
	$english_result = mysql_query($english_query) or die('English translation query failed '. mysql_error());
	if ($english_row = mysql_fetch_assoc($english_result)) {
                if ($english_text = $english_row['story_object_text']) {

                        // Strip out the string
                        // "Unpublished version reads: " if it exists

                        $english_text = str_replace('Unpublished version reads: ', '', $english_text);
                        $english_text = str_replace('Unpublished version reads:', '', $english_text);

			$english_file = fopen("english_pub/".$story_id.".txt", 'w');
			fwrite($english_file, $english_text);
                        fclose($english_file);

                        $story_count++;
                        print $story_count . " " . $english_pub_filename . "\n";

			$english_pub_file = fopen($english_pub_dir, 'w');
			fwrite($english_pub_file, $english_text);
                        fclose($english_pub_file);
                }
	}
	mysql_free_result($english_result);

	$danish_query = 'SELECT story_object_text FROM story_object WHERE story_id='.$story_id.' AND text_type="danish_publication"';
	$danish_result = mysql_query($danish_query) or die('Danish publication query failed '. mysql_error());
	if ($danish_row = mysql_fetch_assoc($danish_result)) {
		if ($danish_text = $danish_row['story_object_text']) {
			$danish_file = fopen("danish_pub/".$story_id.".txt", 'w');
			fwrite($danish_file, $danish_text);
			fclose($danish_file);
		}
	}
	mysql_free_result($danish_result);
	$danish_ms_query = 'SELECT story_object_text FROM story_object WHERE story_id='.$story_id.' AND text_type="danish_manuscript"';
	$danish_ms_result = mysql_query($danish_ms_query) or die('Danish manuscript query failed '. mysql_error());
	if ($danish_ms_row = mysql_fetch_assoc($danish_ms_result)) {
		if ($danish_ms_text = $danish_ms_row['story_object_text']) {
			$danish_ms_file = fopen("danish_man/".$story_id.".txt", 'w');
			fwrite($danish_ms_file, $danish_ms_text);
			fclose($danish_ms_file);
		}
	}
	mysql_free_result($danish_ms_result);
}
mysql_free_result($stories_result);

mysql_close($dblink);
?>
