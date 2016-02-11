<?php

require __DIR__ . '/../../../../../../build/integration/features/bootstrap/BasicStructure.php';
require __DIR__ . '/../../../../../../build/integration/features/bootstrap/Provisioning.php';

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext {

	/** @var array[] */
	protected $notificationIds;

	/** @var int */
	protected $deletedNotification;

	protected $adminUser;

	use BasicStructure;
	use Provisioning;

	/**
	 * @Given /^user "([^"]*)" has notifications$/
	 *
	 * @param string $user
	 */
	public function hasNotifications($user) {
		if ($user === 'test1') {
			$response = $this->setTestingValue('POST', 'apps/notificationsintegrationtesting/notifications', null);
			PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
			PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponse($response));
		}
	}

	/**
	 * @Given /^user "([^"]*)" receives notification with$/
	 *
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function receiveNotification($user, \Behat\Gherkin\Node\TableNode $formData) {
		if ($user === 'test1') {
			$response = $this->setTestingValue('POST', 'apps/notificationsintegrationtesting/notifications', $formData);
			PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
			PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponse($response));
		}
	}

	/**
	 * @Then /^list of notifications has (\d+) entries$/
	 *
	 * @param int $numNotifications
	 */
	public function checkNumNotifications($numNotifications) {
		$notifications = $this->getArrayOfNotificationsResponded($this->response);
		PHPUnit_Framework_Assert::assertCount((int) $numNotifications, $notifications);

		$notificationIds = [];
		foreach ($notifications as $notification) {
			$notificationIds[] = (int) $notification['notification_id'];
		}

		$this->notificationIds[] = $notificationIds;
	}

	/**
	 * @Then /^user "([^"]*)" has (\d+) notifications(| missing the last one| missing the first one)$/
	 *
	 * @param string $user
	 * @param int $numNotifications
	 * @param string $missingLast
	 */
	public function userNumNotifications($user, $numNotifications, $missingLast) {
		if ($user === 'test1') {
			$this->sendingTo('GET', '/apps/notifications/api/v1/notifications?format=json');
			PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());

			$previousNotificationIds = [];
			if ($missingLast) {
				PHPUnit_Framework_Assert::assertNotEmpty($this->notificationIds);
				$previousNotificationIds = end($this->notificationIds);
			}

			$this->checkNumNotifications((int) $numNotifications);

			if ($missingLast) {
				$now = end($this->notificationIds);
				if ($missingLast === ' missing the last one') {
					array_unshift($now, $this->deletedNotification);
				} else {
					$now[] = $this->deletedNotification;
				}

				PHPUnit_Framework_Assert::assertEquals($previousNotificationIds, $now);
			}

		}
	}

	/**
	 * @Then /^(first|last) notification matches$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function matchNotification($notification, $formData) {
		$lastNotifications = end($this->notificationIds);
		if ($notification === 'first') {
			$notificationId = reset($lastNotifications);
		} else/* if ($notification === 'last')*/ {
			$notificationId = end($lastNotifications);
		}

		$this->sendingTo('GET', '/apps/notifications/api/v1/notifications/' . $notificationId . '?format=json');
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
		$response = json_decode($this->response->getBody()->getContents(), true);

		foreach ($formData->getRowsHash() as $key => $value) {
			PHPUnit_Framework_Assert::assertArrayHasKey($key, $response['ocs']['data']);
			PHPUnit_Framework_Assert::assertEquals($value, $response['ocs']['data'][$key]);
		}
	}

	/**
	 * @Then /^delete (first|last) notification$/
	 *
	 * @param string $firstOrLast
	 */
	public function deleteNotification($firstOrLast) {
		PHPUnit_Framework_Assert::assertNotEmpty($this->notificationIds);
		$lastNotificationIds = end($this->notificationIds);
		if ($firstOrLast === 'first') {
			$this->deletedNotification = end($lastNotificationIds);
		} else {
			$this->deletedNotification = reset($lastNotificationIds);
		}
		$this->sendingTo('DELETE', '/apps/notifications/api/v1/notifications/' . $this->deletedNotification);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 * @param ResponseInterface $resp
	 * @return array
	 */
	public function getArrayOfNotificationsResponded(ResponseInterface $resp) {
		$jsonResponse = json_decode($resp->getBody()->getContents(), 1);
		return $jsonResponse['ocs']['data'];
	}

	/**
	 * @BeforeSuite
	 */
	public static function addFilesToSkeleton() {
		// The path to the skeleton files does not match, and we don't need them
	}

	/**
	 * @AfterSuite
	 */
	public static function removeFilesFromSkeleton() {
		// The path to the skeleton files does not match, and we don't need them
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function clearNotifications() {
		$response = $this->setTestingValue('DELETE', 'apps/notificationsintegrationtesting', null);
		PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
		PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponse($response));
	}

	/**
	 * @param $verb
	 * @param $url
	 * @param $body
	 * @return \GuzzleHttp\Message\FutureResponse|ResponseInterface|null
	 */
	protected function setTestingValue($verb, $url, $body) {
		$fullUrl = $this->baseUrl . "v2.php/" . $url;
		$client = new Client();
		$options = [
			'auth' => $this->adminUser,
		];
		if ($body instanceof \Behat\Gherkin\Node\TableNode) {
			$fd = $body->getRowsHash();
			$options['body'] = $fd;
		}

		try {
			return $client->send($client->createRequest($verb, $fullUrl, $options));
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return $ex->getResponse();
		}
	}
}