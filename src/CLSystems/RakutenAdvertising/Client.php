<?php

namespace CLSystems\RakutenAdvertising;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Client
 *
 * @package CLSystems\RakutenAdvertising
 */
class Client
{
	/**
	 * @var string
	 */
	const RAKUTEN_API_BASE_URL = 'https://api.rakutenmarketing.com/';

	/**
	 * @var int
	 */
	const MAX_DAYS = 3;

	/**
	 * @var int
	 */
	const MAX_EVENTS = 1000;

	/**
	 * @var string
	 */
	protected $userName;

	/**
	 * @var string
	 */
	protected $passWord;

	/**
	 * @var string
	 */
	protected $apiKey;

	/**
	 * @var DateTime|null
	 */
	protected $localDateStart;

	/**
	 * @var DateTime|null
	 */
	protected $localDateEnd;

	/**
	 * @var string|null
	 */
	protected $rakutenDateStart;

	/**
	 * @var string|null
	 */
	protected $rakutenDateEnd;

	/**
	 * @var array|null
	 */
	protected $token;

	/**
	 * @var int
	 */
	private $accountId;

	/**
	 * @return string
	 */
	public function getUserName() : string
	{
		return $this->userName;
	}

	/**
	 * @param string $userName
	 * @return void
	 */
	public function setUserName(string $userName) : void
	{
		$this->userName = $userName;
	}

	/**
	 * @return string
	 */
	public function getPassWord() : string
	{
		return $this->passWord;
	}

	/**
	 * @param string $passWord
	 * @return void
	 */
	public function setPassWord(string $passWord) : void
	{
		$this->passWord = $passWord;
	}

	/**
	 * @return string
	 */
	public function getApiKey() : string
	{
		return $this->apiKey;
	}

	/**
	 * @param string $apiKey
	 * @return void
	 */
	public function setApiKey(string $apiKey) : void
	{
		$this->apiKey = $apiKey;
	}

	/**
	 * @param int $accountId
	 */
	public function setAccountId(int $accountId)
	{
		$this->accountId = $accountId;
	}

	/**
	 * Initialize the client
	 * Usage:
	 * $client = (new Client)->initialize([your_username], [your_password], [your_api_key], [website_id]);
	 *
	 * This does the same as:
	 * $client = new Client();
	 * $client->setUsername([your_username]);
	 * $client->setPassWord([your_password]);
	 * $client->setApiKey([your_api_key]);
	 * $client->setAccountId([website_id]);
	 * $client->retrieveNewToken();
	 *
	 * @param string $userName
	 * @param string $passWord
	 * @param string $apiKey
	 * @param int $accountId
	 * @return $this
	 * @throws GuzzleException
	 */
	public function initialize(string $userName, string $passWord, string $apiKey, int $accountId)
	{
		$this->userName = $userName;
		$this->passWord = $passWord;
		$this->apiKey = $apiKey;
		$this->accountId = $accountId;
		$this->retrieveNewToken();
		return $this;
	}

	/**
	 * Init transaction dates
	 *
	 * @param int $daysBack
	 * @return void
	 */
	protected function init(int $daysBack = 3)
	{
		$this->localDateEnd = new DateTime();
		$this->localDateStart = (clone $this->localDateEnd)->modify('-' . $daysBack . 'days');

		$this->rakutenDateEnd = (clone $this->localDateEnd)->setTimezone(new DateTimeZone('GMT'))->format('Y-m-d H:i:s');
		$this->rakutenDateStart = (clone $this->localDateStart)->setTimezone(new DateTimeZone('GMT'))->format('Y-m-d H:i:s');
	}

	/**
	 * Get Rakuten access token
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	protected function getToken()
	{
		$this->loadToken();
		$this->refreshToken();
		if (true === empty($this->token))
		{
			$this->retrieveNewToken();
		}
		$this->saveToken();
	}

	/**
	 * Refresh Rakuten access token
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	protected function refreshToken()
	{
		$client = new GuzzleClient([
			'base_uri' => static::RAKUTEN_API_BASE_URL,
		]);

		try
		{
			$response = $client->post('token', [
				'headers'     => [
					'Authorization' => 'Basic ' . $this->apiKey,
				],
				'form_params' => [
					'grant_type'    => 'refresh_token',
					'refresh_token' => $this->token['refresh_token'] ?? '',
					'scope'         => 'Production',
				],
			]);
		}
		catch (ClientException $exception)
		{
			if (400 === $exception->getCode())
			{
				$errorMessage = json_decode($exception->getResponse()->getBody()->getContents(), true);
				if (true === isset($errorMessage['error']) && 'invalid_grant' === $errorMessage['error'])
				{
					$this->token = null;
					return;
				}
			}

			echo 'Error refreshing Rakuten token: ' . $exception->getMessage() . "\n";
			exit;
		}

		$this->token = json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * Retrieve new Rakuten token
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	protected function retrieveNewToken()
	{
		$client = new GuzzleClient([
			'base_uri' => static::RAKUTEN_API_BASE_URL,
		]);

		try
		{
			$response = $client->post('token', [
				'headers'     => [
					'Authorization' => 'Basic ' . $this->apiKey,
				],
				'form_params' => [
					'grant_type' => 'password',
					'username'   => $this->userName,
					'password'   => $this->passWord,
					'scope'      => $this->accountId,
				],
			]);
		}
		catch (ClientException $exception)
		{
			echo 'Error: Retrieving new Rakuten token: ' . $exception->getMessage() . "\n";
			exit;
		}

		$this->token = json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * Load Rakuten access token
	 *
	 * @param string|null $jsonToken
	 * @return void
	 * @throws GuzzleException
	 */
	protected function loadToken(string $jsonToken = null)
	{
		if (null === $jsonToken)
		{
			$this->retrieveNewToken();
		}
		$this->token = json_decode($jsonToken, true);
	}

	/**
	 * Save Rakuten access token
	 *
	 * @param array $token
	 * @return void
	 */
	protected function saveToken(array $token)
	{
		// @TODO Save the token somewhere (db table for instance)
	}

	/**
	 * Process Rakuten events
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	protected function processEvents()
	{
		$page = 1;
		do
		{
			$events = $this->retrieveEvents($page);
			foreach ($events as $event)
			{
				$this->processEvent($event);
			}

			++$page;
		}
		while (count($events) === static::MAX_EVENTS);
	}

	/**
	 * Retrieve Rakuten events
	 *
	 * @param int $page
	 * @return array
	 * @throws GuzzleException
	 */
	protected function getTransactions(int $page = 1): array
	{
		$client = new GuzzleClient([
			'base_uri' => static::RAKUTEN_API_BASE_URL,
		]);

		try
		{
			$response = $client->get('events/1.0/transactions', [
				'headers' => [
					'Authorization' => 'Bearer ' . $this->token['access_token'],
					'Accept'        => 'text/json',
				],
				'query'   => [
					'page'                   => $page,
					'limit'                  => static::MAX_EVENTS,
					'transaction_date_start' => $this->rakutenDateStart,
					'transaction_date_end'   => $this->rakutenDateEnd,
				],
			]);
		}
		catch (ClientException $exception)
		{
			echo 'Error retrieving Rakuten transactions: ' . $exception->getMessage() . "\n";
			exit;
		}

		return json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * Process Rakuten event
	 *
	 * @param array $event
	 * @return void
	 */
	protected function processEvent(array $event)
	{
		$this->log('Processing Rakuten event (' . $event['etransaction_id'] . ')');

		// Process transactions only
		if ('Y' === $event['is_event'])
		{
			$this->log('Skipping Rakuten event (not a transaction)');
			return;
		}

		// Rakuten u1 parameter (program_id|dci)
		$u1 = explode('|', $event['u1']);
		if (2 !== count($u1))
		{
			echo 'Warning: Rakuten u1 parameter is invalid: ' . var_export($event, true) . "\n";
			return;
		}
		$event['program_id'] = $u1[0];
		$event['dci'] = $u1[1];

		// Check if transaction exists
		$transaction = $this->getTransaction($event);
		if (false === empty($transaction))
		{
			$this->log('Transaction found, skipping insert');
			return;
		}

		$this->insertTransaction($event);
	}

	/**
	 * Get transaction
	 *
	 * @param array $event
	 * @return array
	 */
	protected function getTransaction(array $event): array
	{
		$this->log('Retrieving transaction');

		return TransactionData::getTransactions([
			'source'      => TransactionData::ADVERTISER_AS_SOURCE,
			'program_id'  => $event['program_id'],
			'start_date'  => $this->localDateStart,
			'end_date'    => $this->localDateEnd,
			'program_tag' => $event['etransaction_id'],
		]);
	}

	/**
	 * Insert transaction via direct tracking
	 *
	 * @param array $event
	 * @return void
	 */
	protected function insertTransaction(array $event)
	{
		$this->log('Inserting transaction (calling direct trackling)');

		$transactionDate = $this->formatEventDate($event['transaction_date']);
		if ('' === $transactionDate)
		{
			echo 'Warning: Unable to format transaction date: ' . $event['transaction_date'] . "\n";
			return;
		}

		$program = Std::first(ProgramData::getProgram(['id' => $event['program_id']]));
		if (false === $program)
		{
			echo 'Warning: Unable to retrieve program: ' . $event['program_id'] . "\n";
			return;
		}

		$advertiser = Std::first(AdvertiserData::getAdvertiser(['id' => $program['advertiser_id']]));
		if (false === $advertiser)
		{
			echo 'Warning: Unable to retrieve advertiser: ' . $program['advertiser_id'] . "\n";
			return;
		}

		$trackingSegment = Std::first(TrackingSegmentData::getTrackingSegment(['id' => $advertiser['tracking_segment_id']]));
		if (false === $advertiser)
		{
			echo 'Warning: Unable to retrieve tracking segement: ' . $advertiser['tracking_segment_id'] . "\n";
			return;
		}

		$client = new GuzzleClient([
			'base_uri' => 'https://' . $trackingSegment['tracking_domain'] . '/',
		]);

		$query = [
			'ci'  => $event['program_id'],
			'dci' => $event['dci'],
			'ti'  => $event['etransaction_id'],
			'td'  => $transactionDate,
			'cur' => $event['currency'],
			'a'   => $event['commissions'],
			'r'   => $event['sale_amount'],
			'oa'  => $event['product_name'],
			'sku' => $event['sku_number'],
			'qty' => $event['quantity'],
			'e1'  => $event['order_id'],
		];

		try
		{
			$response = $client->get('d', [
				'verify'          => false,
				'allow_redirects' => true,
				'query'           => $query,
			]);
		}
		catch (ClientException $exception)
		{
			echo 'Error: Inserting transaction via direct tracking: ' . $exception->getMessage() . "\n";
			exit;
		}

		$result = json_decode($response->getBody()->getContents(), true);
		if (false === isset($result['affiliatemarketing_id']))
		{
			echo 'Warning: Inserting transaction via direct tracking: '
				. var_export($response->getBody()->getContents(), true)
				. ' using the following query parameters: '
				. var_export($query, true)
				. "\n";
		}
	}

	/**
	 * Format event date
	 *
	 * @param string $date
	 * @return string
	 */
	protected function formatEventDate(string $date): string
	{
		$date = str_replace('+0000 (UTC)', '', $date);
		$date = DateTime::createFromFormat('D M d Y H:i:s T', $date);

		if (false === $date || array_sum($date::getLastErrors()) > 0)
		{
			return '';
		}

		$date->setTimezone(new DateTimeZone('CET'));
		return $date->format('Y-m-d H:i:s');
	}

}