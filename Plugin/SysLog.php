<?php

namespace opensourceame\Logger\Plugin;

class SysLog extends Plugin
{
	public		$prefix			= null;
	public		$indentText		= '  ';
	public		$priority		= LOG_INFO;
	public		$facility		= LOG_USER;
	public		$logPID			= false;

	public function setupSyslog()
	{
		$options = ($this->logPID == true) ? LOG_PID : ! LOG_PID;

		return openlog($this->prefix, $options, $this->facility);

	}

	public function formatLine($level, $message)
	{
		// append a prefix to negative level messages
		if ($level < 0)
		{
			$message = '!! ' . $message;
		} else {
			$message = str_repeat($this->indentText, (int) $level) . $message;
		}

		if ($this->useLabels)
		{
			$message = $this->getLabel($level) . ' ' . $message;
		}

		if ($this->showElapsedTime)
		{
			$message = $this->getElapsedTime() . ' ' . $message;
		}

		return $message;
	}

	public function log($level, $message)
	{
		$this->setupSyslog();

		return syslog($this->priority, $this->formatLine($level, $message));

	}

}