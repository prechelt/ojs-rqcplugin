<?php

/**
 * @file plugins/generic/rqc/RqcPlugin.inc.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcPlugin
 * @ingroup plugins_generic_rqc
 *
 * @brief Review Quality Collector (RQC) plugin class
 */


/*  for OJS 3.4:
namespace APP\plugins\generic\rqc;
use APP\core\Application;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\OpenWindowAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
*/

// needed in OJS 3.3:
import('lib.pkp.classes.plugins.HookRegistry');
import('lib.pkp.classes.plugins.GenericPlugin');
import('classes.core.Application');
import('plugins.generic.rqc.RqcSettingsForm');
import('plugins.generic.rqc.classes.ReviewerOpting');
import('plugins.generic.rqc.classes.EditorActions');
import('plugins.generic.rqc.classes.EditorActions');

define('RQC_API_VERSION', '2023-09-06');  // the API documentation version last used during development
define('RQC_MHS_ADAPTER', 'https://github.com/prechelt/ojs-rqcplugin');  // the OJS version for which this code should work
define('RQC_PLUGIN_VERSION', '3.3.0');  // the OJS version for which this code should work
define('RQC_SERVER', 'https://reviewqualitycollector.org');
define('RQC_LOCALE', 'en');  // Plugin will enforce this locale internally
define('SUBMISSION_EDITOR_TRIGGER_RQCGRADE', 21);  // pseudo-decision option


/**
 * Class RqcPlugin.
 * Provides a settings dialog (for RQC journal ID and Key);
 *  asks reviewers to opt in or out (once per year per journal) when submitting a review;
 *  adds an editor menu entry to send review data to RQC (to start the grading process manually);
 *  notifies RQC upon the submission acceptance decision (to start the
 *  grading process automatically or extend it with additional reviews, if any);
 *  if sending reviewing data fails, repeats it via cron and a queue.
 */
class RqcPlugin extends GenericPlugin
{
	public function __construct()
	{
		parent::__construct();
		$this->stderr = fopen('php://stderr', 'w');  # print to php -S console stream
	}

	public function _print($msg)
	{
		# print to php -S console stream (during development only)
		if (RqcPlugin::has_developer_functions()) {
			fwrite($this->stderr, $msg);
		}
	}

	/**
	 * @copydoc Plugin::register()
	 *
	 * @param null|mixed $mainContextId
	 */
	public function register($category, $path, $mainContextId = null): bool
	{
		import('lib.pkp.classes.plugins.HookRegistry');
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled()) {
			if ($this->hasValidRqcIdKeyPair()) {
				HookRegistry::register(
					'TemplateResource::getFilename',
					array($this, '_overridePluginTemplates')
				); // needed by ReviewerOpting (automatically override all the templates of ojs with templates set by this plugin. In this case: /reviewer/review/reviewerRecommendation.tpl)
				(new ReviewerOpting())->register();
				(new EditorActions())->register();
			}
			if (RqcPlugin::has_developer_functions()) {
				HookRegistry::register(
					'LoadHandler',
					array($this, 'cb_setupDevHelperHandler')
				);
			}
		}
		return $success;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName(): string
	{
		return __('plugins.generic.rqc.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription(): string
	{
		return __('plugins.generic.rqc.description');
	}

	/**
	 * Disable the settings form in the site-wide plugins list
	 */
	public function isSitePlugin(): bool
	{
		return false;  // our settings are strictly journal-specific
	}

	/**
	 * Get a list of link actions for plugin management.
	 *
	 * @param $request PKPRequest
	 * @param $actionArgs array The list of action args to be included in request URLs.
	 *
	 * @return array List of LinkActions
	 */
	public function getActions($request, $actionArgs): array
	{
		//----- get existing actions, stop if not enabled:
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled() || !$request->getContext()) {  // RQC settings are journal-specific
			return $actions;
		}
		//----- add settings dialog:
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.LinkAction');
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$additions = [];
		$additions[] = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);
		//----- perhaps add development-only stuff:
		if (RqcPlugin::has_developer_functions()) {
			//		import('lib.pkp.classes.linkAction.request.OpenWindowAction');
			//		$additions[] = new LinkAction(
			//			'example_request2',
			//			new OpenWindowAction(
			//				$router->url($request, /*Application::*/ ROUTE_PAGE, 'MySuperHandler', 'myop', null, ['my', 'array'])
			//			),
			//			'(example_request2)',
			//			null
			//		);
		}
		//----- return:
		$actions = array_merge($additions, $actions);
		return $actions;
	}

	public static function has_developer_functions(): bool
	{
		return Config::getVar('rqc', 'activate_developer_functions', false);
	}

	public static function rqc_server(): string
	{
		return Config::getVar('rqc', 'rqc_server', RQC_SERVER);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	public function manage($args, $request): JSONMessage
	{
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				$form = new RqcSettingsForm($this, $context->getId());
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
		}
		return parent::manage($args, $request);
	}

	//========== Callbacks ==========

	/**
	 * Installs Handlers for ad-hoc utilities, used during development only.
	 */
	public function cb_setupDevHelperHandler($hookName, $params)
	{
		$page =& $params[0];
		$op =& $params[1];
		// $this->_print("### cb_setupDevHelperHandler: page='$page' op='$op'\n");
		if (self::has_developer_functions() && $page == 'rqcdevhelper') {
			$this->import('pages/DevHelperHandler');
			define('HANDLER_CLASS', 'DevHelperHandler');
			return true;  // this hook's handling is done
		}
		return false;  // continue calling hook functions for this hook
	}

	//========== Helpers ==========

	/**
	 * Workaround for $request->getQueryArray().
	 * In OJS 3.3.0, that call produces the error
	 * "Application::getContextList() cannot be called statically"
	 */
	function getQueryArray($request)
	{
		$queryString = $request->getQueryString();
		$requestArgs = array();
		if (isset($queryString)) {
			parse_str($queryString, $requestArgs);
		}
		return $requestArgs;
	}

	/**
	 * returns true if the RQC-Key and ID are set in the db
	 * these should only be set if the check for the id-key pair was successfully done in the past (else the entry shouldn't be there or empty)
	 */
	public function hasValidRqcIdKeyPair(): bool
	{
		// PluginRegistry::getPlugin('generic', 'rqcplugin')->hasValidRqcIdKeyPair()
		$request = Application::get()->getRequest();
		$contextId = $request->getContext()->getId();
		$hasId = $this->getSetting($contextId, 'rqcJournalId');
		$hasKey = $this->getSetting($contextId, 'rqcJournalAPIKey');
		$this->_print("\nhasValidRqcIdKeyPair\nId: ".$hasId."\t\tKey: ".$hasKey."\nReturns: ValidkeyPair ".(($hasId and $hasKey) ? "True" : "False")."\n\n");
		return ($hasId and $hasKey);
	}
}
