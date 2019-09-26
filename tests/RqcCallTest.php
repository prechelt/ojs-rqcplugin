<?php

/**
 * @file plugins/generic/reviewqualitycollector/tests/RqcCallTest.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2018-2019 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class RqcCallTest
 * @ingroup plugins_generic_reviewqualitycollector
 *
 * @brief Test API calls to a reviewqualitycollector.org-like test server.
 */

require_once('lib/pkp/tests/phpunit-bootstrap.php');

//require_mock_env('env2'); // Required for mock app locale.

import('lib.pkp.tests.DatabaseTestCase');
//import('lib.pkp.classes.core.PKPRouter');
import('plugins.generic.reviewqualitycollector.classes.RqcCall');

class RqcCallTest extends DatabaseTestCase {

	protected function getAffectedTables() {
		return array();  //'rqc_delayed_calls');
	}

	protected function setUp() {
		parent::setUp();
		$this->rqccall = new RqcCall();
		//$this->rqcplugin = PluginRegistry::getPlugin('generic', 'rqcplugin');
	}

	public function testDoPost() {
		$response = $this->rqccall->do_post(1, "http://www.fu-berlin.de"); //, "nix");
		//self::assertEquals("output", $response);  // nonsense and incomplete
	}
}
?>
