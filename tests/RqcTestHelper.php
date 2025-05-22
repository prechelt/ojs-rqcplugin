<?php

namespace APP\plugins\generic\rqc\tests;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\plugins\generic\rqc\RqcPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\Core;
use PKP\services\PKPContextService;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

/**
 * Routines that help when writing tests, in particular for creating data
 *
 * @ingroup plugins_generic_rqc
 */
class RqcTestHelper
{
//function storeAll(Submission $submission, Publication $publication, Context $context): int
//{
//    $submissionId = Repo::submission()->add($submission, $publication, $context);
//    $submission = Repo::submission()->get($submissionId);
//
//    // Assign submitter to submission
//    Repo::stageAssignment()
//        ->build(
//            $submission->getId(),
//            $submitAsUserGroup->id,
//            $request->getUser()->getId(),
//            0,
//            1
//        );
//
//    // Create an author record from the submitter's user account
//    if ($submitAsUserGroup->roleId === Role::ROLE_ID_AUTHOR) {
//        $author = Repo::author()->newAuthorFromUser($request->getUser(), $submission, $context);
//        $author->setData('publicationId', $publication->getId());
//        $author->setUserGroupId($submitAsUserGroup->id);
//        $authorId = Repo::author()->add($author);
//        Repo::publication()->edit($publication, ['primaryContactId' => $authorId]);
//    }
//
//    $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();
//
//    /** @var GenreDAO $genreDao */
//    $genreDao = DAORegistry::getDAO('GenreDAO');
//    $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();
//
//    if (!$userGroups instanceof LazyCollection) {
//        $userGroups = $userGroups->lazy();
//    }
//
//    $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
//}

    public function make_and_store_reviewable_submission(Context $context, array $authors, array $reviewers): Submission
    {
        // build
        $newSubmission = $this->make_submission($context);
        $newPublication = $this->make_publication($newSubmission);

        $newSubmissionId = Repo::submission()->add($newSubmission, $newPublication, $context);
        $newSubmission = Repo::submission()->get($newSubmissionId);

        // store the authors
        foreach ($authors as $user) {
            $author = Repo::author()->newDataObject();
            $author->_data = [
                'email' => $user->getEmail(),
                'publicationId' => $newPublication->getId(),
            ];
            Repo::author()->add($author);
        }

        // make and store the reviewers
        foreach ($reviewers as $user) {
            $reviewAssignment = $this->make_and_store_reviewAssignment($newSubmission, $user);
        }
        return $newSubmission;
    }

    public function make_publication(Submission $submission): Publication
    {
        $publication = Repo::publication()->newDataObject();
        $publication->_data = [
            'submissionId' => $submission->getId(),
            'title' => 'made ' . date("%Y-%m-%d %H:%M:%S"),
            'seq' => 0,
            'access_status' => 0,
            'lastModified' => Core::getCurrentDate()
        ];
        return $publication;
    }

    public function make_submission(Context $context): Submission
    {
        $newSubmission = Repo::submission()->newDataObject();
        $newSubmission->_data = [
            'contextId' => $context,
            'stageId' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'status' => PKPSubmission::STATUS_QUEUED,  // as in manually created case
            'submissionProgress' => 0,  // as in manually created case
            'dateLastActivity' => Core::getCurrentDate(),
            'dateSubmitted' => Core::getCurrentDate(),
            'lastModified' => Core::getCurrentDate()
        ];
        return $newSubmission;
    }

    public function make_and_store_reviewAssignment(Submission $submission, User $user): ReviewAssignment
    {
        $reviewAssignment = Repo::reviewAssignment()->newDataObject();
        $reviewAssignment->_data = [
            'submissionId' => $submission->getId(),  // Huh? Not a publication?
            'reviewerId' => $user->getId(),
            'reviewRoundId' => 1,
            'stageId' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            // reviewFormId appears to be optional
        ];
        $reviewAssignmentId = Repo::reviewAssignment()->add($reviewAssignment);
        return Repo::reviewAssignment()->get($reviewAssignmentId);  // fetch clean from db
    }

    public function make_and_store_user(string $username): User
    {
        $user = Repo::user()->newDataObject();
        $user->setUsername($username);
        $user->setPassword('1234');
        $user->setGivenname($username[0], RqcPlugin::RQC_LOCALE);
        $user->setFamilyname($username, RqcPlugin::RQC_LOCALE);
        $user->setEmail($username . '@some.where');
        $user->setDateRegistered(Core::getCurrentDate());
        $userId = Repo::user()->add($user);
        return Repo::user()->get($userId); // fetch clean from db
    }

    public function make_and_store_context(string $path, Request $request): Context
    {
        $contextService = app()->get('context');
        /** @var PKPContextService $contextService */
        $context = Application::getContextDAO()->newDataObject();
        $context->setAllData([
            'seq' => 1,
            'primary_locale' => 'en',
            'enabled' => 1
        ]);
        $context->setPath($path);
        return $contextService->add($context, $request);
    }
}
