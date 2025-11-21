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

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		private HttpClientInterface $client,
		) {}

	function groupName(): string {
		return "Brevo";
	}

	public function registerEvent(RegistrationEvent $event, bool $debug): void {
		$listId = $this->params->get('brevo.listId');
		$apiKey = $this->params->get('brevo.apiKey');

		$payload = array("email" => $event->email, "listIds" => array((int) $listId));
		$payload_str = json_encode($payload);

		if ($debug) {
			$this->logger->info("Debug mode: we skip Brevo registration");
		} else {
			$this->logger->info("Going to register on Brevo user " . $event->first_name . " " . $event->last_name);
			$response = $this->client->request('POST', "https://api.brevo.com/v3/contacts", [
				'headers' => ['api-key' => $apiKey, 'content-type' => 'application/json'],
				'body' => $payload_str
			]);
			$response_str = $response->getContent(false);
			if (str_contains($response_str, "email is already associated with another Contact")) {
				$this->logger->info("This user was already registered. Moving on");
			} else if ($response->getStatusCode() != 201) {
				$this->logger->error("Unexpected answer from Brevo: got: " . $response_str);
			}
		}
		$this->logger->info("Done with this registration");
	}

	public function deleteUser(string $email, bool $debug): void {
		// TODO: DELETE https://api.brevo.com/v3/contacts/<mail>
	}

	public function getUsers(): array {
// TODO: GET https://api.brevo.com/v3/contacts
	}
}
