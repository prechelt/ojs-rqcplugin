# Review Quality Collector (RQC) plugin for OJS

created 2017-08-30, Lutz Prechelt

Version 2023-09-12
Status: **beta test, please ask if you want to participate**

## 1. What it is

[Review Quality Collector (RQC)](https://reviewqualitycollector.org)
is an initiative for improving the quality of
scientific peer review.
Its core is a mechanism that supplies a reviewer with a receipt for
their work for each journal year.
The receipt is based on grading each review according to a journal-specific
review quality definition.

This repository is an OJS generic plugin that realizes
an OJS adapter for the RQC API, by which OJS
reports the reviewing data of individual article
submissions to RQC so that RQC can arrange the grading and add the
reviews to the respective reviewers' receipts.

Find the RQC API description at
https://reviewqualitycollector.org/t/api.

## 2. How it works

- Provides journal-specific settings
  `rqcJournalId` and `rqcJournalAPIKey`
  that identify the journal's representation on RQC.
- When both are filled, they are checked against RQC
  whether they are a valid pair and rejected if not.
- If they are accepted, they are stored as additional JournalSettings.
- If these settings exist, the plugin will add a button "RQC-grade the reviews"
  by which editors can submit the reviewing data for a given
  submission to RQC in order to trigger the grading.
  This step is optional for the editors.
  Depending on how RQC is configured for that journal, the given
  editor may then be redirected to RQC to perform (or not)
  a grading right away.
- The plugin will also intercept the acceptance-decision-making
  event and send the decision and reviewing data for that submission
  to RQC then.
- Should the RQC service be unavailable when data is submitted
  automatically at decision time, the request will be stored
  and will be repeated once a day for several days until it goes through.

## 3. How to use it

### 3.1 Installation

Target audience: OJS system administrators.

- The RQC plugin requires PHP 7 and OJS 3.3.
- In `config.inc.php`, set `scheduled_tasks = On`.
- Install the plugin via the plugin gallery (in website settings).
- THE FOLLOWING IS _NOT_ YET NEEDED:
  Make sure there is an entry in your OJS server's crontab
  (see "Scheduled Tasks" in the OJS `docs/README`) and that it includes
  RQC's `scheduledTasks.xml` besides the default one from `registry`.
  This could for instance look like this
  ```crontab
  0 * * * *	(cd /path/to/ojs; php tools/runScheduledTasks.php registry/scheduledTasks.xml plugins/generic/reviewqualitycollector/scheduledTasks.xml)
  ```
- Later, perhaps update the RQC plugin from within OJS.
  This only applies if you know how to create the proper .tar.gz file,
  your server allows in-place plugin updates (or you drop the data
  into the proper place by hand)
  and, most importantly,
  [the newest version of this README](https://github.com/pkp/ojs/tree/master/plugins/generic/reviewqualitycollector/README.md)
  indicates there have been relevant improvements since your version.
- In OJS, go to Settings->Website->Plugins and activate the
  plugin "Review Quality Collector (RQC)" in category Generic Plugins.

### 3.2 Journal setup

Target audience: OJS journal managers, RQC RQGuardians.

- Read about RQC at https://reviewqualitycollector.org.
- In RQC, register an account for yourself.
  Use (or add) the email address you use for the editorial role in OJS.
- Find your publisher in the RQC publisher directory
  and ask your publisherpersons to create an RQC journal for
  your journal.
  (If needed, ask your publisher to register at RQC first.
  As of 2023-09, you can also ask Lutz Prechelt to have your journal under the
  RQC Early Adopters pseudo-publisher.)
- Discuss review quality criteria with your co-editors.
  Formulate an RQC review quality definition.
- As RQGuardian in RQC, set up your journal:
  review quality definition,
  grading parameters (which people must/should/can/cannot grade a review).
- In OJS, open the RQC plugin settings and enter your
  public RQC journal ID and your secret RQC journal API key (which your
  publisherpersons will tell you).
  To do this, you need to be journal manager for your journal in OJS.

### 3.3 Daily use

- The best time to grade reviews in RQC is when you prepare
  the editorial decision in OJS.
- Therefore, each editor who is supposed to provide a grading
  should make it a habit to use the "RQC-grade reviews" button
  before entering their decision in OJS.
  This will redirect them into RQC for the grading and then
  back to OJS for entering the decision.
- If nobody does that (e.g. because editors are not supposed to
  perform review grading at your journal), OJS will submit the
  reviewing data to RQC when the editorial decision is entered into OJS.
  RQC will then remind graders via email.

## 4. How OJS concepts are mapped to RQC concepts

### 4.1 Roles

OJS's _editors_ (assigned to a submission on a case-by-case basis)
are treated as level-1 editors in RQC.

OJS's permanent _journal managers_
are treated as level-3 editors in RQC.

### 4.2 Editorial decisions

(This is not yet relevant, because functionality that makes
RQC react to the decision taken on a submission is not yet
implemented on the RQC side.)

- an OJS "Request Revisions" becomes an RQC "MAJORREVISION"
  for the "suggested_decision" and "decision" fields in the
  [RQC API description](https://reviewqualitycollector.org/t/api)
- an OJS "Accept Submission" becomes an RQC "ACCEPT"
- an OJS "Decline Submission" becomes an RQC "REJECT"

## 5. Limitations

The current version of the plugin has the following limitations:

User-visible:

- The RQC-submission confirmation dialog has no "Cancel" button.
  If you need to close it, use the "x" in the top right corner.
- Attachments uploaded by reviewers are not yet transmitted to RQC.
- Handling journal-specific review forms is implemented,
  but has not yet been tested and may not work.

Internal/technical:

- Automatically repeating the submission to RQC after a failed
  request is not yet implemented.
- No automated tests exist yet. (They are complicated with OJS.)
