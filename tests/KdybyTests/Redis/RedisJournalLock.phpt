<?php

/**
 * Test: \Kdyby\Redis\RedisStorage.
 *
 * @testCase \Kdyby\Redis\RedisJournalLock
 * @author Václav Čevela <spamer@spameri.cz>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Václav Čevela <spamer@spameri.cz>
 */
class RedisJournalLock extends AbstractRedisTestCase
{

	/**
	 * @var \Kdyby\Redis\RedisJournal
	 */
	public $journal;

	/**
	 * @var \Kdyby\Redis\RedisStorage
	 */
	private $storage;



	public function setUp()
	{
		parent::setUp();
		$this->journal = new \Kdyby\Redis\RedisJournal($this->client);
		$this->storage = new \Kdyby\Redis\RedisStorage(
			$this->client,
			$this->journal
		);
	}



	/**
	 * key and data with special chars
	 *
	 * @return array
	 */
	public function basicData()
	{
		return [
			$key = [1, TRUE],
			$value = range("\x00", "\xFF"),
		];
	}



	public function testBasics()
	{
		list($key, $value) = $this->basicData();

		$cache = new \Nette\Caching\Cache(
			$this->storage
		);
		\Tester\Assert::null($cache->load($key), "Cache content");

		// Writing cache...
		$cache->save($key, $value);
		\Tester\Assert::same($value, $cache->load($key), "Is cache ok?");

		// Removing from cache using unset()...
		$cache->remove($key);
		\Tester\Assert::false($cache->load($key) !== NULL, "Is cached?");

		// Removing from cache using set NULL...
		$cache->save($key, $value);
		$cache->save($key, NULL);
		\Tester\Assert::false($cache->load($key) !== NULL, "Is cached?");
	}

	public function testJournalLocked()
	{
		list($key, $value) = $this->basicData();

		$cache = new \Nette\Caching\Cache(
			$this->storage
		);

		$this->journal->lock('Test-T4G');
		$cache->save($key, $value, [
			\Nette\Caching\Cache::TAGS => [
				'Test-T4G'
			]
		]);

		\Tester\Assert::false($cache->load('Test-T4G') !== NULL, 'Is cached?');
	}


	/**
	 * @param mixed $val
	 * @return mixed
	 */
	public static function dependency($val)
	{
		return $val;
	}


}

\run(new RedisJournalLock());
