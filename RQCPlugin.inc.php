<?php

/**
 * @file plugins/generic/reviewqualitycollector/RQCPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RQCPlugin
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Review Quality Collector (RQC) plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.site.VersionCheck');

define ('DEBUG', false);

function rqctrace($msg) {
	if (DEBUG)
		trigger_error($msg, E_USER_WARNING);
}
define('RQC_PLUGIN_VERSION', '3.1.2');  // the OJS version for which this code should work
define('RQC_SERVER', 'https://reviewqualitycollector.org');
define('RQC_ROOTCERTFILE', 'plugins/generic/reviewqualitycollector/DeutscheTelekomRootCA2.pem');
define('RQC_LOCALE', 'en_US');
define('SUBMISSION_EDITOR_TRIGGER_RQCGRADE', 21);  // pseudo-decision option


/**
 * Class RQCPlugin.
 * Provides a settings dialog (for RQC journal ID and Key),
 * adds a menu entry to send review data to RQC (to start the grading process manually),
 * notifies RQC upon the submission acceptance decision (to start the
 * grading process automatically or extend it with additional reviews, if any),
 * if sending reviewing data fails, repeats it via cron and a queue.
 */
class RQCPlugin extends GenericPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled()) {
			HookRegistry::register('EditorAction::modifyDecisionOptions',
				array($this, 'cb_modifyDecisionOptions'));
			HookRegistry::register('EditorAction::recordDecision',
				array($this, 'cb_recordDecision'));
			HookRegistry::register('LoadComponentHandler',
				array($this, 'cb_editorActionRqcGrade'));
			if (RQCPlugin::has_developer_functions()) {
				HookRegistry::register('LoadHandler',
					array($this, 'cb_setupDevHelperHandler'));
			}
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.reviewqualitycollector.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.reviewqualitycollector.description');
	}

	/**
	 * Get a list of link actions for plugin management.
	 * @param request PKPRequest
	 * @param $actionArgs array The list of action args to be included in request URLs.
	 * @return array List of LinkActions
	 */
	function getActions($request, $actionArgs) {
		//----- get existing actions, stop if not enabled:
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) {
			return $actions;
		}
		//----- add settings dialog:
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$additions = array();
		$additions[] = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url($request, null, null, 'manage', null,
					array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);
		//----- perhaps return:
		if (!RQCPlugin::has_developer_functions()) {
			$actions = array_merge($additions, $actions);
			return $actions;
		}
		//----- TODO add developers-only stuff:
		$additions[] = new LinkAction(
			'example_request',
			new AjaxModal(
				$router->url($request, null, null, 'manage', null,
					array('verb' => 'example_request', 'plugin' => $this->getName(), 'category' => 'generic')),
				$this->getDisplayName()
			),
			'(example_request)',
			null
		);
		import('lib.pkp.classes.linkAction.request.OpenWindowAction');
		$additions[] = new LinkAction(
			'example_request2',
			new OpenWindowAction(
				$router->url($request, ROUTE_PAGE, 'MySuperHandler', 'myop', 'mypath', array('my','array'))
			),
			'(example_request2)',
			null
		);
		//----- return:
		$actions = array_merge($additions, $actions);
		return $actions;
	}

	static function has_developer_functions() {
		return Config::getVar('debug', 'activate_developer_functions', false);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
				$templateMgr = TemplateManager::getManager($request);
				$this->import('RQCSettingsForm');
				$form = new RQCSettingsForm($this, $context->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$result = new JSONMessage(true);
						return $result;
					}
				} else {
					$form->initData();
				}
				$result = new JSONMessage(true, $form->fetch($request));
				return $result;
			case 'example_request':
				// TODO
		}
		return parent::manage($args, $request);
	}

	/**
	 * Get the handler path for this plugin.
	 */
	function getHandlerPath() {
		return $this->getPluginPath() . '/pages/';
	}

	//========== Callbacks ==========

	/**
	 * Callback for LoadComponentHandler.
	 */
	function cb_editorActionRqcGrade($hookName, $args) {
		$component =& $args[0];
		$op =& $args[1];
		if ($component == 'modals.editorDecision.EditorDecisionHandler' &&
				$op == 'rqcGrade') {
			$component = 'plugins.generic.reviewqualitycollector.components.editorDecision.RqcEditorDecisionHandler';
			return true;  // no more handling needed
		}
		return false;  // proceed with normal processing
	}


	/**
	 * Callback for EditorAction::modifyDecisionOptions.
	 */
	function cb_modifyDecisionOptions($hookName, $args) {
		$context = $args[0];
		$stageId = $args[1];
		$makeDecision =& $args[2];
		$decisionOpts =& $args[3];
		if ($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			//----- add button for RQC grading:
			$decisionOpts[SUBMISSION_EDITOR_TRIGGER_RQCGRADE] = array(
				'operation' => 'rqcGrade',
				'name' => 'rqcGradeName',
				'title' => 'plugins.generic.reviewqualitycollector.editoraction.grade.button',
			);
		}
		return false;  // proceed with other callbacks, if any
	}

	/**
	 * Callback for EditorAction::recordDecision.
	 */
	function cb_recordDecision($hookName, $args) {
		import('plugins.generic.reviewqualitycollector.classes.RqcData');
		$GO_ON = true;  // false continues processing, true stops it (needed during development).
		$submission =& $args[0];
		$decision =& $args[1];
		$result =& $args[2];
		$is_recommendation =& $args[3];
		rqctrace("cb_recordDecision called", E_USER_WARNING);
		if ($is_recommendation || !RqcOjsData::is_decision($decision)) {
			return $GO_ON;
		}
		// act on decision:
		rqctrace("cb_recordDecision calls RQC", E_USER_WARNING);
		return $GO_ON;
	}

	/**
	 * Installs Handlers for our look-at-an-RQC-request page at /rqcdevhelper/spy
	 * and our make-review-case (MRC) request page at /rqcdevhelper/mrc.
	 * (See setupBrowseHandler in plugins/generic/browse for tech information.)
	 */
	function cb_setupDevHelperHandler($hookName, $params) {
		$page =& $params[0];
		if ($page == 'rqcdevhelper') {
			define('RQC_PLUGIN_NAME', $this->getName());
			define('HANDLER_CLASS', 'DevHelperHandler');
			$handlerFile =& $params[2];
			$handlerFile = $this->getHandlerPath() . 'DevHelperHandler.inc.php';
		}
	}
}

