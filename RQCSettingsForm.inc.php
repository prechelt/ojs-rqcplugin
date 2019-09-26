<?php

/**
 * @file plugins/generic/reviewqualitycollector/RQCSettingsForm.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RQCSettingsForm
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Form for journal managers to modify RQC plugin settings
 */

import('lib.pkp.classes.form.Form');

class RQCSettingsForm extends Form {

	/** @var int */
	var $_contextId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin RQCPlugin
	 * @param $contextId int  the OJS context (the OJS journal)
	 */
	function __construct($plugin, $contextId) {
		$this->_contextId = $contextId;
		$this->_plugin = $plugin;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
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
			'rqcJournalId' => $this->_plugin->getSetting($this->_contextId, 'rqcJournalId'),
			'rqcJournalKey' => $this->_plugin->getSetting($this->_contextId, 'rqcJournalKey'),
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
	function fetch($request, $template = NULL, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalId', trim($this->getData('rqcJournalId')), 'string');
		$this->_plugin->updateSetting($this->_contextId, 'rqcJournalKey', trim($this->getData('rqcJournalKey')), 'string');
		return parent::execute();
	}
}

?>
