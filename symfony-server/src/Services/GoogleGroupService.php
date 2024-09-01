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

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;
use App\Models\GroupWithDeletableUsers;

class GoogleGroupService implements GroupWithDeletableUsers {
	private $groupName;

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private GoogleClientBuilder $clientBuilder,
	) {
		$this->groupName = $this->params->get('google.groupName');
	}

	/**
	 * Not done in constructor because:
	 * - because of our usage of Symfony DI, this class is instantiated at every query received by the API
	 * - this initialization result in performing an HTTP query to Google
	 * So we should initialize this only when we're going to need it.
	 */
	public function initialize(): void {
		$this->service = new \Google_Service_Directory($this->clientBuilder->getClient());
	}

	function groupName(): string {
		return "Google";
	}

	function registerEvent(RegistrationEvent $event, bool $debug): void{
		if ($event->email === "" || $event->email === NULL){
			// Something is probably wrong with the registration form (already seen when a form is badly configured).
			// this ensures we don't block all upcoming registrations.
			$this->logger->error("No email for " . print_r($event, true));
		} else {
			$this->registerEmailToGroup($event->email, $debug);
		}
	}

	private function registerEmailToGroup(string $email, bool $debug): void{
		$this->logger->info("Going to register in Google group " . $this->groupName . " the email " . $email);
		$member = new \Google_Service_Directory_Member();
		$member->setEmail($email);
		$member->setRole("MEMBER");
		if ($debug) {
			$this->logger->info("Debug mode: skipping Google registration");
		} else {
			try {
				$this->service->members->insert($this->groupName, $member);
				$this->logger->info("Done with this registration in the Google group");
			} catch(\Google_Service_Exception $e){
				$reason = $e->getErrors()[0]["reason"];
				if($reason === "duplicate"){
					$this->logger->info("This member already exists");
				} else if ($reason === "notFound"){
					$this->logger->error("Error 'not found'. Perhaps the email adress $email is invalid?");
				} else if ($reason === "invalid") {
					$this->logger->error("Error 'invalid input': email $email seems invalid");
				} else {
					$this->logger->error("Unknown error for email $email:" . $e);
					throw $e;
				}
			}
		}
	}

	function deleteUser(string $email, bool $debug): void{
		$this->logger->info("Going to delete from " . $this->groupName . " the email " . $email);
		if ($debug) {
			$this->logger->info("Debug mode: skipping deletion from Google");
		} else {
			try {
				$this->service->members->delete($this->groupName, $email);
				$this->logger->info("Done with this deletion");
			} catch(\Google_Service_Exception $e){
				if($e->getErrors()[0]["message"] === "Resource Not Found: memberKey"){
					$this->logger->info("This email wasn't in the group already");
				} else {
					$this->logger->error("Unknown error for email $email: " . $e);
					throw $e;
				}
			}
		}
	}

	function getUsers(): array {
		$users = array();
		$didAtLeastOneQuery = false;
		$nextPageToken = NULL;

		while(!$didAtLeastOneQuery || !is_null($nextPageToken)){
			try {
				$this->logger->info("Going to get a page of users from google group. Page token: $nextPageToken");
				$result = $this->service->members->listMembers($this->groupName, array('pageToken' => $nextPageToken));
			} catch(Exception $e){
				$error = "Unknown error: " . $e;
				$this->logger->error($error);
				throw new Exception($error);
			}

			$users = array_merge($users, array_map(function($member) { return $member->email;}, $result->members));
			$nextPageToken = $result->nextPageToken;
			$didAtLeastOneQuery = true;
		}

		return $users;
	}
}
