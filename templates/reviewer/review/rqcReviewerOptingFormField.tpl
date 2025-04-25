{**
 * Last part of step3 of OJS review assignment responses:
 * - the reviewer's decision recommendation
 * - the RQC opt-in or opt-out if needed
 *}
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
