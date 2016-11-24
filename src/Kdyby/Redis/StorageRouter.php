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
		$journal = $this->journal;
		$this->journal = $this->createCleanJournal();
		try {
			foreach ($this->clients as $client) {
				$this->client = $client;
				parent::clean($conditions);
			}
		} finally {
			$this->journal = $journal;
		}
	}


	private function createCleanJournal()
	{
		return new class($this->journal) implements Nette\Caching\Storages\IJournal
		{

			/**
			 * @var Nette\Caching\Storages\IJournal
			 */
			private $journal;
			/**
			 * @var bool
			 */
			private $result = FALSE;


			public function __construct(Nette\Caching\Storages\IJournal $journal = NULL)
			{
				$this->journal = $journal;
			}


			public function write($key, array $dependencies)
			{
				throw new Nette\NotImplementedException;
			}


			public function clean(array $conditions)
			{
				if ($this->journal && $this->result === FALSE) {
					$this->result = $this->journal->clean(...func_get_args());
				}

				return $this->result;
			}

		};
	}

}
