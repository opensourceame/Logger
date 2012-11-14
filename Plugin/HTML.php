<?php

namespace opensourceame\Logger\Plugin;

class HTML extends Plugin
{
	public	$logMode		= \opensourceame\Logger::modeBuffered;
	public	$dateFormat		= 'Y-m-d H:i:s';
	public	$rowsStyled		= false;
	public	$rowsStyles		= array(
			-1		=> 'color: red',
	);
	public	$cssClasses		= array(
			'table'		=> 'logTable',
			'row'		=> 'logRow',
			'cell'		=> 'logCell',
	);

	public function start()
	{
		parent::start();

		$this->output("<table class='{$this->cssClasses['table']}'>\n");

		return true;
	}

	public function getRowTag($level)
	{
		if ( (! $this->rowsStyled)
				or ( $this->rowsStyled and ! isset($this->rowsStyles[$level]))
		)
		{
			return "\n\t<tr class='{$this->cssClasses['row']}'>\n";
		}

		return "\n\t<tr class='{$this->cssClasses['row']}' style='{$this->rowsStyles[$level]}'>\n";
	}

	public function log($level, $message)
	{
		$dateTime 	= $this->getDate();
		$width 		= $level * 10;

		$t  = $this->getRowTag($level);

		$t .= <<<END
		<td class='{$this->cssClasses['cell']}'>$dateTime</td>
		<td class='{$this->cssClasses['cell']}' style='width: {$width}px;'></td>
		<td class='{$this->cssClasses['cell']}'>$level</td>
		<td class='{$this->cssClasses['cell']}'>$message</td>
END;

		if ($this->showElapsedTime)
		{
			$elapsed = $this->getElapsedTime();
			$t .= "<td class='{$this->cssClasses['cell']}'>$elapsed</td>";
		}

		$t .= "\n</tr>\n";

		$this->output($t);

		return true;
	}

	public function stop()
	{
		$this->output("\n</table>\n\n");

		return parent::stop();
	}
}