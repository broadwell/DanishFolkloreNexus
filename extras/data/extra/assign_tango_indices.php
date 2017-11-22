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
/*
$matches_count = 0;
$stories_matched = array();
 */

function decode_heading($tango_index_name) {
	if ($tango_index_name == "Skt_Hans") {
		$tango_index_name = "Skt. Hans";
	} else {
		# String manipulation: replace underscores with
		# spaces, and SLASH with a /
		
		$tango_index_name = str_replace('_', ' ', $tango_index_name);
		$tango_index_name = str_replace('SLASH', '/', $tango_index_name);
	}
	return $tango_index_name;
}

function encode_heading($tango_index_name) {
	if ($tango_index_name == "Skt. Hans") {
		$tango_index_name = "Skt_Hans";
	} else {
		# String manipulation: replace underscores with
		# spaces, and SLASH with a /
		
		$tango_index_name = str_replace(' ', '_', $tango_index_name);
		$tango_index_name = str_replace('/', 'SLASH', $tango_index_name);
	}
	return $tango_index_name;
}


$info_query = "SELECT * FROM tango_index_data";
$info_result =  mysql_query($info_query) or die('Tango index data query failed: ' . mysql_error());
while ($info_row = mysql_fetch_assoc($info_result)) {

/*	echo "Info row keys: ";
	foreach ($info_row as $key=>$value) {
		echo $key . ", ";
	}
	break;
 */
	#$story_query = "SELECT DISTINCT story_id, publication_info FROM story WHERE publication_info REGEXP '^".$range_row['range_prefix'].".*'";
	$story_query = "SELECT DISTINCT story_id, publication_info FROM story WHERE publication_info LIKE '".$info_row['publication_info']."'";
	$story_result = mysql_query($story_query) or die('Tango index story query failed: ' . mysql_error());

	$matches_count = 0;
	while ($story_row = mysql_fetch_assoc($story_result)) {
		$matches_count++;
		$story_id = $story_row['story_id'];
	}

	if ($matches_count != 1) {
		echo "pub_info " . $info_row['publication_info'] . " matched " . $matches_count . " stories, skipping\n";
		mysql_free_result($story_result);
		continue;
	}

	// DO LOTS OF OTHER STUFF WITH $story_row and $info_row

	echo "Working with story ID " . $story_id . ", pub_info " . $info_row['publication_info'] . "\n";
	foreach($info_row as $key => $value) {

		if (($key == "publication_info") || ($key == "tango_index_data_id") || ($key == "Valborg"))
			continue;
		if ($key == "Genre") {
			# Skip if it's blank

			if ($value == "")
				continue;

			$genre_query = 'SELECT genre_id FROM genre WHERE name="'.$value.'"';
			$genre_result = mysql_query($genre_query) or die('Genre query failed: ' . mysql_error());
			if ($genre_row = mysql_fetch_assoc($genre_result))
				$genre_id = $genre_row['genre_id'];
			else
				continue;
			
			# Check to make sure this story->genre association
			# doesn't already exist
			$story_genre_query = 'SELECT DISTINCT story_to_genre_id FROM story_to_genre WHERE story_id="'.$story_id.'" AND genre_id="'.$genre_id.'"';
			$story_genre_result = mysql_query($story_genre_query) or die('Story to genre query failed: ' . mysql_error());

			if (!($story_genre_row = mysql_fetch_assoc($story_genre_result))) {
				$new_story_genre_query = 'INSERT INTO story_to_genre (story_id, genre_id) VALUES ("'.$story_id.'", "'.$genre_id.'" )';
			echo "Adding genre association: " . $value . ", ID " . $genre_id . "\n";
			mysql_query($new_story_genre_query) or die('Failed to add new story to genre association: ' . mysql_error());
			}

		} else if ($key == "Resolution") {
			# If value is blank, make it "None / Unspecified" 

			if (($value == "") || ($value == "None") || ($value == "No"))
				$value = "None / Unspecified";	

			$resolution_index_query = 'SELECT tango_index_id FROM tango_index WHERE type="Resolution" AND name="'.$value.'"';
			$resolution_index_result = mysql_query($resolution_index_query) or die('Resolution index query failed: ' . mysql_error());
			if ($resolution_index_row = mysql_fetch_assoc($resolution_index_result)) {

				$resolution_index_id = $resolution_index_row['tango_index_id'];

				$resolution_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$resolution_index_id.'" )';
				echo "Adding resolution: " . $value . ", Tango index " . $resolution_index_id . "\n";
				mysql_query($resolution_query) or die('Failed to add new story to Tango resolution association: ' . mysql_error());
			}

		} else {

			if ($value == "False")
				continue;

			$tango_index_name = decode_heading($key);
			
			$tango_type_query = "SELECT tango_index_id, name FROM tango_index WHERE type='". $tango_index_name."'";
			$tango_type_result = mysql_query($tango_type_query) or die('Tango type query failed: ' . mysql_error());
			$parent_matched = false;
			$child_matched = false;
			$none_unspecified_index_id = 0;
			while ($index_row = mysql_fetch_assoc($tango_type_result)) {
				$parent_matched = true;
				# An index type (parent) is True for this story.
				# See if any of its children are too
				if (array_key_exists(encode_heading($index_row['name']), $info_row) && ($info_row[encode_heading($index_row["name"])] == "True")) {
					$child_matched = true;
					# If so, add this entry to the DB
					$name_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$index_row["tango_index_id"].'" )';
					echo "Matched parent and child index: " . $tango_index_name . "=>" . $index_row['name'] . ", Tango index " . $index_row["tango_index_id"] . "\n";
					mysql_query($name_query) or die('Failed to add new story to Tango name association: ' . mysql_error());
				
				}
				# Make a note of the None / Unspecified index
				# value if we see it
				if ($index_row["name"] == 'None / Unspecified')
					$none_unspecified_index_id = $index_row["tango_index_id"];
			}
			# If the type index is true but none of its children
			# (name) are, add an entry for type + unspecified
			if ($parent_matched && !$child_matched) {
				if ($none_unspecified_index_id > 0) {
					# add an entry for this type + None/unsp
					$type_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$none_unspecified_index_id.'" )';
					echo "Matched parent but not child: " . $tango_index_name . "=>None / Unspecified, Tango index " . $none_unspecified_index_id . "\n";
					mysql_query($type_query) or die('Failed to add new story to Tango type association with name = None / Unspecified: ' . mysql_error());
				}
			}
			
			mysql_free_result($tango_type_result);
			
			$tango_name_query = "SELECT tango_index_id, type, name FROM tango_index WHERE name='". $tango_index_name."'";
			$tango_name_result = mysql_query($tango_name_query) or die('Tango name query failed: ' . mysql_error());
			if ($name_row = mysql_fetch_assoc($tango_name_result)) {

				# If this entry is a name (child) whose parent
				# is not True, then add an entry for this
				# index. If the parent is True, then an entry
				# was already added in the loop above while
				# processing the parent (type)
				if (!array_key_exists(encode_heading($name_row["type"]), $info_row) || (array_key_exists(encode_heading($name_row["type"]), $info_row) && ($info_row[encode_heading($name_row["type"])] == "False"))) {
					$name_query = 'INSERT INTO story_to_tango_index (story_id, tango_index_id) VALUES ("'.$story_id.'", "'.$name_row["tango_index_id"].'" )';
					echo "Matched child but not parent: " . $name_row['type'] . "=>" . $tango_index_name . ", Tango index " . $name_row["tango_index_id"] . "\n";
					mysql_query($name_query) or die('Failed to add new story to Tango type association based on child name: ' . mysql_error());
				}

			}
			mysql_free_result($tango_name_result);
		}

	}

	mysql_free_result($story_result);
}
mysql_free_result($info_result);

mysql_close($dblink);
?>
