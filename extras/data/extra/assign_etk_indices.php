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

/* UTF8 encoding is necessary so that all place names get written to the
 * proper files (especially fieldtrips.xml, places.xml and the individual
 * informants' files). */
$encoding_query = "SET names \"utf8\"";
#$encoding_query = "SET names \"latin1\"";
$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed: ' . mysql_error());

$matches_count = 0;
$stories_matched = array();

$range_query = "SELECT DISTINCT etk_index_id, range_prefix, range_start, range_end FROM etk_index_range";
$range_result =  mysql_query($range_query) or die('ETK index range query failed: ' . mysql_error());
while ($range_row = mysql_fetch_assoc($range_result)) {

	$story_query = "SELECT DISTINCT story_id, publication_info FROM story WHERE publication_info REGEXP '^".$range_row['range_prefix'].".*'";
	$story_result = mysql_query($story_query) or die('ETK index story query failed: ' . mysql_error());
	while ($story_row = mysql_fetch_assoc($story_result)) {

		$number_pattern = '/^'.$range_row['range_prefix'] . '(\d*).*/i';
		preg_match($number_pattern, $story_row['publication_info'], $matches);
		if (count($matches) > 1) {
			$publication_number = $matches[1];
		} else {
			echo "for range " . $range_row['range_prefix'] . " " . $range_row['range_start'] . " to " . $range_row['range_end'] . " no matches found for: " . $story_row['publication_info'] . "\n";
			continue;
		}

		if ( ($range_row['range_start'] == -1) || 
		     (($range_row['range_start'] != -1) && ($range_row['range_end'] == -1) && ($publication_number >= $range_row['range_start'])) ||
		     (($range_row['range_start'] != -1) && ($range_row['range_end'] != -1) && ($publication_number >= $range_row['range_start']) && ($publication_number <= $range_row['range_end'])) ) {

			echo "for range " . $range_row['range_prefix'] . $range_row['range_start'] . " to " . $range_row['range_end'] . " matched story: " . $story_row['publication_info'] . ", storyID: " . $story_row['story_id'] . "\n";
			if (in_array($story_row['story_id'], $stories_matched)) {
				echo "STORY HAS MORE THAN ONE INDEX: " . $story_row['story_id'] . "\n";
			} else {
				$stories_matched[] = $story_row['story_id'];
			}

			$story_to_etk_index_query = "INSERT INTO story_to_etk_index (story_id, etk_index_id) VALUES(".$story_row['story_id'].", ".$range_row['etk_index_id'].")";
			mysql_query($story_to_etk_index_query) or die('INSERT query for story to ETK index table failed: ' . mysql_error());
			$matches_count++;

		}

	}
	mysql_free_result($story_result);
}
mysql_free_result($range_result);

echo "Total matches found: " . $matches_count . "\n";

mysql_close($dblink);
?>
