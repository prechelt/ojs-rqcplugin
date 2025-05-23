<?php

namespace APP\plugins\generic\rqc;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorRegExp;
use PKP\form\validation\FormValidator;

use APP\plugins\generic\rqc\classes\RqcCall;
use APP\plugins\generic\rqc\classes\RqcLogger;
use APP\plugins\generic\rqc\classes\RqcDevHelper;


/**
 * Form for journal managers to modify RQC plugin settings
 *
 * @ingroup plugins_generic_rqc
 */
class RqcFormValidator extends FormValidator
{
	function isValid(): bool
	{
		$form = $this->_form;
		$hostUrl = $form->_plugin->rqcServer();
		$contextId = $form->_contextId;
		$rqcJournalId = $form->getData('rqcJournalId');
		$rqcJournalAPIKey = $form->getData('rqcJournalAPIKey');
		$result = RqcCall::callMhsApikeyCheck($hostUrl, $rqcJournalId, $rqcJournalAPIKey,
			!$form->_plugin->hasDeveloperFunctions());
		//RqcDevHelper::writeObjectToConsole($result);
		$status = $result['status'];
		switch ($status) {
			case 200:
				return true;  // all is fine
			case 400:
			case 404:
				$msg = array_key_exists('response', $result) ? $result['response']['error']
					: "something went wrong with the RQC request";
				$form->addError('rqcJournalId', $msg);
				RqcLogger::logError("API Key check went wrong: Didn't save the new credentials for the context $contextId. Status $status with response " . json_encode($result['response']));
				// $form->addError('rqcJournalId', print_r($result, true));  // debug
				return true;  // suppress the message configured at the FormValidator level
			case 403:
				$msg = array_key_exists('response', $result) ? $result['response']['error']
					: "something went horribly wrong with the RQC request";
				$form->addError('rqcJournalAPIKey', $msg);
				RqcLogger::logError("API Key check went wrong: Didn't save the new credentials for the context $contextId. Status $status with response " . json_encode($result['response']));
				// $form->addError('rqcJournalAPI', print_r($result, true));  // debug
				return true;  // suppress the message configured at the FormValidator level
			default:
				RqcLogger::logError("API Key check went wrong: Didn't save the new credentials for the context $contextId. Status $status with response " . json_encode($result['response']));
				return true;  // suppress the message configured at the FormValidator level
		}
	}
}

class RqcSettingsForm extends Form
{

	/** @var int */
	public int $_contextId;

	/** @var RqcPlugin */
	public RqcPlugin $_plugin;

	/**
	 * Constructor
	 * @param $plugin    RqcPlugin
	 * @param $contextId int  the OJS context (the OJS journal)
	 */
	function __construct($plugin, $contextId)
	{
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorRegExp($this, 'rqcJournalId', 'required',
			'plugins.generic.rqc.settingsform.rqcJournalIDInvalid',
			'/^[0-9]+$/'));
		$this->addCheck(new FormValidatorRegExp($this, 'rqcJournalAPIKey', 'required',
			'plugins.generic.rqc.settingsform.rqcJournalAPIKeyInvalid',
			'/^[0-9A-Za-z]+$/'));
		$this->addCheck(new RqcFormValidator($this, null, 'required', ""));
	}

	/**
	 * Initialize form data.
	 */
	function initData(): void
	{
		$this->_data = array(
			'rqcJournalId'     => $this->_plugin->getSetting($this->_contextId, 'rqcJournalId'),
			'rqcJournalAPIKey' => $this->_plugin->getSetting($this->_contextId, 'rqcJournalAPIKey'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData(): void
	{
		$this->readUserVars(array('rqcJournalId', 'rqcJournalAPIKey'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = NULL, $display = false): string
	{
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionArgs)
	{
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalId', trim($this->getData('rqcJournalId')), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalAPIKey', trim($this->getData('rqcJournalAPIKey')), 'string');
		RqcLogger::logInfo("API Key check successful: Saving the credentials rqcJournalId (" . trim($this->getData('rqcJournalId')) . ") and rqcJournalAPIKey for the context " . $this->_contextId);
		return parent::execute();
	}
}

?>
