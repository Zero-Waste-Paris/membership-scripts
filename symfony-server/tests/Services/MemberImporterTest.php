<?php

declare(strict_types=1);
require_once __DIR__ . '/../TestHelperTrait.php';

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use PHPUnit\Framework\Constraint\ObjectEquals;
use Psr\Log\LoggerInterface;

use App\Entity\Member;
use App\Models\RegistrationEvent;
use App\Repository\MemberRepository;
use App\Repository\OptionsRepository;
use App\Services\BrevoConnector;
use App\Services\MemberImporter;
use App\Services\HelloAssoConnector;
use App\Services\MailchimpConnector;
use App\Services\GoogleGroupService;
use App\Services\EmailService;
use App\Services\GroupMemberDeleter;
use App\Services\NowProvider;
use App\Services\HelloAssoException;

final class MemberImporterTest extends KernelTestCase {
	use TestHelperTrait;

	private EmailService $mailMock;
	private OptionsRepository $optionRepoMock;
	private MemberRepository $memberRepoMock;
	private HelloAssoConnector $helloAssoMock;
	private GoogleGroupService $googleMock;
	private MailchimpConnector $mailchimpMock;
	private BrevoConnector $brevoMock;
	private NowProvider $nowProviderMock;
	private LoggerInterface $loggerMock;
	private ContainerBagInterface $paramsMock;

	private bool $useMockForMemberRepository;
	private bool $shouldSendEmailAboutSlackMembersToReactivate;


	protected function setUp(): void {
		$this->useMockForMemberRepository = true;
		$this->shouldSendEmailAboutSlackMembersToReactivate = true;
		self::bootKernel();

		$this->mailMock        = $this->createMock(EmailService::class);
		$this->optionRepoMock  = $this->createMock(OptionsRepository::class);
		$this->memberRepoMock  = $this->createMock(MemberRepository::class);
		$this->helloAssoMock   = $this->createMock(HelloAssoConnector::class);
		$this->googleMock      = $this->createMock(GoogleGroupService::class);
		$this->mailchimpMock   = $this->createMock(MailchimpConnector::class);
		$this->brevoMock       = $this->createMock(BrevoConnector::class);
		$this->nowProviderMock = $this->createMock(NowProvider::class);
		$this->loggerMock      = $this->createMock(LoggerInterface::class);
		$this->paramsMock      = $this->createMock(ContainerBagInterface::class);

		$this->googleMock->expects(self::once())->method('initialize');
	}

	private function registerAllMockInContainer(): void {
		if ($this->shouldSendEmailAboutSlackMembersToReactivate) {
			$this->mailMock->expects(self::once())->method('sendEmailAboutSlackMembersToReactivate');
		}
		self::getContainer()->set(EmailService::class,       $this->mailMock);
		self::getContainer()->set(OptionsRepository::class,  $this->optionRepoMock);
		self::getContainer()->set(HelloAssoConnector::class, $this->helloAssoMock);
		self::getContainer()->set(GooGleGroupService::class, $this->googleMock);
		self::getContainer()->set(MailchimpConnector::class, $this->mailchimpMock);
		self::getContainer()->set(BrevoConnector::class,     $this->brevoMock);
		self::getContainer()->set(NowProvider::class,        $this->nowProviderMock);
		self::getContainer()->set(LoggerInterface::class,    $this->loggerMock);
		self::getContainer()->set(ContainerBagInterface::class, $this->paramsMock);
		if ($this->useMockForMemberRepository) {
			self::getContainer()->set(MemberRepository::class, $this->memberRepoMock);
		}
	}

	public function test_happyPath(): void {
		// Setup
		$now = new \DateTime('2020-09-08T06:30:00Z');
		$lastSuccessfulRunDate = new \DateTime('2020-09-08T01:00:00Z');
		$registrationEvent = $this->buildHelloassoEvent('1984-03-04T09:30:00Z', 'firstName', 'lastName', 'me@myself.com');

		$this->expectsEventRegistration($registrationEvent);
		$this->expectsOldMembersAreNotDeleted();
		$this->expectsNoNotificationsAreSentAboutNewcomers();
		$this->expectsToResetTheNumberOfSuccessiveHelloassoFailures();
		$this->setDatesInMock($now, $lastSuccessfulRunDate);

		$this->registerAllMockInContainer();

		// Act
		$sut = self::getContainer()->get(MemberImporter::class);
		$sut->run(false);
	}

	public function test_logWarningWhenOnlyAFewHelloassoErrors(): void {
		// Setup
		$this->setUpHelloAssoToThrow();
		$this->expectsToLogAWarningButNoError();
		$this->expectsToIncrementTheNumberOfSuccessiveHelloassoFailuresAndToGetItsValue(1);
		$this->shouldSendEmailAboutSlackMembersToReactivate = false; // Not because it should never do it, but because we don't care (and in practice it indeed does not do it)
		$this->paramsMock->expects(self::once())->method('get')->with($this->equalTo('helloasso.nbSuccessiveFailuresBeforeLoggingAnError'))->willReturn(5);

		$this->registerAllMockInContainer();

		// Act
		$this->runAndExpectHelloassoErrorToBeRethrown();
	}

	public function test_logErrorWhenSeveralSuccessiveRunsFailedBecauseOfHelloAssoErrors(): void {
		// Setup
		$this->setUpHelloAssoToThrow();
		$this->expectsToLogAnError();
		$this->expectsToIncrementTheNumberOfSuccessiveHelloassoFailuresAndToGetItsValue(100);
		$this->shouldSendEmailAboutSlackMembersToReactivate = false; // Not because it should never do it, but because we don't care (and in practice it indeed does not do it)
		$this->paramsMock->expects(self::once())->method('get')->with($this->equalTo('helloasso.nbSuccessiveFailuresBeforeLoggingAnError'))->willReturn(5);

		$this->registerAllMockInContainer();

		// Act
		$this->runAndExpectHelloassoErrorToBeRethrown();
	}

	public function test_sendNotificationForAdminsAboutNewcomers(): void {
		// Setup
		$now = new DateTime("2020-08-08T00:00:00", new DateTimeZone("Europe/Paris")); // Saturday
		$lastSuccessfulRunDate = new DateTime("2020-08-04"); // before the dead line

		$this->expectsNoEventRegistration();
		$this->expectsNotificationsAreSentAboutNewcomers([new Member()]);
		$this->setDatesInMock($now, $lastSuccessfulRunDate);

		$this->registerAllMockInContainer();

		// Act
		$sut = self::getContainer()->get(MemberImporter::class);
		$sut->run(false);
	}

	public function test_deleteOutdatedMembers(): void {
		// Setup
		$now = new DateTime("2023-02-02"); // Just after the dead line
		$lastSuccessfulRunDate = new DateTime("2023-01-30"); // before the dead line

		$this->expectsNoEventRegistration();
		$this->setDatesInMock($now, $lastSuccessfulRunDate);

		// PHPUnit mock would not make it easy to assert we have all the individual deletions we expect, so mock GroupMemberDeleter directly in this test
		$groupMemberDeleterMock = $this->createMock(GroupMemberDeleter::class);
		$groupMemberDeleterMock->expects(self::once())->method('deleteOutdatedMembersFromGroups')->with($this->equalTo(['young@member.com']));
		self::getContainer()->set(GroupMemberDeleter::class, $groupMemberDeleterMock);

		$this->useMockForMemberRepository = false;
		$this->registerAllMockInContainer();

		// We'd rather use an actual db because mocks are stateless but here we rely on a dynamic behavior
		// (eg: if the tested code deletes users from db before or after deleting them from groups, matters)
		$memberRepo = self::getContainer()->get(MemberRepository::class);
		$memberRepo->addOrUpdateMember($this->buildHelloassoEvent("2021-08-31", "VeryOld", "Member", "veryold@member.com"), false);
		$memberRepo->addOrUpdateMember($this->buildHelloassoEvent("2022-08-31", "Old", "Member", "old@member.com"), false);
		$memberRepo->addOrUpdateMember($this->buildHelloassoEvent("2023-01-15", "Young", "Member", "young@member.com"), false);

		// Act
		$sut = self::getContainer()->get(MemberImporter::class);
		$sut->run(false);

		// Assert
		$this->assertEquals(["old@member.com", "young@member.com"], array_map(fn(Member $m) => $m->getEmail(), $memberRepo->findAll()),
				"We keep data up to 1 year after the end of subscription, so we delete only extremely old members");
	}

	private function setDatesInMock(\DateTime $now, \DateTime $lastSuccessfulRunDate): void {
		$this->optionRepoMock->expects(self::once())->method('getLastSuccessfulRunDate')->willReturn($lastSuccessfulRunDate);
		$this->optionRepoMock->expects(self::once())->method('writeLastSuccessfulRunDate')->with($this->equalTo($now));

		$this->nowProviderMock->method('getNow')->willReturn($now);
	}

	private function setUpHelloAssoToThrow(): void {
		$this->helloAssoMock->expects(self::once())->method('getAllHelloAssoSubscriptions')->will($this->throwException(new HelloAssoException(new Exception())));
	}

	private function expectsToLogAnError(): void {
		$this->loggerMock->expects(self::once())->method('error');
	}

	private function expectsToLogAWarningButNoError(): void {
		$this->loggerMock->expects(self::never())->method('error');
		$this->loggerMock->expects(self::once())->method('warning');
	}

	private function expectsToIncrementTheNumberOfSuccessiveHelloassoFailuresAndToGetItsValue(int $value): void {
		$this->optionRepoMock->expects(self::once())->method('incrementNumberOfSuccessiveHelloassoFailures');
		$this->optionRepoMock->expects(self::once())->method('getNumberOfSuccessiveHelloassoFailures')->willReturn($value);
	}

	private function expectsToResetTheNumberOfSuccessiveHelloassoFailures(): void {
		$this->optionRepoMock->expects(self::never())->method('incrementNumberOfSuccessiveHelloassoFailures');
		$this->optionRepoMock->expects(self::once())->method('resetNumberOfSuccessiveHelloassoFailures');
	}

	private function expectsEventRegistration(RegistrationEvent $expected): void {
		$this->helloAssoMock->expects(self::once())->method('getAllHelloAssoSubscriptions')->willReturn([$expected]);
		$this->googleMock->expects(self::once())->method('registerEvent')->with(new ObjectEquals($expected));
		$this->mailchimpMock->expects(self::once())->method('registerEvent')->with(new ObjectEquals($expected));
		$this->brevoMock->expects(self::once())->method('registerEvent')->with(new ObjectEquals($expected));
		$this->memberRepoMock->expects(self::once())->method('addOrUpdateMember')->with(new ObjectEquals($expected));
	}

	private function expectsNoEventRegistration(): void {
		$this->helloAssoMock->expects(self::once())->method('getAllHelloAssoSubscriptions')->willReturn([]);
		$this->googleMock->expects(self::never())->method('registerEvent');
		$this->mailchimpMock->expects(self::never())->method('registerEvent');
		$this->brevoMock->expects(self::never())->method('registerEvent');
		$this->memberRepoMock->expects(self::never())->method('addOrUpdateMember');
	}

	private function expectsNoNotificationsAreSentAboutNewcomers(): void {
		$this->mailMock->expects(self::never())->method('sendNotificationForAdminsAboutNewcomers');
		$this->memberRepoMock->expects(self::never())->method('updateMembersForWhichNotificationHasBeenSentoToAdmins');
	}

	private function expectsNotificationsAreSentAboutNewcomers(array $newcomers): void {
		$this->memberRepoMock->expects(self::once())->method('getMembersForWhichNoNotificationHasBeenSentToAdmins')->willReturn($newcomers);
		$this->mailMock->expects(self::once())->method('sendNotificationForAdminsAboutNewcomers')->with($this->equalTo($newcomers));
		$this->memberRepoMock->expects(self::once())->method('updateMembersForWhichNotificationHasBeenSentoToAdmins')->with($this->equalTo($newcomers));
	}

	private function expectsOldMembersAreNotDeleted(): void {
		$this->memberRepoMock->expects(self::never())->method('deleteMembersOlderThan');
		$this->googleMock->expects(self::never())->method('deleteUsers');
		$this->mailchimpMock->expects(self::never())->method('deleteUsers');
		$this->brevoMock->expects(self::never())->method('deleteUsers');
	}

	private function runAndExpectHelloassoErrorToBeRethrown(): void {
		$sut = self::getContainer()->get(MemberImporter::class);
		try {
			$sut->run(false);
			$this->fail("Expected to have an HelloAssoException rethrown but we had no expection at all");
		} catch (HelloAssoException $e) {
		  // Nothing to do: it's the expected behavior
		}
	}
}
