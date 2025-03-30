<?php

/**
 * @file    plugins/generic/rqc/tests/helpers.inc.php
 *
 * Copyright (c) 2022-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @ingroup plugins_generic_rqc
 *
 * @brief   Routines that help when writing tests, in particular for creating data
 */

import('lib.pkp.classes.user.User');
import('classes.submission.Submission');

import('plugins.generic.rqc.classes.ReviewerOpting');
import('plugins.generic.rqc.RqcPlugin');

function make_reviewable_submission($context, $authors, $reviewers): Submission
{
	$newSubmission = make_submission($context);
	$newPublication = make_publication($newSubmission, $authors);
	foreach ($reviewers as $user) {
		$reviewAssignment = make_reviewAssignment($newSubmission, $user);
	}
	return $newSubmission;
}

function make_reviewAssignment($newSubmission, $user)
{
	$reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
	$reviewAssignment = $reviewAssignmentDAO->newDataObject();
	$reviewAssignment->_data = [
		'submissionId'  => $newSubmission->getId(),  // Huh? Not a publication?
		'reviewerId'    => $user->getId(),
		'reviewRoundId' => 1,
		'stageId'       => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
		// reviewFormId appears to be optional
	];
	$newreviewAssignment = $reviewAssignmentDAO->insertObject(
		$reviewAssignment,
		Application::get()->getRequest()
	);
	return $reviewAssignment;
}

function make_publication($newSubmission, $authors)
{
	$publication = DAORegistry::getDAO('PublicationDAO')->newDataObject();
	$publication->_data = [
		'submissionId' => $newSubmission->getId(),
		'title'        => 'made ' . strftime("%Y-%m-%d %H:%M:%S"),
	];
	$newPublication = Services::get('publication')->add(
		$publication,
		Application::get()->getRequest()
	);
	foreach ($authors as $user) {
		$author = DAORegistry::getDAO('AuthorDAO')->newDataObject();
		$author->_data = [
			'email'         => $user->getEmail(),
			'publicationId' => $newPublication->getId(),
		];
		$newAuthor = Services::get('author')->add(
			$author,
			Application::get()->getRequest()
		);
	}
	return $newPublication;
}

function make_submission($context)
{
	$submission = DAORegistry::getDAO('SubmissionDAO')->newDataObject();
	$submission->_data = [
		'contextId'          => $context,
		'stageId'            => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
		'status'             => STATUS_QUEUED,  // as in manually created case
		'submissionProgress' => 0  // as in manually created case
	];
	$newSubmission = Services::get('submission')->add(
		$submission,
		Application::get()->getRequest()
	);
	return $newSubmission;
}

function make_user($username): User
{
	$userDao = DAORegistry::getDAO('UserDAO');
	$user = $userDao->newDataObject();
	$user->setUsername($username);
	$user->setPassword('1234');
	$user->setGivenname($username[0], RQC_LOCALE);
	$user->setFamilyname($username, RQC_LOCALE);
	$user->setEmail($username . '@some.where');
	$userDao->insertObject($user);
	return $user;
}
