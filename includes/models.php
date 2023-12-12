<?PHP

namespace Models;

class DatabaseConnector
{
	protected \PDO $connection;
	protected string $type;
	public ?Cache\DatabaseConnector $cacheConnector;

	private $queries = array(
		'listTables' => array(
			'mysql' => 'SHOW FULL tables',
			'sqlite' => 'SELECT * FROM sqlite_schema WHERE type =\'table\' AND name NOT LIKE \'sqlite_%\'',
			'sqlsrv' => 'SELECT DISTINCT TABLE_NAME FROM information_schema.tables'
		),
		'getTableInformation' => array(
			'mysql' => 'DESCRIBE ?',
			'sqlite' => 'PRAGMA table_info(?)',
			'sqlsrv' => 'SELECT * FROM information_schema.columns WHERE TABLE_NAME = ? order by ORDINAL_POSITION'
		),
		'getTableIndexes' => array(
			'mysql' => 'SHOW INDEX FROM ?',
			'sqlite' => 'SELECT * FROM sqlite_master WHERE type = \'index\' AND tbl_name = ?',
			'sqlsrv' => 'SELECT * FROM sys.indexes WHERE object_id = (SELECT object_id FROM sys.objects WHERE name = ?)'
		),
		'getTableCreation' => array(
			'mysql' => 'SHOW CREATE TABLE ?',
			'sqlite' => 'SELECT sql FROM sqlite_schema WHERE name = ?',
			'sqlsrv' => false //Not available without a stored procedure.
		),
		'createTable' => array(
			'mysql' => 'CREATE TABLE IF NOT EXISTS ? ()',
			'sqlite' => 'CREATE TABLE IF NOT EXISTS ? (column_name datatype, column_name datatype);',
			'sqlsrv' => ''
		)
	);

	public function __construct(string $type, string $hostPath, int $port = null, string $db = '', string $user = '', string $pass = '', string $charset = 'utf8mb4', bool|null $trustCertificate = null)
	{
		$this->type = strtolower(trim($type));
		try
		{
			//Creating DSN string.
			$dsn = $this->type;
			if ($this->type === 'mysql')
				$dsn .= ':host=';
			elseif ($this->type === 'sqlite')
				$dsn .= ':';
			elseif ($this->type === 'sqlsrv')
				$dsn .= ':Server=';

			$dsn .= $hostPath;

			if ($this->type === 'mysql')
				$dsn .= ';port=' . strval($port);

			if ($this->type === 'mysql')
				$dsn .= ';dbname=';
			elseif ($this->type === 'sqlsrv')
				$dsn .= ';Database=';

			$dsn .= $db;

			if ($this->type === 'mysql')
				$dsn .= ';charset=' . $charset;
			if ($this->type === 'sqlsrv' && $trustCertificate !== null)
				$dsn .= ';TrustServerCertificate=' . strval(intval($trustCertificate));

			//Attempting connection.
			$this->connection = new \PDO($dsn, $user, $pass);
			$this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
			$this->connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
			$this->connection->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		}
		catch (\PDOException $e)
		{
			exit($e->getMessage());
		}

		return $this->connection;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $filepath
	 * @param array<\Cache\Table> $tables
	 * @return void
	 */
	public function useCache(string $filepath, array $tables)
	{
		$this->cacheConnector = new Cache\DatabaseConnector('sqlite', $filepath);
		$this->cacheConnector->tables = $tables;
		$this->cacheConnector->createCacheTables();
	}

	public function executeStatement($query = '', $params = [], $skipPrepare = false)
	{
		if ($this->cacheConnector === null)
			$connector = &$this;
		else
			$connector = &$this->cacheConnector;

		try
		{
			if ($skipPrepare !== true)
			{
				$stmt = $connector->connection->prepare($query);

				if ($stmt === false)
					throw new \Exception('Unable to do prepared statement: ' . $query);

				$stmt->execute($params);
				return $stmt;
			}
			else
				return $connector->connection->exec($query);
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	public function select($query = '', $params = [])
	{
		try
		{
			$stmt = $this->executeStatement($query, $params);
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	public function update($query = '', $params = [])
	{
		try
		{
			$stmt = $this->executeStatement($query, $params);
			return $stmt->rowCount();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	public function getLastInsertID(): string
	{
		return $this->connection->lastInsertId();
	}

	public function listTables($includeViews = true)
	{
		$query = $this->queries[__FUNCTION__][$this->type];
		if ($query === false)
			return false;

		if ($includeViews === false && $this->type === 'mysql')
			$query .= ' WHERE Table_Type = \'BASE TABLE\'';
		elseif ($includeViews === false && $this->type === 'sqlsrv')
			$query .= ' WHERE TABLE_TYPE = \'BASE TABLE\'';

		try
		{
			$stmt = $this->executeStatement($query);
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	public function getTableInformation(string $table)
	{
		$query = $this->queries[__FUNCTION__][$this->type];
		if ($query === false)
			return false;

		elseif ($this->type === 'sqlite')
			$query = 'PRAGMA table_info(?)';
		elseif ($this->type === 'sqlsrv')
			$query = 'SELECT * FROM information_schema.columns WHERE TABLE_NAME = ? order by ORDINAL_POSITION';
		try
		{
			$stmt = $this->executeStatement($query, array($table));
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	public function getTableIndexes(string $table)
	{
		$query = $this->queries[__FUNCTION__][$this->type];
		if ($query === false)
			return false;

		try
		{
			$stmt = $this->executeStatement($query, array($table));
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	public function getTableCreation(string $table)
	{
		$query = $this->queries[__FUNCTION__][$this->type];
		if ($query === false)
			return false;

		try
		{
			$stmt = $this->executeStatement($query, array($table));
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		return false;
	}

	//$columns is expected to follow the structure below:
	// [
	// 	0 => array(
	// 		'name' => '',
	// 		'type' => '',
	// 		'index' => false,
	// 		'primary' => false,
	// 		'null' => false,
	// 		'default' => '', //Any type.
	// 		'foreign_key' => array()
	// 	),
	// ]
	public function createTable(string $tableName, array $columns)
	{
		$query = $this->queries[__FUNCTION__][$this->type];
		if ($query === false)
			return false;

		try
		{
			$stmt = $this->executeStatement($query, array($tableName,));
			return $stmt->fetchAll();
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}

		return false;
	}
}

class Mailer
{
	public $senderEmail;

	private function __construct(string $senderEmail)
	{
		$this->senderEmail = $senderEmail;
	}

	public function sendMail(array|string $destination, string $subject, string $message, array|string $carbonCopy = '', array|string $blindCarbonCopy = '', array $additionalHeaders = array())
	{
		//Formatting destination.
		if (is_array($destination))
			$destination = implode(',', $destination);
		if (is_array($carbonCopy))
			$carbonCopy = implode(',', $carbonCopy);
		if (is_array($blindCarbonCopy))
			$blindCarbonCopy = implode(',', $blindCarbonCopy);


		$headers['From'] = $this->senderEmail;

		if ($carbonCopy !== '')
			$headers['CC'] = $carbonCopy;
		if ($blindCarbonCopy !== '')
			$headers['BCC'] = $blindCarbonCopy;

		$headers['MIME-Version'] = '1.0';
		$headers['Content-type'] = 'text/html';

		foreach ($additionalHeaders as $name => $header)
		{
			$headers[$name] = $header;
		}
		mail($destination, $subject, $message, $headers);
	}

	// private function checkEmailSentStatus()
	// {
	// }
}

namespace Models\Cache;

class DatabaseConnector extends \Models\DatabaseConnector
{
	/** @var array<Table> */
	public array $tables;

	public function createCacheTables()
	{
		foreach ($this->tables as $table)
		{
			$this->executeStatement($table->generateCreateStatement());
		}
		// return;
	}

	public function importCSV(string $table, string $filepath, string $schema = 'main', $delimiter = ',')
	{
		function csv_read(string $filepath, string $delimiter = ',')
		{
			$header = [];
			$row = 0;
			# tip: dont do that every time calling csv_read(), pass handle as param instead ;)
			$handle = fopen($filepath, "r");

			if ($handle === false)
				return false;

			while (($data = fgetcsv($handle, 0, $delimiter)) !== false)
			{

				if (0 == $row)
					$header = $data;
				else
					yield array_combine($header, $data); # on demand usage

				++$row;
			}
			fclose($handle);
			return true;
			// Usage example:
			// $generator = csv_read('file.csv');

			// foreach ($generator as $item)
			// {
			//
			// }
		}

		$generator = csv_read($filepath, $delimiter);

		$this->truncateTable($table, $schema);
		foreach ($generator as $item)
		{
			$this->executeStatement('INSERT INTO [' . $schema . '].[' . $table . '] (' . implode(', ', array_keys($item)) . ') VALUES(' . join(', ', array_fill(0, count($item),  '?')) . ')', array_values($item));
		}
		return true;
	}

	public function importQuery(string $table, \Models\DatabaseConnector $connection, string $selectQuery, string $schema = 'main')
	{
		$results = $connection->select($selectQuery);
		if ($results === false)
			return false;


		$this->truncateTable($table, $schema);
		foreach ($results as $row)
		{
			$this->executeStatement('INSERT INTO [' . $schema . '].[' . $table . '] (' . implode(', ', array_keys($row)) . ') VALUES(' . join(', ', array_fill(0, count($row),  '?')) . ')', array_values($row));
		}
	}

	public function truncateTable($table, $schema = 'main')
	{
		$this->executeStatement('DELETE FROM [' . $schema . '].[' . $table . ']');
	}
}

class Table
{
	public string $name;
	public string $schema;
	/** @var array<Column> */
	public array $columns;
	public array $constraints;
	public bool $rowID = false;

	public function __construct(string $name, string $schema = 'main', array $columns = [], array $constraints = [], bool $rowID = false)
	{
		$this->name = $name;
		$this->schema = $schema;
		$this->columns = $columns;
		$this->constraints = $constraints;
		$this->rowID = $rowID;
	}

	public function generateCreateStatement($overwrite = false)
	{
		$overwriteStatement = ($overwrite === false ? ' IF NOT EXISTS' : '');
		$rowIDString = ($this->rowID === false ? ' WITHOUT ROWID' : '');
		$columnStatements = [];

		foreach ($this->columns as $column)
		{
			$columnStatements[] = $column->generateColumnStatement();
		}
		return 'CREATE TABLE' . $overwriteStatement . ' [' . $this->schema . '].[' . $this->name . '] (' . implode(', ', $columnStatements) . ')' . $rowIDString . ';';
	}
}

class Column
{
	public string $name;
	public string $type;
	public bool $allowNull = false;
	public bool $isPrimaryKey = false;
	public bool $isIndex = false;
	public ?string $foreignKey;
	public ?string $default;

	public function __construct(string $name, string $type, bool $allowNull = false, bool $isPrimaryKey = false, bool $isIndex = false, ?string $foreignKey = null, ?string $default = null)
	{
		$this->name = $name;
		$this->type = $type;
		$this->allowNull = $allowNull;
		$this->isPrimaryKey = $isPrimaryKey;
		$this->isIndex = $isIndex;
		$this->foreignKey = $foreignKey;
		$this->default = $default;
	}

	public function generateColumnStatement()
	{
		return $this->name . ' ' . $this->type . ($this->allowNull === true ? ' NULL' : ' NOT NULL') . ($this->isPrimaryKey === true ? ' PRIMARY KEY' : '') . ($this->foreignKey === null ? '' : ' REFERENCES [' . $this->foreignKey . '](' . $this->foreignKey . ')') . ($this->default === null ? '' : ' DEFAULT ' . $this->default);
	}

	public function generateIndexStatement()
	{
		return 'CREATE INDEX [ix_' . $this->name . '] ON [dbo].[' . $this->name . '] (' . $this->name . ');';
	}
}

namespace Models\Table;

class Table
{
	public TableRows $rows;

	public function newTable(TableColumns $columns)
	{
		$this->rows = new TableRows($columns);
	}

	/**
	 * Returns array of rows
	 *
	 * @return Array<row>
	 */
	public function getRows()
	{
		return $this->rows->getRows();
	}

	public function importData(array $data, bool $overwrite = false)
	{
		if ($overwrite === true)
			$this->rows->clearData();
		if ($data === false)
			return false;
		foreach ($data as $row)
		{
			$this->rows->addRow(new Row($row));
		}

		return true;
	}

	public function listColumns(bool $fullyQualifiedName = false, bool $includeSpecialColumns = true)
	{
		return $this->rows->listColumns($fullyQualifiedName, $includeSpecialColumns);
	}

	public function getColumns()
	{
		return $this->rows->getColumns();
	}

	public function getColumn(string $name)
	{
		return $this->rows->getColumn($name);
	}

	/**
	 * Returns HTML of the inputs.
	 *
	 * @return string
	 */
	public function displayInputs()
	{
		$output = '';
		$columns = $this->getColumns();

		$type = array(
			'bool' => 'select',
			'email' => 'email',
			'int' => 'number',
			'json' => 'text',
			'phone' => 'number',
			'string' => 'text',
		);
		$first = $this->getRows()[0];
		foreach ($first->values as $key => $value)
		{
			//Getting any label styles.
			if (isset($columns[$key]->labelStyles))
			{
				$labelStyles = '';
				foreach ($columns[$key]->labelStyles as $style => $styleValue)
				{
					$labelStyles = $style . ':' . $styleValue . ';';
				}
			}
			//Creating wrapper label.
			$output .= '<label for="' . $key . '"' . (isset($labelStyles) ? ' style="' . $labelStyles . '"' : '') . '>' . ucwords(str_replace('_', ' ', $key));
			//Getting any input styles.
			if (isset($columns[$key]->inputStyles))
			{
				$inputStyles = '';
				foreach ($columns[$key]->inputStyles as $style => $styleValue)
				{
					$inputStyles = $style . ':' . $styleValue . ';';
				}
			}
			//Creating select input.
			if (isset($columns[$key]->inputType) && strtolower($columns[$key]->inputType) === 'select')
			{
				//Select options.
				if (isset($columns[$key]->inputSelectOptions))
				{
					$output .= '<select class="select2" name="' . $key . (isset($columns[$key]->inputSelectOptions['Multiple']) ? '[]' : '') . '"' . (isset($columns[$key]->inputSelectOptions['Multiple']) ? ' multiple=' . $columns[$key]->inputSelectOptions['Multiple'] : '') . (isset($inputStyles) ? ' style="' . $inputStyles . '"' : '') . (isset($columns[$key]->inputSelectOptions['Allow New']) ? ' data-tags=' . $columns[$key]->inputSelectOptions['Allow New'] : '') . ' type="select">';
					foreach ($columns[$key]->inputSelectOptions as $name => $selectOption)
					{
						if ($name === 'Possible Values')
						{
							foreach ($selectOption as $valueName => $possibleValue) //Creating values.
							{
								if (!isset($selectedValue) && isset($columns[$key]->inputSelectOptions['Selected Values']) && sizeof($columns[$key]->inputSelectOptions['Selected Values']) > 0)
									$selectedValues = $columns[$key]->inputSelectOptions['Selected Values'];
								elseif ($columns[$key]->type === 'int')
									$selectedValues[] = intval($value);
								else
									$selectedValues[] = $value;

								$output .=  '<option value="' .  $possibleValue . '" ' . (in_array($possibleValue, $selectedValues) ? 'selected' : '') . '>' . $valueName . '</option>';
							}
						}
					}
				}
				else
					$output .= '<select class="select2" name="' . $key . '" type="select">';
				$output .= '</select>';
			}
			else //Creating standard input.
				$output .= '<input id="' . $key . '" name="' . $key . '"' . (isset($inputStyles) ? ' style="' . $inputStyles . '"' : '') . ' type="' . $type[$columns[$key]->type] . '" value="' .  $value . '" checked>';
			$output .= '</label>';
		}

		return $output;
	}
}

/**
 * Columns pull the most amount of work.
 */
class TableColumns
{
	public array $columns = array();

	public function __construct(Column ...$columns)
	{
		foreach ($columns as $column)
		{
			$this->columns[$column->name] = $column;
		}
	}

	public function addColumn(Column $column)
	{
		$this->columns[$column->name] = $column;
		return true;
	}

	public function listColumns(bool $fullyQualifiedName = false, bool $includeSpecialColumns = true)
	{
		$columnsNames = array();

		foreach ($this->getColumns() as $column)
		{
			$columnsNames[] = (isset($column->specialColumn) && $column->specialColumn !== null && $includeSpecialColumns ? $column->specialColumn . ' as ' . $column->getFullColumnName(false) : $column->getFullColumnName($fullyQualifiedName));
		}

		return $columnsNames;
	}

	/**
	 * Returns array of columns
	 *
	 * @return Array<Column>
	 */
	public function getColumns()
	{
		return $this->columns;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $columnName
	 * @return Column
	 */
	public function getColumn(string $columnName)
	{
		if (isset($this->columns[$columnName]))
			return $this->columns[$columnName];
		else
			return false;
	}

	public function importData(array $data)
	{
		foreach ($data as $row)
		{
			foreach ($this->getColumns() as $columns)
			{
				$this->columns[$columns->name]->addValue($row, $columns->name);
			}
		}

		return true;
	}
}

/**
 * Rows store the actual data. Each row is made up of X number of columns 
 */
class TableRows
{
	public TableColumns $columns;
	public array $rows = array();

	public function __construct(TableColumns $columns)
	{
		$this->initializateColumns($columns);
	}

	public function addRow(row $row)
	{
		$this->rows[] = $row;
	}

	/**
	 * Returns array of rows
	 *
	 * @return Array<row>
	 */
	public function getRows()
	{
		return $this->rows;
	}

	private function initializateColumns(TableColumns $columns)
	{
		$this->columns = $columns;
	}


	public function listColumns(bool $fullyQualifiedName = false, bool $includeSpecialColumns = true)
	{
		return $this->columns->listColumns($fullyQualifiedName, $includeSpecialColumns);
	}

	public function getColumns()
	{
		return $this->columns->getColumns();
	}

	public function getColumn(string $name)
	{
		return $this->columns->getColumn($name);
	}

	public function clearData()
	{
		$this->rows = array();
		return true;
	}
}

/**
 * Contains information for creating an HTML input.
 */
class Column
{
	public string $name;
	/**
	 * Valid types: bool | email | int | json | phone | string
	 *
	 * @var string
	 */
	public string $type;
	public string $table;
	public ?array $labelStyles;
	public ?string $inputType;
	public ?array $inputStyles;
	public array $inputSelectOptions;
	public ?string $specialColumn;

	public function __construct(string $columnName, string $type, string $table, array $options = array())
	{
		$this->name = $columnName;
		$this->type = $type;
		$this->table = $table;
		foreach ($options as $key => $option)
		{
			if ($key === 'Input Type')
				$this->inputType = $option;
			elseif ($key === 'Input Styles')
				$this->inputStyles = $option;
			elseif ($key === 'Label Styles')
				$this->labelStyles = $option;
			elseif ($key === 'Select Options')
				$this->inputSelectOptions = $option;
			elseif ($key === 'Special Column')
				$this->specialColumn = $option;
		}
	}

	public function getFullColumnName(bool $fullyQualifiedName = false)
	{
		if ($this->table === '' || $fullyQualifiedName === false)
			return $this->name;
		else
			return $this->table . '.' . $this->name;
	}
}

/**
 * Simple row class.
 */
class Row
{
	public array $values = array();

	/**
	 * Providing an array will push each element of the array onto the variable stack.
	 *
	 * @param mixed $value
	 */
	public function __construct(array $data)
	{
		$this->values = $data;
	}

	public function getValues()
	{
		return $this->values;
	}

	public function addValue(mixed $data)
	{
		$this->values[] = $data;
	}
}
