<?php

/**
 * @file plugins/generic/reviewqualitycollector/RQCPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RQCPlugin
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Review Quality Collector (RQC) plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

define ('DEBUG', false);

function rqctrace($msg) {
	if (DEBUG)
		trigger_error($msg, E_USER_WARNING);
}
rqctrace("RQCPlugin.inc.php loaded!", E_USER_WARNING);

define('SUBMISSION_EDITOR_TRIGGER_RQCGRADE', 21);  // pseudo-decision option


/**
 * Class RQCPlugin.
 * Provides a settings dialog (for RQC journal ID and Key),
 * adds a menu entry to send review data to RQC (to start the grading process manually),
 * and notifies RQC upon the submission acceptance decision (to start the
 * grading process automatically or extend it with additional reviews,
 * if any).
 */
class RQCPlugin extends GenericPlugin {

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		rqctrace("RQCPlugin::register called");
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled()) {
			rqctrace("RQC HookRegistry::register called");
			HookRegistry::register('EditorAction::modifyDecisionOptions',
				array($this, 'cb_modifyDecisionOptions'));
			HookRegistry::register('EditorAction::recordDecision',
				array($this, 'cb_recordDecision'));
			HookRegistry::register('LoadComponentHandler',
				array($this, 'cb_editorActionRqcGrade'));
			if (Config::getVar('debug', 'activate_developer_functions', false)) {
				HookRegistry::register('LoadHandler',
					array($this, 'cb_setupSpyHandler'));
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
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		import('lib.pkp.classes.linkAction.request.OpenWindowAction');
		$result = array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null,
							array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
		// TODO:
		if (Config::getVar('debug', 'activate_developer_functions', false)) {
			$result[] =	new LinkAction(
				'example_request',
				new AjaxModal(
					$router->url($request, null, null, 'manage', null,
						array('verb' => 'example_request', 'plugin' => $this->getName(), 'category' => 'generic')),
					$this->getDisplayName()
				),
				'(example_request)',
				null
			);
			$result[] =	new LinkAction(
				'example_request2',
				new OpenWindowAction(
					$router->url($request, ROUTE_PAGE, 'MySuperHandler', 'myop', 'mypath', array('my','array'))
				),
				'(example_request2)',
				null
			);

		}
		return $result;
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
				$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));
				$this->import('RQCSettingsForm');
				$form = new RQCSettingsForm($this, $context->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true, $form->fetch($request));
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
			case 'example_request':
				// TODO
		}
		return parent::manage($args, $request);
	}

	/**
	 * @copydoc PKPPlugin::getTemplatePath
	 */
	function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore) . 'templates/';
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
		$submission =& $args[0];
		$editorDecision =& $args[1];
		// TODO: act on decision
	}

	/**
	 * Installs Handler class for our look-at-an-RQC-request page.
	 * (See setupBrowseHandler in plugins/generic/browse for tech information.)
	 */
	function cb_setupSpyHandler($hookName, $params) {
		rqctrace("setupSpyHandler!");
		$page =& $params[0];
		if ($page == 'rqcspy') {
			define('RQC_PLUGIN_NAME', $this->getName());
			define('HANDLER_CLASS', 'SpyHandler');
			$handlerFile =& $params[2];
			$handlerFile = $this->getHandlerPath() . 'SpyHandler.inc.php';
		}

	}

}
?>
