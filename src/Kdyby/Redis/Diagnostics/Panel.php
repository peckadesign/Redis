<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis\Diagnostics;

use Kdyby;
use Kdyby\Redis\RedisClientException;
use Nette;
use Nette\PhpGenerator as Code;
use Tracy\Debugger;
use Tracy\IBarPanel;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Panel extends Nette\Object implements IBarPanel
{

	/** @internal */
	public const TIMER_NAME = 'redis-client-timer';

	/**
	 * @var int
	 */
	public static $maxLength = 1000;

	/**
	 * @var float
	 */
	private $totalTime = 0;

	/**
	 * @var array
	 */
	private $queries = [];

	/**
	 * @var array
	 */
	private $errors = [];

	/**
	 * @var bool
	 */
	public $renderPanel = TRUE;

	/**
	 * @var string
	 */
	public $name;



	public function getQueryCount(): int
	{
		return count($this->queries);
	}



	/**
	 * @return int milliseconds
	 */
	public function getTotalTime(): int
	{
		return $this->totalTime * 1000;
	}



	public function begin(array $args, int $dbIndex): void
	{
		if (!$this->renderPanel) {
			$cmd = '';

		} else {
			$cmd = [];
			foreach ($args as $arg) {
				if (!$arg instanceof \Closure) {
					$cmd[] = is_array($arg) ? urldecode(http_build_query($arg, '', ' ')) : $arg;
				}
			}
			$cmd = implode(' ', $cmd);
		}

		$this->queries[] = (object) [
			'errors' => [],
			'cmd' => $cmd,
			'db' => $dbIndex,
			'time' => 0
		];

		Debugger::timer(self::TIMER_NAME); // reset timer
	}



	public function error(\Throwable $e): void
	{
		$this->errors[] = $e;
		if ($query = end($this->queries)) {
			$query->errors[] = $e;
		}
	}



	public function end(): void
	{
		$time = Debugger::timer(self::TIMER_NAME);
		if ($query = end($this->queries)) {
			$query->time = $time;
		}
		$this->totalTime += $time;
	}



	/**
	 * Renders HTML code for custom tab.
	 */
	public function getTab(): string
	{
		return
			'<style>
				#nette-debug div.kdyby-RedisClientPanel table td,
				#tracy-debug div.kdyby-RedisClientPanel table td { text-align: right }
				#nette-debug div.kdyby-RedisClientPanel table td.kdyby-RedisClientPanel-cmd,
				#tracy-debug div.kdyby-RedisClientPanel table td.kdyby-RedisClientPanel-cmd { background: white !important; text-align: left }
				#nette-debug .kdyby-redis-panel svg,
				#tracy-debug .kdyby-redis-panel svg { vertical-align: bottom; max-height: 1.55em; width: 1.50em; }
			</style>' .
			'<span title="Redis Storage' . ($this->name ? ' - ' . $this->name : '') . '" class="kdyby-redis-panel">' .
			file_get_contents(__DIR__ . '/logo.svg') .
			'<span class="tracy-label">' .
				count($this->queries) . ' queries' .
				($this->errors ? ' / ' . count($this->errors) . ' errors' : '') .
				($this->queries ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . ' ms' : '') .
			'</span></span>';
	}



	/**
	 * Renders HTML code for custom panel.
	 */
	public function getPanel(): string
	{
		if (!$this->renderPanel) {
			return '';
		}

		$s = '';
		$h = 'htmlSpecialChars';
		foreach ($this->queries as $query) {
			$s .= '<tr><td>' . sprintf('%0.3f', $query->time * 1000000);
			$s .= '</td><td class="kdyby-RedisClientPanel-dbindex">' . $query->db;
			$s .= '</td><td class="kdyby-RedisClientPanel-cmd">' .
				$h(substr(Code\Helpers::dump(self::$maxLength ? substr($query->cmd, 0, self::$maxLength) : $query->cmd), 1, -1));
			$s .= '</td></tr>';
		}

		return empty($this->queries) ? '' :
			'<h1>Queries: ' . count($this->queries) . ($this->totalTime ? ', time: ' . sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : '') . '</h1>
			<div class="nette-inner tracy-inner kdyby-RedisClientPanel">
			<table>
				<tr><th>Time&nbsp;µs</th><th title="Database index">DB</th><th>Command</th></tr>' . $s . '
			</table>
			</div>';
	}



	public static function renderException(RedisClientException $e): ?array
	{
		$panel = NULL;
		if ($e instanceof RedisClientException) {
			if ($e->request) {
				$panel .= '<h3>Redis Request</h3>' .
					'<pre class="nette-dump"><span class="php-string">' .
					nl2br(htmlSpecialChars(implode(' ', $e->request))) .
					'</span></pre>';
			}
			if ($e->response) {
				$response = Code\Helpers::dump($e->response);
				$panel .= '<h3>Redis Response (' . strlen($e->response) . ')</h3>' .
					'<pre class="nette-dump"><span class="php-string">' .
					htmlSpecialChars($response) .
					'</span></pre>';
			}

			if ($panel !== NULL) {
				$panel = [
					'tab' => 'Redis Response',
					'panel' => $panel
				];
			}
		}

		return $panel;
	}



	public static function register(): \Kdyby\Redis\Diagnostics\Panel
	{
		self::getDebuggerBlueScreen()->addPanel([$panel = new static(), 'renderException']);
		self::getDebuggerBar()->addPanel($panel);
		return $panel;
	}



	private static function getDebuggerBar(): \Tracy\Bar
	{
		return Debugger::getBar();
	}



	private static function getDebuggerBlueScreen(): \Tracy\BlueScreen
	{
		return Debugger::getBlueScreen();
	}

}
