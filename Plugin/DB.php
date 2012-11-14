<?php

namespace opensourceame\Logger\Plugin;

class DB extends Plugin
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