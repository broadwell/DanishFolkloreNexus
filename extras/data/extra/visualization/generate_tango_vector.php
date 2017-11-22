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

$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed '. mysql_error());

$story_to_tango_index_query = "SELECT * from story_to_tango_index";
$story_to_tango_index_result = mysql_query($story_to_tango_index_query) or die("Story to Tango index query failed" . mysql_error());
$story_to_tango_indices = array();
while ($index_row = mysql_fetch_assoc($story_to_tango_index_result)) {

	if (array_key_exists($index_row['story_id'], $story_to_tango_indices))
		$story_to_tango_indices[$index_row['story_id']][] = $index_row['tango_index_id'];
	else
		$story_to_tango_indices[$index_row['story_id']] = array($index_row['tango_index_id']);
}

$outfile = fopen('tangoIndicesVector.txt', 'w+');

fwrite($outfile, " \t");

$tango_indices_query = "SELECT tango_index_id FROM tango_index ORDER BY tango_index_id";
$tango_indices_result = mysql_query($tango_indices_query) or die("tango indices query failed" . mysql_error());

$tango_indices = array();

while ($tango_index_row = mysql_fetch_assoc($tango_indices_result)) {
	fwrite($outfile, $tango_index_row['tango_index_id'] . "\t");
	$tango_indices[] = $tango_index_row['tango_index_id'];
}

fwrite($outfile, "\n");

$stories_data = array();

$stories_query = "SELECT distinct story_id from story order by story_id";
$stories_query_result = mysql_query($stories_query) or die("stories query failed" . mysql_error());

while ($story_row = mysql_fetch_assoc($stories_query_result)) {
	$stories_data[$story_row['story_id']] = $story_row;
}

ksort($story_to_tango_indices);

foreach ($story_to_tango_indices as $story_id=>$story_tango_indices) {

	if ((sizeof($story_tango_indices) == 1) && (in_array('130', $story_tango_indices)))
		continue;

	fwrite($outfile, $story_id . "\t");

	foreach ($tango_indices as $tango_index_id) {

		if (in_array($tango_index_id, $story_tango_indices))
				fwrite($outfile, "1\t");
			else
				fwrite($outfile, "0\t");
	}
	fwrite($outfile, "\n");
}

fclose($outfile);

mysql_close($dblink);

?>
