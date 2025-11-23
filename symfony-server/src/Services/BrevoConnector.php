<?php
/*
Copyright (C) 2020-2025  Zero Waste Paris

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

use App\Models\RegistrationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Models\GroupWithDeletableUsers;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BrevoConnector implements GroupWithDeletableUsers {
	private int $listId;
	private string $apiKey;

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private HttpClientInterface $client,
	) {
		$this->listId = $this->params->get('brevo.listId');
		$this->apiKey = $this->params->get('brevo.apiKey');
	}

	function groupName(): string {
		return "Brevo";
	}

	public function registerEvent(RegistrationEvent $event, bool $debug): void {
		$payload = array("email" => $event->email, "listIds" => array($this->listId));
		$payload_str = json_encode($payload);

		if ($debug) {
			$this->logger->info("Debug mode: we skip Brevo registration");
		} else {
			$this->logger->info("Going to register on Brevo user " . $event->first_name . " " . $event->last_name);
			$response = $this->client->request('POST', "https://api.brevo.com/v3/contacts", [
				'headers' => ['api-key' => $this->apiKey, 'content-type' => 'application/json'],
				'body' => $payload_str
			]);
			$response_str = $response->getContent(false);
			if (str_contains($response_str, "email is already associated with another Contact")) {
				$this->logger->info("This user was already registered. Not sure if they already are in the list so we try to add them");
				$this->addToList($event-email, $debug);
			} else if ($response->getStatusCode() != 201) {
				$this->logger->error("Unexpected answer from Brevo: got: " . $response_str);
			}
		}
		$this->logger->info("Done with this registration");
	}

	public function deleteUsers(array $emails, bool $debug): void {
		// TODO: DELETE https://api.brevo.com/v3/contacts/<mail>
	}

	function removeFromList(string $email, bool $debug): void {
		$payload_str = json_encode(array("unlinkListIds" => array($this->listId)));
		if ($debug) {
			$this->logger->info("Not removing $email from $this->listId because we're in debug mode");
		} else {
			$this->client->request('PUT', "https://api.brevo.com/v3/contacts/$email", [
				'headers' => ['api-key' => $this->apiKey, 'content-type' => 'application/json'],
				'body' => $payload_str,
			])->getContent(); // No content is expected, but this call should throw in case of error
		}
	}

	function addToList(string $email, bool $debug): void {
		$payload_str = json_encode(array("listIds" => array($this->listId)));
		if ($debug) {
			$this->logger->info("Not adding $email to $this->listId because we're in debug mode");
		} else {
			$this->client->request('PUT', "https://api.brevo.com/v3/contacts/$email", [
				'headers' => ['api-key' => $this->apiKey, 'content-type' => 'application/json'],
				'body' => $payload_str,
			])->getContent(); // No content is expected, but this call should throw in case of error
		}

	}

	public function getUsers(): array {
		$this->logger->info("Going to get Brevo contacts");
		$response = $this->client->request('GET', "https://api.brevo.com/v3/contacts?listIds=[$this->listId]&limit=1000", [ // In theory we should handle pagination, but in practice, with a limit of 1000 we're safe
			'headers' => ['api-key' => $this->apiKey],
		]);
		$responseObj = json_decode($response->getContent());
		return array_map(fn($contact): string => $contact->email, $responseObj->contacts);
	}
}
