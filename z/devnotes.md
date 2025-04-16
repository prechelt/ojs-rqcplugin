## Development setup

### Windows with WSL Debian

- starting point: This repo has been cloned into `/ws/gh/ojs34` on WSL Debian.
- sudo apt-get install `cat z/apt-packages.txt`
- add `/home/prechelt/.local/bin` to `$PATH`.
- `pip3 install -r z/requirements.txt`
- We do not need a venv for ojs development if `fabric` is installed globally.
- We develop on branch `lutz34`, which branches off of
  `stable-3_4_0` and is rebased on that or its 3.4.x successor
  from time to time. (Ditto for 3.3.x)
  See `init_rqc33`/`init_rqc34` in `.bashrc`.
- I'll accumulate my required patches to OJS and PKP locally
  and see what to do with them once RQC is more-or-less release-ready.
  https://docs.pkp.sfu.ca/dev/contributors/#before-you-begin

### Debian

TODO 2: insert the .txt file contents??

### Fedora

TODO 2: insert the .sh file contents

my custom setup for the config.inc.php (see https://docs.pkp.sfu.ca/dev/documentation/3.3/en/getting-started)

- [general]:
	- base_url = "http://localhost:8000"
	- allowed_hosts = "[\"localhost\"]"
- [database]:
	- driver = postgres9
	- name = ojs3_3
- [files]:
	- files_dir = "_your_path_to_files_folder"
- [rqc]:
	- activate_developer_functions = On
- [scheduled_tasks]
	- scheduled_tasks = On (currently doesn't work? TODO 2)

### generally used

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
- [rqc]: activate_developer_functions = On; rqc_server = http://localhost:<port>
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
- http://localhost:<port>/index.php/rqctest/rqcdevhelper/1/?viewonly=1
- Perhaps apply patches to OJS codebase from `ojspatches`.
- For OJS to talk to RQC, start the Django dev server
- used https://github.com/pkp/staticPages as a template for DelayedRqcSchemaMigration

## OJS knowledge

### General OJS knowledge

- Overall PKP/OJS developer documentation: https://docs.pkp.sfu.ca/dev/
- Templates:
	- Smarty: https://smarty-php.github.io/smarty/designers/language-builtin-functions.html
	- fbv: FormBuilderVocabulary
- Data:
	- Accessing data: https://docs.pkp.sfu.ca/pkp-theming-guide/en/html-smarty
	- Injecting data: https://docs.pkp.sfu.ca/pkp-theming-guide/en/advanced-custom-data.html
	- DAO class names are in classes/core/Application.inc.php::getDAOmap())
- Forum:
	- [create plugin and custom URL](https://forum.pkp.sfu.ca/t/ojs-3-0-3-0-1-browse-plugin-doesnt-show/26145/9?u=prechelt)
- Misc:
	- see notes in 2018.3.txt of 2018-10-02
	- Editor assignment:
	  "Can only recommend decision, authorized editor must record it."
	- Maybe-helpful items from the code:
	  _callbackHandleCustomNavigationMenuItems

### OJS data model (the relevant parts)

RQC speaks of Journals, Submissions (i.e. articles), Authors,
Reviewers, Reviews, Editors, EditorAssignments (which Editor has which
role for which Submission).
Authors, Editors, and Reviewers are all Persons.

This is how these concepts are represented in OJS (class names,
other typical identifiers for such objects):

- OJS speaks of four "stages": submission, review, copyediting, production.
  RQC is concerned with the review stage only.
- A revised article in OJS is not a new submission but rather a new
  "review round" of the same submission (with new files).
- Most classes have a corresponding DAO (data access object, as the ORM).
  Accessing objects often involves retrieving them (by using the DAO)
  via the primary key, called the `id`:
- Journal: `Journal`;
  the journal is often called the `context`.
- Submission: `Submission`
  (there is a `PKPSubmission` class as well: the PKP library's superclass).
- Person: `User` (extends `Identity`).
- Author: `Author` (but the term is oddly also used for the 'author' of
  a Review: the Reviewer).
  Inheritance: `Author<--PKPAuthor<--Identity<--DataObject`.
  The author submits the submission. What is which the other co-authors. Are they identities?
- Editor: `User`(?)
  The handling editor is called Section Editor in OJS.
- Decision constants see `EditorDecisionActionsManager`.
  Role ID constants see `Role`.
- `StageAssignment` maps (submission, user, usergroup(=role?) to
  two flags `recommend_only` and `can_change_metadata`.
  These are settings valid for the stage `stage_id` in the submission object
  (constants see far down in `PKPApplication`, e.g. `WORKFLOW_STAGE_ID_EXTERNAL_REVIEW`).
- Reviewer: `User` (but usually called `reviewer`).
- A `ReviewAssignment` connects a Reviewer to a Submission and also contains
  various timestamps, `declined`, `round`, `reviewRoundId`, `reviewMethod`.
- A `ReviewRound` represents the version number of a manuscript:
  OJS could theoretically use the same `Article` for all, say,
  three versions of a manuscript until
  eventual acceptance or rejection and represents the versions explicitly.
  In contrast, RQC always uses three separate Submission
  objects.
  How to get it: `ReviewRoundDAO::getLastReviewRoundBySubmissionId`
  (`ReviewRoundDAO::getCurrentRoundBySubmissionId` gets the round number).
	- Once the proper `ReviewRound` is known, get the `ReviewAssignments` by
	  `ReviewAssignmentDAO::getByReviewRoundId` (one could also use
	  `ReviewAssignmentDAO::getBySubmissionId`).
	  This returns an array. Its indices are the review IDs!.

### OJS 3.4

- List all hooks: `php lib/pkp/tools/getHooks.php | tr , '\n'`
  Many hooks live in lib/pkp, not in ojs.
- `HookRegistry::register()` turns into `Hook::add()`.
- Hook: the former `modifydecisionoptionshook` is now `Decision::add`
  and `Decision::validate`.
- Convert code to use namespaces, new data model, new hooks.
  Will make it incompatible with OJS 3.3. Hmm.
  Hints regarding namespaces: https://github.com/pkp/pkp-lib/issues/6091
  in section "Additional details on plugins"

### phpunit and PKPTestCase/DatabaseTestCase

- 3.3: Complete DB reset is available by returning `PKP_TEST_ENTIRE_DB`
  from `getAffectedTables`.

### Releasing a plugin

- Docs: https://docs.pkp.sfu.ca/dev/plugin-guide/en/release
- Perhaps install the tool:
  `npm install pkp-plugin-cli; alias pkp-plugin-cli="./node_modules/pkp-plugin-cli/bin/index.js"`
- A plugin is released as a plain, publicly downloadable .tar.gz file,
  accompanied by a pull-request on the
  [plugin gallery repo](https://github.com/pkp/plugin-gallery))
  for the corresponding XMl descriptor block.
- The .tar.gz contains only the required files, LICENSE, version.xml.
  Its root is the 'rqc' directory (not only its contents).
- We use `(cd plugins/generic/rqc; make tar)` to create a bare-named
  tarfile in `tar/` and rename and upload it manually.
- When preparing the XML snippet for the plugin gallery, the
  OJS version for the `<version>` tag is found in `dbscripts/xml/version.xml`.

## RQC adapter

- submit flag that RQC should emphasize the MHS page link,
  because grading-relevant material is only on the MHS page.

- Add hook to PKPReviewerReviewStep3Form::saveForLater() so that I can do
  ReviewerOpting::setStatus(..., RQC_PRELIM_OPTING), which currently is never used.
- Setting `activate_developer_functions = On` in `config.inc.php`
  enables helpers from RqcDevHelperHandler:
	- `http://localhost:8033/index.php/rqctest/rqcdevhelper/hello` shows some request information
	- `http://localhost:8033/index.php/rqctest/rqcdevhelper/rqccall/1/?viewonly=1` shows what would get sent to RQC
	- `http://localhost:8033/index.php/rqctest/rqcdevhelper/rqccall/1/?viewonly=0` makes an RQC call
- See [my PKP forum thread](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/6)
- In particular
  regarding [exploring the data model (qu. 5)](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/9?u=prechelt)
- Pieces that take part in review submission[superclasslevel]:
	- ReviewerHandler[0] -> PKPReviewerHandler[1]
		- submission()[1]: called for the overall form with tabs
		- step()[1]: display one tab's contents  (has no hooks)
			- getReviewForm()[0] -> ReviewerReviewStep3Form[0]
		- saveStep()[1]: store data preliminarily  (has no hooks)
			- getReviewForm()[0] -> ReviewerReviewStep3Form[0]
	- Templates (overwriting levels: Plugin[0] -> OJS[1] -> PKP[2]; includes: [I1], [I2], [IName]): all in
	  reviewer.review
		- step3[1][IreviewerRecommendations[0]][Istep3[2]]
		- reviewerRecommendations[0]: select:recommendation, select:rqc_opt_in
	- ReviewerReviewStep3Form[0] -> PKPReviewerReviewStep3Form[1] -> ReviewerReviewForm[2] -> Form[3]
		- Template assignment (to `$this->_template`)
		  via `parent::__construct(sprintf('reviewer/review/step%d.tpl', $step));`
		  in ReviewerReviewForm::__construct()
		- Form[3] has many hooks, all accessible by downcased subclassname, e.g.:
		  ::Constructor, ::display (in fetch()), ::initData, ::validate, ::execute, ::readUserVars,
- How does data get to a template?
  Handler calls `$templateMgr->assign(array('attrname' => value))`,
  template uses `$attrname`.
- OJS review rounds must create successive submission ids for RQC.
- Cronjob via an own crontab
	- test what the cronjob does with php tools/runScheduledTasks.php plugins/generic/rqc/scheduledTasks.xml
- Delayed-call storage via Migration and own DAO that extends SchemaDAO?

### TO DO and status

- store opt-in response in review submission
  https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-custom-field
- add all hooks and actual activity
- Switch to LetsEncrypt and put its root certificate into the plugin,
  because the Telekom certificate ends 2019-07-09
  (and RQC's ends 2019-03-27!)
- elaborate on "ask your publisher" in locale.po
- write automated tests
  - also for RqcApiIdKeyPair
- testing
  - every year the demojournals at RQC are deleted (so add a remark somewhere)
  - which states can OJs be in in which RQC doesn't work?
- package the plugin, submit it for publication in OJS plugin gallery
- document typical problems
  - same email is needed for both services (RQC login and OJS user) (that is the "id" that identifies the people)
- rewrite some things here in the devnodes (some are old and I don't know what they mean)
- better response if the APIIdKeyPair is not valid anymore? Or does the response body of rqc suffice?
  - maybe if implicit calls also throw errors with pop ups then yeah I guess
- if attachment sets are supported by the server: uncomment the place where its called
- which patches are applied, which aren't
- go through all the already written functions (especially RqcData and "how many rounds does the manuscript have?" (??; go through the whole OJS data model) if there are some logical errors there)
- opting ranges (they go from e.g. 01.2025 til (incl.) 02.2026 because decisions can take a while; after that you have to redo the opting) TODO Q
  - if not redone the opting then: use the opting status from the last year
- set up the Issue tracker for the future (after my bachelors thesis is finished)

- TODOs in the files
  - TODO 1, 2, 3
  - TODO Q (Question @prechelt)
  - TODO Forum (ask in the Forum and wait for an answer)
  - TODO if issue is closed: issue xy

- these decisions should maybe be rethought about and done differently
  - logging: Are all the places really needed?
  - logging the body of Received an unexpected non-JSON response from RQC while making a .. That can be really large and we don't have to do that or do we?


## OJS versions

### Release timeline

- 3.0: 2016-08-31
- 3.1: 2017-10-23
- 3.2: 2020-02-28
- 3.3: 2020-11-28
- 3.4: 2023-06-09
- 3.5: under development as of 2023-08

### Which release is used how much (as of 2024-12)?

Number of journals using this version.
Taken from the CSV file at
https://dataverse.harvard.edu/file.xhtml?fileId=10740907&version=5.0
(which is updated yearly)
via `cut -d',' -f3 beacon.csv | cut -d'.' -f1-3 | sort -r | uniq -c`

```
   5271 3.4.0
    491 3.3.9
     48 3.3.1
  33390 3.3.0
   7136 3.2.1
    833 3.2.0
   5177 3.1.2
   2933 3.1.1
    814 3.1.0
   1078 3.0.2
    166 3.0.1
     67 3.0.0
   7856 2.4.8
   1023 2.4.7
    204 2.4.6
    207 2.4.5
     29 2.4.4
     24 2.4.3
    120 2.4.2
      2 2.3.8
     18 2.3.7
     40 2.3.6
     11 2.3.2
     16 2.3.1
      1 1.1.1
      5  ([sub]version number is to high and doesn't make sense)
    178  (no version indicated)
```

Minor Versions (without patches)

```
   5271 3.4
  33929 3.3
   7969 3.2
   8924 3.1
   1311 3.0
   9463 2.4
     87 2.3
      1 1.1
```
