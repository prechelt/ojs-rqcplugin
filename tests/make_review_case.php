<?php
/*
 * NO LONGER NEEDED AND DOES NOT WORK. See DevHelperHandler instead.
 *
 * Helper for manual testing, to be called from the command line like this:
 * alias mrc='php -f plugins/reviewqualitycollector/tests/make_review_case.php --'
 * mrc -s"submission title" -a"authorlastname" -e"editorlastname" -r"reviewer1lastname"...
 * Creates a submission object assigns existing authors, editors, reviewers, gives each reviewer a
 * default pseudo-review.
 * There are useful defaults as shown in the respective parts below.
 */

require('tools/bootstrap.inc.php');
import('classes.journal.Journal');
import('classes.journal.JournalDAO');



//----- Initialize variables:

$authors = array();
$editors = array();
$reviewers = array();

//----- Read arguments:

foreach (array_values(array_slice($argv, 1)) as $i => $arg) {
	printf("%d: %s\n", $i, $arg);
	$opt = substr($arg, 0, 2);  // the "-r" part etc.
	$val = substr($arg, 2);  // the remainder after the "-x" part
	switch ($opt) {
		case "-j":
			$journal_id = $val;
			break;
		case "-s":
			$submissiontitle = $val;
			break;
		case "-a":
			$authors[] = $val;
			break;
		case "-e":
			$editors[] = $val;
			break;
		case "-r":
			$reviewers[] = $val;
			break;
		default:
			printf("ERROR: unknown option: %s\n", $opt);
	}

}

//----- Defaults:

if (!isset($submissiontitle))
	$submissiontitle = "MRC submission " . date("H:i:s");
if (count($authors) == 0)
	$authors[] = "Author1";
if (count($editors) == 0)
	$editors[] = "Editor1";
if (count($reviewers) == 0)
	$reviewers[] = "Reviewer1";

//----- Report what you found:
printf("%s: %s\n   Editors %s\n   Reviewers %s\n\n",
	implode(", ", $authors), $submissiontitle,
	implode(", ", $editors),
	implode(", ", $reviewers));

//----- Access DAOs:

$journalDao = DAORegistry::getDAO('JournalDAO');
$articleDao = DAORegistry::getDAO('ArticleDAO');
$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
$reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO');
$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
$userDao = DAORegistry::getDAO('UserDAO');
$userGroupDao = DAORegistry::getDAO('UserGroupDAO');


//----- Retrieve data objects:

if(!isset($journal_id)) {
	$all_journals = $journalDao->getTitles();
	printf("ERROR: journal_id (-j) missing. Available: \n%s\n", $all_journals);
}

// $editor = $userDao->get


// TODO
