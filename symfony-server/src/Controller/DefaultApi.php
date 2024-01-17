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

namespace App\Controller;

use OpenAPI\Server\Api\DefaultApiInterface;
use OpenAPI\Server\Model\ApiMembersGet200ResponseInner;
use OpenAPI\Server\Model\ApiUpdateUserPasswordPostRequest;
use OpenAPI\Server\Model\TimestampedSlackUserList;
use OpenAPI\Server\Model\TimestampedSlackUserListMembersInner;
use OpenAPI\Server\Model\TimestampedSlackUserListWithUserUpdatedTimestamp;
use OpenAPI\Server\Model\TimestampedSlackUserListWithUserUpdatedTimestampAllOfMembers;
use App\Models\SlackMembersTimestamped;
use App\Services\RegistrationDateUtil;
use App\Services\MemberImporter;
use App\Services\SlackService;
use App\Repository\UserRepository;
use JoliCode\Slack\Api\Model\ObjsUser;

use App\Repository\MemberRepository;
use App\Entity\Member;
use App\Entity\MemberAdditionalEmail;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DefaultApi implements DefaultApiInterface {

	public function __construct(
		private LoggerInterface $logger,
		private MemberRepository $memberRepository,
		private RegistrationDateUtil $registrationDateUtil,
		private MemberImporter $memberImporter,
		private ContainerBagInterface $params,
		private SlackService $slackService,
		private UserRepository $userRepository,
		private UserPasswordHasherInterface $passwordHasher,
		private Security $security, // Wouldn't be needed if we extends AbstractController, but it would make the code harder to test
	) { }

	public function apiMembersGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$registrationThreshold = $this->registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();

		$result = array();
		foreach($this->memberRepository->findAll() as $entity) {
			$data = array();
			$data['userId'] = $entity->getId();
			$data['firstName'] = $entity->getFirstName();
			$data['lastName'] = $entity->getLastName();
			$data['email'] = $entity->getEmail();
			$data['postalCode'] = $entity->getPostalCode();
			$data['helloAssoLastRegistrationEventId'] = $entity->getHelloAssoLastRegistrationEventId();
			$data['city'] = $entity->getCity();
			$data['howDidYouKnowZwp'] = $entity->getHowDidYouKnowZwp();
			$data['wantToDo'] = $entity->getWantToDo();
			$data['firstRegistrationDate'] = $entity->getFirstRegistrationDate();
			$data['lastRegistrationDate'] = $entity->getLastRegistrationDate();
			$data['isZWProfessional'] = $entity->isIsZWProfessional();
			$data['additionalEmails'] = $entity->getAdditionalEmails();
			$data['phone'] = $entity->getPhone();
			$data['isRegistrationUpToDate'] = $entity->getLastRegistrationDate() >= $registrationThreshold;

			$result []= new ApiMembersGet200ResponseInner($data);
		}

		return $result;
	}

	public function apiTriggerImportRunGet(string $token, ?bool $debug, int &$responseCode, array &$responseHeaders): void {
		if ($token !== $this->params->get("cron.accessToken")) {
			$this->logger->info("rejecting query because the token is incorrect");
			$responseCode = 403;
			return;
		} else {
			$this->memberImporter->run($debug ?? true);
		}
	}

	public function apiSlackAccountsToReactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$data = $this->slackService->findDeactivatedMembers();
		return $this->toTimestampedSlackUserList($data);
	}

	public function apiSlackAccountsToDeactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$data = $this->slackService->findUsersToDeactivate();
		return $this->toTimestampedSlackUserListWithUserUpdatedTimestamp($data);
	}

	private function toTimestampedSlackUserList(SlackMembersTimestamped $data): TimestampedSlackUserList {
		$res = new TimestampedSlackUserList();
		$res->setMembers(array_map(function(ObjsUser $member) { return $this->toTimestampedSlackUserListMembersInner($member);}, $data->getMembers()));
		$res->setIsFresh($data->isFresh());
		$res->setTimestamp($data->getTimestamp());
		return $res;
	}

	private function toTimestampedSlackUserListMembersInner(ObjsUser $slackMember): TimestampedSlackUserListMembersInner {
		$res = new TimestampedSlackUserListMembersInner();
		$res->setEmail(SlackService::extractUserEmail($slackMember));
		return $res;
	}

	private function toTimestampedSlackUserListWithUserUpdatedTimestamp(SlackMembersTimestamped $data): TimestampedSlackUserListWithUserUpdatedTimestamp {
		$res = new TimestampedSlackUserListWithUserUpdatedTimestamp();
		$res->setMembers(array_map(function(ObjsUser $member) { return $this->toTimestampedSlackUserListWithUserUpdatedTimestampAllOfMembers($member);}, $data->getMembers()));
		$res->setIsFresh($data->isFresh());
		$res->setTimestamp($data->getTimestamp());
		return $res;
	}
	private function toTimestampedSlackUserListWithUserUpdatedTimestampAllOfMembers(ObjsUser $slackMember): TimestampedSlackUserListWithUserUpdatedTimestampAllOfMembers {
		$res = new TimestampedSlackUserListWithUserUpdatedTimestampAllOfMembers();
		$res->setEmail(SlackService::extractUserEmail($slackMember));
		$res->setUpdated($slackMember->getUpdated());
		return $res;
	}

	public function apiUpdateUserPasswordPost(ApiUpdateUserPasswordPostRequest $apiUpdateUserPasswordPostRequest, int &$responseCode, array &$responseHeaders): void {
		$newPassword = $apiUpdateUserPasswordPostRequest->getNewPassword();

		if ($newPassword === null || $newPassword == "") {
			$this->logger->info("Cannot update with a new password null or empty");
			$responseCode = 400;
			return;
		}

		$user = $this->security->getUser();

		if ($user === null) {
			$this->logger->info("Cannot update the password of an unauthenticated user");
			$responseCode = 401;
			return;
		}

		if (!$this->passwordHasher->isPasswordValid($user, $apiUpdateUserPasswordPostRequest->getCurrentPassword())) {
			$this->logger->info("Don't update the password because the current password is incorrect");
			$responseCode = 403;
			return;
		}

		$this->logger->info("Updateing password");
		$this->userRepository->upgradePassword($user, $this->passwordHasher->hashPassword($user, $newPassword));
	}
}
