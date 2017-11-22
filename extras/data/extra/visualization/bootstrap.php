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

$encoding_query = "SET names \"utf8\"";

$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed '. mysql_error());

$max_story_query = "SELECT MAX(story_id) FROM story";
$max_story_result = mysql_query($max_story_query) or die("Max story query failed" . mysql_error());
$max_story_id = mysql_result($max_story_result, 0);

$story_to_etk_index_query = "SELECT * from story_to_etk_index";
$story_to_etk_index_result = mysql_query($story_to_etk_index_query) or die("Story to ETK index query failed" . mysql_error());
$etk_index_info = array();
while ($etk_index_row = mysql_fetch_assoc($story_to_etk_index_result)) {
	$etk_index_info[$etk_index_row['story_id']] = $etk_index_row['etk_index_id'];
}

$etk_info_query = "SELECT * FROM etk_index ORDER BY etk_index_id";
$etk_info_result = mysql_query($etk_info_query) or die("ETK query failed" . mysql_error());
$etk_info = array();
while ($etk_info_row = mysql_fetch_assoc($etk_info_result)) {
	$etk_info[$etk_info_row['etk_index_id']] = $etk_info_row;
}

$keywords_query = "SELECT * FROM keyword ORDER BY keyword_id";
$keywords_result = mysql_query($keywords_query) or die("Keywords failed" . mysql_error());
$keywords = array();
while ($keyword_row = mysql_fetch_assoc($keywords_result)) {
	$keywords[$keyword_row['keyword_id']] = $keyword_row;
}

$occupations_query = "SELECT * FROM occupation";
$occupations_result = mysql_query($occupations_query) or die("Occupations query failed" . mysql_error());
$occupations = array();
while ($occupation_row = mysql_fetch_assoc($occupations_result)) {
	$occupations[$occupation_row['occupation_id']] = $occupation_row;
}

$person_to_occupation_query = "SELECT * FROM person_to_occupation";
$person_to_occupation_result = mysql_query($person_to_occupation_query) or die("Person to occupation query failed" . mysql_error());
$occupation_info = array();
while ($occupation_row = mysql_fetch_assoc($person_to_occupation_result)) {
	if (!array_key_exists($occupation_row['person_id'], $occupation_info))
		$occupation_info[$occupation_row['person_id']] = array($occupations[$occupation_row['occupation_id']]);
	else
		$occupation_info[$occupation_row['person_id']][] = $occupations[$occupation_row['occupation_id']];
}

$genres_query = "SELECT * FROM genre";
$genres_result = mysql_query($genres_query) or die("Genres query failed" . mysql_error());
$genres_info = array();
while ($genre_row = mysql_fetch_assoc($genres_result)) {
	$genres_info[$genre_row['genre_id']] = $genre_row;
}

$story_to_genre_query = "SELECT * FROM story_to_genre";
$story_to_genre_result = mysql_query($story_to_genre_query) or die("Story to genre query failed" . mysql_error());
$genre_info = array();
while ($genre_row = mysql_fetch_assoc($story_to_genre_result)) {
	$genre_info[$genre_row['story_id']] = $genre_row['genre_id'];
}

$tango_indices = array();
$tango_indices_query = "SELECT * FROM tango_index WHERE tango_index_id!=130 ORDER BY tango_index_id";
$tango_indices_result = mysql_query($tango_indices_query) or die("Tango indices query failed" . mysql_error());
while ($tango_row = mysql_fetch_assoc($tango_indices_result)) {
	$tango_indices[$tango_row['tango_index_id']] = $tango_row;
}

$story_to_tango_index_query = "SELECT * FROM story_to_tango_index WHERE tango_index_id!=130";
$story_to_tango_index_result = mysql_query($story_to_tango_index_query) or die("Story to tango index query failed" . mysql_error());
$tango_index_info = array();
while ($tango_index_row = mysql_fetch_assoc($story_to_tango_index_result)) {

	if (array_key_exists($tango_index_row['story_id'], $tango_index_info))
		$tango_index_info[$tango_index_row['story_id']][] = $tango_index_row['tango_index_id'];
	else
		$tango_index_info[$tango_index_row['story_id']] = array($tango_index_row['tango_index_id']);
}

$persons_query = "SELECT * FROM person";
$persons_result = mysql_query($persons_query) or die('Persons query failed ' . mysql_error());
$people_info = array();
while ($person_row = mysql_fetch_assoc($persons_result)) {
	$people_info[$person_row['person_id']] = $person_row;
}
$people_info["0"] = array();
$people_info["0"]['gender'] = 'N/A';

$places_query = "SELECT * FROM place";
$places_result = mysql_query($places_query) or die('Places query failed ' . mysql_error());
$places_info = array();
while ($place_row = mysql_fetch_assoc($places_result)) {
	$places_info[$place_row['place_id']] = $place_row;
}

?>
