<?php

namespace APP\plugins\generic\rqc\tests;

use APP\plugins\generic\rqc\classes\DelayedRqcCall\DelayedRqcCallDAO;
use APP\plugins\generic\rqc\RqcPlugin;
use APP\plugins\generic\rqc\tests\RqcTestHelper;
use APP\plugins\generic\rqc\classes\RqcReviewerOpting\RqcReviewerOptingDAO;
use APP\plugins\generic\rqc\classes\ReviewerOpting;

use PKP\db\DAORegistry;
use PKP\tests\DatabaseTestCase;
use PKP\tests\PKPTestHelper;
use PKP\tests\plugins\PluginTestCase;

//require_mock_env('env2'); // Required for mock app locale.

/**
 * Test reviewer opt-in/opt-out functionality.
 *
 * @ingroup plugins_generic_rqc
 */
class RqcReviewerOptingTest extends PluginTestCase
{

	protected function getAffectedTables(): int|array
    {
        // complete DB reset is available by returning PKP_TEST_ENTIRE_DB
        //return PKPTestHelper::PKP_TEST_ENTIRE_DB; // doesn't work for me
        return array( // the order matters!!
            'rqc_reviewer_opting_settings', 'rqc_reviewer_opting',
            'review_assignments',
            'review_rounds',
            'author_settings', 'authors',
            'user_settings', 'users',
            'publication_settings', 'publications',
            'submission_settings', 'submissions',
            'journals', 'journal_settings'
        );
	}

	protected function setUp(): void
	{
		parent::setUp();
        $helper = new RqcTestHelper();
		$this->reviewer11 = $helper->make_and_store_user("reviewer11");
        $this->request11 = $this->mockRequest("testJournal/test", $this->reviewer11->getId());
        $this->context11 = $helper->make_and_store_context("testJournal", $this->request11);
        $this->submission11 = $helper->make_and_store_reviewable_submission($this->context11, [], [$this->reviewer11]);
        $this->submission12 = $helper->make_and_store_reviewable_submission($this->context11, [], [$this->reviewer11]);
		$this->assertTrue($this->reviewer11->getId() > 0);  // ensure that make_and_store_user() writes to DB
        $this->assertTrue($this->context11->getId() > 0);  // ensure that make_and_store_context() writes to DB
        $this->assertTrue($this->submission11->getId() > 0);  // ensure that make_and_store_reviewable_submission() writes to DB
        DAORegistry::registerDAO('DelayedRqcCallDAO', new DelayedRqcCallDAO());
        DAORegistry::registerDAO('RqcReviewerOptingDAO', new RqcReviewerOptingDAO());
	}

	public function testReviewerOpting()
	{
		$contextId = $this->context11->getId();  // fictive, does not really exist
		$user = $this->reviewer11;
        $submission1 = $this->submission11;
        $submission2 = $this->submission12;

		$opting = new ReviewerOpting();
		$this->expectOutputString('');  // report any debugging output we print during development

        //----- check undefined initial case:
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
		$this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertTrue($opting->userOptingRequired($contextId, $user));
        $this->assertFalse($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

        //----- check storing IN preliminarily:
		$opting->setOptingStatus($contextId, $submission1, $user, RQC_OPTING_STATUS_IN, RQC_PRELIM_OPTING);

        $this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertTrue($opting->userOptingRequired($contextId, $user));
        $this->assertFalse($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

        //----- check storing OUT preliminarily:
        $opting->setOptingStatus($contextId, $submission1, $user, RQC_OPTING_STATUS_OUT, RQC_PRELIM_OPTING);

        $this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertTrue($opting->userOptingRequired($contextId, $user));
        $this->assertFalse($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

		//----- check storing IN:
        $opting->setOptingStatus($contextId, $submission1, $user, RQC_OPTING_STATUS_IN, !RQC_PRELIM_OPTING, date('Y'));

        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertFalse($opting->userOptingRequired($contextId, $user));
        $this->assertFalse($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

		//----- check storing OUT:
        $opting->setOptingStatus($contextId, $submission1, $user, RQC_OPTING_STATUS_OUT, !RQC_PRELIM_OPTING);

        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertFalse($opting->userOptingRequired($contextId, $user));
        $this->assertTrue($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

        //----- check opting with other years and submissions
        $this->assertTrue($opting->userOptingRequired($contextId, $user, "2024"));

        $opting->setOptingStatus($contextId, $submission2, $user, RQC_OPTING_STATUS_IN, RQC_PRELIM_OPTING, "2024");

        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING, "2024"));
        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING, "2024"));
        $this->assertFalse($opting->userOptingRequired($contextId, $user));
        $this->assertTrue($opting->userOptingRequired($contextId, $user, "2024"));
        $this->assertTrue($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

        $opting->setOptingStatus($contextId, $submission2, $user, RQC_OPTING_STATUS_IN, !RQC_PRELIM_OPTING, "2024"); // and now without preliminary opting

        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING, "2024"));
        $this->assertEquals(RQC_OPTING_STATUS_IN, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING, "2024"));
        $this->assertFalse($opting->userOptingRequired($contextId, $user));
        $this->assertFalse($opting->userOptingRequired($contextId, $user, "2024"));
        $this->assertTrue($opting->isOptedOut($submission1, $user));
        $this->assertFalse($opting->isOptedOut($submission2, $user));

        //----- test the deletion of the 2024er entry and add a 2025 entry both of submission2
        $rqcReviewerOptingDAO = new rqcReviewerOptingDAO();
        $rqcReviewerOptingDAO->delete($rqcReviewerOptingDAO->getReviewerOpting($contextId, $submission2->getId(), $user->getId(), "2024"));
        $opting->setOptingStatus($contextId, $submission2, $user, RQC_OPTING_STATUS_OUT, !RQC_PRELIM_OPTING);

        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_OUT, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING));
        $this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, !RQC_PRELIM_OPTING, "2024"));
        $this->assertEquals(RQC_OPTING_STATUS_UNDEFINED, $opting->getUserOptingStatus($contextId, $user, RQC_PRELIM_OPTING, "2024"));
        $this->assertFalse($opting->userOptingRequired($contextId, $user));
        $this->assertTrue($opting->userOptingRequired($contextId, $user, "2024"));
        $this->assertTrue($opting->isOptedOut($submission1, $user));
        $this->assertTrue($opting->isOptedOut($submission2, $user));
    }
}


class RqcReviewerOptingFormTest extends DatabaseTestCase
{

	protected function getAffectedTables(): int|array
    {
        //return PKPTestHelper::PKP_TEST_ENTIRE_DB;
        // complete DB reset is available by returning PKP_TEST_ENTIRE_DB
		return array(
            'rqc_reviewer_opting_settings', 'rqc_reviewer_opting',
            'review_assignments',
            'review_rounds',
            'author_settings', 'authors',
            'user_settings', 'users',
            'publication_settings', 'publications',
            'submission_settings', 'submissions',
            'journals', 'journal_settings'
		);
	}

	protected function setUp(): void
	{
		parent::setUp();
        $helper = new RqcTestHelper();
        $request = $this->mockRequest();
        $this->context = $helper->make_and_store_context("testJournal", $request);
		$this->author21 = $helper->make_and_store_user("author21");
		$this->reviewer21 = $helper->make_and_store_user("reviewer21");
		$this->reviewer22 = $helper->make_and_store_user("reviewer22");
		$this->submission = $helper->make_and_store_reviewable_submission(
			$this->context, [$this->author21],
			[$this->reviewer21, $this->reviewer22]);
	}

	public function testOptingFormGET()
	{
		// get Step3Form and look for opting question
        $reviewerOpting = new ReviewerOpting();
        $reviewerOpting->callbackInitOptingData();

		// TODO 3
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
