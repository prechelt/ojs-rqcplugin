<?php

/**
 * @file plugins/generic/reviewqualitycollector/RQCSettingsForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RQCSettingsForm
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Form for journal managers to modify RQC plugin settings
 */

import('lib.pkp.classes.form.Form');

class RQCSettingsForm extends Form {

	/** @var int */
	var $_journalId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin ReviewQualityCollectorPlugin
	 * @param $journalId int
	 */
	function __construct($plugin, $journalId) {
		$this->_journalId = $journalId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplatePath() . 'settingsForm.tpl');

		$this->addCheck(new FormValidatorRegExp($this, 'rqcJournalId', 'required',
								'plugins.generic.reviewqualitycollector.settingsform.rqcJournalIDInvalid',
								'/^[0-9]+$/'));
		$this->addCheck(new FormValidatorRegExp($this, 'rqcJournalKey', 'required',
								'plugins.generic.reviewqualitycollector.settingsform.rqcJournalKeyInvalid',
								'/^[0-9A-Za-z]+$/'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$this->_data = array(
			'rqcJournalId' => $this->_plugin->getSetting($this->_journalId, 'rqcJournalId'),
			'rqcJournalKey' => $this->_plugin->getSetting($this->_journalKey, 'rqcJournalKey'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('rqcJournalId', 'rqcJournalKey'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$this->_plugin->updateSetting($this->_journalId, 'rqcJournalId', trim($this->getData('rqcJournalId')), 'string');
		$this->_plugin->updateSetting($this->_journalKey, 'rqcJournalKey', trim($this->getData('rqcJournalKey')), 'string');
	}
}

?>
