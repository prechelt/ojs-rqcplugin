## Development notes: Development setup

- configure the machine itself:
- starting point: This repo has been cloned into `/ws/gh/ojs34` on WSL Debian.
- sudo apt-get install `cat z/apt-packages.txt`
- add `/home/prechelt/.local/bin` to `$PATH`.
- `pip3 install -r z/requirements.txt`
- We do not need a venv for ojs development if `fabric` is installed globally.
- We develop on branch `lutz34`, which branches off of
  `stable-3_4_0` and is rebased on that or its 3.4.x successor
  from time to time.
  See `init_rqc33`/`init_rqc34` in `.bashrc`.
- I'll accumulate my required patches to OJS and PKP locally
  and see what to do with them once RQC is more-or-less release-ready.
  https://docs.pkp.sfu.ca/dev/contributors/#before-you-begin
- I may want them in 3.3 _and_ 3.4, because as of 2022-09 the release timeline so far was
  - 3.0: 2016-08-31
  - 3.1: 2017-10-23
  - 3.2: 2020-02-28
  - 3.3: 2020-11-28
  - 3.4: 2023-06-09
  - 3.5: under development as of 2023-08
- set up OJS for development:
  see OJS developer installation for reference:
  https://docs.pkp.sfu.ca/dev/documentation/en/getting-started
- perform the steps described under _Install_ manually.
- set remotes as described under _Remotes_
- Retrieve the previous `config.inc.php` and/or adjust `config.inc.php` to the following settings:
- [general]: installed = Off; base_url, scheduled_tasks
- [debug]: show_stacktrace = On; display_errors = Off  (display_errors breaks Ajax functions when it kicks in)
- [database]: host, port, name, username, password, persistent
- [email]: smtp_server, smtp_port, smtp_auth, smtp_username, smtp_password
- [reviewqualitycollector]: activate_developer_functions = On; rqc_server = http://localhost:<port>
  (the plugin will use rqc_server if set and a fixed default otherwise)
- For DB setup (schema, admin user), you must use the web config dialog
  at `http://localhost:port`.
  _For this to work, postgres must answer to its standard port 5432,_
  because as of 2022-08 the setup dialog has no port field.
  Stop the DB container (see `fab --list`) and set it back to the desired port right after
  the config dialog has finished successfully. Restart `php -S`.
- create initial data:
  - create journal rqctest with path rqctest:
  - create users editor1, author1, reviewer1, reviewer2;
  - create a submission, 2 review assignments, 2 reviews;
  - Settings->Website->Plugins turn on RQC plugin
- tests/backup.sh backup
  so you can quickly restore the review case during testing
- http://localhost:<port>/index.php/rqctest/rqcdevhelper/
- Perhaps apply patches to OJS codebase from `ojspatches`.
- For OJS to talk to RQC, start the Django dev server


## Development notes: General OJS knowledge

- Overall PKP/OJS developer documentation
- Architecture: https://docs.pkp.sfu.ca/dev/documentation/en/architecture

- Templates:
- Smarty: https://smarty-php.github.io/smarty/designers/language-builtin-functions.html
- fbv: FormBuilderVocabulary
- Accessing data: https://docs.pkp.sfu.ca/pkp-theming-guide/en/html-smarty
- Injecting data: https://docs.pkp.sfu.ca/pkp-theming-guide/en/advanced-custom-data.html
- ...
- DAO class names are in classes/core/Application.inc.php::getDAOmap())
- Forum: [create plugin and custom URL](https://forum.pkp.sfu.ca/t/ojs-3-0-3-0-1-browse-plugin-doesnt-show/26145/9?u=prechelt)
- Control flow, dispatch, URLs:
https://pkp.sfu.ca/wiki/index.php?title=Router_Architecture
- see notes in 2018.3.txt of 2018-10-02
- Editor assignment:
"Can only recommend decision, authorized editor must record it."
- Settings->Website->Plugins->Plugin Gallery
- Beware of the various _persistent_ caches, e.g. for plugin settings
- LoadHandler described in OSJ2.1 TechRef p. 46
- Maybe-helpful items from the code:
_callbackHandleCustomNavigationMenuItems


## Development notes: OJS data model (the relevant parts)

RQC speaks of Journals, Submissions (i.e. articles), Authors,
Reviewers, Reviews, Editors, EditorAssignments (which Editor has which
role for which Submission).
Authors, Editors, and Reviewers are all Persons.

This is how these concepts are represented in OJS (class names,
other typical identifiers for such objects).
OJS speaks of four "stages": submission, review, copyediting, production.
RQC is concerned with the review stage only.
A revised article in OJS is not a new submission but rather a new
"review round" of the same submission (with new files).
Most classes have a corresponding DAO (data access object, as the ORM).
Accessing objects often involves retrieving them (by using the DAO)
via the primary key, called the `id`:
- Journal: `Journal`;
the journal is often called the `context`.
- Submission: `Article`
(there is a `Submission` class as well: the PKP library's superclass).
- Person: `User` (extends `Identity`).
- Author: `Author` (but the term is oddly also used for the 'author' of
a Review: the Reviewer).
Inheritance: `Author<--PKPAuthor<--Identity<--DataObject`.
- Editor: `User`(?)
Decision constants see `EditorDecisionActionsManager`.
Role ID constants see `Role`.
The handling editor is called Section Editor in OJS.
`StageAssignment` appears to map a user ID to
a stage (constants see `PKPApplication`, e.g. `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`)
in a given role(?) (`UserGroup`(?), constants see ` `???).
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
- Maybe-helpful items from the code:
_callbackHandleCustomNavigationMenuItems

After setting up OJS anew:
- config.inc.php:
show_stacktrace = On
display_errors = Off
activate_developer_functions = On
rqc_server = http://192.168.3.1:8000
(display_errors breaks Ajax functions when it kicks in)
- create journal rqctest:
create users editor1, author1, reviewer1, reviewer2;
create a submission, 2 review assignments, 2 reviews;
Settings->Website->Plugins turn on RQC plugin
- tests/backup.sh backup
so you can quickly restore the review case during testing
- http://localhost:8000/index.php/rqctest/rqcdevhelper/

Patches to OJS codebase that may be needed:

--- a/classes/template/PKPTemplateManager.inc.php
+++ b/classes/template/PKPTemplateManager.inc.php
@@ -869,7 +869,7 @@ class PKPTemplateManager extends Smarty {
static function &getManager($request = null) {
if (!isset($request)) {
$request = Registry::get('request');
-                       if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call without request object.');
+                       // if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call without request object.');
}
assert(is_a($request, 'PKPRequest'));


### Development notes: phpunit and PKPTestCase/DatabaseTestCase

- Complete DB reset is available by returning `PKP_TEST_ENTIRE_DB`
from `getAffectedTables`.
- Selenium test example see `StaticPagesFunctionalTest`.


## Development notes: OJS 3.4

- Hooks: `php lib/pkp/tools/getHooks.php | tr , '\n'`
  Many hooks live in lib/pkp, not in ojs.
- Hook: statt `modifydecisionoptionshook` gibt es jetzt `Decision::add`
  und `Decision::validate`.


## Development notes: RQC

- resubmit elsewhere counts as reject (or is its own decision?)
- do not submit confidential comments as part of the review.
- allow a file upload instead of review text???
- submit flag that RQC should emphasize the MHS page link,
  because grading-relevant material is only on the MHS page.

- Review Forms can be configured freely.
  We will use only the 'extended text box' parts that are marked as
  'will be sent to authors'
- Our opt-in field must be added in review step3, which currently shows:
  Review files, Review form (several fields), Reviewer files, Review discussions,
  Recommendation.
  The best place would be at the very bottom, after the recommendation.
  To put my field there, I simply put a
  `templates/reviewer/review/reviewerRecommendations.tpl` in the plugin
- Setting `activate_developer_functions = On` in `config.inc.php`
  enables `example_request` functionality in `RQCPlugin::manage`
  and `::getActions`. Not yet implemented.
- See [my PKP forum thread](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/6)
- In particular regarding [exploring the data model (qu. 5)](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/9?u=prechelt)
- OJS review rounds must create successive submission ids for RQC.
- SpyHandler (now DevHelperHandler) gets 8 notices a la
  "Undefined index: first_name in /home/vagrant/ojs/lib/pkp/classes/submission/PKPAuthorDAO.inc.php on line 127"
- Cronjob via PKPAcronPlugin?
- Delayed-call storage via Plugin::updateSchema and my own DAO?


### TO DO

- store opt-in response in review submission
  https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-custom-field
- settings: add the journal ID/key validation via an RQC call
- add all hooks and actual activity
- Switch to LetsEncrypt and put its root certificate into the plugin,
  because the Telekom certificate ends 2019-07-09
  (and RQC's ends 2019-03-27!)
- elaborate on "ask your publisher" in locale.po
- write automated tests
- package the plugin, submit it for publication in OJS plugin gallery
