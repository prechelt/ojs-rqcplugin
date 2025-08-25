<?php

namespace APP\plugins\generic\rqc\classes;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use PKP\observers\events\DecisionAdded;
use PKP\plugins\Hook;
use APP\facades\Repo;

use APP\plugins\generic\rqc\pages\RqcCallHandler;
use APP\plugins\generic\rqc\classes\RqcDevHelper;


/**
 * RQC adapter parts revolving around editorial decisions using the hooking mechanism.
 *
 * @ingroup plugins_generic_rqc
 */
class EditorActions
{
	/**
	 * Register callbacks. This is to be called from the plugin's register().
	 */
	public function register(): void
    {
        Hook::add('LoadComponentHandler', $this->callbackEditorActionRqcGrade(...));
        Hook::add('Workflow::Decisions', $this->callbackModifyDecisionOptions(...)); // TODO 3.5: https://docs.pkp.sfu.ca/dev/release-notebooks/en/3.4-release-notebook#editoraction
        Hook::add('LoadHandler', $this->callbackPageHandlers(...));
        Hook::add('Decision::types', $this->callbackAddDecisionOption(...));
        Event::subscribe($this);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(DecisionAdded::class, self::class . '@handleDecisionAdded');
    }

	/**
	 * Callback for LoadComponentHandler.
	 * Directs clicks of button "RQC-Grade the Reviews" to RqcEditorDecisionHandler.
	 */
	public function callbackEditorActionRqcGrade($hookName, $args): bool
	{
		$component = &$args[0];
		$op = &$args[1];
		if ($component == 'modals.editorDecision.EditorDecisionHandler' &&
			$op == 'rqcGrade') {
			$component = 'plugins.generic.rqc.components.editorDecision.RqcEditorDecisionHandler';
			return true;  // no more handling needed, RqcEditorDecisionHandler will do the work
		}
		return false;  // proceed with normal processing
	}

    /**
     * Decision::types in Repository::getDecisionTypes()
     */
    public function callbackAddDecisionOption($hookName, $args): bool
    {
        /** @var Collection $decisionTypes */
        $decisionTypes = &$args[0];
        $decisionTypes = $decisionTypes->push(new ExplicitCallDecisionType());
        return false; // proceed with normal processing
    }


	/**
	 * Callback for Workflow::Decisions.
	 * Adds button "RQC-Grade the Reviews" to the Workflow page.
	 */
	public function callbackModifyDecisionOptions($hookName, $args): bool
	{
        $decisionTypes = &$args[0]; /** @var $decisionTypes array */
        $stageId = $args[1]; /** @var $stageId int */

        //array_splice($decisionTypes, 2, 1);
        $decisionTypes[] = new ExplicitCallDecisionType();
		return false;  // proceed with other callbacks, if any
	}

	/**
	 * Callback for installing page handlers.
	 */
	public function callbackPageHandlers($hookName, $params): bool
	{
		$page =& $params[0];
		$op =& $params[1];
		if ($page == 'rqccall') {
			define('HANDLER_CLASS', 'RqcCallHandler');
			return true;
		}
		return false;
	}
}
