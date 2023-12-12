<?PHP
require_once(__DIR__ . '/config.php'); //Load in the config to verify the cron should run.
$manualRun = (isset($_GET['manual-run']) && isset($_GET['cron-password']) && $_GET['cron-password'] === CRON_PASSWORD ? true : false);

if ($manualRun === false && DISABLE_CRON === true)
	exit(1);
elseif ($manualRun === false && (!isset($argv) || !is_array($argv) || sizeof($argv) <= 1 || $argv[1] !== CRON_PASSWORD))
	exit(2);

//Load in the loader to get everything.
require_once(__DIR__ . '/includes/loader.php');
require_once(__DIR__ . '/includes/cron_functions.php');

/**
 * Defines a list of cron jobs to run.
 * @var Array<CronJob> $jobs
 */
$jobs = [
	// 'Testing' => new CronJob('Testing', 'test_function', 60, null, null, 'Creates a text file in the cron.php directory and writes the current time in it.'), //Every minute (60)
	// 'Helpful Name' => new CronJob('Helpful Name', 'function_name', 60, new DateTime('2023-01-01'), '0 0 * * 5', 'Helpful Description'), //Every other Friday.
];

//Creating database is there.
$trackingDB = new Models\DatabaseConnector('sqlite', __DIR__ . '/cron_tracking.db');
$trackingDB->executeStatement(
	'CREATE TABLE IF NOT EXISTS cron (
		job_name TEXT PRIMARY KEY,
		last_run INTEGER
	) WITHOUT ROWID;'
);

//Initializing Cron.
$cron = new cron($trackingDB, $jobs);
$cron->createCronEntries($jobs); //Ensuring entries are created.
$cron->run(); //Running cron.


/**
 * This class models a cron job. It contains the information needed to run a cron job.
 */
class CronJob
{
	public string $name;
	public string $functionName = '';
	public int $intervalSeconds = 60; //Seconds (1 minute)
	public string $desciption = '';
	public ?DateTime $startDate = null; // Optional start date
	/*
	 * https://crontab.guru/
	*/
	public ?string $cronSchedule = null; // Optional custom cron schedule string

	public function __construct(string $name, string $functionName, int $intervalSeconds = 60, DateTime $startDate = null, string $cronSchedule = null, string $desciption = '')
	{
		$this->name = $name;
		$this->functionName = $functionName;
		$this->intervalSeconds = $intervalSeconds;
		$this->desciption = $desciption;
		$this->$startDate = $startDate; // Optional start date
		$this->cronSchedule = $cronSchedule;
	}

	public function runJob()
	{
		call_user_func($this->functionName);
	}

	public function isScheduled(DateTime $currentDate): bool
	{
		if ($this->startDate && $currentDate < $this->startDate)
			return false;
		elseif ($this->cronSchedule)
			return $this->matchesSchedule($currentDate);

		return true;
	}

	private function matchesSchedule(DateTime $currentDate): bool
	{
		if ($this->startDate && $currentDate < $this->startDate)
			return false;

		$startTimestamp = $this->startDate ? $this->startDate->getTimestamp() : $currentDate->getTimestamp();

		$parts = explode(' ', $this->cronSchedule);
		$cronParts = array_slice($parts, 0, 5); // Extract the first 5 parts

		list($min, $hour, $dayOfMonth, $month, $dayOfWeek) = $cronParts;

		if ($min !== '*' && $currentDate->format('i') != $min) // Check if the minute matches
			return false;
		elseif ($hour !== '*' && $currentDate->format('G') != $hour) // Check if the hour matches
			return false;
		elseif ($dayOfMonth !== '*' && $currentDate->format('j') != $dayOfMonth) // Check if the day of the month matches
			return false;
		elseif ($month !== '*' && $currentDate->format('n') != $month) // Check if the month matches
			return false;
		elseif ($dayOfWeek !== '*' && $currentDate->format('w') != $dayOfWeek) // Check if the day of the week matches
			return false;

		$currentTimestamp = $currentDate->getTimestamp();
		$elapsedSeconds = $currentTimestamp - $startTimestamp;

		return $elapsedSeconds % $this->intervalSeconds === 0;
	}
}

/**
 * This class models cron as a whole. It handles the tracking of the last run time, and the creation of cron entries in the tracking database.
 */
class Cron
{
	public Models\DatabaseConnector $db;
	/** 
	 * @var array<CronJob>
	 */
	public array $jobs = array();

	public function __construct(Models\DatabaseConnector $db, array $jobs = array())
	{
		$this->db = $db;
		$this->jobs = $jobs;
		$this->createCronEntries();
	}

	private function isTaskReady(CronJob $job)
	{
		if (!$job->isScheduled(new DateTime())) //Checking if it is scheduled to run.
			return false;

		$results = $this->db->select('SELECT * FROM cron WHERE job_name = ?', [$job->name]);
		if ($results === false)
			trigger_error('Failed query cron table.', E_USER_ERROR);
		elseif (empty($results))
			return true;
		elseif (count($results) === 1)
		{
			$lastRun = strtotime($results[0]['last_run']);
			$intervalSeconds = $job->intervalSeconds;

			if ($lastRun === false || ($lastRun + $intervalSeconds) < time())
				return true;
		}

		return false;
	}

	public function run(bool $forceRun = false)
	{
		foreach ($this->jobs as $job)
		{
			if ($forceRun === false && $this->isTaskReady($job) === false)
				continue;

			$this->updateLastRun($job);
			$job->runJob();
		}
	}

	private function updateLastRun(CronJob $job)
	{
		return $this->db->executeStatement('UPDATE cron SET last_run = strftime(\'%s\', \'now\') WHERE job_name = ?', [$job->name]);
	}

	public function createCronEntries()
	{
		foreach ($this->jobs as $job)
		{
			$this->db->executeStatement('INSERT OR IGNORE INTO cron (job_name, last_run) VALUES (?,?)', [$job->name, 0]);
		}
	}
}
