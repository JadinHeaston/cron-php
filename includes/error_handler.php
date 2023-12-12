<?php

function errorHandler($severity, $message, $file, $line)
{
	function formatBacktrace(array $backtrace): string
	{
		//Format the backtrace
		$backtraceStr = '';
		foreach ($backtrace as $i => $trace)
		{
			if ($i === 0)
				continue;

			$argList = '';
			foreach ($trace['args'] as $key => $arg)
			{
				if ($key > 0)
					$argList .= ', ';
				if (is_string($arg))
					$argList .= '"' . $arg . '"';
				else if (is_array($arg) || is_object($arg))
					$argList .= json_encode($arg);
				else if ($arg === NULL)
					$argList .= 'NULL';
				else
					$argList .= $arg;
			}
			$argList = json_encode($trace['args']);

			$backtraceStr .= sprintf(
				"#%d %s%s%s called at [%s:%d]<br>",
				--$i,
				(isset($trace['class']) ? $trace['class'] . '::' : ''),
				$trace['function'],
				(isset($trace['args']) ? '(' . $argList . ')' : '()'),
				(isset($trace['file']) ? $trace['file'] : 'unknown'),
				(isset($trace['line']) ? $trace['line'] : '?')
			);
		}

		return $backtraceStr;
	}

	$errorType = [
		E_ERROR => 'Error',
		E_WARNING => 'Warning',
		E_PARSE => 'Parsing Error',
		E_NOTICE => 'Notice',
		E_CORE_ERROR => 'Core Error',
		E_CORE_WARNING => 'Core Warning',
		E_COMPILE_ERROR => 'Compile Error',
		E_COMPILE_WARNING => 'Compile Warning',
		E_USER_ERROR => 'User Error',
		E_USER_WARNING => 'User Warning',
		E_USER_NOTICE => 'User Notice',
		E_STRICT => 'Strict Standards',
		E_RECOVERABLE_ERROR => 'Recoverable Error',
		E_DEPRECATED => 'Deprecated',
		E_USER_DEPRECATED => 'User Deprecated',
	];

	$headers = "From:email@example.com \r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/html\r\n";

	$errorEmail = ''; //Replace with your email address
	$subject = 'PHP ' . $errorType[$severity] . ' on line ' . $line . ' of ' . $_SERVER['SCRIPT_NAME'];
	$messageBody = 'A PHP "' . $errorType[$severity] . '" occurred on line <b>' . $line . '</b> of <b>' . $file . '</b>: ' . $message;
	$messageBody .= '<br>Server: ' . $_SERVER['HTTP_HOST'];
	$messageBody .= '<br>Request URI: ' . $_SERVER['REQUEST_URI'];

	$messageBody .= '<br><br>Backtrace (Single/Double quotes not distinquished for strings):<br>' . formatBacktrace(debug_backtrace());

	if (isset($_SESSION))
		$messageBody .= '<br><br>Session: ' . json_encode($_SESSION, JSON_PRETTY_PRINT);

	if (isset($_REQUEST))
		$messageBody .= '<br><br>Request: ' . json_encode($_REQUEST, JSON_PRETTY_PRINT);

	if (DISABLE_ERROR_EMAILS === false) //Send the email
		mail($errorEmail, $subject, $messageBody, $headers);

	// // handle the error
	if (error_reporting() & $severity)
		throw new ErrorException($message, 1, $severity, $file, $line);
	exit(1);
	// trigger_error('This is a notice', E_USER_NOTICE);
	// trigger_error('This is a warning', E_USER_WARNING);
	// trigger_error('This is an error', E_USER_ERROR);
}

//Set the error handler
set_error_handler('errorHandler');
