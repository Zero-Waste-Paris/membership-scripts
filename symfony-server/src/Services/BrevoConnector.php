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

class BrevoConnector implements GroupWithDeletableUsers {

	public function __construct(
		private LoggerInterface $logger,
		private ContainerBagInterface $params,
		) {}

	function groupName(): string {
		return "Brevo";
	}

	public function registerEvent(RegistrationEvent $event, bool $debug): void {
		$listId = $this->params->get('brevo.listId'); // TODO: set it in the config files

		$apiKey = $this->params->get('brevo.apiKey'); // TODO: set in the config files
		$config = Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
		$apiInstance = new Brevo\Client\Api\ContactsApi($config);

		$createContact = new \Brevo\Client\Model\CreateContact([
			'email' => $event->email,
			        //'updateEnabled' => true, // TODO: find out if we need this
					     //'attributes' => [[ 'FIRSTNAME' => 'Max ', 'LASTNAME' => 'Mustermann', 'isVIP'=> 'true' ]],    // TODO: find out which attributes we should send
						      'listIds' =>[[$listId]]
		]);
		try {
		  // TODO: log instead of performing the action when we we're in debug mode
		      $result = $apiInstance->createContact($createContact);
			      print_r($result);
		} catch (Exception $e) {
		  // TODO: in case of status 400 and body containing '"code": "duplicate_parameter"' then it's just that the user was already inserted
		      echo 'Exception when calling ContactsApi->createContact: ', $e->getMessage(), PHP_EOL;
		}

	  // TODO: POST https://api.brevo.com/v3/contacts 
	  // {
	  //   "email": "<mail>",
	  //     "listIds": [5]
	  // }
	}

	public function deleteUser(string $email, bool $debug): void {
		// TODO: DELETE https://api.brevo.com/v3/contacts/<mail>
	}

	public function getUsers(): array {
// TODO: GET https://api.brevo.com/v3/contacts
	}
}
