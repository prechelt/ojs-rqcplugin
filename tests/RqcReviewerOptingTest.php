<?php

/**
 * @file    plugins/generic/rqc/tests/RqcReviewerOptingTest.php
 *
 * Copyright (c) 2022-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @class   RqcReviewerOptingTest
 * @ingroup plugins_generic_rqc
 *
 * @brief   Test reviewer opt-in/opt-out functionality.
 */

require_once('lib/pkp/tests/phpunit-bootstrap.php');
//require_mock_env('env2'); // Required for mock app locale.

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.db.DAORegistry');
//import('lib.pkp.classes.core.PKPRouter');

import('plugins.generic.rqc.classes.ReviewerOpting');
import('plugins.generic.rqc.tests.helpers');

class RqcReviewerReviewerOptingTest extends DatabaseTestCase
{

	protected function getAffectedTables()
	{
		return array('users', 'user_settings');
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->reviewer11 = make_user("reviewer11");
		$this->assertTrue($this->reviewer11->getId() > 0);  // ensure that make_user() writes to DB
	}

	public function testReviewerOpting()
	{
		$context = 1;  // fictive, does not really exist
		$user = $this->reviewer11;
		$opting = new ReviewerOpting();
		$this->expectOutputString('');  // report any debugging output we print during development
		//----- check undefined initial case:
		$status = $opting->getStatus($context, $user, !RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $status);
		$status = $opting->getStatus($context, $user, RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $status);
		//----- check storing IN preliminarily:
		$opting->setStatus($context, $user, RQC_OPTING_STATUS_IN, RQC_PRELIM_OPTING);
		$status = $opting->getStatus($context, $user, RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_IN, $status);
		$status = $opting->getStatus($context, $user);
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $status);
		//----- check storing OUT preliminarily:
		$opting->setStatus($context, $user, RQC_OPTING_STATUS_OUT, RQC_PRELIM_OPTING);
		$status = $opting->getStatus($context, $user, RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_OUT, $status);
		$status = $opting->getStatus($context, $user);
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $status);
		//----- check storing IN:
		$opting->setStatus($context, $user, RQC_OPTING_STATUS_IN);
		$status = $opting->getStatus($context, $user, RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_IN, $status);
		$status = $opting->getStatus($context, $user);
		$this->assertEquals(RQC_OPTING_STATUS_IN, $status);
		//----- check storing OUT:
		$opting->setStatus($context, $user, RQC_OPTING_STATUS_OUT);
		$status = $opting->getStatus($context, $user, RQC_PRELIM_OPTING);
		$this->assertEquals(RQC_OPTING_STATUS_OUT, $status);
		$status = $opting->getStatus($context, $user);
		$this->assertEquals(RQC_OPTING_STATUS_OUT, $status);
	}
}


class RqcReviewerOptingFormTest extends DatabaseTestCase
{

	protected function getAffectedTables()
	{
		// complete DB reset is available by returning PKP_TEST_ENTIRE_DB
		return array(
			'authors',
			'publications', 'publication_settings',
			'review_assignments',
			'submissions', 'submission_settings',
			'users', 'user_settings'
		);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->context = 1;  // fictive, does not really exist
		$this->author11 = make_user("author11");
		$this->reviewer11 = make_user("reviewer11");
		$this->reviewer12 = make_user("reviewer12");
		$this->submission = make_reviewable_submission(
			$this->context, [$this->author11],
			[$this->reviewer11, $this->reviewer12]);
	}

	public function testOptingFormGET()
	{
		// get Step3Form and look for opting question

		// TODO
	}

	public function testOptingFormPOST()
	{
		// submit Step3Form and look for stored opting status
	}

	protected function tearDown(): void
	{
		parent::tearDown();  // restore previous DB contents
	}
}

?>
