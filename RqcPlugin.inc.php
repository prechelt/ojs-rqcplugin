<?php

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

import('lib.pkp.classes.plugins.HookRegistry');
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('classes.core.Application');
import('plugins.generic.rqc.RqcSettingsForm');
import('plugins.generic.rqc.classes.ReviewerOpting');
import('plugins.generic.rqc.classes.EditorActions');
import('plugins.generic.rqc.classes.RqcDevHelper');
import('plugins.generic.rqc.classes.DelayedRqcCallSchemaMigration');
import('plugins.generic.rqc.classes.DelayedRqcCallDAO');

define('RQC_API_VERSION', '2023-09-06');  // the API documentation version last used during development
define('RQC_MHS_ADAPTER', 'https://github.com/prechelt/ojs-rqcplugin');  // the OJS version for which this code should work
define('RQC_PLUGIN_VERSION', '3.3.0');  // the OJS version for which this code should work
define('RQC_SERVER', 'https://reviewqualitycollector.org');
define('RQC_LOCALE', 'en');  // Plugin will enforce this locale internally
define('SUBMISSION_EDITOR_TRIGGER_RQCGRADE', 21);  // pseudo-decision option


/**
 * Review Quality Collector (RQC) plugin class:
 * Provides a settings dialog (for RQC journal ID and Key);
 *  - asks reviewers to opt in or out (once per year per journal) when submitting a review;
 *  - adds an editor menu entry to send review data to RQC (to start the grading process manually);
 *  - notifies RQC upon the submission acceptance decision (to start the
 *  grading process automatically or extend it with additional reviews, if any);
 *  - if sending reviewing data fails, repeats it via cron and a queue.
 *
 * @ingroup plugins_generic_rqc
 */
class RqcPlugin extends GenericPlugin
{
	/**
	 * @copydoc Plugin::register()
	 *
	 * @param null|mixed $mainContextId
	 */
	public function register($category, $path, $mainContextId = null): bool
	{
		$success = parent::register($category, $path, $mainContextId);
		if ($success && $this->getEnabled()) { // register only if the plugin is usable
			if ($this->hasValidRqcIdKeyPair()) {  // register only if the credentials for sending the data to RQC are present
				HookRegistry::register(
					'TemplateResource::getFilename',
					array($this, '_overridePluginTemplates')
				); // needed by ReviewerOpting (automatically override all the templates of ojs with templates set by this plugin. In this case: /reviewer/review/step3.tpl)
				(new ReviewerOpting())->register();
				(new EditorActions())->register();
				DAORegistry::registerDAO('DelayedRqcCallDAO', new DelayedRqcCallDAO());
			}
			if (RqcPlugin::hasDeveloperFunctions()) {  // register the devFunctions independent of RQC-ID-Key-Pair
				HookRegistry::register(
					'LoadHandler',
					array($this, 'callbackSetupRqcDevHelperHandler')
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
	 * @param $request    PKPRequest
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
		if (RqcPlugin::hasDeveloperFunctions()) {
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

	public static function hasDeveloperFunctions(): bool
	{
		return Config::getVar('rqc', 'activate_developer_functions', false);
	}

	public static function rqcServer(): string
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
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * @copydoc Plugin::getInstallMigration()
	 */
	public function getInstallMigration(): DelayedRqcCallSchemaMigration
	{
		return new DelayedRqcCallSchemaMigration();
	}

	//========== Callbacks ==========

	/**
	 * Installs Handlers for ad-hoc utilities, used during development only.
	 * => https://base.url/context/rqcdevhelper/method1/arg0/arg1
	 * => rqcdevhelperhandler is the router; method1 is the method to be called with the args
	 */
	public function callbackSetupRqcDevHelperHandler($hookName, $params)
	{
		$page =& $params[0];
		$op =& $params[1];
		// RqcDevHelper::writeToConsole("### callbackSetupRqcDevHelperHandler: page='$page' op='$op'\n");
		if (self::hasDeveloperFunctions() && $page == 'rqcdevhelper') {
			$this->import('pages/RqcDevHelperHandler');
			define('HANDLER_CLASS', 'RqcDevHelperHandler');
			return true;  // this hook's handling is done
		}
		return false;  // continue calling hook functions for this hook
	}

	/**
	 * to block an infinite loop
	 * with _overridePluginTemplates() "template:step3" and "core:step3" are both replaced by "plugin:step3"
	 * (if the file is present)
	 * without "plugin:step3" this happens: "template:step3" includes "core:step3" and that is displayed
	 * but with "plugin:step3" this happens: "template:step3" is overwritten by "plugin:step3"
	 * which also includes "core:step3" (but that is overwritten as well to be "plugin:step3" to go into infinite recursion)
	 * so this "if" blocks "plugin:step3" overwriting "core:step3"
	 */
	public function _overridePluginTemplates($hookName, $args): void
	{
		if (!str_contains($args[0], "lib/pkp/templates/reviewer/review/step3.tpl")) {
			parent::_overridePluginTemplates($hookName, $args);
		}
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
		// RqcDevHelper::writeToConsole("\nhasValidRqcIdKeyPair\nId: ".$hasId."\t\tKey: ".$hasKey."\nReturns: ValidkeyPair ".(($hasId and $hasKey) ? "True" : "False")."\n\n");
		return ($hasId and $hasKey);
	}
}
