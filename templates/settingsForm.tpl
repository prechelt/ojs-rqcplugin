{**
 * plugins/generic/rqc/settingsForm.tpl
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * RQC journal setup form: Journal ID, journal API key
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#gaSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
</script>

<form class="pkp_form" id="gaSettingsForm" method="post"
	  action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="rqcSettingsFormNotification"}

	<div id="description">{translate key="plugins.generic.rqc.settings.description"}</div>

	{fbvFormArea id="rqcSettingsFormArea" title="plugins.generic.rqc.settings.header"}
		<br>
	{fbvFormSection id="rqcSettingsFormSectionId"}
		<div
			id="rqcJournalIdDescription">{translate key="plugins.generic.rqc.settingsform.rqcJournalIDDescription"}</div>
	{fbvElement type="text" id="rqcJournalId" name="rqcJournalId" value=$rqcJournalId label="plugins.generic.rqc.settingsform.rqcJournalID" required="true"}
	{/fbvFormSection}
	{fbvFormSection id="rqcSettingsFormSectionKey"}
		<div
			id="rqcJournalAPIKeyDescription">{translate key="plugins.generic.rqc.settingsform.rqcJournalAPIKeyDescription"}</div>
	{fbvElement type="text" id="rqcJournalAPIKey" name="rqcJournalAPIKey" value=$rqcJournalAPIKey label="plugins.generic.rqc.settingsform.rqcJournalAPIKey" required="true"}
	{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
