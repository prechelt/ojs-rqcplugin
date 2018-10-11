# Review Quality Collector (RQC) plugin for OJS
created 2017-08-30, Lutz Prechelt

Version 2018-10-10 
Status: unfinished, not yet usable

## What it is

Review Quality Collector (RQC) is an initiative for improving the quality of 
scientific peer review. 
Its core is a mechanism that supplies a reviewer with a receipt for their work for 
each journal year.
The receipt is based on grading each review according to a journal-specific
review quality definition.

This plugin is an OJS adapter for the RQC API, by which manuscript
handling systems (such as OJS) report the reviewing data of individual
submissions to RQC so that RQC can arrange the grading and add the
reviews to the respective reviewers' receipts.

Find the RQC API description at
https://reviewqualitycollector.org/t/api


## How it works

- extends the journal master data forms by two additional fields: 
  `rqcJournalID` and `rqcJournalKey`.
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
  

## TO DO

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


## Development notes

### About OJS 3

- installation: https://pkp.sfu.ca/wiki/index.php?title=Github_Documentation_for_PKP_Contributors
- Many hooks are provided in `pkp-lib` like this 
  `HookRegistry::call(strtolower_codesafe(get_class($this) . '::validate')`
  (this particular one is from `classes/form/Form.inc.php`)
- DAO class names are in classes/core/Application.inc.php::getDAOmap())
- Forum: [create plugin and custom URL](https://forum.pkp.sfu.ca/t/ojs-3-0-3-0-1-browse-plugin-doesnt-show/26145/9?u=prechelt)
- see notes in 2018.3.txt of 2018-10-02
- Editor assignment: 
  "Can only recommend decision, authorized editor must record it."
- Settings->Website->Plugins->Plugin Gallery
- Plugins with Settings: 
  Google Analytics (Settings fail) 
  RQC (settings fail) 
  Web Feed (2 radiobuttons, one with an integer textbox) 
  Usage statistics (Sections, checkboxes, text fields, pulldown)
- LoadHandler described in OSJ2.1 TechRef p. 46


### About the RQC plugin

- See
  [my PKP forum thread](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/6)
- In particular regarding 
  [exploring the data model (qu. 5)](https://forum.pkp.sfu.ca/t/need-help-to-build-review-quality-collector-rqc-plugin/33186/9?u=prechelt)



## About RQC

- OJS review rounds must create successive submission ids for RQC.
- resubmit elsewhere counts as reject (or is its own decision?)
- do not submit confidential comments as part of the review.
- allow a file upload instead of review text???
- submit flag that RQC should emphasize the MHS page link,
  because grading-relevant material is only on the MHS page.

