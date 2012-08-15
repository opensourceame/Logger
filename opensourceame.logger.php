<?php
/**
 * A flexible logger class
 *
 * @description		a flexible class for logging actions
 * @package 		opensourceame
 * @subpackage		logger
 * @author			David Kelly
 * @copyright		David Kelly, 2009 (http://opensourceame.com)
 * @version			3.4
 */

namespace opensourceame;

if (! defined('STDOUT'))
{
        define('STDOUT', null);
}

/**
 * This is the primary logger class which sets up the plugin and passes relevant
 * calls to the plugin.
 */
class logger
{
	const		version					= '3.5.1';
	const		modeRealTime			= 1;
	const		modeBuffered			= 2;
	const		logLevelFatal			= 1;
	const		logLevelWarning			= 2;
	const		logLevelInfo			= 3;
	const		logLevelDebug			= 4;
	const		logLevelTrace			= 5;

	private		$_plugin				= null;
	private		$_pluginError			= false;
	private		$_pluginErrorMessage	= false;
	private		$_pluginProperties		= array();

	private		$messageCount			= 0;

	protected	$logFile				= null;
	protected 	$logFileHandle			= STDOUT;
	protected	$logMode				= self::modeRealTime;
	protected	$logStartStop			= true;

	protected	$level					= 5;
	protected	$pluginReady			= false;
	protected	$config					= array(
		'level'				=> 5,
		'plugin'			=> 'Text',
		'dateFormat'		=> 'Y-m-d H:i:s',
		'breakOnError'		=> false,
	);

	public		$timeStart				= null;
	public		$timeStop				= null;
	public		$breakOnError			= false;

	/**
	 * Class constructor
	 *
	 * @param array|string $config Configuration for the logger
	 *
	 */
	public function __construct($config = false)
	{
		if ($config == false)
			return true;

		$this->timeStart	= time();

		if (is_array($config))
		{
			$this->config = array_merge($this->config, $config);
		}

		if (is_string($config))
		{
			$this->config['plugin'] = $config;
		}

		if (! isset($this->config['plugin']))
		{
			$this->error("no plugin specified for logger");
		}

		if (! $this->loadPlugin($this->config['plugin']))
		{
			$this->error("logger cannot load plugin ".$this->config['plugin']);
		}

		$this->level 		= $this->config['level'];
		$this->breakOnError	= $this->config['breakOnError'];

		return true;
	}

	/**
	 * Class destructor always stops the logger plugin
	 */
	public function __destruct()
	{
		if ($this->logStartStop)
			$this->log(0, 'stop');

		if ($this->_pluginError)
		{
			return false;
		}

		return $this->plugin()->stop();
	}

	/**
	 * Magic set method copies the value over to the plugin
	 *
	 * @param unknown_type $name
	 * @param unknown_type $value
	 */
	public function __set($name, $value)
	{
		// copy value to the plugin if appropriate
		if (isset($this->_pluginProperties[$name]))
		{
			$this->plugin()->$name = $value;
		} else {
			echo "no property in plugin:\n";
		}

	}

	/**
	 * Passes the call onto the plugin if no public local method exists
	 *
	 */
	public function __call($method, $arguments)
	{
		if ( $this->_pluginError and ! $this->breakOnError)
		{
			// skip any function calls
			return false;
		}

		if (! method_exists($this, $method))
		{
		    return call_user_func_array(array($this->plugin(), $method), $arguments);
		}

		return false;
	}

	public function error($message)
	{
		$this->_pluginError					= true;
		$this->_pluginErrorMessage			= $message;

		return false;
	}

	/**
	 * Create an instance of the plugin class
	 *
	 * @param		string $plugin The plugin name (e.g. XML)
	 * @return 		boolean
	 */
	public function loadPlugin($plugin)
	{
		$classes = array(
			"\opensourceame\loggerPlugin_$plugin",
			$plugin,
		);

		foreach ($classes as $c)
		{
			if (class_exists($c))
			{
				$this->_plugin = new $c($this);

				// copy config to the properties of the plugin
				foreach ($this->config as $key => $val)
				{
					if (property_exists($this->plugin(), $key))
						$this->plugin()->$key = $val;
				}

				$reflection = new \ReflectionClass($c);

				foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $p)
				{
					$this->_pluginProperties[$p->name] = true;
				}

				return true;
			}
		}

		return false;

	}

	/**
	 * Returns the plugin object
	 *
	 * @return		object	Plugin object
	 */
	public function plugin()
	{
		return $this->_plugin;
	}

	/**
	 * Log an entry. The level and message are passed on to the plugin's log() method.
	 * Also calls the plugin's start() method if the plugin is not ready.
	 */
	public	function log($level, $message)
	{
		if (! $this->pluginReady)
		{
			// run the setup class method
			if (! $this->plugin()->start())
			{
				return false;
			}

			$this->pluginReady = true;

			if ($this->logStartStop)
				$this->log(0, 'start');
		}

		// only log negative levels or those below the configured threshold
		if ( ($this->level < $level) and ($level > 0) )
			return false;

		// arrays get logged as individual lines
		if (is_array($message))
		{
			foreach ($message as $m)
				$this->log($level, $m);

			return true;
		}

		try {

			$result = $this->plugin()->log($level, $message);

		} catch(Exception $e) {

			throw $e;
		}

		if ( ($result === false) and $this->breakOnError)
		{
			throw new Exception('logger plugin failed');
		}

		$this->messageCount ++;

		return $result;
	}

	public function registerInstance($name = null)
	{
		$GLOBALS['_opensourceame_logger'][$name] =& $this;
	}

	static public function instanceExists($name)
	{
		return isset($GLOBALS['_opensourceame_logger'][$name]);
	}

	static public function getInstance($name = null)
	{
		if (self::instanceExists($name))
		{
			return $GLOBALS['_opensourceame_logger'][$name];
		} else {
			return new logger(array(
				'plugin'	=> 'SysLog',
			));
		}
	}
}

class loggerPlugin
{
	private 	$_parent			= null;
	private		$buffer				= null;
	private		$lastLogTime		= null;

	public		$logMode			= logger::modeRealTime;
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

	protected function getLabel($level)
	{
		if (! isset($this->labels[$level]))
			return 'unknown';

		return $this->labels[$level];
	}

	/**
	 * Instantiate the plugin and link back to the parent
	 *
	 * @param logger $parentObject
	 */
	public function __construct(logger $parentObject)
	{
		$this->_parent = $parentObject;

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

	public function parent()
	{
		return $this->_parent;
	}

	public function error($message)
	{
		return $this->parent()->error($message);
	}

	public function start()
	{
		if ($this->logFile != null)
		{
			if (false === ($this->logFileHandle = @ fopen($this->logFile, 'a')))
			{
				$this->error("cannot write to $this->logFile");

				return false;
			}
		}

		return true;
	}

	public function stop()
	{
		if ($this->logMode == logger::modeBuffered)
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
	 * Trance defaults to level 5
	 */
	public function trace($message)
	{
		return $this->parent()->log(5, $message);
	}

	/**
	 * Output or buffer text depending on the logging mode
	 */
	public function output($text)
	{
		if ($this->logMode == logger::modeRealTime)
		{
			return @ fwrite($this->logFileHandle, $text);
		}

		return $this->buffer($text);

	}

	/**
	 * Add text to the buffer
	 */
	public function buffer($text)
	{
		$this->buffer .= $text;

		return true;
	}

	/**
	 * @return 			string contents of the buffer
	 */
	public function getBuffer()
	{
		return $this->buffer;
	}

	/**
	 * Clear the contents of the buffer
	 */
	public function clearBuffer()
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

class loggerPlugin_null extends loggerPlugin
{
	public function log($level, $message)
	{
		return true;
	}
}

class loggerPlugin_Text extends loggerPlugin
{
	public		$logFile		= null;
	public		$prefix			= null;
	public		$indentText		= '  ';

	public function log($level, $message)
	{
		return $this->output($this->formatLine($level, $message) . "\n");
	}

	public function formatLine($level, $message)
	{

		$m  = $this->getDate() . ' ';
		$m .= $this->prefix . ' ';

		if ($this->logPID)
		{
		    $m .= sprintf('[%7s] ', getmypid());
		}

		if ($this->showElapsedTime)
		{
			$m .= $this->getElapsedTime() . ' ';
		}

		if ($this->useLabels)
		{
			$m .= $this->getLabel($level) . ' ';
		}

		// append a prefix to negative level messages
		if ($level < 0)
		{
			$message = '!! ' . $message;
		} else {
			$message = str_repeat($this->indentText, (int) $level) . $message;
		}

		$message = $m . $message;

		return $message;
	}

}

class loggerPlugin_SysLog extends loggerPlugin
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

class loggerPlugin_HTML extends loggerPlugin
{
	public	$logMode		= logger::modeBuffered;
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

class loggerPlugin_XML extends loggerPlugin
{
	public	$logMode		= logger::modeBuffered;
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

class loggerPlugin_DB extends loggerPlugin
{
	protected	$db;

	public		$dsn			= null;
	public		$username		= null;
	public		$password		= null;
	public		$table			= null;
	public		$dateFormat		= 'Y-m-d H:i:s';
	public		$fields			= array(
		'id'		=> 'id',
		'date'		=> 'log_date',
		'level'		=> 'level',
		'message'	=> 'message',
	);

	public function start()
	{
		if ($this->showElapsedTime)
		{
			$this->fields['elapsed'] = 'elapsed';
		}

		$this->db = new \PDO($this->dsn, $this->username, $this->password);

		if ($this->db === false)
		{
			$error = $this->db->errorInfo();

			$this->error("db connect failed: $error[2]\n");
		}

		foreach ($fields as $key => $val)
		{
			if (! isset($this->config['db'][$key]))
			{
				$this->config['db'][$key] = $val;
			}
		}

		return true;
	}

	public function log($level, $message)
	{
		$date = $this->getDate();

		if ($this->showElapsedTime)
		{
			$dbq = $this->db->prepare("INSERT INTO $this->table ({$this->fields['date']}, {$this->fields['level']}, {$this->fields['message']}, , {$this->fields['elapsed']}) values (?, ?, ?) ;");

			$dbq->execute(array($date, $level, $message, $this->getElapsedTime()));

		} else {
			$dbq = $this->db->prepare("INSERT INTO $this->table ({$this->fields['date']}, {$this->fields['level']}, {$this->fields['message']}) values (?, ?, ?) ;");

			$dbq->execute(array($date, $level, $message));
		}

		if ($dbq === false)
		{
			$error = $this->db->errorInfo();

			$this->error("db query failed: $error[2]\n");
		}

		return true;
	}

}

class loggerPlugin_zTemplate extends loggerPlugin
{
	public		$logMode			= logger::modeBuffered;
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
