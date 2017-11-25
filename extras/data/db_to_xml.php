<?php

# Connect to the database
$user="dfl";
$password="???";
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
$encoding_result =  mysql_query($encoding_query) or die('Encoding query failed ' . mysql_error());

# Necessary to be able to sort lists according to Danish collation
# (A-Z, Æ Ø Å)
setlocale(LC_COLLATE, "da_DK");

/* Every time we encounter a new place name, we add it to the global place
 * names list, if it isn't already on the list. */
$global_places_list = array();
$global_places_list['N/A'] =  array();
$global_places_list['N/A']['name'] = "N/A";
$global_places_list['N/A']['name_alternate'] = "";
$global_places_list['N/A']['latitude'] = "";
$global_places_list['N/A']['longitude'] = "";
$global_places_list['N/A']['topo_num'] = "";

/* Associative array linking place IDs to place names */
$global_place_names = array();

$missing_places_count = 0;
#echo "MISSING PLACES:\n";

function add_to_global_places($source_array) {
	global $global_places_list;
	global $global_place_names;
	foreach ($source_array as $place_id) {
		if ($place_id && (!(array_key_exists($place_id, $global_places_list)))) {
#			$place_query = "SELECT place_name, alt_place_name, latitude, longitude  FROM story_places WHERE CONVERT(place_name USING utf8)=\"$place\" or CONVERT(alt_place_name USING utf8)=\"$place\"";
			$place_query = "SELECT name, name_alternate, latitude, longitude, topo_num FROM place WHERE place_id=$place_id";
			$place_result =  mysql_query($place_query) or die('Place query failed ' . mysql_error());
			$place_row = mysql_fetch_assoc($place_result);

			if (!($place_row)) {
				echo "missing place: $place_id\n";
			} else {
				if ($place_row['name'] == "") {
					echo "missing place name: $place_id\n";
				} else {
					$global_place_names[$place_id] = $place_row['name'];
				}
			} 

			$global_places_list[$place_id] = $place_row;

			mysql_free_result($place_result);

#			$global_places_list[] = $new_item;

		}
	}
}

# Array indexed by fieldtrip_id and containing an array of story query rows
# (each of which is also an associative array) for each story collected on
# that fieldtrip.
$fieldtrips = array();

# Array that maps fieldtrip_id fields to fieldtrip_name entries.
$fieldtrip_name = array();

/* Array of story info indexed by the place the story was collected. */
$stories_collected_list = array();

/* An array, indexed by place_id, listing all the stories that mention
 * a particular place. */
$places_mentioned_list = array();

# Function to add an XML <name>value</name> node as a child of another node.
# Returns an identifier for this node that can be used to add children
# to it, or set name=value attributes via add_attribute()
# To specify a valueless node (just <name></name>) pass in a '' for $value
function add_child($dom, $base, $name, $value) {
	if ($value) {
		// Stupid DOMDocument functions don't escape ampersands (&)
		$value = str_replace('&', '&amp;', $value);
		$child = $base->appendChild($dom->createElement($name, $value));
	} else {
		$child = $base->appendChild($dom->createElement($name));
	}
	return $child;
}

# Function to assign an XML <element name=value>BLAH</element> attribute
# to an existing node. Returns an identifier for this attribute
function add_attribute($dom, $element, $name, $value) {
	$attr = $element->appendChild($dom->createAttribute($name));
	$attr->appendChild($dom->createTextNode($value));
	return $attr;
}

function add_to_fieldtrip_names($fieldtrip_id) {
	if (($fieldtrip_id === null) || ($fieldtrip_id == "None recorded")) {
		return "";
	}
	global $fieldtrip_name;
	if (!(array_key_exists($fieldtrip_id, $fieldtrip_name))) {
		$fieldtrip_name_query = "SELECT fieldtrip_name FROM fieldtrip WHERE fieldtrip_id=$fieldtrip_id";
		$fieldtrip_name_result = mysql_query($fieldtrip_name_query) or die('Fieldtrip query failed when adding fieldtrip ' . $fieldtrip_id . '; ' . mysql_error());
		$fieldtrip_name_row = mysql_fetch_assoc($fieldtrip_name_result);
		$fieldtrip_name[$fieldtrip_id] = $fieldtrip_name_row['fieldtrip_name'];
		mysql_free_result($fieldtrip_name_result);
	}
	return $fieldtrip_name[$fieldtrip_id];
}

function dtrim($str) {
	return trim(trim($str,'.,;'));
}
echo("Generating bibliography file\n");

$display_strings = array();

function build_notes_string($bib_row) {
	$notes_string = "";
	if ($bib_row['alternate_title'] != "")
		$notes_string .= 'Source abbreviation: ' . dtrim($bib_row['alternate_title']) . ' .';
	if ($bib_row['edition'] != "")
		$notes_string .= 'Edition: ' . dtrim($bib_row['edition']) . '. ';
	if ($bib_row['accession_number'] != "")
		$notes_string .= 'Accession number: ' . dtrim($bib_row['accession_number']) . '. ';
	if ($bib_row['call_number'] != "")
		$notes_string .= 'Call number: ' . dtrim($bib_row['accession_number']) . '. ';
	if ($bib_row['isbn'] != "")
		$notes_string .= 'ISBN: ' . dtrim($bib_row['isbn']) . '. ';
//	if ($bib_row['notes'] != "")
//	$notes_string .= 'Notes: ' . $bib_row['notes'] . '. ';

}

# XML structure for the bibliography.xml file
$bib_dom = new DOMDocument('1.0');
$bib_info = $bib_dom->appendChild($bib_dom->createElement('bibliography'));
// Do bibliography stuff here 
#$bib_query = 'SELECT * FROM (SELECT * FROM bibliography WHERE source="etk_endnote" OR source="danish_ethno_endnote" ORDER BY author) AS t1 ORDER BY endnote_reference_type';
$bib_query = 'SELECT * FROM (SELECT * FROM (SELECT * FROM dfl_bibliography ORDER BY reference_type) AS t1 ORDER BY year) AS t2 ORDER BY author';
$bib_result = mysql_query($bib_query) or die('bibliography query failed ' . mysql_error());
 
/* Key to EndNote reference types:
   0 = Article
   1 = Book
   2 = Thesis
   3 = Conference proceedings
   5 = Newspaper article
   6 = Computer resource
   7 = Book section
   9 = Edited book
   12 = Audiovisual material
   20 = Manuscript
   21 = Film or broadcast
   31 = Other - there's only one of these, should be changed to 0 (article)
   37 = Unpublished work */

$ref_types = array("0" => "Article", "1" => "Book", "2" => "Thesis", "3" => "Proceedings", "5" => "Newspaper article", "6" => "Computer resource", "7" => "Book section", "9" => "Edited book", "12" => "Audiovisual material", "20" => "Manuscript", "21" => "Film or broadcast", "31" => "Other", "37" => "Unpublished work");
while ($bib_row = mysql_fetch_assoc($bib_result)) {

	//str_replace("\r", "", $text);
	$reference_type = $ref_types[$bib_row['reference_type']];
	$bib_string = '[' . $reference_type . '] ';

	if ($bib_row['author'] != "") {	
		$author_string = str_replace("\r", ", ", $bib_row['author']);

		$bib_string .= dtrim($author_string) . '. ';
	}

	$bib_string .= dtrim($bib_row['year']) . '. ';

	if (($reference_type == "Book") || ($reference_type == "Edited book")) {
		if ($bib_row['volume'] != "")
			$bib_string .= 'Vol. ' . dtrim($bib_row['volume']) . ' of ';
		$bib_string .= '<i>' . dtrim($bib_row['title']);
		if (($bib_row['secondary_title'] != "") && ($bib_row['secondary_title'] != $bib_row['title']))
			$bib_string .= ' (' . dtrim($bib_row['secondary_title']) . ')';
		$bib_string .= '</i>. ';
	} else { // If it's not a book, we don't italicize the title or put it in quotes
		if ($bib_row['title'] != "")
			$bib_string .= dtrim($bib_row['title']) . '. ';
		if ((($reference_type == "Book section") || ($reference_type == "Proceedings")) && (($bib_row['secondary_title'] != "") && ($bib_row['secondary_title'] != $bib_row['title']))) {
			$bib_string .= 'In ';	
		}
		if (($bib_row['secondary_title'] != "") && ($bib_row['secondary_title'] != $bib_row['title'])) {
			if ($bib_row['volume'] != "")
				$bib_string .= 'Vol. ' . dtrim($bib_row['volume']) . ' of ';
			$bib_string .= '<i>' . dtrim($bib_row['secondary_title']) . '</i>';
		}
	}

	if (($reference_type == "Edited book") && ($bib_row['secondary_author'] != ""))
		$bib_string .= 'Ed. ' . dtrim($bib_row['secondary_author']) . '. ';

	if ($reference_type == "Article") {
		if ($bib_row['number'] != "")
			$bib_string .= ' ' . dtrim($bib_row['number']);
		if ($bib_row['pages'] != "") {
			if ($bib_row['number'] != "")
				$bib_string .= ': ' . dtrim($bib_row['pages']);
			else
				$bib_string .= ', ' . dtrim($bib_row['pages']);
		}
	}

	if ($reference_type == "Book section") {
		if ($bib_row['pages'] != "")
			$bib_string .= ': ' . dtrim($bib_row['pages']);
		$bib_string .= '. ';
	}

	if (($reference_type == "Book") || ($reference_type == "Edited book") || ($reference_type == "Book section")) {
		$bib_string .= dtrim($bib_row['place_published']);
		if (($bib_row['publisher']) && ($bib_row['place_published']))
			$bib_string .= ": ";
		$bib_string .= dtrim($bib_row['publisher']);
	}
	if ($reference_type == "Newspaper article") {
		if ($bib_row['place_published'])
			$bib_string .= ": " . dtrim($bib_row['place_published']);
	}
	if (($reference_type == "Audiovisual material") || ($reference_type == "Film or broadcast"))  {
		if ($bib_row['place_published'] && $bib_row['publisher'])
			$bib_string .= $bib_row['place_published'] . ", " . $bib_row['publisher'];
		else if ($bib_row['publisher'])
			$bib_string .= $bib_row['publisher'];
	}
	if ($reference_type == "Unpublished work") {
		if ($bib_row['place_published'])
			$bib_string .= $bib_row['place_published'];
	}

	if ($reference_type == "Thesis") {
		$bib_string .= 'PhD diss., ' . dtrim($bib_row['publisher']);
	}

	$bib_string = str_replace("\r", ", ", $bib_string);
	$bib_string = trim($bib_string);
	if ($bib_string[strlen($bib_string)-1] != '.')
		$bib_string .= '.';

	$notes_string = build_notes_string($bib_row);

	$bib_node = add_child($bib_dom, $bib_info, 'entry', ''); 
	add_attribute($bib_dom, $bib_node, 'reference_id', $bib_row['reference_id']);
	add_attribute($bib_dom, $bib_node, 'reference_type', $bib_row['reference_type']);
	add_attribute($bib_dom, $bib_node, 'category', $bib_row['category']);
//	if ($bib_row['source'] == "etk_endnote")
//		add_attribute($bib_dom, $bib_node, 'bibliography_type', "ETK");
//	else // danish_folklore_endnote
//		add_attribute($bib_dom, $bib_node, 'bibliography_type', "Folklore");
// 
	add_child($bib_dom, $bib_node, 'display_string', $bib_string);
	add_child($bib_dom, $bib_node, 'notes_string', $notes_string);

	$display_strings[$bib_row['reference_id']] = $bib_string;
}
mysql_free_result($bib_result);
/*
$bib_dom->formatOutput = true;
$bib_dom->save('bibliography.xml');
 */
# person_id to names:
# 150 = Bitte Jens Kristensen (BJK), formerly 22
# 123 = Peder Johansen (PJ), formerly 39
# 241 = Jens Peter Pedersen (JPP), formerly 92
# 90 = (Ane) Margrete Jensdatter (AMJ), formerly 188
# 235 = Kirsten Marie Pedersdatter (KMP), formerly 189

$informant_residence = array();
$informants = array();
$informants_query = "SELECT DISTINCT informant_id FROM story_to_informant LEFT JOIN person ON story_to_informant.informant_id=person.person_id where core_informant=1";
$informants_result =  mysql_query($informants_query) or die('Informants query failed ' . mysql_error());
while ($informant_row = mysql_fetch_assoc($informants_result)) {
	$informants[] = $informant_row['informant_id'];
}
$informants_query = "SELECT DISTINCT informant_id FROM story_to_informant LEFT JOIN person ON story_to_informant.informant_id=person.person_id order by person.last_name";
$informants_result =  mysql_query($informants_query) or die('Informants query failed ' . mysql_error());
while ($informant_row = mysql_fetch_assoc($informants_result)) {
	$informants[] = $informant_row['informant_id'];
}

mysql_free_result($informants_result);

$informant_stories = array();
$all_stories = array();

$keywords_to_stories = array();
$keywords = array();

$etk_indices_to_stories = array();
$etk_indices = array();

$tango_indices_to_stories = array();
$tango_indices = array();
$tango_hierarchy = array();

$genres_to_stries = array();
$genres = array();

/* Array, index by story_id, listing person info of all secondary informants
 * for that story. */
$story_to_secondary_informants = array();

# Populate the informant_stories array (informant_id => array of story_ids)
foreach ($informants as $informant_id) {

	$informant_stories[$informant_id] = array();
	$stories_query = "SELECT DISTINCT story_id, secondary FROM story_to_informant WHERE informant_id=$informant_id";
	$stories_result =  mysql_query($stories_query) or die('Story to informant query failed ' . mysql_error());

	while ($stories_row = mysql_fetch_assoc($stories_result)) {
		/* If story_to_informant has two entries for the story, one in which the
		 * informant_id is 0 and one in which it's something else (this happens
		 * several times, for some reason), we skip it when we're building the
		 * list for the null informant. Some informant is always better than none. */
		if ($informant_id == 0) {
			$null_informant_query = "SELECT DISTINCT story_id from story_to_informant WHERE story_id=".$stories_row['story_id']." AND informant_id != 0";
			$null_informant_result = mysql_query($null_informant_query) or die('null informant query failed ' . mysql_error());
			if (mysql_fetch_assoc($null_informant_result))
				continue;
		}
		$informant_stories[$informant_id][] = $stories_row;
	}

	mysql_free_result($stories_result);
}

# XML structure for the informants.xml file
$informantlist_dom = new DOMDocument('1.0');
$informantlist_info = $informantlist_dom->appendChild($informantlist_dom->createElement('informants'));

# XML structure for the stories.xml file
$storylist_dom = new DOMDocument('1.0');
$storylist_info = $storylist_dom->appendChild($storylist_dom->createElement('stories'));

# XML structure for the story_texts.xml file
$storytexts_dom = new DOMDocument('1.0');
$storytexts_info = $storytexts_dom->appendChild($storytexts_dom->createElement('story_texts'));

# XML structure for the story_search.xml file
$storysearch_dom = new DOMDocument('1.0');
$storysearch_info = $storysearch_dom->appendChild($storysearch_dom->createElement('story_search'));

# XML structure for the manuscript_images.xml file
$manuscripts_dom = new DOMDocument('1.0');
$manuscripts_info = $manuscripts_dom->appendChild($manuscripts_dom->createElement('manuscript_images'));

# Create the informants' directory, if it doesn't already exist
if (!(is_dir("informants"))) {
	mkdir("informants", 0755);
}
# Create the global stories directory, if it doesn't already exist
if (!(is_dir("stories"))) {
	mkdir("stories", 0755);
}

foreach($informant_stories as $informant_id=>$stories_array) {

	echo("Working with informant ID " . $informant_id . "\n");
	# XML structure for the individual informant file 
	$informant_dom = new DOMDocument('1.0');
	$informant_info = $informant_dom->appendChild($informant_dom->createElement('informant'));

    if ($informant_id == 0) {
	$informant_row = array();
        $full_name = "Unknown informant";
	$last_name = "informant";
	$first_name = "Unknown";
	$informant_row['core_informant'] = 0;
	$informant_row['gender'] = 'N/A';
	$informant_row['birth_date'] = 'N/A';
	$informant_row['death_date'] = 'N/A';
	$informant_row['birth_place_id'] = 'N/A';
	$informant_row['death_place_id'] = 'N/A';
	$informant_row['confirmation_place_id'] = '0';
    } else {
	$informant_query = "SELECT first_name, last_name, nickname, title, gender, birth_date, birth_place_id, death_date, death_place_id, confirmation_date, confirmation_place_id, core_informant FROM person WHERE person_id=$informant_id";
	$informant_result =  mysql_query($informant_query) or die('Informant query failed ' . mysql_error());
	$informant_row = mysql_fetch_assoc($informant_result);

	$first_name = $informant_row['first_name'];
	$last_name = $informant_row['last_name'];

	$birth_place_id = $informant_row['birth_place_id'];
	$death_place_id = $informant_row['death_place_id'];

	if ($informant_row['nickname']) {
		$full_name = $first_name . ' (' . $informant_row['nickname'] . ') ' . $last_name;
	} else {
		$full_name = $first_name . ' ' . $last_name;
	}

	if ($informant_row['title']) {
		$full_name = $informant_row['title'] . ' ' . $full_name;
	}

	if (!$informant_row['gender'])
		$informant_row['gender'] = 'N/A';
	if (!$informant_row['birth_date'])
		$informant_row['birth_date'] = 'N/A';
	if (!$informant_row['birth_place_id'])
		$informant_row['birth_place_id'] = 'N/A';
	if (!$informant_row['death_date'])
		$informant_row['death_date'] = 'N/A';
	if (!$informant_row['death_place_id'])
		$informant_row['death_place_id'] = 'N/A';
	if (!$informant_row['confirmation_date'])
		$informant_row['confirmation_date'] = 'N/A';
/*	if (!$informant_row['confirmation_place_id'])
		$informant_row['confirmation_place_id'] = '0'; */

	# Stuff for the global informants.xml file
	$informantlist_node = add_child($informantlist_dom, $informantlist_info, 'informant', '');
	add_attribute($informantlist_dom, $informantlist_node, 'person_id', $informant_id);
	add_child($informantlist_dom, $informantlist_node, 'core_informant', $informant_row['core_informant']);
	add_child($informantlist_dom, $informantlist_node, 'full_name', $full_name);
	add_child($informantlist_dom, $informantlist_node, 'last_name', $last_name);
	add_child($informantlist_dom, $informantlist_node, 'first_name', $first_name);
	add_child($informantlist_dom, $informantlist_node, 'gender', $informant_row['gender']);
	# For this to work, all of the informants' images have to be moved into
	# their respective folders, and the images must be named [NUMBER].jpg
	if (file_exists("informants/$informant_id.jpg"))
		add_child($informantlist_dom, $informantlist_node, 'image', "data/informants/$informant_id.jpg");
	add_child($informantlist_dom, $informantlist_node, 'url', "data/informants/$informant_id.dfl");

	# We'll need to know about these places for both the global informants
	# file and each individual one, so we might as well query them all
	# at once.	
	add_to_global_places(array($birth_place_id, $death_place_id, $informant_row['confirmation_place_id']));

	# XXX Eventually we can get the informant's residence place from the DB,
	# but for now this query would work:
	$residence_query = "SELECT place_recorded_id, COUNT(place_recorded_id) AS place_count FROM story, story_to_informant WHERE story_to_informant.informant_id=".$informant_id." AND story_to_informant.story_id=story.story_id GROUP BY place_recorded_id ORDER BY place_count DESC";
	$residence_result = mysql_query($residence_query) or die('Residence query failed ' . mysql_error());
	$residence_row = mysql_fetch_assoc($residence_result);
	if ($residence_row) {
		$residence_place_id = $residence_row['place_recorded_id'];
		$informant_residence[$informant_id] = $residence_place_id;
		add_to_global_places(array($residence_place_id));
		$informant_row['residence_place_id'] = $residence_place_id;
	}
	mysql_free_result($residence_result);

	$informant_location_node = add_child($informantlist_dom, $informantlist_node, 'residence_place', '');
	if ($residence_place_id) {
		add_attribute($informantlist_dom, $informant_location_node, 'place_id', $residence_place_id);
		add_child($informantlist_dom, $informant_location_node, 'name', $global_places_list[$residence_place_id]['name']);
		add_child($informantlist_dom, $informant_location_node, 'latitude', $global_places_list[$residence_place_id]['latitude']);
		add_child($informantlist_dom, $informant_location_node, 'longitude', $global_places_list[$residence_place_id]['longitude']);
	}

    } // End if informant_id

	# Stuff for the individual informant's XML file (e.g., 21.xml)
	add_attribute($informant_dom, $informant_info, 'person_id', $informant_id);
	add_child($informant_dom, $informant_info, 'core_informant', $informant_row['core_informant']);
	add_child($informant_dom, $informant_info, 'full_name', $full_name);
	add_child($informant_dom, $informant_info, 'last_name', $last_name);
	add_child($informant_dom, $informant_info, 'first_name', $first_name);
	add_child($informant_dom, $informant_info, 'gender', $informant_row['gender']);
	if (file_exists("informants/$informant_id.jpg"))
		add_child($informant_dom, $informant_info, 'image', "data/informants/$informant_id.jpg");
	if (file_exists('informants/'.$informant_id.'_full.dfl'))
		add_child($informant_dom, $informant_info, 'fullbio', 'data/informants/'.$informant_id.'_full.dfl');
	if (file_exists("informants/$informant_id"."_bio.dfl"))
		add_child($informant_dom, $informant_info, 'intro_bio', "data/informants/$informant_id"."_bio.dfl");

	add_child($informant_dom, $informant_info, 'birth_date', $informant_row['birth_date']);
	add_child($informant_dom, $informant_info, 'death_date', $informant_row['death_date']);

	$personal_occupation_query = "SELECT DISTINCT occupation_id FROM person_to_occupation WHERE person_id=$informant_id";
	$personal_occupation_result = mysql_query($personal_occupation_query) or die('Occupation query failed ' . mysql_error());
	$personal_occupation_row = mysql_fetch_assoc($personal_occupation_result);
	$occupations = add_child($informant_dom, $informant_info, 'occupations', '');
	while ($personal_occupation_row) {
		$occupation_id = $personal_occupation_row['occupation_id'];
		$occupation_query = "SELECT name FROM occupation WHERE occupation_id=$occupation_id";
		$occupation_result = mysql_query($occupation_query) or die('Occupation query failed ' . mysql_error());
		$occupation_row = mysql_fetch_assoc($occupation_result);

		$occupation_info = add_child($informant_dom, $occupations, 'occupation', $occupation_row['name']);
		add_attribute($informant_dom, $occupation_info, 'occupation_id', $occupation_id);

		$personal_occupation_row = mysql_fetch_assoc($personal_occupation_result);
		mysql_free_result($occupation_result);
	}
	mysql_free_result($personal_occupation_result);

#	Eventually we'll query the "marriage" table for spouse info
#	add_child($informant_dom, $informant_info, 'marital_status', $informant_row['marital_status']);
#	XXX Eventually include primary residence here?

	# Add the stories node to the individual informant file
	$stories_node = add_child($informant_dom, $informant_info, 'stories', '');

	$informant_story_places = array();
	$informant_places_mentioned = array();

	# Do lots of stuff for each informant's stories
	foreach ($stories_array as $story_data) {
		$story_id = $story_data['story_id'];
		$secondary = $story_data['secondary'];
		if ($secondary == 0)
			$informant_type = "primary";
		else
			$informant_type = "secondary";

		echo("Working with story ID ". $story_id . " - " . $informant_type . " informant\n");

		# Do the story query
		# PMB NOTE the story table no longer contains a date_told field
		$story_query = "SELECT story_id, fieldtrip_id, publication_info, order_told, annotation, bibliographic_info, place_recorded_id, fielddiary_page_start, fielddiary_page_end, core_or_variant FROM story WHERE story_id=$story_id";
		$story_result =  mysql_query($story_query) or die('Story query failed for story ' . $story_id . '; ' . mysql_error());
		$story_row = mysql_fetch_assoc($story_result);

		# Need to add this manually now
		$story_row['informant_id'] = $informant_id;

		$story_row['informant_full_name'] = $full_name;

		$order_told = sprintf("%.2f", $story_row['order_told']);

		$publication_info = $story_row['publication_info'];

		$story_full_name = $informant_id . ' - ' . $order_told . ' - ' . $publication_info;

		# Get the published & manuscript versions
		$story_object_query = "SELECT story_object_text, text_type FROM story_object WHERE story_id=$story_id";
		$story_object_result = mysql_query($story_object_query) or die('Story object query failed ' . mysql_error());
		$story_object_row = mysql_fetch_assoc($story_object_result);
		# Iterate through these: there should be at most 3:
		# danish_manuscript, english_manuscript and english_publication
		# Need to add blank tags even if there's nothing in the DB

		$danish_manuscript = "";
		$danish_publication = ""; # XXX currently not in DB
		$english_manuscript = "";
		$english_publication = "";
		
		while ($story_object_row) {
			if ($story_object_row['text_type'] == "danish_manuscript") {
				$danish_manuscript = $story_object_row['story_object_text'];
			} else if ($story_object_row['text_type'] == "english_manuscript") {
				$english_manuscript = $story_object_row['story_object_text'];
			} else if ($story_object_row['text_type'] == "danish_publication") {
				$danish_publication = $story_object_row['story_object_text'];
			} else if ($story_object_row['text_type'] == "english_publication") {
				$english_publication = $story_object_row['story_object_text'];
			}
			$story_object_row = mysql_fetch_assoc($story_object_result);
		}

		mysql_free_result($story_object_result);

		$place_recorded_id = $story_row['place_recorded_id'];
		add_to_global_places(array($place_recorded_id));

		# Put an entry for the story in the informant's XML file
		$story_node = add_child($informant_dom, $stories_node, 'story', '');
		add_attribute($informant_dom, $story_node, 'story_id', $story_id);
		add_attribute($informant_dom, $story_node, 'secondary_informant', $secondary);
		add_child($informant_dom, $story_node, 'informant_id', $informant_id);
		add_child($informant_dom, $story_node, 'publication_info', $publication_info);
		if ($secondary==1)
			add_child($informant_dom, $story_node, 'full_name', $story_full_name . "*");
		else
			add_child($informant_dom, $story_node, 'full_name', $story_full_name);
		add_child($informant_dom, $story_node, 'url', "data/stories/$story_id.dfl");
	
		if ($place_recorded_id)	 {
			$place_recorded_node = add_child($informant_dom, $story_node, 'place_recorded', $global_places_list[$place_recorded_id]['name']);
			add_attribute($informant_dom, $place_recorded_node, 'id', $place_recorded_id);

			# Put an entry for the place where the story was recorded
			# in the informant's XML file
			if (!(in_array($place_recorded_id, $informant_story_places)))
				$informant_story_places[] = $place_recorded_id;
		}

		/* PMB skip to the next story for the informant if the informant is
		 * a secondary informant for this story -- the info in stories.xml
		 * and the separate XML file for the story will be filled in
		 * while handling the stories told by the story's PRIMARY informant
		 */
		if ($secondary==1) {
			$story_to_secondary_informants[$story_id][] = array($full_name, $informant_id);
			continue;
		}

		# Put an entry for the story in story_search.xml
		$story_search_string = $publication_info . " (" . $story_id . "): " . $full_name;
#		$story_search_string = $publication_info;
		$storysearch_node = add_child($storysearch_dom, $storysearch_info, 'story', $story_search_string);
		add_attribute($storysearch_dom, $storysearch_node, 'story_id', $story_id);

		# Put an entry for the story in story_texts.xml
		$storytexts_node = add_child($storytexts_dom, $storytexts_info, 'story', '');
		add_attribute($storytexts_dom, $storytexts_node, 'story_id', $story_id);
		add_child($storytexts_dom, $storytexts_node, 'full_name', $story_full_name);
		add_child($storytexts_dom, $storytexts_node, 'publication_info', $publication_info);

		# Put an entry for the story in stories.xml
		$storylist_node = add_child($storylist_dom, $storylist_info, 'story', '');
		add_attribute($storylist_dom, $storylist_node, 'story_id', $story_id);
		$informant_node = add_child($storylist_dom, $storylist_node, 'informant', $full_name);
		add_attribute($storylist_dom, $informant_node, 'id', $informant_id);
		if (in_array($story_id, array_keys($story_to_secondary_informants))) {
			$secondary_informants_node = add_child($storylist_dom, $storylist_node, 'secondary_informants', '');
			foreach ($story_to_secondary_informants[$story_id] as $secondary_informant_data) {
				$secondary_informant_node = add_child($storylist_dom, $secondary_informants_node, 'informant', $secondary_informant_data[0]);
				add_attribute($storylist_dom, $secondary_informant_node, 'id', $secondary_informant_data[1]);
			}
		}

		add_child($storylist_dom, $storylist_node, 'publication_info', $publication_info);
		add_child($storylist_dom, $storylist_node, 'full_name', $story_full_name);
		add_child($storylist_dom, $storylist_node, 'search_string', $story_search_string);
		if ($place_recorded_id) {
			$place_recorded_node = add_child($storylist_dom, $storylist_node, 'place_recorded', $global_places_list[$place_recorded_id]['name']);
			add_attribute($storylist_dom, $place_recorded_node, 'id', $place_recorded_id);
		}
		add_child($storylist_dom, $storylist_node, 'url', "data/stories/$story_id.dfl");
		if ((!$story_row['fieldtrip_id']) || ($story_row['fieldtrip_id'] == "None recorded")) {
			echo "no fieldtrip recorded for story $story_id\n";
		}
		if (add_to_fieldtrip_names($story_row['fieldtrip_id'])) {
			$fieldtrip_node = add_child($storylist_dom, $storylist_node, 'fieldtrip', $fieldtrip_name[$story_row['fieldtrip_id']]);
			add_attribute($storylist_dom, $fieldtrip_node, 'id', $story_row['fieldtrip_id']);
		} else {
			$fieldtrip_node = add_child($storylist_dom, $storylist_node, 'fieldtrip', "None recorded");
			add_attribute($storylist_dom, $fieldtrip_node, 'id', "");
		}

		if ($place_recorded_id) {
			# Store the story's details in the story_collected_list array
			if (array_key_exists($place_recorded_id, $stories_collected_list)) {
				$stories_collected_list[$place_recorded_id][] = $story_row;
			} else {
				$stories_collected_list[$place_recorded_id] = array($story_row);
			}
		}

		$story_dom = new DOMDocument('1.0');
		$story_info = $story_dom->appendChild($story_dom->createElement('story'));
		add_attribute($story_dom, $story_info, 'story_id', $story_id);
		add_child($story_dom, $story_info, 'publication_info', $publication_info);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_child($story_dom, $story_info, 'full_name', $story_full_name);

		add_child($story_dom, $story_info, 'informant_id', $informant_id);
		add_child($story_dom, $story_info, 'informant_last_name', $last_name);
		add_child($story_dom, $story_info, 'informant_first_name', $first_name);
		add_child($story_dom, $story_info, 'informant_full_name', $full_name);
		#		add_child($story_dom, $story_info, 'date_told', $story_row['date_told']);
		add_child($story_dom, $story_info, 'order_told', $order_told);
		
		if (in_array($story_id, array_keys($story_to_secondary_informants))) {
			$secondary_informants_node = add_child($story_dom, $story_info, 'secondary_informants', '');
			foreach ($story_to_secondary_informants[$story_id] as $secondary_informant_data) {
				$secondary_informant_node = add_child($story_dom, $secondary_informants_node, 'informant', $secondary_informant_data[0]);
				add_attribute($story_dom, $secondary_informant_node, 'id', $secondary_informant_data[1]);
			}
		}

		if (add_to_fieldtrip_names($story_row['fieldtrip_id'])) {
			$fieldtrip_node = add_child($story_dom, $story_info, 'fieldtrip', $fieldtrip_name[$story_row['fieldtrip_id']]);
			add_attribute($story_dom, $fieldtrip_node, 'id', $story_row['fieldtrip_id']);
			$fieldtrip_dates_query = "SELECT start_date, end_date FROM fieldtrip WHERE fieldtrip_id=".$story_row['fieldtrip_id'];
			$fieldtrip_dates_result = mysql_query($fieldtrip_dates_query) or die('Fieldtrip dates query failed ' . mysql_error());
			$fieldtrip_dates_row = mysql_fetch_assoc($fieldtrip_dates_result);
			if (!$fieldtrip_dates_row['start_date'])
				$fieldtrip_dates_row['start_date'] = 'N/A';
			if (!$fieldtrip_dates_row['end_date'])
				$fieldtrip_dates_row['end_date'] = 'N/A';
			add_child($story_dom, $story_info, 'fieldtrip_start_date', $fieldtrip_dates_row['start_date']);
			add_child($story_dom, $story_info, 'fieldtrip_end_date', $fieldtrip_dates_row['end_date']);
			mysql_free_result($fieldtrip_dates_result);
		} else {
			$fieldtrip_node = add_child($story_dom, $story_info, 'fieldtrip', "None recorded");
			add_attribute($story_dom, $fieldtrip_node, 'id', "");
		}
		# Create a parent node for all the places associated with this
		# story (place recorded & places mentioned, for now)
		$places_node = add_child($story_dom, $story_info, 'places', '');
	
		if ($place_recorded_id) {	
			$place_recorded_node = add_child($story_dom, $places_node, 'place', '');
			add_attribute($story_dom, $place_recorded_node, 'type', 'place_recorded');
			add_child($story_dom, $place_recorded_node, 'place_id', $place_recorded_id);
			add_child($story_dom, $place_recorded_node, 'name', $global_places_list[$place_recorded_id]['name']);
			$display_name = $global_places_list[$place_recorded_id]['name'] . ' (place recorded)';
			add_child($story_dom, $place_recorded_node, 'display_name', $display_name);
		}

		# List the places mentioned in this story (if any)
		$places_mentioned_query = "SELECT DISTINCT place_id FROM story_to_place_mentioned WHERE story_id=$story_id";
		$places_mentioned_result = mysql_query($places_mentioned_query) or die('Story to place mentioned query failed ' . mysql_error());
		$places_mentioned_row = mysql_fetch_assoc($places_mentioned_result);

		/* Also add this info to the stories.xml file */
		$places_mentioned_node = add_child($storylist_dom, $storylist_node, 'places_mentioned', '');

		$informant_places_mentioned_node = add_child($informant_dom, $story_node, 'places_mentioned', ''); 

		while ($places_mentioned_row) {
			$place_mentioned_id = $places_mentioned_row['place_id'];
			if ($place_mentioned_id) {
				add_to_global_places(array($place_mentioned_id));
				$place_mentioned_node = add_child($story_dom, $places_node, 'place', '');
				add_attribute($story_dom, $place_mentioned_node, 'type', 'place_mentioned');
				add_child($story_dom, $place_mentioned_node, 'place_id', $place_mentioned_id);
				add_child($story_dom, $place_mentioned_node, 'name', $global_places_list[$place_mentioned_id]['name']);
				$display_name = $global_places_list[$place_mentioned_id]['name'] . ' (place mentioned)';
				add_child($story_dom, $place_mentioned_node, 'display_name', $display_name);
	
				$place_mentioned_node2 = add_child($storylist_dom, $places_mentioned_node, 'place', '');
				add_attribute($storylist_dom, $place_mentioned_node2, 'place_id', $place_mentioned_id);
				add_child($storylist_dom, $place_mentioned_node2, 'place_name', $global_places_list[$place_mentioned_id]['name']);
	
				if (array_key_exists($place_mentioned_id, $places_mentioned_list)) {
					$places_mentioned_list[$place_mentioned_id][] = $story_row;
				} else {
					$places_mentioned_list[$place_mentioned_id] = array($story_row);
				}

				if (!in_array($place_mentioned_id, array_keys($informant_places_mentioned)))
					$informant_places_mentioned[$place_mentioned_id] = 1;
				else
					$informant_places_mentioned[$place_mentioned_id]++;
				/* Add an entry for the place mentioned to the
			 	* informant's XML file */
				$informant_place_mentioned_node = add_child($informant_dom, $informant_places_mentioned_node, 'place', '');
				add_attribute($informant_dom, $informant_place_mentioned_node, 'id', $place_mentioned_id);
		        	add_child($informant_dom, $informant_place_mentioned_node, 'name', $global_places_list[$place_mentioned_id]['name']);
/*			add_child($informant_dom, $informant_place_mentioned_node, 'latitude', $global_places_list[$place_mentioned_id]['latitude']);
			add_child($informant_dom, $informant_place_mentioned_node, 'longitude', $global_places_list[$place_mentioned_id]['longitude']); */
			
			}
			$places_mentioned_row = mysql_fetch_assoc($places_mentioned_result);

		}
		mysql_free_result($places_mentioned_result);

		/* List the other stories mentioned in the annotation to this story (if any)
		   or the other stories whose annotation mention this story. */
		$stories_mentioned_query ="select distinct mentioned_story_id from ((select distinct story_id_1 as mentioned_story_id from story_to_story where story_id_2=$story_id) union (select distinct story_id_2 as mentioned_story_id from story_to_story where story_id_1=$story_id)) as a";
		$stories_mentioned_result = mysql_query($stories_mentioned_query) or die('Story mentioned query failed ' . mysql_error());

		$stories_mentioned_node = add_child($story_dom, $story_info, 'stories_mentioned', '');

		$mentioned_story_ids = array();

		while ($story_mentioned_row = mysql_fetch_assoc($stories_mentioned_result)) {
			$story_mentioned_id = $story_mentioned_row['mentioned_story_id'];

			$story_data_query ="select story.story_id, publication_info, order_told, place_recorded_id, informant_id from story, story_to_informant where story.story_id=".$story_mentioned_id." and story_to_informant.story_id=".$story_mentioned_id;
			$story_data_result = mysql_query($story_data_query) or die('Story data query failed ' . mysql_error());
			$story_data_row = mysql_fetch_assoc($story_data_result);

			//add_to_global_places(array($story_mentioned_row['place_recorded_id']));
			// PMB: Somehow the query above allows duplicate stories
			// mentioned, but the duplicates should not go into the XML
			if (in_array($story_mentioned_id, $mentioned_story_ids)) {
				echo "ALERT: Duplicate story mentioned ID: ". $story_mentioned_id . "\n";
				continue;
			}

			$mentioned_story_ids[] = $story_mentioned_id;

			if ($story_data_row['informant_id'] == "0") {
				$null_informant_query = "SELECT DISTINCT informant_id from story_to_informant WHERE story_id=".$story_mentioned_id." AND informant_id != 0";
				$null_informant_result = mysql_query($null_informant_query) or die('null informant query failed ' . mysql_error());
				if ($null_informant_row = mysql_fetch_assoc($null_informant_result))
					$story_data_row['informant_id'] = $null_informant_row['informant_id'];
				mysql_free_result($null_informant_result);
			}

			$mentioned_order_told = sprintf("%.2f", $story_data_row['order_told']);
			$story_mentioned_full_name = $story_data_row['informant_id'] . ' - ' . $mentioned_order_told . ' - ' . $story_data_row['publication_info'];

			# Put an entry for the story in the story XML file
			$story_mentioned_node = add_child($story_dom, $stories_mentioned_node, 'story', '');
			add_attribute($story_dom, $story_mentioned_node, 'story_id', $story_mentioned_id);
			add_child($story_dom, $story_mentioned_node, 'informant_id', $story_data_row['informant_id']);
			add_child($story_dom, $story_mentioned_node, 'publication_info', $story_data_row['publication_info']);
			add_child($story_dom, $story_mentioned_node, 'full_name', $story_mentioned_full_name);
			add_child($story_dom, $story_mentioned_node, 'url', "data/stories/".$story_mentioned_id.".dfl");

			mysql_free_result($story_data_result);
		}

		mysql_free_result($stories_mentioned_result);

		if (!$story_row['fielddiary_page_start'])
			$story_row['fielddiary_page_start'] = 'N/A';
		if (!$story_row['fielddiary_page_end'])
			$story_row['fielddiary_page_end'] = 'N/A';
		add_child($story_dom, $story_info, 'fielddiary_page_start', $story_row['fielddiary_page_start']);
		add_child($story_dom, $story_info, 'fielddiary_page_end', $story_row['fielddiary_page_end']);

		add_child($story_dom, $story_info, 'bibliographic_info', fix_newlines($story_row['bibliographic_info']));

		# Get the bibliography references associated with this story
		$biblio_query = "SELECT dfl_bibliography.reference_id, dfl_bibliography.category FROM dfl_bibliography, story_to_biblio WHERE story_to_biblio.story_id=$story_id AND dfl_bibliography.reference_id=story_to_biblio.reference_id";
		$biblio_result =  mysql_query($biblio_query) or die('Bibliography query failed ' . mysql_error());
		$references_node = add_child($story_dom, $story_info, 'bibliography_references', '');
		while ($biblio_row = mysql_fetch_assoc($biblio_result)) {
			$reference_node = add_child($story_dom, $references_node, 'reference', '');
			add_attribute($story_dom, $reference_node, 'id', $biblio_row['reference_id']);
			add_attribute($story_dom, $reference_node, 'category', $biblio_row['category']);
		        add_child($story_dom, $reference_node, 'display_string', $display_strings[$biblio_row['reference_id']]);
		}

		add_child($story_dom, $story_info, 'annotation', fix_newlines($story_row['annotation']));
		add_child($story_dom, $story_info, 'danish_manuscript', fix_newlines($danish_manuscript));
		add_child($story_dom, $story_info, 'english_manuscript', fix_newlines($english_manuscript));
		add_child($story_dom, $story_info, 'danish_publication', fix_newlines($danish_publication));
		add_child($story_dom, $story_info, 'english_publication', fix_newlines($english_publication));

		# Add the texts and annotations to the story_texts.xml file, for easier
		# searching
		add_child($storytexts_dom, $storytexts_node, 'annotation', fix_newlines($story_row['annotation']));
		add_child($storytexts_dom, $storytexts_node, 'danish_manuscript', fix_newlines($danish_manuscript));
		add_child($storytexts_dom, $storytexts_node, 'english_manuscript', fix_newlines($english_manuscript));
		add_child($storytexts_dom, $storytexts_node, 'danish_publication', fix_newlines($danish_publication));
		add_child($storytexts_dom, $storytexts_node, 'english_publication', fix_newlines($english_publication));
		
		# Add entries to the manuscript_images file for this story

		$manuscript_query = "SELECT image_filename, field_diary_start_page, field_diary_end_page FROM manuscript_image WHERE story_id = $story_id ORDER BY image_filename";
		$manuscript_result =  mysql_query($manuscript_query) or die('Query failed ' . mysql_error());
		$manuscript_row = mysql_fetch_assoc($manuscript_result);

		$story_node = add_child($manuscripts_dom, $manuscripts_info, 'story', '');
		add_attribute($manuscripts_dom, $story_node, 'id', $story_id);
		add_child($manuscripts_dom, $story_node, 'publication_info', $publication_info);
		$image_informant_node = add_child($manuscripts_dom, $story_node, 'informant', $full_name);
		add_attribute($manuscripts_dom, $image_informant_node, 'id', $informant_id);
		add_child($manuscripts_dom, $story_node, 'order_told', $order_told);
		# PMB XXX THESE NEED TO BE CONSISTENT!!!!
		add_child($manuscripts_dom, $story_node, 'fielddiary_page_start', $story_row['fielddiary_page_start']);
		add_child($manuscripts_dom, $story_node, 'fielddiary_page_end', $story_row['fielddiary_page_end']);
/*		add_child($manuscripts_dom, $story_node, 'fielddiary_page_start', $manuscript_row['field_diary_start_page']);
		add_child($manuscripts_dom, $story_node, 'fielddiary_page_end', $manuscript_row['field_diary_end_page']); */
		$images_node = add_child($manuscripts_dom, $story_node, 'images', '');
		$image_count = 0;

		while ($manuscript_row) {

			$image_count++;

			$image_filename = $manuscript_row['image_filename'];
			$image_thumbnail = "thumb_" . $image_filename;
			$image_path = "manuscript_images/" . $image_filename;
			$thumbnail_path = "manuscript_images/thumbnails/" . $image_thumbnail;

			$image_node = add_child($manuscripts_dom, $images_node, 'image', '');
			add_attribute($manuscripts_dom, $image_node, 'seqno', $image_count);
			add_child($manuscripts_dom, $image_node, 'image_path', $image_path);
			add_child($manuscripts_dom, $image_node, 'thumbnail_path', $thumbnail_path);
			$manuscript_row = mysql_fetch_assoc($manuscript_result);
		}

		# Get the keywords associated with this story
		$keyword_query = "SELECT DISTINCT keyword, keyword.keyword_id, frequency, display_string FROM story_to_keyword, keyword WHERE story_to_keyword.story_id=$story_id AND keyword.keyword_id=story_to_keyword.keyword_id";
		$keyword_result =  mysql_query($keyword_query) or die('Keywords query failed ' . mysql_error());
		$keywords_node = add_child($story_dom, $story_info, 'keywords', '');

		while ($keyword_row = mysql_fetch_assoc($keyword_result)) {
			$keyword_node = add_child($story_dom, $keywords_node, 'keyword', $keyword_row['display_string']);
			add_attribute($story_dom, $keyword_node, 'id', $keyword_row['keyword_id']);
			add_attribute($story_dom, $keyword_node, 'frequency', $keyword_row['frequency']);
			add_attribute($story_dom, $keyword_node, 'keyword', $keyword_row['keyword']);

			if (!array_key_exists($keyword_row['keyword_id'], $keywords)) {
				$keywords[$keyword_row['keyword_id']] = $keyword_row;
				$keywords_to_stories[$keyword_row['keyword_id']] = array($story_row);
			} else {
				$keywords_to_stories[$keyword_row['keyword_id']][] = $story_row;
			}
		}
		mysql_free_result($keyword_result);

		# Get the ETK index associated with this story
		$etk_query = "SELECT etk_index.etk_index_id, heading_danish, heading_english FROM story_to_etk_index, etk_index WHERE story_to_etk_index.story_id=$story_id AND etk_index.etk_index_id=story_to_etk_index.etk_index_id";
		$etk_result =  mysql_query($etk_query) or die('ETK indices story query failed ' . mysql_error());
		$etk_node = add_child($story_dom, $story_info, 'etk_index', '');
		if ($etk_row = mysql_fetch_assoc($etk_result)) {

			add_attribute($story_dom, $etk_node, 'id', $etk_row['etk_index_id']);
			add_child($story_dom, $etk_node, 'heading_danish', $etk_row['heading_danish']);
			add_child($story_dom, $etk_node, 'heading_english', $etk_row['heading_english']);

			if (!array_key_exists($etk_row['etk_index_id'], $etk_indices)) {
				$etk_indices[$etk_row['etk_index_id']] = $etk_row;
				$etk_indices_to_stories[$etk_row['etk_index_id']] = array($story_row);
			} else {
				$etk_indices_to_stories[$etk_row['etk_index_id']][] = $story_row;
			}
		}
		mysql_free_result($etk_result);
		
		# Get the Tangherlini index associated with this story
		$tango_query = "SELECT tango_index.tango_index_id, type, name FROM story_to_tango_index, tango_index WHERE story_to_tango_index.story_id=$story_id AND tango_index.tango_index_id=story_to_tango_index.tango_index_id ORDER BY tango_index.tango_index_id";
		$tango_result =  mysql_query($tango_query) or die('Tango indices story query failed ' . mysql_error());
		$tango_info = add_child($story_dom, $story_info, 'tango_indices', '');
		while ($tango_row = mysql_fetch_assoc($tango_result)) {

			$tango_node = add_child($story_dom, $tango_info, 'tango_index', '');

			add_attribute($story_dom, $tango_node, 'id', $tango_row['tango_index_id']);
			add_attribute($story_dom, $tango_node, 'display_name', $tango_row['type'] . ": " . $tango_row['name']);

			if (!array_key_exists($tango_row['tango_index_id'], $tango_indices)) {
				$tango_indices[$tango_row['tango_index_id']] = $tango_row;
				$tango_indices_to_stories[$tango_row['tango_index_id']] = array($story_row);
			} else {
				$tango_indices_to_stories[$tango_row['tango_index_id']][] = $story_row;
			}

			# Tango hierarchy is a hash of arrays, keyed on the parent row names
			if (!array_key_exists($tango_row['type'], $tango_hierarchy))
				$tango_hierarchy[$tango_row['type']] = array($tango_row);
			if (!in_array($tango_row, $tango_hierarchy[$tango_row['type']]))
				$tango_hierarchy[$tango_row['type']][] = $tango_row;
		}
		mysql_free_result($tango_result);

		# Get the Genre associated with this story
		$genre_query = "SELECT genre.genre_id, name FROM story_to_genre, genre WHERE story_to_genre.story_id=$story_id AND genre.genre_id=story_to_genre.genre_id";
		$genre_result =  mysql_query($genre_query) or die('Genre story query failed ' . mysql_error());
		if ($genre_row = mysql_fetch_assoc($genre_result)) {

			$genre_node = add_child($story_dom, $story_info, 'genre', '');

			add_attribute($story_dom, $genre_node, 'id', $genre_row['genre_id']);
			add_attribute($story_dom, $genre_node, 'name', $genre_row['name']);

			if (!array_key_exists($genre_row['genre_id'], $genres)) {
				$genres[$genre_row['genre_id']] = $genre_row;
				$genres_to_stories[$genre_row['genre_id']] = array($story_row);
			} else {
				$genres_to_stories[$genre_row['genre_id']][] = $story_row;
			}
		}
		mysql_free_result($genre_result);
		
		# Save the story's XML file
		$story_dom->formatOutput = true;
		$story_dom->save("stories/$story_id.dfl");

		if (!in_array($story_id, $all_stories)) {
			$all_stories[] = $story_id;
		}

		# Add the story's fieldtrip_id to the list of fieldtrips
		# if it's not already there.

		$fieldtrip_id = $story_row['fieldtrip_id'];

		if ($fieldtrip_id) {
			if (array_key_exists($fieldtrip_id, $fieldtrips)) {
				$fieldtrips[$fieldtrip_id][] = $story_row;
			} else {
				$fieldtrips[$fieldtrip_id] = array($story_row);
			}
		}
		
		mysql_free_result($story_result);
#		mysql_free_result($publication_result);
		mysql_free_result($manuscript_result);

	}

	$places_node = add_child($informant_dom, $informant_info, 'places', '');

	# Life places (birth, death, story collection) for the informant

	foreach (array('birth', 'death', 'confirmation') as $place_type) {
		if ($informant_row[$place_type.'_place_id']) {
			$place_node = add_child($informant_dom, $places_node, 'place', '');
			add_attribute($informant_dom, $place_node, 'type', $place_type.'_place');
			add_child($informant_dom, $place_node, 'place_id', $informant_row[$place_type.'_place_id']);
			add_child($informant_dom, $place_node, 'name', $global_places_list[$informant_row[$place_type.'_place_id']]['name']);
			add_child($informant_dom, $place_node, 'display_name', $global_places_list[$informant_row[$place_type.'_place_id']]['name'] . " (".$place_type." place)");
//			add_child($informant_dom, $place_node, 'latitude', $global_places_list[$informant_row[$place_type.'_place_id']]['latitude']);
//			add_child($informant_dom, $place_node, 'longitude', $global_places_list[$informant_row[$place_type.'_place_id']]['longitude']);
		}
	}

	add_to_global_places($informant_story_places);
	foreach ($informant_story_places as $place_recorded_id) {
		#$place_recorded_node = add_child($informant_dom, $informant_info, 'place', '');
		$place_recorded_node = add_child($informant_dom, $places_node, 'place', '');
		add_attribute($informant_dom, $place_recorded_node, 'type', 'story_place');
		add_child($informant_dom, $place_recorded_node, 'place_id', $place_recorded_id);
		add_child($informant_dom, $place_recorded_node, 'name', $global_places_list[$place_recorded_id]['name']);
		add_child($informant_dom, $place_recorded_node, 'display_name', $global_places_list[$place_recorded_id]['name'] . " (stories recorded here)");
		// PMB don't think it is necessary to know this
//		add_child($informant_dom, $place_recorded_node, 'fieldtrip_id', $story_row['fieldtrip_id']);
//		add_child($informant_dom, $place_recorded_node, 'latitude', $global_places_list[$place_recorded_id]['latitude']);
//		add_child($informant_dom, $place_recorded_node, 'longitude', $global_places_list[$place_recorded_id]['longitude']);
	}

	# Sort by descending number of stories the place is mentioned in
	arsort($informant_places_mentioned);

	add_to_global_places(array_keys($informant_places_mentioned));
	foreach ($informant_places_mentioned as $place_mentioned_id=>$story_count) {
		$story_or_stories = "story";
		if ($story_count > 1)
			$story_or_stories = "stories";

		$place_mentioned_node = add_child($informant_dom, $places_node, 'place', '');
		add_attribute($informant_dom, $place_mentioned_node, 'type', 'place_mentioned');
		add_child($informant_dom, $place_mentioned_node, 'place_id', $place_mentioned_id);
		add_child($informant_dom, $place_mentioned_node, 'name', $global_places_list[$place_mentioned_id]['name']);
		add_child($informant_dom, $place_mentioned_node, 'display_name', $global_places_list[$place_mentioned_id]['name'] . " (mentioned in ". $story_count . " " . $story_or_stories . ")");
		// PMB don't think it is necessary to know this
//		add_child($informant_dom, $place_recorded_node, 'fieldtrip_id', $story_row['fieldtrip_id']);
//		add_child($informant_dom, $place_mentioned_node, 'latitude', $global_places_list[$place_mentioned_id]['latitude']);
//		add_child($informant_dom, $place_mentioned_node, 'longitude', $global_places_list[$place_mentioned_id]['longitude']);
	}

    	if ($informant_id) 
		mysql_free_result($informant_result);

	# Save the individual informant's XML file
	$informant_dom->formatOutput = true;
	$informant_dom->save("informants/$informant_id.dfl");

}

# Save the informant.xml file
$informantlist_dom->formatOutput = true;
$informantlist_dom->save('informants.xml');

# Save the stories.xml file
$storylist_dom->formatOutput = true;
$storylist_dom->save('stories.xml');

# Save the story_texts.xml file
$storytexts_dom->formatOutput = true;
$storytexts_dom->save('story_texts.xml');

# Save the story_search.xml file
$storysearch_dom->formatOutput = true;
$storysearch_dom->save('story_search.xml');

# Save the manuscript_images.xml file
$manuscripts_dom->formatOutput = true;
$manuscripts_dom->save('manuscript_images.xml');

# XML structure for the fieldtrips.xml file
$fieldtrips_dom = new DOMDocument('1.0');
$fieldtrips_info = $fieldtrips_dom->appendChild($fieldtrips_dom->createElement('fieldtrips'));

/* ========================================================================================== */
/* ----------------------------- End of informant-based query loop -------------------------- */
/* ========================================================================================== */

function fix_newlines($text) {

#return str_replace('&#xD;', '', $text);
return str_replace("\r", "", $text);

}

function date_cmp($a, $b) {
# Date format: YYYY-MM-DD

$asplit = split('-', $a);
$bsplit = split('-', $b);

# Compare year first
if ($asplit[0] == $bsplit[0]) {

	# Then month if the years are the same
	if ($asplit[1] == $bsplit[1]) {

		# Then day if the month and year are the same
		if ($asplit[2] == $bsplit[2]) {
			$ret = 0;
		} else {
			$ret = ($asplit[2] > $bsplit[2]) ? 1: -1;
		}

	} else {
		$ret = ($asplit[1] > $bsplit[1]) ? 1: -1;
	}
} else {
	$ret = ($asplit[0] > $bsplit[0]) ? 1: -1;
}

return $ret;
}

$fieldtrips_by_start_date = array();

# This step just makes sure the fieldtrips are listed in chronological order
# by start date in the XML file
foreach (array_keys($fieldtrips) as $fieldtrip_id) {
	
	$fieldtrip_query = "SELECT DISTINCT start_date, fieldtrip_id, fieldtrip_name, end_date, fielddiary_page_start, fielddiary_page_end, shapefile_location FROM fieldtrip where fieldtrip_id=\"$fieldtrip_id\"";

	$fieldtrip_result =  mysql_query($fieldtrip_query) or die('Fieldtrip query failed when sorting ' . mysql_error());
	$fieldtrip_row = mysql_fetch_assoc($fieldtrip_result);

	$fieldtrips_by_start_date[$fieldtrip_row['start_date']] = $fieldtrip_row;

	mysql_free_result($fieldtrip_result);
}

uksort($fieldtrips_by_start_date, "date_cmp");

$last_order_visited = 0;
$last_place_visited = null;
$last_date_visited = null;

foreach ($fieldtrips_by_start_date as $start_date => $fieldtrip_row) {

	$fieldtrip_id = $fieldtrip_row['fieldtrip_id'];

	echo("Working with fieldtrip ID " . $fieldtrip_id . "\n");

	$fieldtrip_detail_query = "SELECT place_id, order_visited, visit_date_start, visit_date_end FROM fieldtrip_detail WHERE fieldtrip_id=$fieldtrip_id";
	$fieldtrip_detail_result =  mysql_query($fieldtrip_detail_query) or die('Fieldtrip detail query failed ' . mysql_error());
	$fieldtrip_detail_row = mysql_fetch_assoc($fieldtrip_detail_result);

	$fieldtrip_node = add_child($fieldtrips_dom, $fieldtrips_info, 'fieldtrip', '');
	add_attribute($fieldtrips_dom, $fieldtrip_node, 'fieldtrip_id', $fieldtrip_id);
	add_child($fieldtrips_dom, $fieldtrip_node, 'fieldtrip_name', $fieldtrip_row['fieldtrip_name']);
	add_child($fieldtrips_dom, $fieldtrip_node, 'start_date', $fieldtrip_row['start_date']);
	add_child($fieldtrips_dom, $fieldtrip_node, 'end_date', $fieldtrip_row['end_date']);
	add_child($fieldtrips_dom, $fieldtrip_node, 'shapefile', $fieldtrip_row['shapefile_location']);
#	add_child($fieldtrips_dom, $fieldtrip_node, 'fielddiary_page_start', $fieldtrip_row['fielddiary_page_start']);
#	add_child($fieldtrips_dom, $fieldtrip_node, 'fielddiary_page_end', $fieldtrip_row['fielddiary_page_end']);

	$places_visited_node = add_child($fieldtrips_dom, $fieldtrip_node, 'places_visited', '');

	# Watch out for duplicate entries - but make sure we're not erroneously
	# ignoring situations in which the informant revisited the same location
	# multiple times on the same day
	while ($fieldtrip_detail_row && !(($fieldtrip_detail_row['order_visited'] == $last_order_visited) && ($fieldtrip_detail_row['place_id'] == $last_place_id) && ($fieldtrip__detail_row['visit_date_start'] == $last_visit_date))) {

		$place_id = $fieldtrip_detail_row['place_id'];

		add_to_global_places(array($place_id));

		$place_visited = $global_places_list[$place_id]['name'];

		$visit_date_start = $fieldtrip_detail_row['visit_date_start'];
		$visit_date_end = $fieldtrip_detail_row['visit_date_end'];

		if (($visit_date_start != "0000-00-00") && ($visit_date_end != "0000-00-00")) {
			if ($visit_date_start == $visit_date_end) {
				$full_name = "$place_visited: $visit_date_start";
			} else {
				$full_name = "$place_visited: $visit_date_start - $visit_date_end";
			}
		} else {
			$full_name = $place_visited;
		}

		$place_node = add_child($fieldtrips_dom, $places_visited_node, 'place', '');
		add_attribute($fieldtrips_dom, $place_node, 'place_id', $place_id);
		add_child($fieldtrips_dom, $place_node, 'order_visited', $fieldtrip_detail_row['order_visited']);
		add_child($fieldtrips_dom, $place_node, 'name', $place_visited);
		add_child($fieldtrips_dom, $place_node, 'full_name', $full_name);
		add_child($fieldtrips_dom, $place_node, 'visit_date_start', $visit_date_start);
		add_child($fieldtrips_dom, $place_node, 'visit_date_end', $visit_date_end);
//		add_child($fieldtrips_dom, $place_node, 'latitude', $global_places_list[$place_id]['latitude']);
//		add_child($fieldtrips_dom, $place_node, 'longitude', $global_places_list[$place_id]['longitude']);

		$last_order_visited = $fieldtrip_detail_row['order_visited'];;
		$last_place_id = $place_id;
		$last_date_visited = $visit_date_start;

		$fieldtrip_detail_row = mysql_fetch_assoc($fieldtrip_detail_result);

	}

	# Add another section to the fieldtrip entry, listing the stories
	# collected during that fieldtrip
	$stories_collected_node = add_child($fieldtrips_dom, $fieldtrip_node, 'stories_collected', '');

	$stories_collected_array = $fieldtrips[$fieldtrip_id];

	foreach ($stories_collected_array as $story_row) {

		add_to_global_places(array($story_row['place_recorded_id']));

		$story_node = add_child($fieldtrips_dom, $stories_collected_node, 'story', '');
		add_attribute($fieldtrips_dom, $story_node, 'story_id', $story_row['story_id']);
		$collected_order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $collected_order_told . ' - ' . $story_row['publication_info'];

		add_child($fieldtrips_dom, $story_node, 'full_name', $story_full_name);
		add_child($fieldtrips_dom, $story_node, 'publication_info', $story_row['publication_info']);
		/*
		$informant_node = add_child($fieldtrips_dom, $story_node, 'informant', $story_row['informant_full_name']);
		add_attribute($fieldtrips_dom, $informant_node, 'id', $story_row['informant_id']);
		#		add_child($fieldtrips_dom, $story_node, 'date_told', $story_row['date_told']);
		add_child($fieldtrips_dom, $story_node, 'order_told', $story_row['order_told']);
		*/
		$place_recorded_node = add_child($fieldtrips_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
		add_attribute($fieldtrips_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
/*		add_child($fieldtrips_dom, $story_node, 'longitude', $global_places_list[$story_row['place_recorded_id']]['longitude']);
		add_child($fieldtrips_dom, $story_node, 'latitude', $global_places_list[$story_row['place_recorded_id']]['latitude']);
*/
	}
	mysql_free_result($fieldtrip_detail_result);

	/* Add an informant entry for every person visited on the fieldtrip */
	$informantlist_node = add_child($fieldtrips_dom, $fieldtrip_node, 'people_visited', '');
	$informant_location_query = "SELECT DISTINCT person_id, first_name, last_name, nickname, title, gender, core_informant, place_id, name, latitude, longitude FROM person, place, story, story_to_informant WHERE story.fieldtrip_id=".$fieldtrip_id." AND story.story_id=story_to_informant.story_id AND story_to_informant.informant_id=person.person_id AND story.place_recorded_id=place.place_id";
	$informant_location_result =  mysql_query($informant_location_query) or die('Informant location query failed ' . mysql_error());
	$informant_row = mysql_fetch_assoc($informant_location_result);
	while ($informant_row) {
		$informant_node = add_child($fieldtrips_dom, $informantlist_node, 'person', '');
		$informant_id = $informant_row['person_id'];
		add_attribute($fieldtrips_dom, $informant_node, 'person_id', $informant_id);

		if ($informant_row['nickname']) {
			$full_name = $informant_row['first_name'] . ' (' . $informant_row['nickname'] . ') ' . $informant_row['last_name'];
		} else {
			$full_name = $informant_row['first_name'] . ' ' . $informant_row['last_name'];
		}
		if ($informant_row['title']) {
			$full_name = $informant_row['title'] . ' ' . $full_name;
		}
		add_child($fieldtrips_dom, $informant_node, 'full_name', $full_name);
		add_child($fieldtrips_dom, $informant_node, 'last_name', $informant_row['last_name']);
		add_child($fieldtrips_dom, $informant_node, 'first_name', $informant_row['first_name']);
		add_child($fieldtrips_dom, $informant_node, 'gender', $informant_row['gender']);
		add_child($fieldtrips_dom, $informant_node, 'core_informant', $informant_row['core_informant']);
		# For this to work, all of the informants' images have to be moved into
		# their respective folders, and the images must be named [NUMBER].jpg
		if (file_exists("informants/$informant_id.jpg"))
			add_child($fieldtrips_dom, $informant_node, 'image', "data/informants/$informant_id.jpg");
		add_child($fieldtrips_dom, $informant_node, 'url', "data/informants/$informant_id.dfl");

		$informant_location_node = add_child($fieldtrips_dom, $informant_node, 'place_visited', '');
		$place_visited_id = $informant_row['place_id'];	
		if ($place_visited_id) {
			add_to_global_places(array($place_visited_id));
	
			add_attribute($fieldtrips_dom, $informant_location_node, 'place_id', $place_visited_id);
			add_child($fieldtrips_dom, $informant_location_node, 'name', $global_places_list[$place_visited_id]['name']);
//			add_child($fieldtrips_dom, $informant_location_node, 'latitude', $global_places_list[$place_visited_id]['latitude']);
//			add_child($fieldtrips_dom, $informant_location_node, 'longitude', $global_places_list[$place_visited_id]['longitude']);
		}

		$informant_row = mysql_fetch_assoc($informant_location_result);
	}
	mysql_free_result($informant_location_result);
}

/* Handle the 'All' fieldtrips entry (needed for the Flex fieldtrips toolbar)
 * as a special case. */
$fieldtrip_node = add_child($fieldtrips_dom, $fieldtrips_info, 'fieldtrip', '');
add_attribute($fieldtrips_dom, $fieldtrip_node, 'fieldtrip_id', '-1');
add_child($fieldtrips_dom, $fieldtrip_node, 'fieldtrip_name', 'All');

# Save fieldtrips.xml
$fieldtrips_dom->formatOutput = true;
$fieldtrips_dom->save('fieldtrips.xml');

# XML structure for the stories_collected.xml file
$stories_collected_dom = new DOMDocument('1.0');
$stories_collected_info = $stories_collected_dom->appendChild($stories_collected_dom->createElement('stories_collected'));

echo("Creating stories collected file\n");

foreach ($stories_collected_list as $place_id => $story_rows_array) {

	$num_stories = sizeof($story_rows_array);
	$story_or_stories = "stories";
	if (sizeof($story_rows_array) == 1)
		$story_or_stories = "story";

	$place_node = add_child($stories_collected_dom, $stories_collected_info, 'place', '');
	add_attribute($stories_collected_dom, $place_node, 'place_id', $place_id);
	add_child($stories_collected_dom, $place_node, 'name', $global_places_list[$place_id]['name']);
	add_child($stories_collected_dom, $place_node, 'display_name', $global_places_list[$place_id]['name'] . ": " . $num_stories . " " . $story_or_stories . " collected here");
	add_child($stories_collected_dom, $place_node, 'latitude', $global_places_list[$place_id]['latitude']);
	add_child($stories_collected_dom, $place_node, 'longitude', $global_places_list[$place_id]['longitude']);

	$stories_node = add_child($stories_collected_dom, $place_node, 'stories', '');
	foreach ($story_rows_array as $story_row) {

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		$story_node = add_child($stories_collected_dom, $stories_node, 'story', '');
		add_attribute($stories_collected_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($stories_collected_dom, $story_node, 'publication_info', $story_row['publication_info']);
		add_attribute($stories_collected_dom, $story_node, 'fieldtrip_id', $story_row['fieldtrip_id']);
		$informant_node = add_child($stories_collected_dom, $story_node, 'informant', $story_row['informant_full_name']);
		add_attribute($stories_collected_dom, $informant_node, 'id', $story_row['informant_id']);
		add_child($stories_collected_dom, $story_node, 'full_name', $story_full_name);
	}
}

# Save stories_collected.xml
$stories_collected_dom->formatOutput = true;
$stories_collected_dom->save('stories_collected.xml');

# XML structure for the places_mentioned.xml file
$places_mentioned_dom = new DOMDocument('1.0');
$places_mentioned_info = $places_mentioned_dom->appendChild($places_mentioned_dom->createElement('places_mentioned'));

echo("Creating places mentioned file\n");
foreach ($places_mentioned_list as $place_id => $story_rows_array) {

	$num_stories = sizeof($story_rows_array);
	$story_or_stories = "stories";
	if (sizeof($story_rows_array) == 1)
		$story_or_stories = "story";

	$place_node = add_child($places_mentioned_dom, $places_mentioned_info, 'place', '');
	add_attribute($places_mentioned_dom, $place_node, 'place_id', $place_id);
	add_child($places_mentioned_dom, $place_node, 'name', $global_places_list[$place_id]['name']);
	add_child($places_mentioned_dom, $place_node, 'display_name', $global_places_list[$place_id]['name'] . ": mentioned in " . $num_stories . " " . $story_or_stories);
	add_child($places_mentioned_dom, $place_node, 'latitude', $global_places_list[$place_id]['latitude']);
	add_child($places_mentioned_dom, $place_node, 'longitude', $global_places_list[$place_id]['longitude']);

	$stories_node = add_child($places_mentioned_dom, $place_node, 'stories', '');
	foreach ($story_rows_array as $story_row) {

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		$story_node = add_child($places_mentioned_dom, $stories_node, 'story', '');
		add_attribute($places_mentioned_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($places_mentioned_dom, $story_node, 'publication_info', $story_row['publication_info']);
		add_attribute($places_mentioned_dom, $story_node, 'fieldtrip_id', $story_row['fieldtrip_id']);
		$informant_node = add_child($places_mentioned_dom, $story_node, 'informant', $story_row['informant_full_name']);
		add_attribute($places_mentioned_dom, $informant_node, 'id', $story_row['informant_id']);
		add_child($places_mentioned_dom, $story_node, 'full_name', $story_full_name);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($places_mentioned_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($places_mentioned_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
		}
	}
}

# Save places_mentioned.xml
$places_mentioned_dom->formatOutput = true;
$places_mentioned_dom->save('places_mentioned.xml');

#print_r($global_places_list);

/* For every place in the global places list, do a database query to get its
 * location info and add this data to the global places.xml file. */
$places_fp = fopen('places.xml', 'w');
$places_dom = new DOMDocument('1.0');
$places_info = $places_dom->appendChild($places_dom->createElement('places'));

echo("Creating global places file\n");

# Sort by Danish alphabetical collation
uasort($global_place_names, "strcoll"); 

#print_r($global_place_names);

#foreach ($global_places_list as $place_id => $place_row) {

foreach ($global_place_names as $place_id => $place_name) {

	if ($place_id == 'N/A')
		continue;

	$place_row = $global_places_list[$place_id];

	$place_node = add_child($places_dom, $places_info, 'place', '');

	add_attribute($places_dom, $place_node, 'place_id', $place_id);
	add_child($places_dom, $place_node, 'name', $place_row['name']);
	add_child($places_dom, $place_node, 'latitude', $place_row['latitude']);
	add_child($places_dom, $place_node, 'longitude', $place_row['longitude']);

	$place_people = array();
	$place_people_relations = array();

	$informant_location_query = "SELECT DISTINCT person_id, last_name, first_name, middle_name, nickname, gender, core_informant, title FROM person, story, story_to_informant WHERE story.place_recorded_id=".$place_id." AND story.story_id=story_to_informant.story_id AND story_to_informant.informant_id=person.person_id";
	$informant_location_result =  mysql_query($informant_location_query) or die('Informant location query failed ' . mysql_error());
	$informant_row = mysql_fetch_assoc($informant_location_result);

	while ($informant_row) {

		$place_people[$informant_row['person_id']] = $informant_row;
		$place_people_relations[$informant_row['person_id']] = "told stories here";

		$informant_row = mysql_fetch_assoc($informant_location_result);
	}
	mysql_free_result($informant_location_result);

	$life_places_query = "SELECT DISTINCT person_id, last_name, first_name, middle_name, nickname, gender, core_informant, title FROM person WHERE birth_place_id=$place_id OR death_place_id=$place_id OR confirmation_place_id = $place_id";
	$life_places_result =  mysql_query($life_places_query) or die('Life places query failed ' . mysql_error());
	$informant_row = mysql_fetch_assoc($life_places_result);

	while ($informant_row) {

		if (!array_key_exists($informant_row['person_id'], $place_people)) {

			$place_people[$informant_row['person_id']] = $informant_row;
			$place_people_relations[$informant_row['person_id']] = "lived here";
		} else {
			$place_people_relations[$informant_row['person_id']] .= ", lived here";
		}
		$informant_row = mysql_fetch_assoc($life_places_result);
	}
	mysql_free_result($life_places_result);

	$people_node = add_child($places_dom, $place_node, 'people', '');

	foreach ($place_people as $person_id => $informant_row) {

		$person_node = add_child($places_dom, $people_node, 'person', '');

		if ($informant_row['nickname']) {
			$full_name = $informant_row['first_name'] . ' (' . $informant_row['nickname'] . ') ' . $informant_row['last_name'];
		} else {
			$full_name = $informant_row['first_name'] . ' ' . $informant_row['last_name'];
		}
		if ($informant_row['title']) {
			$full_name = $informant_row['title'] . ' ' . $full_name;
		}

		if (!$informant_row['gender'])
			$informant_row['gender'] = 'N/A';

		add_attribute($places_dom, $person_node, 'person_id', $person_id);
		add_child($places_dom, $person_node, 'relationship', $place_people_relations[$person_id]);
		add_child($places_dom, $person_node, 'full_name', $full_name);
		add_child($places_dom, $person_node, 'last_name', $informant_row['last_name']);
		add_child($places_dom, $person_node, 'first_name', $informant_row['first_name']);
		add_child($places_dom, $person_node, 'gender', $informant_row['gender']);
		add_child($places_dom, $person_node, 'core_informant', $informant_row['core_informant']);
		if (file_exists("informants/$person_id.jpg"))
			add_child($places_dom, $person_node, 'image', "data/informants/$person_id.jpg");
		add_child($places_dom, $person_node, 'url', "data/informants/$person_id.dfl");

		$residence_node = add_child($places_dom, $person_node, 'residence_place', '');
		if (array_key_exists($person_id, $informant_residence)) {
			$residence_place_id = $informant_residence[$person_id];
			if ($residence_place_id) {
				add_attribute($places_dom, $residence_node, 'place_id', $residence_place_id);
				add_child($places_dom, $residence_node, 'name', $global_places_list[$residence_place_id]['name']);
//				add_child($places_dom, $residence_node, 'latitude', $global_places_list[$residence_place_id]['latitude']);
//				add_child($places_dom, $residence_node, 'longitude', $global_places_list[$residence_place_id]['longitude']);
			}
		}
	}

	$fieldtrips_node = add_child($places_dom, $place_node, 'fieldtrips', '');

	$associated_fieldtrips = array();

	$route_fieldtrips_query = "SELECT DISTINCT fieldtrip.fieldtrip_id, fieldtrip.start_date FROM fieldtrip, fieldtrip_detail WHERE fieldtrip.fieldtrip_id=fieldtrip_detail.fieldtrip_id AND fieldtrip_detail.place_id=$place_id";

	$route_fieldtrips_result =  mysql_query($route_fieldtrips_query) or die('Route fieldtrips query failed ' . mysql_error());
	$fieldtrip_row = mysql_fetch_assoc($route_fieldtrips_result);

	while ($fieldtrip_row) {
		$associated_fieldtrips[$fieldtrip_row['start_date']] = $fieldtrip_row['fieldtrip_id'];
		$fieldtrip_row = mysql_fetch_assoc($route_fieldtrips_result);
	}
	mysql_free_result($route_fieldtrips_result);

	$story_fieldtrips_query = "SELECT DISTINCT fieldtrip.fieldtrip_id, fieldtrip.start_date FROM fieldtrip, story WHERE fieldtrip.fieldtrip_id=story.fieldtrip_id AND story.place_recorded_id=$place_id";
	$story_fieldtrips_result =  mysql_query($story_fieldtrips_query) or die('Story fieldtrips query failed ' . mysql_error());
	$fieldtrip_row = mysql_fetch_assoc($story_fieldtrips_result);

	while ($fieldtrip_row) {
		if (!in_array($fieldtrip_row['fieldtrip_id'], $associated_fieldtrips)) {
			$associated_fieldtrips[$fieldtrip_row['start_date']] = $fieldtrip_row['fieldtrip_id'];
		}
		$fieldtrip_row = mysql_fetch_assoc($story_fieldtrips_result);
	}
	mysql_free_result($story_fieldtrips_result);

	uksort($associated_fieldtrips, "date_cmp");

	foreach ($associated_fieldtrips as $fieldtrip_start_date=>$fieldtrip_id) {
		add_child($places_dom, $fieldtrips_node, 'fieldtrip_id', $fieldtrip_id);
	}

}

$places_dom->formatOutput = true;
$places_dom->save('places.xml');

echo "Creating global keywords file\n";

#$keywords_fp = fopen('keywords.xml', 'w');
$keywords_dom = new DOMDocument('1.0');
$keywords_info = $keywords_dom->appendChild($keywords_dom->createElement('keywords'));

asort($keywords);

foreach ($keywords as $keyword_id=>$keyword_row) {

	$keyword_node = add_child($keywords_dom, $keywords_info, 'keyword', '');
	add_attribute($keywords_dom, $keyword_node, 'keyword_id', $keyword_id);
	add_child($keywords_dom, $keyword_node, 'keyword_name', $keyword_row['keyword']);
	add_child($keywords_dom, $keyword_node, 'display_string', $keyword_row['display_string']);

	$stories_node = add_child($keywords_dom, $keyword_node, 'stories', '');

	$keyword_places = array();

	foreach ($keywords_to_stories[$keyword_id] as $story_row) {
		$story_node = add_child($keywords_dom, $stories_node, 'story', '');

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_attribute($keywords_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($keywords_dom, $story_node, 'publication_info', $story_row['publication_info']);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($keywords_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($keywords_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
		}
		add_child($keywords_dom, $story_node, 'full_name', $story_full_name);

		if (!array_key_exists($story_row['place_recorded_id'], $keyword_places))
			$keyword_places[$story_row['place_recorded_id']] = 1;
		else
			$keyword_places[$story_row['place_recorded_id']]++;
	}

	$places_node = add_child($keywords_dom, $keyword_node, 'places', '');

	foreach (array_keys($keyword_places) as $keyword_place_id) {

		if ($keyword_place_id) {
			$place_node = add_child($keywords_dom, $places_node, 'place', '');
			add_attribute($keywords_dom, $place_node, 'place_id', $keyword_place_id);
			add_child($keywords_dom, $place_node, 'name', $global_places_list[$keyword_place_id]['name']);
			/*
			add_child($keywords_dom, $place_node, 'latitude', $global_places_list[$keyword_place_id]['latitude']);
			add_child($keywords_dom, $place_node, 'longitude', $global_places_list[$keyword_place_id]['longitude']);
			*/
			add_child($keywords_dom, $place_node, 'times_associated', $keyword_places[$keyword_place_id]);
		}
	}
}

$keywords_dom->formatOutput = true;
$keywords_dom->save('keywords.xml');

echo "Creating global ETK indices file\n";

#$etk_fp = fopen('etk_indices.xml', 'w');
$etk_dom = new DOMDocument('1.0');
$etk_info = $etk_dom->appendChild($etk_dom->createElement('etk_indices'));

asort($etk_indices);

foreach ($etk_indices as $etk_index_id=>$etk_row) {

	$index_node = add_child($etk_dom, $etk_info, 'etk_index', '');
	add_attribute($etk_dom, $index_node, 'id', $etk_index_id);
	add_child($etk_dom, $index_node, 'heading_danish', $etk_row['heading_danish']);
	add_child($etk_dom, $index_node, 'heading_english', $etk_row['heading_english']);

	$stories_node = add_child($etk_dom, $index_node, 'stories', '');

	$index_places = array();

	foreach ($etk_indices_to_stories[$etk_index_id] as $story_row) {
		$story_node = add_child($etk_dom, $stories_node, 'story', '');

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_attribute($etk_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($etk_dom, $story_node, 'publication_info', $story_row['publication_info']);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($etk_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($etk_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
		}
		add_child($etk_dom, $story_node, 'full_name', $story_full_name);

		if (!array_key_exists($story_row['place_recorded_id'], $index_places))
			$index_places[$story_row['place_recorded_id']] = 1;
		else
			$index_places[$story_row['place_recorded_id']]++;
	}

	$places_node = add_child($etk_dom, $index_node, 'places', '');

	foreach (array_keys($index_places) as $index_place_id) {

		if ($index_place_id) {
			$place_node = add_child($etk_dom, $places_node, 'place', '');
			add_attribute($etk_dom, $place_node, 'place_id', $index_place_id);
			add_child($etk_dom, $place_node, 'name', $global_places_list[$index_place_id]['name']);
			/*
			add_child($etk_dom, $place_node, 'latitude', $global_places_list[$index_place_id]['latitude']);
			add_child($etk_dom, $place_node, 'longitude', $global_places_list[$index_place_id]['longitude']);
			*/
			add_child($etk_dom, $place_node, 'times_associated', $index_places[$index_place_id]);
		}
	}
}

$etk_dom->formatOutput = true;
$etk_dom->save('etk_indices.xml');

asort($tango_indices);

echo "Creating global Tango indices file\n";

#$etk_fp = fopen('tango_indices.xml', 'w');
$tango_dom = new DOMDocument('1.0');

$tango_info = $tango_dom->appendChild($tango_dom->createElement('tango_indices'));

$parents_to_stories = array();
$parents_to_places = array();

foreach ($tango_indices as $tango_index_id=>$tango_row) {

	if (!array_key_exists($tango_row['type'], $parents_to_stories)) {
		$parents_to_stories[$tango_row['type']] = array();
		$parents_to_places[$tango_row['type']] = array();
	}

	$index_node = add_child($tango_dom, $tango_info, 'tango_index', '');
	add_attribute($tango_dom, $index_node, 'id', $tango_index_id);
	add_child($tango_dom, $index_node, 'type', $tango_row['type']);
	add_child($tango_dom, $index_node, 'name', $tango_row['name']);

	$stories_node = add_child($tango_dom, $index_node, 'stories', '');

	$index_places = array();

	foreach ($tango_indices_to_stories[$tango_index_id] as $story_row) {

		if (!array_key_exists($story_row['story_id'], $parents_to_stories[$tango_row['type']]))
			$parents_to_stories[$tango_row['type']][] = $story_row;

		$story_node = add_child($tango_dom, $stories_node, 'story', '');

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_attribute($tango_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($tango_dom, $story_node, 'publication_info', $story_row['publication_info']);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($tango_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($tango_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
		}
		add_child($tango_dom, $story_node, 'full_name', $story_full_name);

		if (!array_key_exists($story_row['place_recorded_id'], $index_places))
			$index_places[$story_row['place_recorded_id']] = 1;
		else
			$index_places[$story_row['place_recorded_id']]++;
	}

	$places_node = add_child($tango_dom, $index_node, 'places', '');

	foreach (array_keys($index_places) as $index_place_id) {

		if ($index_place_id) {
			
			$place_node = add_child($tango_dom, $places_node, 'place', '');
			add_attribute($tango_dom, $place_node, 'place_id', $index_place_id);
			add_child($tango_dom, $place_node, 'name', $global_places_list[$index_place_id]['name']);
			add_child($tango_dom, $place_node, 'times_associated', $index_places[$index_place_id]);
		
		}
	}
}

foreach (array_keys($parents_to_stories) as $tango_type) {

	$parent_collection_places = array();

	$index_node = add_child($tango_dom, $tango_info, 'tango_index', '');
	add_attribute($tango_dom, $index_node, 'id', "-1");
	add_child($tango_dom, $index_node, 'type', $tango_type);

	$stories_node = add_child($tango_dom, $index_node, 'stories', '');

	foreach ($parents_to_stories[$tango_type] as $story_row) {

		$story_node = add_child($tango_dom, $stories_node, 'story', '');

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_attribute($tango_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($tango_dom, $story_node, 'publication_info', $story_row['publication_info']);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($tango_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($tango_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
			if (!array_key_exists($story_row['place_recorded_id'], $parent_collection_places))
				$parent_collection_places[$story_row['place_recorded_id']] = 1;
			else
				$parent_collection_places[$story_row['place_recorded_id']]++;
		}
		add_child($tango_dom, $story_node, 'full_name', $story_full_name);
	}
	
	$places_node = add_child($tango_dom, $index_node, 'places', '');

	foreach ($parent_collection_places as $index_place_id=>$times_associated) {

		if ($index_place_id) {
			$place_node = add_child($tango_dom, $places_node, 'place', '');
			add_attribute($tango_dom, $place_node, 'place_id', $index_place_id);
			add_child($tango_dom, $place_node, 'name', $global_places_list[$index_place_id]['name']);
			add_child($tango_dom, $place_node, 'times_associated', $times_associated);
		}
	}
}

$tango_dom->formatOutput = true;
$tango_dom->save('tango_indices.xml');

function tango_row_cmp($row_a, $row_b) {
	if ($row_a['name'] <= $row_b['name'])
		return -1;
	else
		return 1;
}

echo "Creating global Tango tree file\n";

#$etk_fp = fopen('tango_indices.xml', 'w');
$tree_dom = new DOMDocument('1.0');

$tango_tree = $tree_dom->appendChild($tree_dom->createElement('tango_tree'));
$current_type = "";

$tree_root = add_child($tree_dom, $tango_tree, 'node', '');
add_attribute($tree_dom, $tree_root, 'label', 'indices');

ksort($tango_hierarchy);

foreach ($tango_hierarchy as $type=>$tango_rows) {
	$parent_node = add_child($tree_dom, $tree_root, 'node', '');
	add_attribute($tree_dom, $parent_node, 'label', $type);
	add_attribute($tree_dom, $parent_node, 'level', 'parent');

	usort($tango_rows, "tango_row_cmp");

	foreach ($tango_rows as $tango_row) {
		$child_node = add_child($tree_dom, $parent_node, 'node', '');
		add_attribute($tree_dom, $child_node, 'id', $tango_row['tango_index_id']);
		add_attribute($tree_dom, $child_node, 'label', $tango_row['name']);
		add_attribute($tree_dom, $child_node, 'level', 'child');
	}
}
/*
foreach ($tango_indices as $tango_index_id=>$tango_row) {

	if ($tango_row['type'] != $current_type) {
		$current_type = $tango_row['type'];
		$parent_node = add_child($tree_dom, $tree_root, 'node', '');
		add_attribute($tree_dom, $parent_node, 'label', $tango_row['type']);
		add_attribute($tree_dom, $parent_node, 'level', 'parent');
	}
	$child_node = add_child($tree_dom, $parent_node, 'node', '');
	add_attribute($tree_dom, $child_node, 'id', $tango_index_id);
	add_attribute($tree_dom, $child_node, 'label', $tango_row['name']);
	add_attribute($tree_dom, $child_node, 'level', 'child');

}
*/
$tree_dom->formatOutput = true;
$tree_dom->save('tango_tree.xml');

echo "Creating global Genres file\n";

$genres_dom = new DOMDocument('1.0');
$genres_info = $genres_dom->appendChild($genres_dom->createElement('genres'));

asort($genres);

foreach ($genres as $genre_id=>$genre_row) {

	$genre_node = add_child($genres_dom, $genres_info, 'genre', '');
	add_attribute($genres_dom, $genre_node, 'id', $genre_id);
	add_child($genres_dom, $genre_node, 'name', $genre_row['name']);

	$stories_node = add_child($genres_dom, $genre_node, 'stories', '');

	$genre_places = array();

	foreach ($genres_to_stories[$genre_id] as $story_row) {
		$story_node = add_child($genres_dom, $stories_node, 'story', '');

		$order_told = sprintf("%.2f", $story_row['order_told']);
		$story_full_name = $story_row['informant_id'] . ' - ' . $order_told . ' - ' . $story_row['publication_info'];
		add_attribute($genres_dom, $story_node, 'story_id', $story_row['story_id']);
		add_child($genres_dom, $story_node, 'publication_info', $story_row['publication_info']);
		if ($story_row['place_recorded_id']) {
			$place_recorded_node = add_child($genres_dom, $story_node, 'place_recorded', $global_places_list[$story_row['place_recorded_id']]['name']);
			add_attribute($genres_dom, $place_recorded_node, 'id', $story_row['place_recorded_id']);
		}
		add_child($genres_dom, $story_node, 'full_name', $story_full_name);

		if (!array_key_exists($story_row['place_recorded_id'], $genre_places))
			$genre_places[$story_row['place_recorded_id']] = 1;
		else
			$genre_places[$story_row['place_recorded_id']]++;
	}

	$places_node = add_child($genres_dom, $genre_node, 'places', '');

	foreach (array_keys($genre_places) as $genre_place_id) {

		if ($genre_place_id) {
			$place_node = add_child($genres_dom, $places_node, 'place', '');
			add_attribute($genres_dom, $place_node, 'place_id', $genre_place_id);
			add_child($genres_dom, $place_node, 'name', $global_places_list[$genre_place_id]['name']);
			add_child($genres_dom, $place_node, 'times_associated', $genre_places[$genre_place_id]);
		}
	}
}

$genres_dom->formatOutput = true;
$genres_dom->save('genres.xml');

asort($tango_indices);

echo "Creating global Tango indices file\n";

#$etk_fp = fopen('tango_indices.xml', 'w');
$tango_dom = new DOMDocument('1.0');

$tango_info = $tango_dom->appendChild($tango_dom->createElement('tango_indices'));

/*
echo "Writing story##.html files\n";
# Create the html_stories directory, if it doesn't already exist
if (!(is_dir("html_stories"))) {
	mkdir("html_stories", 0755);
}
foreach ($all_stories as $story_id) {

	$f1 = fopen("index_firsthalf.html", 'r');
	$f2 = fopen("index_secondhalf.html", 'r');

	$f1text = fread($f1, filesize("index_firsthalf.html"));
	$f2text = fread($f2, filesize("index_secondhalf.html"));

	$storyfile = fopen("html_stories/story".$story_id.".html", 'w');

	$storytext = trim($f1text) . "?storyID=".$story_id . trim($f2text);

	fwrite($storyfile, $storytext);

	fclose($f1);
	fclose($f2);
	fclose($storyfile);
}
*/
mysql_close($dblink);
?>
