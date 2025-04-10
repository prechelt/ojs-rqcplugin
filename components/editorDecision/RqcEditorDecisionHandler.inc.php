<?php

/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\core\Application;
use APP\core\PageRouter;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\handler\PKPHandler;
use PKP\plugins\PluginRegistry;
*/

import('classes.handler.Handler');
import('classes.core.PageRouter');
import('lib.pkp.classes.handler.PKPHandler');
import('lib.pkp.classes.core.JSONMessage');

import('plugins.generic.rqc.RqcPlugin');


/**
 * Handle modal dialog before submitting and redirecting to RQC
 *
 * @ingroup plugins_generic_rqc
 */
class RqcEditorDecisionHandler extends PKPHandler
{
	public Plugin|null $plugin;

	function __construct()
	{
		parent::__construct();
		$this->plugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	/**
	 * Confirm submission+redirection to RQC.
	 * Called when an editor uses the "RQC-grade submission" button.
	 */
	function rqcGrade($args, $request)
	{
		//----- prepare processing:
		$requestArgs = $this->plugin->getQueryArray($request);
		$submissionId = $requestArgs['submissionId'];
		$submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
		//----- modal dialog:
		$pageRouter = new PageRouter();
		$pageRouter->setApplication(Application::get());  // so that url() will find context
		$target = $pageRouter->url($request, null, 'rqccall', 'submit', null,
			array('submissionId' => $submissionId, 'stageId' => $submission->getStageId()));
		$okButton = "<a href='$target' class='pkp_button_primary submitFormButton'>" . __('common.ok') . '</a>';  // TODO 3: set focus
		// $cancelButton = '<a href="#" class="pkp_button pkpModalCloseButton cancelButton">' . __('common.cancel') . '</a>';
		$content = __('plugins.generic.rqc.editoraction.grade.explanation');
		$buttons = "<p>$okButton</p>";  // TODO 3: add a working cancel button: Using the templateMrg->fetchJson(some.tpl) that includes the {fbvFormButtons} didn't work either. So I guess the problem is before this function maybe? Something missing that has to be set. Or maybe is the structure wrong for that kind of modal form?
		return new JSONMessage(true, "$content$buttons");
	}
}
