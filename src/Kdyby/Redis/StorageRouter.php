<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Kdyby;
use Nette;


/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class StorageRouter extends RedisStorage
{

	/**
	 * @var ClientsPool
	 */
	private $clients;



	public function __construct(ClientsPool $clients, JournalRouter $journal = NULL)
	{
		$this->clients = $clients;
		$this->journal = $journal;
	}


	public function read($key)
	{
		$this->client = $this->clients->choose($key);
		return parent::read($key);
	}



	public function lock($key)
	{
		$this->client = $this->clients->choose($key);
		parent::lock($key);
	}



	public function write($key, $data, array $dependencies)
	{
		$this->client = $this->clients->choose($key);
		parent::write($key, $data, $dependencies);
	}



	public function remove($key)
	{
		$this->client = $this->clients->choose($key);
		return parent::remove($key);
	}


	public function clean(array $conditions)
	{
		// cleaning using file iterator
		if ( ! empty($conditions[Nette\Caching\Cache::ALL])) {
			foreach ($this->clients as $client) {
				if ($keys = $client->send('keys', array(self::NS_NETTE . ':*'))) {
					$client->send('del', $keys);
				}
			}

			if ($this->journal) {
				$this->journal->clean($conditions);
			}

			return;
		}

		// cleaning using journal
		if ($this->journal) {
			if ($keys = $this->journal->clean($conditions, $this)) {
				foreach ($this->clients as $client) {
					$client->send('del', $keys);
				}
			}
		}
	}

}
