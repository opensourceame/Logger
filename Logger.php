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
class Logger
{
	const		version					= '4.0.0';
	const		modeRealTime			= 1;
	const		modeBuffered			= 2;
	const		logLevelFatal			= 1;
	const		logLevelWarning			= 2;
	const		logLevelInfo			= 3;
	const		logLevelDebug			= 4;
	const		logLevelTrace			= 5;

	private		$plugin					= null;
	private		$pluginError			= false;
	private		$pluginErrorMessage		= false;
	private		$pluginProperties		= array();

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
			$this->addError("no plugin specified for logger");
		}

		if (! $this->loadPlugin($this->config['plugin']))
		{
			$this->addError("logger cannot load plugin ".$this->config['plugin']);
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

		if ($this->pluginError)
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
		if (isset($this->pluginProperties[$name]))
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
		if ( $this->pluginError and ! $this->breakOnError)
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

	public function addError($message)
	{
		$this->pluginError					= true;
		$this->pluginErrorMessage			= $message;

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
			'\opensourceame\Logger\Plugin\\' . $plugin,
			$plugin,
		);

		$pluginFile = __DIR__ . '/Plugin/' . $plugin . '.php';

		if (file_exists($pluginFile)) {
			require_once __DIR__ . '/Plugin/Plugin.php';
			require_once $pluginFile;
		}

		foreach ($classes as $c)
		{
			if (class_exists($c))
			{
				$this->plugin = new $c($this);

				// copy config to the properties of the plugin
				foreach ($this->config as $key => $val)
				{
					if (property_exists($this->plugin(), $key))
						$this->plugin()->$key = $val;
				}

				$reflection = new \ReflectionClass($c);

				foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $p)
				{
					$this->pluginProperties[$p->name] = true;
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
		return $this->plugin;
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
			return new Logger(array(
				'plugin'	=> 'SysLog',
			));
		}
	}
}
