{**
 * Last part of step3 of OJS review assignment responses:
 * - the reviewer's decision recommendation
 * - the RQC opt-in or opt-out if needed
 * We use this approach (which duplicates the original content of the same-named file)
 * rather than overwriting step3.tpl (which would duplicate only structure, not content) TODO 1: add into structure instead of like its done here
 * because for some reason we could not get the required
 *   {include file="core:reviewer/review/step3.tpl"}
 * to work; it always ran into some infinite recursion.
 * TODO 1: Is there another way? Maybe its the "HookRegistry::register('TemplateResource::getFilename', array($this, '_overridePluginTemplates'));
 * 			// needed by ReviewerOpting (automatically override all the templates of ojs with templates set by this plugin. In this case: /reviewer/review/reviewerRecommendation.tpl)"
 *}

{fbvFormSection label="reviewer.article.recommendation" description=$description|default:"reviewer.article.selectRecommendation"}
	{fbvElement type="select" id="recommendation" from=$reviewerRecommendationOptions selected=$reviewAssignment->getRecommendation() size=$fbvStyles.size.MEDIUM required=$required|default:true disabled=$readOnly}
{/fbvFormSection}
{if $rqcOptingRequired}
	{fbvFormSection label="plugins.generic.rqc.reviewerOptIn.header" for="rqcOptIn"
					description=$rqcDescription|default:"plugins.generic.rqc.reviewerOptIn.text"}
		{fbvElement type="select" id="rqcOptIn"
					from=$rqcReviewerOptingChoices
					selected=$rqcPreselectedOptIn
					size=$fbvStyles.size.MEDIUM
					required=true
					disabled=$readOnly}
	{/fbvFormSection}
{/if}
