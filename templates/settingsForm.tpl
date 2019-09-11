{**
 * plugins/generic/reviewqualitycollector/settingsForm.tpl
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * RQC journal setup form: Journal ID, journal key
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#gaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="gaSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="rqcSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.reviewqualitycollector.settings.description"}</div>

	{fbvFormArea id="rqcSettingsFormArea" title="plugins.generic.reviewqualitycollector.settings.header"}
		<br>
		{fbvFormSection id="rqcSettingsFormSectionId"}
			<div id="rqcJournalIdDescription">{translate key="plugins.generic.reviewqualitycollector.settingsform.rqcJournalIDDescription"}</div>
			{fbvElement type="text" name="rqcJournalId" value=$rqcJournalId label="plugins.generic.reviewqualitycollector.settingsform.rqcJournalID" required="true"}
		{/fbvFormSection}
    	{fbvFormSection id="rqcSettingsFormSectionKey"}
			<div id="rqcJournalKeyDescription">{translate key="plugins.generic.reviewqualitycollector.settingsform.rqcJournalKeyDescription"}</div>
  			{fbvElement type="text" name="rqcJournalKey" value=$rqcJournalKey label="plugins.generic.reviewqualitycollector.settingsform.rqcJournalKey" required="true"}
        {/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
