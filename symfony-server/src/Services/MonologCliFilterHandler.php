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

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Handler\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;


class MonologCliFilterHandler extends Handler implements ProcessableHandlerInterface
{
    use ProcessableHandlerTrait;

  public function __construct(private bool $bubble = true) {}
	public function isHandling(LogRecord $record): bool
	{
		//return !$this->isRunningFromCli();
		return true;
	}

	public function handle(LogRecord $record): bool {
		//if ( !$this->isRunningFromCli() ) {
		//	$this->getHandler($record)->handle($record);
		//}
		$r = new LogRecord(
		  $record->datetime,
		  $record->channel,
		  $record->level,
		  "tempGT: from cli: " . $this->isRunningFromCli() . ": " . $record->message,
		  $record->context,
		  $record->extra,
		  $record->formatted
		);
		$this->getHandler($r)->handle($r);
		return $this->bubble;
	}

	private function isRunningFromCli(): bool {
		return PHP_SAPI == "cli";
	}

	public function getHandler(LogRecord $record = null): HandlerInterface {
		//if (!$this->handler instanceof HandlerInterface) {
		//	$handler = ($this->handler)($record, $this);
		//	if (!$handler instanceof HandlerInterface) {
		//		throw new \RuntimeException("The factory Closure should return a HandlerInterface");
		//	}
		//	$this->handler = $handler;
		//}

		return $this->processors[0];
	}
}
