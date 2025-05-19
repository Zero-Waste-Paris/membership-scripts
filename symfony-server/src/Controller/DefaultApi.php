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
use OpenAPI\Server\Model\ApiEnableTotpPostRequest;
use OpenAPI\Server\Model\ApiMembersGet200ResponseInner;
use OpenAPI\Server\Model\ApiUpdateUserPasswordPostRequest;
use OpenAPI\Server\Model\TimestampedSlackUserList;
use App\Models\SlackMembersTimestamped;
use App\Services\RegistrationDateUtil;
use App\Services\MemberImporter;
use App\Services\SlackService;
use App\Repository\UserRepository;

use App\Repository\MemberRepository;
use App\Entity\Member;
use App\Entity\MemberAdditionalEmail;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
		private UrlGeneratorInterface $router,
		private TotpAuthenticatorInterface $totpAuthenticator,
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
		if (!$this->isTokenOk($token, $responseCode)) {
			return;
		}
		$this->memberImporter->run($debug ?? true);
	}

	public function apiLogErrorIfThereAreSlackAccountsToDeactivateGet(string $token, int &$responseCode, array &$responseHeaders): void {
		if (!$this->isTokenOk($token, $responseCode)) {
			return;
		}
		$nbUsersToDeactivate = count($this->slackService->findUsersToDeactivate()->getMembers());
		if ($nbUsersToDeactivate > 0) {
			$this->logger->error("Il y a $nbUsersToDeactivate comptes Slack qui ne sont pas associé à une adresse mail connue." .
			" La liste est disponible via: " . $this->router->generate("open_api_server_default_apislackaccountstodeactivateget", [], UrlGeneratorInterface::ABSOLUTE_URL));
		} else {
			$this->logger->info("Every slack user is a registered member");
		}
	}

	public function apiSlackAccountsToReactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$data = $this->slackService->findDeactivatedMembers();
		return $this->toTimestampedSlackUserList($data);
	}

	public function apiSlackAccountsToDeactivateGet(int &$responseCode, array &$responseHeaders): array|object|null {
		$data = $this->slackService->findUsersToDeactivate();
		return $this->toTimestampedSlackUserList($data);
	}

	private function toTimestampedSlackUserList(SlackMembersTimestamped $data) {
		$res = new TimestampedSlackUserList();
		$res->setMembers($data->getMembers());
		$res->setIsFresh($data->isFresh());
		$res->setTimestamp($data->getTimestamp());
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

		$this->logger->info("Updating password");
		$this->userRepository->upgradePassword($user, $this->passwordHasher->hashPassword($user, $newPassword));
	}

	public function apiDisableTotpPost(int &$responseCode, array &$responseHeaders): void {
		$user = $this->security->getUser();
		if ($user === null) {
			$this->logger->info("Cannot disable Totp for an unauthenticated user");
			$responseCode = 401;
			return;
		}
		$user->disableTotp();
		$this->userRepository->saveAndFlush($user);
	}

	public function apiEnableTotpPost(?ApiEnableTotpPostRequest $apiEnableTotpPostRequest, int &$responseCode, array &$responseHeaders): void {
		$user = $this->security->getUser();
		if ($user === null) {
			$this->logger->info("Cannot enable Totp for an unauthenticated user");
			$responseCode = 401;
			return;
		}

		if ($this->totpAuthenticator->checkCode($user, $apiEnableTotpPostRequest->getTotp()) !== true) {
			$this->logger->info("Totp code does not match, we don't enable totp for this user");
			$responseCode = 400;
			return;
		}

		$user->setTotpAuthenticationEnabled(true);
		$this->userRepository->saveAndFlush($user);
	}

	public function apiGenerateTotpSecretPost(int &$responseCode, array &$responseHeaders): mixed {
		$user = $this->security->getUser();
		if ($user === null) {
			$this->logger->info("Cannot enable Totp for an unauthenticated user");
			$responseCode = 401;
			return null;
		}

		if ($user->isTotpAuthenticationEnabled()) {
			$this->logger->info("Totp is already enabled for the user, we do not change the secret");
			$responseCode = 400;
			return null;
		}

		$user->setTotpSecret($this->totpAuthenticator->generateSecret());
		$this->userRepository->saveAndFlush($user);
		$builder = new Builder(writer: new PngWriter(), data: $this->totpAuthenticator->getQRContent($user));
		return $builder->build()->getString();
	}

	public function apiHasTotpEnabledGet(int &$responseCode, array &$responseHeaders): bool {
		$user = $this->security->getUser();
		if ($user === null) {
			$this->logger->info("Can't check if non authenticated user has totp enabled");
			$responseCode = 401;
			return false;
		}

		return $user->isTotpAuthenticationEnabled();
	}


	private function isTokenOk(string $queryToken, int &$responseCode): bool {
		if ($queryToken !== $this->params->get("cron.accessToken")) {
			$this->logger->info("rejecting query because the token is incorrect");
			$responseCode = 403;
			return false;
		} else {
			return true;
		}
	}

}
