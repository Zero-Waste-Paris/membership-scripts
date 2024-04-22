<?php

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Monolog\Handler\Handler;

namespace App;

class NonSAPIHandler extends Handler
{
	public function __construct(private Closure|HandlerInterface $handler, private bool $bubble = true) {}
	public function isHandling(LogRecord $record): bool
	{
		return !$this->isRunningFromCli();
	}

	public function handle(LogRecord $record): bool {
		if ( !$this->isRunningFromCli() ) {
			$this->getHandler($record)->handle($record);
		}
		return $this->bubble;
	}

	private function isRunningFromCli(): bool {
		return PHP_SAPI == "cli";
	}

	public function getHandler(LogRecord $record = null): HandlerInterface {
		if (!$this->handler instanceof HandlerInterface) {
			$handler = ($this->handler)($record, $this);
			if (!$handler instanceof HandlerInterface) {
				throw new \RuntimeException("The factory Closure should return a HandlerInterface");
			}
			$this->handler = $handler;
		}

		return $this->handler;
	}
}
