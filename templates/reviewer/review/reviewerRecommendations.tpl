{**
 * templates/reviewer/review/reviewerRecommendations.tpl
 *
 * Copyright (c) 2022 Lutz Prechelt
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Last part of step3 of OJS review assignment responses:
 * - the reviewer's decision recommendation
 * - the RQC opt-in or opt-out if needed
 * We use this approach (which duplicates the original content of the same-named file)
 * rather than overwriting step3.tpl (which would duplicate only structure, not content)
 * because for some reason we could not get the required
 *   {include file="core:reviewer/review/step3.tpl"}
 * to work; it always ran into some infinite recursion.
 *}

{fbvFormSection label="reviewer.article.recommendation" description=$description|default:"reviewer.article.selectRecommendation"}
	{fbvElement type="select" id="recommendation" from=$reviewerRecommendationOptions selected=$reviewAssignment->getRecommendation() size=$fbvStyles.size.MEDIUM required=$required|default:true disabled=$readOnly}
{/fbvFormSection}

{if $rqcOptingRequired}
	{fbvFormSection label="plugins.generic.rqc.reviewerOptIn.header"
					description=$description|default:"plugins.generic.rqc.reviewerOptIn.text"}
		{fbvElement type="select" id="rqcOptIn"
					from=$rqcReviewerOptingChoices
					selected=null
					size=$fbvStyles.size.MEDIUM
					required=true
					disabled=$readOnly}
	{/fbvFormSection}
{/if}
