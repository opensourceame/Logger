<?php

namespace opensourceame\Logger\Plugin;

class zTemplate extends Plugin
{
	public		$logMode			= opensourceame\Logger::modeBuffered;
	public		$templateFile		= null;
	protected	$template			= null;
	public		$mainBlock			= 'logger';
	public		$levelBlocks		= array(
			-1		=> 'log',
		 0		=> 'log',
		 1		=> 'log',
		 2		=> 'log',
		 3		=> 'log',
		 4 		=> 'log',
		 5		=> 'log',
		 6		=> 'log',
		 7		=> 'log',
		 8		=> 'log',
		 9		=> 'log',
		 10		=> 'log',
	);

	public function start()
	{
		parent::start();

		require_once 'opensourceame.ztemplate.php';

		$this->template = new \zTemplate($this->templateFile);

		return true;
	}

	public function stop()
	{
		$this->template->parse($this->mainBlock);

		return fwrite($this->logFileHandle, $this->template->get($this->mainBlock));
	}


	public function log($level, $message)
	{

		$this->template->assign('level', 	$level);
		$this->template->assign('message', 	$message);
		$this->template->assign('date', 	$this->getDate());
		$this->template->assign('elapsed',	$this->getElapsedTime());

		$block = $this->levelBlocks[$level];

		return $this->template->parse("$this->mainBlock.$block");
	}

	public function assign($name, $value)
	{
		return $this->template->assign($name, $value);
	}
}
