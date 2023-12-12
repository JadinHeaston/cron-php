<?PHP

class ScopeTimer
{
	public $name;
	public $startTime;

	private function __construct($name = 'Timer')
	{
		$this->startTime = microtime(true);
		$this->name = $name;
	}

	private function __destruct()
	{
		$elapsed_time = microtime(true) - $this->startTime;
		echo $this->name . ': ' . $elapsed_time . 'ms';
	}

	//$timer = new ScopeTimer(__FILE__);
}
