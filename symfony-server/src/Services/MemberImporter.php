<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace App\Services;

use App\Entity\Options;
use App\Repository\OptionsRepository;
use App\Models\GroupWithDeletableUsers;
use Psr\Log\LoggerInterface;
use App\Repository\MemberRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Entity\Member;

class MemberImporter {

	public function __construct(
		private LoggerInterface $logger,
		private OptionsRepository $optionRepository,
		private HelloAssoConnector $helloassoConnector,
		private MemberRepository $memberRepository,
		private MailchimpConnector $mailchimpConnector,
		private GoogleGroupService $googleConnector,
		private EmailService $mail,
		private GroupMemberDeleter $groupMemberDeleter,
		private RegistrationDateUtil $dateUtil,
		private NowProvider $nowProvider,
		private ContainerBagInterface $params,
		) {}

	public function run(bool $debug) {
		try {
			$this->googleConnector->initialize();

			$lastSuccessfulRunDate = $this->optionRepository->getLastSuccessfulRunDate();
			$dateBeforeWhichAllRegistrationsHaveBeenHandled = $this->computeDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);

			$subscriptions = $this->helloassoConnector->getAllHelloAssoSubscriptions($dateBeforeWhichAllRegistrationsHaveBeenHandled, $this->nowProvider->getNow());
			$this->optionRepository->resetNumberOfSuccessiveHelloassoFailures($debug);
			$this->logger->info("retrieved data from HelloAsso. Got " . count($subscriptions) . " action(s)");

			foreach($subscriptions as $subscription) {
				$this->memberRepository->addOrUpdateMember($subscription, $debug);
				$this->mailchimpConnector->registerEvent($subscription, $debug);
				$this->googleConnector->registerEvent($subscription, $debug);
			}
			$this->deleteOutdatedMembersIfNeeded($lastSuccessfulRunDate, $debug);

			$this->sendEmailNotificationForAdminsAboutNewcomersIfneeded($lastSuccessfulRunDate, $debug);
			$this->mail->sendEmailAboutSlackmembersToReactivate($debug);

			$this->optionRepository->writeLastSuccessfulRunDate($this->nowProvider->getNow(), $debug);
			$this->logger->info("Completed successfully");
		} catch (HelloAssoException $e) {
			$this->handleHelloassoException($e, $debug);
			throw $e;
		} catch (\Throwable $t) {
			$this->logger->error("Failed with error of type " . get_class($t) . " :" . $t->getMessage() . ". " . $t->getTraceAsString());
			throw $t;
		}
	}

	private function handleHelloassoException(HelloAssoException $e, bool $debug): void {
		$this->optionRepository->incrementNumberOfSuccessiveHelloassoFailures($debug);
		$nbSucessiveHAFailures = $this->optionRepository->getNumberOfSuccessiveHelloassoFailures();
		$logMessage = "Failed to query helloasso (" . $nbSucessiveHAFailures. "): " . $e->getMessage() . " . " . $e->getTraceAsString();
		if ($nbSucessiveHAFailures < $this->params->get("helloasso.nbSuccessiveFailuresBeforeLoggingAnError")) {
			$this->logger->warning($logMessage);
		} else {
			$this->logger->error($logMessage);
		}
	}

	private function computeDateBeforeWhichAllRegistrationsHaveBeenHandled(\DateTime $lastSuccessfulRunDate): \DateTime {
		$dateBeforeWhichAllRegistrationsHaveBeenHandled = RegistrationDateUtil::getDateBeforeWhichAllRegistrationsHaveBeenHandled($lastSuccessfulRunDate);
		$this->logger->info("Last successful run was at " . $this->dateToStr($lastSuccessfulRunDate) . ". We handle registrations that occur after " . $this->dateToStr($dateBeforeWhichAllRegistrationsHaveBeenHandled));

		return $dateBeforeWhichAllRegistrationsHaveBeenHandled;
	}

	private function dateToStr(\DateTime $d) : string {
		return $d->format('Y-m-d\TH:i:s');
	}

	private function sendEmailNotificationForAdminsAboutNewcomersIfneeded(\DateTime $lastSuccessfulRunDate, bool $debug) {
		if ($this->dateUtil->needToSendNotificationAboutLatestRegistrations($lastSuccessfulRunDate)) {
			$this->logger->info("Going to send weekly email about newcomers");

			$newcomers = $this->memberRepository->getMembersForWhichNoNotificationHasBeenSentToAdmins();
			$this->mail->sendNotificationForAdminsAboutNewcomers($newcomers, $debug);
			$this->memberRepository->updateMembersForWhichNotificationHasBeenSentoToAdmins($newcomers, $debug);
		} else {
			$this->logger->info("No need to send weekly email about newcomers");
		}
	}

	private function deleteOutdatedMembersIfNeeded(\DateTime $lastSuccessfulRunStartDate, bool $debug): void {
		if ( !$this->dateUtil->needToDeleteOutdatedMembers($lastSuccessfulRunStartDate) ) {
			$this->logger->info("not the time to delete outdated members");
			return;
		}

		$upTo = $this->dateUtil->getMaxDateBeforeWhichRegistrationsInfoShouldBeDiscarded();
		$this->logger->info("We're going to delete outdated members with no registration after " . $upTo->format("Y-m-d"));
		$this->memberRepository->deleteMembersOlderThan($upTo, $debug);

		$this->groupMemberDeleter->deleteOutdatedMembersFromGroups(
				array_map(fn(Member $m) => $m->getEmail(), $this->memberRepository->getAllUpToDateMembers()),
				[$this->googleConnector, $this->mailchimpConnector],
				$debug);
	}
}
