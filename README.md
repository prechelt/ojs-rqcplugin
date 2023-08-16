# Review Quality Collector (RQC) plugin for OJS
created 2017-08-30, Lutz Prechelt

Version 2022-09-15
Status: **plugin is unfinished, not yet usable**

## What it is

Review Quality Collector (RQC) is an initiative for improving the quality of
scientific peer review.
Its core is a mechanism that supplies a reviewer with a receipt for
their work for each journal year.
The receipt is based on grading each review according to a journal-specific
review quality definition.

This repository is a fork of https://github.com/pkp/ojs
with branches `rqc33` (off `stable-3_3_0`) and `rqc34` (off `main`)
that add a directory `plugins/generic/reviewqualitycollector`.

This plugin is an OJS adapter for the RQC API, by which OJS
reports the reviewing data of individual article
submissions to RQC so that RQC can arrange the grading and add the
reviews to the respective reviewers' receipts.

Find the RQC API description at
https://reviewqualitycollector.org/t/api


## How it works

- Provides journal-specific settings
  `rqcJournalId` and `rqcJournalAPIKey`
  that identify the journals's representation on RQC.
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
  and will be repeated once a day for several days until it goes through
  (not yet implemented).


## How to use it: Installation

Target audience: OJS system administrators.

- The RQC plugin requires PHP 7 and OJS 3.3.
- In `config.inc.php`, set `scheduled_tasks = On`.
- Make sure there is an entry in your OJS server's crontab
  (see "Scheduled Tasks" in the OJS `docs/README`) and that it includes
  RQC's `scheduldTasks.xml` besides the default one from `registry`.
  This could for instance look like this
  ```crontab
  0 * * * *	(cd /path/to/ojs; php tools/runScheduledTasks.php registry/scheduledTasks.xml plugins/generic/reviewqualitycollector/scheduledTasks.xml)
  ```
- Perhaps update the RQC plugin from within OJS.
  This only applies if you know how to create the proper .tar.gz file,
  your server allows in-place plugin updates (or you drop the data
  into the proper place by hand)
  and, most importantly,
  [the newest version of this README}(https://github.com/pkp/ojs/tree/master/plugins/generic/reviewqualitycollector/README.md)
  indicates there have been relevant improvements since your version.
- In OJS, go to Settings->Website->Plugins and activate the
  plugin "Review Quality Collector (RQC)" in category Generic Plugins.


## How to use it: Journal setup

Target audience: OJS journal managers, RQC RQGuardians.

- Read about RQC at https://reviewqualitycollector.org.
- In RQC, register an account for yourself.
- Find your publisher in the RQC publisher directory
  and ask your publisherpersons to create an RQC journal for
  your journal.
  (If needed, ask your publisher to register at RQC first.
  TODO: discuss generic publishers for free and very-low-fee journals.)
- Discuss review quality criteria with your co-editors.
  Formulate an RQC review quality definition.
- As RQGuardian in RQC, set up your journal:
  review quality definition,
  grading parameters (which people must/should/can/cannot grade a review).
- In OJS, open the RQC plugin settings and enter your
  public RQC journal ID and your secret RQC journal key.
  To do this, you need to be journal manager for your journal in OJS.


## How to use it: Daily use

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
