<?php

import('lib.pkp.classes.core.DataObject');
import('plugins.generic.rqc.classes.RqcDevHelper');


/**
 * DataObject representing the opting decision of the reviewer for a submission and year
 *
 * @see     RqcReviewerOptingDAO
 * @see     RqcReviewerOptingSchemaMigration
 * @see     rqcReviewerOpting.json
 * @ingroup plugins_generic_rqc
 */
class RqcReviewerOpting extends DataObject
{
    public function __construct(int $contextId = null, int $submissionId = null, int $userId = null, int $optingStatus = null, int $year = null)
    {
        parent::__construct();
        if ($contextId !== null) {
            $this->setContextId($contextId);
        }
        if ($submissionId !== null) {
            $this->setSubmissionId($submissionId);
        }
        if ($userId !== null) {
            $this->setUserId($userId);
        }
        if ($optingStatus !== null) {
            $this->setOptingStatus($optingStatus);
        }
        if ($year !== null) {
            $this->setYear($year);
        }
    }

    public function setContextId(int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    public function setSubmissionId(int $submissionId): void
    {
        $this->setData('submissionId', $submissionId);
    }

    public function getSubmissionId(): int
    {
        return $this->getData('submissionId');
    }

    public function setUserId(int $userId): void
    {
        $this->setData('userId', $userId);
    }

    public function getUserId(): int
    {
        return $this->getData('userId');
    }

    public function setOptingStatus(int $optingStatus): void
    {
        $this->setData('optingStatus', $optingStatus);
    }

    public function getOptingStatus(): int
    {
        return $this->getData('optingStatus');
    }

    public function setYear(int $year): void
    {
        $this->setData('year', $year);
    }


    public function getYear(): int
    {
        return $this->getData('year');
    }
}
