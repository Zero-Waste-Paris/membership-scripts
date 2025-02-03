<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionListener {
	public function __construct(private LoggerInterface $logger) {}

	public function __invoke(Exceptionevent $event): void {
		$exception = $event->getThrowable();

		if ($exception instanceof NotFoundHttpException) {
			$response = new Response();
			$response->setStatusCode(Response::HTTP_NOT_FOUND);
			$response->setContent("Not found");
			$event->setResponse($response);        
		} else if ($exception->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
			$this->logger->warning("Caught unauthorized access");
			$response = new Response();
			$response->setStatusCode(Response::HTTP_UNAUTHORIZED);
			$response->setContent("Unauthorized");
			$event->setResponse($response);
			// TODO 1: according to this, perhaps this code could be replaced by some conf on this firewall:
			// > No Authentication entry point configured, returning a 401 HTTP response. Configure "entry_point" on the firewall "main" if you want to modify the response.
			
			// TODO 2: Perhaps we should instead redirect to the login page?
		} else {
			$this->logger->error("Caught exception: " . $exception->getMessage() . "(code: " . $exception->getCode() . "). Stack trace: " . $exception->getTraceAsString());
		}
	}
}
