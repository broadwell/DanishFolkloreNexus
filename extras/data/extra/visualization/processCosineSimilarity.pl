#!/usr/bin/perl -w

use strict;
use warnings;
use utf8;
use POSIX qw(locale_h);

setlocale(LC_CTYPE, "is_IS.UTF-8");

binmode(STDOUT=>':encoding(UTF-8)');

open(MATRIXFILE, '<:encoding(UTF-8)', 'DFLmatrix.txt');
my @cosineMatrix = <MATRIXFILE>;
close(MATRIXFILE);

open(OUTFILE, '>:encoding(UTF-8)', 'DFLsimilarity.txt');

my $labelsRow = $cosineMatrix[0];

my @labels = split(/\s/, $labelsRow);

my @storyIDs;
my $storyID = 0;
my $relatedStoryID = 0;
my %rowToStoryID;
my %storyIDtoLabel;
my $rowNumber = 0;

my $smallestValue = 1;
my $largestValue = 0;

my @distanceValues;

foreach my $label (@labels) {
    my @labelParts = split(/-/, $label);
    $storyID = $labelParts[0] + 0;

    $rowToStoryID{$rowNumber} = $storyID;
    $storyIDtoLabel{$storyID} = $label;
    $rowNumber++;
}

#print "labelsRow is " . $labelsRow . "\n";

for ($rowNumber=1; $rowNumber<=$#cosineMatrix; $rowNumber++) {
#for ($rowNumber=1; $rowNumber<=1; $rowNumber++) {

    my $row = $cosineMatrix[$rowNumber];

    $storyID = $rowToStoryID{$rowNumber-1};

    my @columns = split(/\s/, $row);

    for (my $colNumber=0; $colNumber<=$#columns; $colNumber++) {

        $relatedStoryID = $rowToStoryID{$colNumber};

        my $value = $columns[$colNumber];

        if (($value > .2) && ($value != 1)) {
#            print $storyIDtoLabel{$storyID} . "->" . $storyIDtoLabel{$relatedStoryID} . ": " . $value . "\n";
            push (@distanceValues, $value);
            if ($value < $smallestValue) {
                $smallestValue = $value;
            }
            if ($value > $largestValue) {
                $largestValue = $value;
            }
            print(OUTFILE $value . " ");
        }

    }
}
close(OUTFILE);
print "Size of nonzero, non-identity similarity list: " . ($#distanceValues + 1) . "\n";
print "Max similarity value: " . $largestValue . "\n";
print "Min similarity value: " . $smallestValue . "\n";
