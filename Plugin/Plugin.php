<?php

namespace opensourceame\Logger\Plugin;

class Plugin
{
	private 	$parent				= null;
	private		$buffer				= null;
	private		$lastLogTime		= null;

	public		$logMode			= \opensourceame\Logger::modeRealTime;
	public		$logFile			= null;
	public		$logFileHandle		= STDOUT;
	public		$logStartStop		= true;
	public      $logPID             = false;
	public		$level				= 10;
	public		$useLabels			= false;
	public		$dateFormat			= 'Y-m-d H:i:s';
	public		$showElapsedTime	= false;
	public		$elapsedTimeFormat	= '[%2.3fs]';

	public		$labels				= array(
			0		=> 'init',
			1		=> 'fatal',
			2		=> 'warning',
			3		=> 'info',
			4		=> 'debug',
			5		=> 'trace',
	);

	/**
	 * Get the label for a specific level of logging
	 *
	 * @param integer $level
	 * @return string
	 */
	protected function getLabel($level)
	{
		if (! isset($this->labels[$level]))
			return 'unknown';

		return $this->labels[$level];
	}

	/**
	 * Instantiate the plugin and link back to the parent
	 *
	 * @param Logger $parentObject
	 */
  	public function __construct(\opensourceame\Logger $parentObject)
  	{
		var_dump("DFJKLFDJFKLD");
		$this->parent = $parentObject;

		$longest = 0;

		foreach ($this->labels as $label)
		{
			$longest = max($longest, strlen($label));
		}

		$longest ++;

		foreach ($this->labels as $key => $label)
		{
			$this->labels[$key] = $label . str_repeat(' ', $longest - (strlen($label)));
		}

		$this->getElapsedTime();

		return true;
	}

	/**
	 * Return the link back to the parent object
	 *
	 * @return \opensourceame\Logger
	 */
	protected function parent()
	{
		return $this->parent;
	}

	protected function addError($message)
	{
		return $this->parent()->addError($message);
	}

	public function start()
	{
		if ($this->logFile != null)
		{
			if (false === ($this->logFileHandle = @ fopen($this->logFile, 'a')))
			{
				$this->addError("cannot write to $this->logFile");

				return false;
			}
		}

		return true;
	}

	public function stop()
	{
		if ($this->logMode == \opensourceame\Logger::modeBuffered)
		{
			fwrite($this->logFileHandle, $this->getBuffer());
		}

		return true;
	}

	/**
	 * Log the message
	 */
	public function log($level, $message)
	{
		echo $level.' : '.$message;

		return true;
	}

	/**
	 * Fatal defaults to level 1
	 */
	public function fatal($message)
	{
		return $this->parent()->log(1, $message);
	}

	/**
	 * Warning defaults to level 2
	 */
	public function warning($message)
	{
		return $this->parent()->log(2, $message);
	}

	/**
	 *  Info defaults to level 3
	 */
	public function info($message)
	{
		return $this->parent()->log(3, $message);
	}

	/**
	 * Debug defaults to level 4
	 */
	public function debug($message)
	{
		return $this->parent()->log(4, $message);
	}

	/**
	 * Trace defaults to level 5
	 */
	public function trace($message)
	{
		return $this->parent()->log(5, $message);
	}

	/**
	 * Output or buffer text depending on the logging mode
	 */
	protected function output($text)
	{
		if ($this->logMode == \opensourceame\Logger::modeRealTime)
		{
			return @ fwrite($this->logFileHandle, $text);
		}

		return $this->buffer($text);

	}

	/**
	 * Add text to the buffer
	 */
	protected function buffer($text)
	{
		$this->buffer .= $text;

		return true;
	}

	/**
	 * @return 			string contents of the buffer
	 */
	protected function getBuffer()
	{
		return $this->buffer;
	}

	/**
	 * Clear the contents of the buffer
	 */
	protected function clearBuffer()
	{
		$this->buffer = null;

		return true;
	}

	/**
	 * Get the date in the format specified
	 */
	public function getDate()
	{
		return date($this->dateFormat);
	}

	public function getElapsedTime()
	{
		$now 		= microtime(true);
		$elapsed 	= sprintf($this->elapsedTimeFormat, round($now - $this->lastLogTime, 3));

		$this->lastLogTime = $now;

		return $elapsed;
	}
}
