# Review Quality Collector (RQC) plugin for OJS
created 2017-08-30, Lutz Prechelt


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

