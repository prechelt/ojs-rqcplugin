# Review Quality Collector (RQC) plugin for OJS
created 2017-08-30, Lutz Prechelt

Version 2018-10-16 
Status: unfinished, not yet usable

## What it is

Review Quality Collector (RQC) is an initiative for improving the quality of 
scientific peer review. 
Its core is a mechanism that supplies a reviewer with a receipt for 
their work for each journal year.
The receipt is based on grading each review according to a journal-specific
review quality definition.

This plugin is an OJS adapter for the RQC API, by which OJS
reports the reviewing data of individual article
submissions to RQC so that RQC can arrange the grading and add the
reviews to the respective reviewers' receipts.

Find the RQC API description at
https://reviewqualitycollector.org/t/api


## How it works

- extends the journal master data forms by two additional fields: 
  `rqcJournalId` and `rqcJournalKey`.
- If both are filled, they are checked against RQC 
  whether they are a valid pair.
- If they are accepted, they are stored as additional JournalSettings.
- If these settings exist, the plugin will add a menu entry
  by which editors can submit the reviewing data for a given
  submission to RQC in order to trigger the grading.
  This step is optional for the editors.
  Depending on how RQC is configured for that journal, the given
  editor may then be redirected to RQC to perform (or not)
  a grading rightaway. 
- The plugin will also intercept the acceptance-decision-making
  event and send the decision and reviewing data for that submission
  to RQC then.
  
-------------------------------------

## Development notes: TO DO

- add the journal ID/key validation via an RQC call
- add all hooks and actual activity

Steps towards the latter:
- assign editor2 (ER2 Prechelt)
- assign reviewers editor2 (ER2 Prechelt, open) and reviewer1 (R1 Prechelt, double-blind)
- submit reviews (editor2 minor, reviewer1 accept)
- create debug-only RQC plugin page (to show request content)
- write logic to find the submission process object
- create JSON response
- find out and add the various fields
- create a second round of submission and reviews, test with that
- write automated tests 
- elaborate on "ask your publisher" in locale.xml


## Development notes: OJS data model (the relevant parts)

RQC speaks of Journals, Submissions (i.e. articles), Authors, 
Reviewers, Reviews, Editors, EditorAssignments (which Editor has which
role for which Submission). 
Authors, Editors, and Reviewers are all Persons.

This is how these concepts are represented in OJS (class names,
other typical identifiers for such objects).
Most classes have a corresponding DAO (data access object, as the ORM). 
Accessing objects often involves
retrieving them (by using the DAO) via the primary key, called the `id`:
- Journal: `Journal`; 
  the journal is often called the `context`.
- Submission: `Article`.
- Person: `User` (a minor extension of `PKPUser`).
- Author: `Author` (but the term is also oddly used for the 'author' of 
  a Review: the Reviewer)
- Editor: `User`? 
  Decision constants see `EditorDecisionActionsManager`. 
  Role ID constants see `Role`. 
  `StageAssignment` appears to map a user ID to 
  a stage (constants see `PKPApplication`, e.g. `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`)
  in a given role(?) (`UserGroup`(?), constants see ``).
  
- Reviewer: `User` (but usually called `reviewer`).
  - A `ReviewAssignment` connects a Reviewer to a Submission and also contains
    various timestamps, `declined`, `round`, `reviewRoundId`, `reviewMethod`. 
  - A `ReviewRound` represents the version number of a manuscript: 
    OJS could theoretically use the same `Article` for the, say,
    three versions of a manuscript until
    eventual acceptance or rejection and represents the versions explicitly.
    In contrast, RQC always uses three separate Submission 
    objects connected more implicitly via predecessor links.  
    How to get it: `ReviewRoundDAO::getLastReviewRoundBySubmissionId`
    (`ReviewRoundDAO::getCurrentRoundBySubmissionId` gets the round number).
  - Once the proper `ReviewRound` is known, get the `ReviewAssignments` by
    `ReviewAssignmentDAO::getByReviewRoundId` (one could also use 
    `ReviewAssignmentDAO::getBySubmissionId`).
    This returns an array. Its indices are the review IDs!.
- Review: `ReviewerSubmission`, but please hold on:
  - This class extends `Article`, presumably because reviewers can upload annotated 
    versions of the submission. 
  - Get one by `ReviewerSubmissionDAO::getReviewerSubmission($reviewId)`. ``
  - Attributes: timestamps, `declined`, `reviewMethod`, `reviewerId`, 
    `reviewId` (in fact reviewAssignmentId), `recommendation`, `decisions`.
  - Recommendation constants see 
  define('SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT', 1);
  define('SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS', 2);
  define('SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE', 3);
  define('SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE', 4);
  define('SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE', 5);
  define('SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS', 6);
  - Also an array of `SubmissionComments` which represent
    review text. Retrieve by `getMostRecentPeerReviewComment`
  - `SubmissionComment` attributes: `authorEmail`, `authorId`, 
    `comments`, `commentTitle`, `commentType` (should be 1: `COMMENT_TYPE_PEER_REVIEW`),
    timestamps, `roleId`, `submissionId`, `viewable`.


## Development notes: OJS3

- installation: https://pkp.sfu.ca/wiki/index.php?title=Github_Documentation_for_PKP_Contributors
- Many hooks are provided in `pkp-lib` like this 
  `HookRegistry::call(strtolower_codesafe(get_class($this) . '::validate')`
  (this particular one is from `classes/form/Form.inc.php`)
- DAO class names are in classes/core/Application.inc.php::getDAOmap())
- Forum: [create plugin and custom URL](https://forum.pkp.sfu.ca/t/ojs-3-0-3-0-1-browse-plugin-doesnt-show/26145/9?u=prechelt)
- Control flow, dispatch, URLs:
  https://pkp.sfu.ca/wiki/index.php?title=Router_Architecture
- see notes in 2018.3.txt of 2018-10-02
- Editor assignment: 
  "Can only recommend decision, authorized editor must record it."
- Settings->Website->Plugins->Plugin Gallery
- Plugins with Settings: 
  Google Analytics (Settings fail) 
  RQC (settings fail) 
  Web Feed (2 radiobuttons, one with an integer textbox) 
  Usage statistics (Sections, checkboxes, text fields, pulldown)
- Beware of the various _persistent_ caches, e.g. for plugin settings
- LoadHandler described in OSJ2.1 TechRef p. 46


### Development notes: RQC plugin

- Setting `activate_developer_functions = On` in `config.inc.php`
  enables `example_request` functionality in `RQCPlugin::manage`
  and `::getActions`. Not yet implemented.
- See
  [my PKP forum thread](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/6)
- In particular regarding 
  [exploring the data model (qu. 5)](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/9?u=prechelt)
- settings dialog does not close after OK.
- OJS review rounds must create successive submission ids for RQC.
- SpyHandler gets 8 notices a la 
  "Undefined index: first_name in /home/vagrant/ojs/lib/pkp/classes/submission/PKPAuthorDAO.inc.php on line 127"


## Development notes: RQC

- resubmit elsewhere counts as reject (or is its own decision?)
- do not submit confidential comments as part of the review.
- allow a file upload instead of review text???
- submit flag that RQC should emphasize the MHS page link,
  because grading-relevant material is only on the MHS page.

