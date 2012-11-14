<?php

namespace opensourceame\Logger\Plugin;

class Text extends Plugin
{
	public $logFile 		= null;
	public $prefix 			= null;
	public $indentText 		= '  ';

	public function log($level, $message)
	{
		return $this->output($this->formatLine($level, $message) . "\n");
	}

	public function formatLine($level, $message)
	{

		$m  = $this->getDate()  . ' ';
		$m .= $this->prefix 	. ' ';

		if ($this->logPID) {
			$m .= sprintf('[%7s] ', getmypid());
		}

		if ($this->showElapsedTime) {
			$m .= $this->getElapsedTime() . ' ';
		}

		if ($this->useLabels) {
			$m .= $this->getLabel($level) . ' ';
		}

		// append a prefix to negative level messages
		if ($level < 0) {
			$message = '!! ' . $message;
		} else {
			$message = str_repeat($this->indentText, (int) $level) . $message;
		}

		$message = $m . $message;

		return $message;
	}

}

