<?php

/**
 * @file plugins/generic/rqc/RqcSettingsForm.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcSettingsForm
 * @ingroup plugins_generic_rqc
 *
 * @brief Form for journal managers to modify RQC plugin settings
 */


/* for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorRegExp;
*/
import('lib.pkp.classes.form.Form');
import('plugins.generic.rqc.classes.RqcCall');
import('plugins.generic.rqc.classes.RqcDevHelper');

class RqcFormValidator extends FormValidator {
	use RqcDevHelper;
	function isValid(): bool {
		$form = $this->_form;
		$hostUrl = $form->_plugin->rqcServer();
		$rqcJournalId = $form->getData('rqcJournalId');
		$rqcJournalAPIKey = $form->getData('rqcJournalAPIKey');
		$result = RqcCall::callMhsApikeycheck($hostUrl, $rqcJournalId, $rqcJournalAPIKey,
			!$form->_plugin->hasDeveloperFunctions());
		//$this->_print("\n".print_r($result, true)."\n");
		$status = $result['status'];
		if ($status == 200) {
			return true;  // all is fine
		}
		if ($status == 400 || $status == 404) {
			$msg = array_key_exists('response', $result) ? $result['response']['error']
				                                             : "something went wrong with the RQC request";
			$form->addError('rqcJournalId', $msg);
			// $form->addError('rqcJournalId', print_r($result, true));  // debug
			return true;  // suppress the message configured at the FormValidator level
		}
		if ($status == 403) {
			$msg = array_key_exists('response', $result) ? $result['response']['error']
				                                             : "something went horribly wrong with the RQC request";
			$form->addError('rqcJournalAPIKey', $msg);
			// $form->addError('rqcJournalAPI', print_r($result, true));  // debug
			return true;  // suppress the message configured at the FormValidator level
		}
		if ($status >= 500) {
			$form->addError('rqcJournalId', "Internal server error at RQC with status " . $status);
			return true;  // suppress the message configured at the FormValidator level
		}
		return true;  // suppress the message configured at the FormValidator level
	}
}

class RqcSettingsForm extends Form {

	/** @var int */
	var int $_contextId;

	/** @var RqcPlugin */
	var RqcPlugin $_plugin;

	/**
	 * Constructor
	 * @param $plugin RqcPlugin
	 * @param $contextId int  the OJS context (the OJS journal)
	 */
	function __construct($plugin, $contextId) {
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
		$this->addCheck(new RqcFormValidator($this, null, 'required',""));
	}

	/**
	 * Initialize form data.
	 */
	function initData(): void {
		$this->_data = array(
			'rqcJournalId' => $this->_plugin->getSetting($this->_contextId, 'rqcJournalId'),
			'rqcJournalAPIKey' => $this->_plugin->getSetting($this->_contextId, 'rqcJournalAPIKey'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData(): void {
		$this->readUserVars(array('rqcJournalId', 'rqcJournalAPIKey'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = NULL, $display = false): string {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionArgs) {
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalId', trim($this->getData('rqcJournalId')), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalAPIKey', trim($this->getData('rqcJournalAPIKey')), 'string');
		return parent::execute();
	}
}

?>
