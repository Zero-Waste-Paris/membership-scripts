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

use \MatomoTracker;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class MatomoService {

	private MatomoTracker $tracker;

	public function __construct(
		ContainerBagInterface $params,
		private LoggerInterface $logger,
	) {
		$idSite = $params->get('matomo.idSite');
		$apiUrl = $params->get('matomo.apiUrl');

		if ($idSite && $apiUrl) {
			$this->tracker = new MatomoTracker($idSite, $apiUrl);
		} else {
			$this->logger->info("NOT creating a matomo tracker because some conf is missing");
		}
	}

	public function doTrackEvent(string $action) {
		if ($this->tracker) {
			$this->logger->info("tracking event $action");
			$this->tracker->doTrackEvent("symfony-category", $action);
		}
	}
}
