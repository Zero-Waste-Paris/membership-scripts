<?php

namespace App\Tests\Controller;
require_once __DIR__ . '/../TestHelperTrait.php';

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use App\Entity\Member;
use App\Models\RegistrationEvent;
use App\Repository\MemberRepository;
use App\Services\NowProvider;
use App\Services\MemberImporter;
use App\Services\SlackService;
use App\Controller\DefaultApi;
use App\Entity\User;
use App\Repository\UserRepository;

use OpenAPI\Server\Model\ApiMembersGet200ResponseInner;
use OpenAPI\Server\Model\ApiUpdateUserPasswordPostRequest;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DefaultApiTest extends KernelTestCase {
	use \TestHelperTrait;

	private array $members = array();

	// placeholders to be passed by reference
	private int $responseCode;
	private array $responseHeaders = array();

	protected function setUp(): void {
		// Some plumbing
		$this->responseCode = 200;
		$this->members = array();
		self::bootKernel();

		// Setup "now"
		$nowProviderMock = $this->createMock(NowProvider::class);
		$nowProviderMock->method('getNow')->willReturn(new \DateTime("2023-12-01"));
		self::getContainer()->set(NowProvider::class, $nowProviderMock);

		// Setup placeholder members
		$memberRepo = self::getContainer()->get(MemberRepository::class);
		$this->buildAndRegisterMember($memberRepo, "2020-01-01", "soOld", "shouldntBeReturned", "old@mail.com", "75018", false);
		$this->buildAndRegisterMember($memberRepo, "2023-03-04", "titi", "somename", "titi@mail.com", "92100", true);
		$this->buildAndRegisterMember($memberRepo, "2023-09-08", "tata", "somename", "tata@mail.com", "75018", true);
		$this->buildAndRegisterMember($memberRepo, "2023-11-08", "toto", "somename", "toto@mail.com", "92100", true);
	}

	public function test_apiMembersGet(): void {
		$sut = self::getContainer()->get(DefaultApi::class);

		$actualMembers = $sut->apiMembersGet($this->responseCode, $this->responseHeaders);

		// sort the array to ease asserting on its content (since that method can return the items in any order)
		usort($actualMembers, function(ApiMembersGet200ResponseInner $m1, ApiMembersGet200ResponseInner $m2) {return $m1->getLastRegistrationDate() <=> $m2->getLastRegistrationDate();});
		$this->assertEquals(
			array($this->members["2020-01-01"], $this->members["2023-03-04"], $this->members["2023-09-08"], $this->members["2023-11-08"]),
			$actualMembers);
	}

	private function buildAndRegisterMember(MemberRepository $repo, string $event_date, string $first_name, string $last_name, string $email, string $postal_code, bool $isRegistrationUpToDate): void {
		$event = $this->buildHelloassoEvent($event_date, $first_name, $last_name, $email, $postal_code);
		$repo->addOrUpdateMember($event, false);
		$this->members[$event_date] = $this->buildApiMembersGet200ResponseInner($event, $isRegistrationUpToDate);
	}

	private int $autoIncrementedIdx = 0;
	private function buildApiMembersGet200ResponseInner(RegistrationEvent $event, bool $isRegistrationUpToDate): ApiMembersGet200ResponseInner {
		$this->autoIncrementedIdx++;
		$ret = new ApiMembersGet200ResponseInner();

		$ret->setUserId($this->autoIncrementedIdx);
		$ret->setHelloAssoLastRegistrationEventId($event->helloasso_event_id);
		$ret->setFirstName($event->first_name);
		$ret->setLastName($event->last_name);
		$ret->setEmail($event->email);
		$ret->setPostalCode($event->postal_code);
		$ret->setCity($event->city);
		$ret->setHowDidYouKnowZwp($event->how_did_you_know_zwp);
		$ret->setWantToDo($event->want_to_do);
		$ret->setPhone($event->phone);
		$ret->setFirstRegistrationDate(new \DateTime($event->event_date)); // because these tests don't make user register twice
		$ret->setLastRegistrationDate(new \DateTime($event->event_date));
		$ret->setAdditionalEmails(array());
		$ret->setIsZWProfessional(false);
		$ret->setIsRegistrationUpToDate($isRegistrationUpToDate);

		return $ret;
	}

	public function test_apiTriggerImportRunGet_defaultTo_DebugOn_whenNoValueIsProvided(): void {
		$memberImporterMock = $this->createMock(MemberImporter::class);
		$memberImporterMock->expects(self::once())->method('run')->with($this->equalTo(true));
		self::getContainer()->set(MemberImporter::class, $memberImporterMock);

		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiTriggerImportRunGet("cron-token-for-test", null, $this->responseCode, $this->responseHeaders);
	}
	public function test_apiTriggerImportRunGet_rejectRequestsWhenTokenIsInvalid(): void {
		// Setup and an assert
		$memberImporterMock = $this->createMock(MemberImporter::class);
		$memberImporterMock->expects(self::never())->method('run');
		self::getContainer()->set(MemberImporter::class, $memberImporterMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiTriggerImportRunGet("invalid-token", null, $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals("403", $this->responseCode);
	}

	public function test_apiLogErrorIfThereAreSlackAccountsToDeactivateGet_rejectRequestsWhenTokenIsInvalid(): void {
		// Setup and an assert
		$slackServiceMock = $this->createMock(SlackService::class);
		$slackServiceMock->expects(self::never())->method('findUsersToDeactivate');
		self::getContainer()->set(SlackService::class, $slackServiceMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiLogErrorIfThereAreSlackAccountsToDeactivateGet("invalid-token", $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals("403", $this->responseCode);
	}

	public function test_userCanUpdateHerPassword(): void {
		// Setup and assert
		$user = new User();
		$currentPassword = "current-password";
		$newPassword = "my-new-password";
		$hashedNewPassword = "my-hash";

		$securityMock = $this->createMock(Security::class);
		$securityMock->expects(self::once())->method('getUser')->willReturn($user);
		self::getContainer()->set(Security::class, $securityMock);

		$hasherMock = $this->createMock(UserPasswordHasherInterface::class);
		$hasherMock->expects(self::once())->method('hashPassword')->with($this->equalTo($user), $this->equalTo($newPassword))->willReturn($hashedNewPassword);
		$hasherMock->expects(self::once())->method('isPasswordValid')->with($this->equalTo($user), $this->equalTo($currentPassword))->willReturn(true);
		self::getContainer()->set(UserPasswordHasherInterface::class, $hasherMock);

		$userRepoMock = $this->createMock(UserRepository::class);
		$userRepoMock->expects(self::once())->method('upgradePassword')->with($this->equalTo($user), $this->equalTo($hashedNewPassword));
		self::getContainer()->set(UserRepository::class, $userRepoMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiUpdateUserPasswordPost(new ApiUpdateUserPasswordPostRequest(["newPassword" => $newPassword, "currentPassword" => $currentPassword]), $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals(200, $this->responseCode);
	}

	public function test_userCannotUpdateWithAWrongCurrentPassword(): void {
		// Setup and assert
		$user = new User();
		$wrongPassword = "wrong-password";
		$newPassword = "my-new-password";

		$securityMock = $this->createMock(Security::class);
		$securityMock->expects(self::once())->method('getUser')->willReturn($user);
		self::getContainer()->set(Security::class, $securityMock);

		$hasherMock = $this->createMock(UserPasswordHasherInterface::class);
		$hasherMock->expects(self::never())->method('hashPassword');
		$hasherMock->expects(self::once())->method('isPasswordValid')->with($this->equalTo($user), $this->equalTo($wrongPassword))->willReturn(false);
		self::getContainer()->set(UserPasswordHasherInterface::class, $hasherMock);

		$userRepoMock = $this->createMock(UserRepository::class);
		$userRepoMock->expects(self::never())->method('upgradePassword');
		self::getContainer()->set(UserRepository::class, $userRepoMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiUpdateUserPasswordPost(new ApiUpdateUserPasswordPostRequest(["newPassword" => $newPassword, "currentPassword" => $wrongPassword]), $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals(403, $this->responseCode);
	}

	public function test_userCannotUpdaterWithAnEmptyPassword(): void {
		// Setup and assert
		$userRepoMock = $this->createMock(UserRepository::class);
		$userRepoMock->expects(self::never())->method('upgradePassword');
		self::getContainer()->set(UserRepository::class, $userRepoMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiUpdateUserPasswordPost(new ApiUpdateUserPasswordPostRequest(["newPassword" => ""]), $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals(400, $this->responseCode);
	}

	public function test_unauthenticatedUserCantUpdateAPassword(): void {
		// Setup and assert
		$userRepoMock = $this->createMock(UserRepository::class);
		$userRepoMock->expects(self::never())->method('upgradePassword');
		self::getContainer()->set(UserRepository::class, $userRepoMock);

		// Act
		$sut = self::getContainer()->get(DefaultApi::class);
		$sut->apiUpdateUserPasswordPost(new ApiUpdateUserPasswordPostRequest(["newPassword" => "somePassword"]), $this->responseCode, $this->responseHeaders);

		// Remaining assert
		$this->assertEquals(401, $this->responseCode);

	}
}
