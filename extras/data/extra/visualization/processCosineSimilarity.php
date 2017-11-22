<?php

$matrixFile = file_get_contents('DFLmatrix.txt');

$cosineMatrix = explode("\n", $matrixFile);

$labelsRow = $cosineMatrix[0];

$labels = explode(' ', $labelsRow);

$storyIDs = array();
$storyID = 0;
$relatedStoryID = 0;
$rowToStoryID = array();
$storyIDtoLabel = array();
$rowNumber = 1;

$smallestValue = 1;
$largestValue = 0;

$distanceValues = array();

foreach ($labels as $label) {
    $labelParts = explode('-', $label);
    $storyID = $labelParts[0] + 0;

    $rowToStoryID[$rowNumber] = $storyID;

    echo $rowNumber . " => " . $storyID . "\n";    

    $storyIDtoLabel[$storyID] = $label;
    $rowNumber++;
}

for ($rowNumber=1; $rowNumber<(sizeof($cosineMatrix)-1); $rowNumber++) {

    $row = $cosineMatrix[$rowNumber];

    $storyID = $rowToStoryID[$rowNumber];

    $columns = explode(' ', $row);

    for ($colNumber=0; $colNumber<sizeof($columns); $colNumber++) {

        $relatedStoryID = $rowToStoryID[$colNumber+1];

        $value = $columns[$colNumber];

        if (($value > .2) && ($value != 1)) {
#            print $storyIDtoLabel{$storyID} . "->" . $storyIDtoLabel{$relatedStoryID} . ": " . $value . "\n";
            $distanceValues[] = $value;
            if ($value < $smallestValue) {
                $smallestValue = $value;
            }
            if ($value > $largestValue) {
                $largestValue = $value;
            }
            file_put_contents('DFLsimilarity.txt', $value . " ", FILE_APPEND);
        }

    }
}
echo "Size of nonzero, non-identity similarity list: " . sizeof($distanceValues) . "\n";
echo "Max similarity value: " . $largestValue . "\n";
echo "Min similarity value: " . $smallestValue . "\n";
