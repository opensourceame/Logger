<?php

namespace opensourceame\Logger\Plugin;

class XML extends Plugin
{
	public	$logMode		= \opensourceame\Logger::modeBuffered;
	public	$useAttributes	= false;

	public function start()
	{
		parent::start();

		$this->output("<xml version='1.0' encoding='UTF-8'>");

		return true;
	}

	public function stop()
	{
		$this->output("</xml>\n");

		return parent::stop();
	}

	public function log($level, $message)
	{
		$message	= htmlentities( $message, ENT_QUOTES );
		$dateTime 	= $this->getDate();
		$elapsed	= $this->getElapsedTme();

		if ($this->useAttributes)
		{
			$t = "\n\t<log date='$dateTime' level='$level'>$message</log>";
		} else {
			$t = <<<END

	<log>
		<level>$level</level>
		<date>$dateTime</date>
		<message>$message</message>
		<elapsed>$elapsed</elapsed>
	</log>

END;
		}

		$this->output($t);

		return true;
	}

}
