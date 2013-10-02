<?php
namespace PHPSchemaManager\Drivers;

class DriverMysql implements DriverInterface
{

    protected $sm;
    protected $conn;
    protected $databaseSelected = false;
    protected $linkIdentifier;
    protected $exclusiveSchema = false;
    protected $ignoredSchemas = array();


    public function __construct(\PHPSchemaManager\Connection $conn)
    {
        $this->conn = $conn;
    }


    // Methods from the interface
    public function connect()
    {
        if (empty($this->linkIdentifier)) {

            $port = $this->conn->port;

            if (empty($port)) {
                $port = "3306";
            }

            $host = "{$this->conn->hostname}:{$port}";
            $username = $this->conn->username;
            $password = $this->conn->password;
            $linkIdentifier = mysql_connect($host, $username, $password);
            if (!$linkIdentifier) {
                $msg = "Failed to connect on {$this->conn->dbms} at {$host} with {$username} user";
                throw new \SchemaManager\Exceptions\MysqlException($msg);
            }

            $this->linkIdentifier = $linkIdentifier;
        }

        return $this->linkIdentifier;
    }

    public function selectDb($dbName = null)
    {

        if (empty($dbName)) {
            $dbName = $this->getDatabaseSelected();
        }

        if ($this->databaseSelected != $dbName) {
            if (!mysql_select_db($dbName)) {
                $msg = "Database '$dbName' wasn't found, you have to create it first";
                throw new \SchemaManager\Exceptions\MysqlException($msg);
            }

            $this->databaseSelected = $dbName;
        }

        return $dbName;
    }

    public function getDatabaseSelected()
    {
        return $this->databaseSelected;
    }

    public function getSchemas()
    {
        $schemas = array();

        // check how this environment should operate
        $lowerCaseTableNames = $this->checkLowerCaseTableNames();

        // get the list of databases found in this connection
        $res = mysql_list_dbs();
        while ($row = mysql_fetch_array($res)) {
            $schema = new \PHPSchemaManager\Objects\Schema($row['Database']);

            // configure the schema to operate according to how this environment should work
            $lowerCaseTableNames ? $schema->turnCaseSensitiveNamesOn() : $schema->turnCaseSensitiveNamesOff();

            $schema->persisted();

            $schemas[] = $schema;
        }

        return $schemas;
    }

    public function getTables(\PHPSchemaManager\Objects\Schema $schema)
    {

        try {
            $this->selectDb($schema->getName());
        } catch (\PHPSchemaManager\Exceptions\MysqlException $e) {
            // most probably, it because the schema wasn't created yet
            // in this case, an empty set of tables will be replied
            return array();
        }

        // get the tables from the database
        $sql = "SHOW TABLES";
        $res = $this->dbQuery($sql);
        while ($row = mysql_fetch_row($res)) {
            $table = new \PHPSchemaManager\Objects\Table($row[0]);

            // get the columns and put them into the table object directly
            $this->getColumns($table);

            // get the indexes
            $this->getIndexes($table);

            // get table specifics
            $this->getSpecifics($table);

            $table->persisted();

            $schema->addTable($table);
        }

        // get the foreign keys from all tables
        $this->getForeignKeys($schema);
    }

    public function dbQuery($sql)
    {

        $result = mysql_query($sql);

        if (!$result) {
            $msg = 'MySQL Error: ' . mysql_error() . "\nQuery: $sql";
            throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
        }

        return $result;
    }

    public function dbFetchArray($result)
    {
        return mysql_fetch_assoc($result);
    }

    public function getCreateTableStatement(\PHPSchemaManager\Objects\Table $table)
    {
        $sql = "SHOW CREATE TABLE `{$table}`";
        $result = $this->dbQuery($sql);
        $row = $this->dbFetchArray($result);
        return $row["Create Table"];
    }

    public function getTableCount($table)
    {
        $this->selectDb();

        $sql = "SELECT COUNT(*) AS num_rows FROM $table";
        $res = $this->dbFetchArray($this->dbQuery($sql));
        return (int)$res['num_rows'];
    }

    public function flush(\PHPSchemaManager\Objects\Schema $schema)
    {
        // if the schema should be ignored, just mark it as synced and move on
        if ($schema->shouldBeIgnored()) {
            $schema->persisted();
            return true;
        }

        // flush schema
        switch ($schema->getAction()) {
            case \PHPSchemaManager\Objects\Schema::ACTIONCREATE:
                $this->createDatabase($schema->getName());
                break;
            case \PHPSchemaManager\Objects\Schema::ACTIONALTER:
                //TODO implement changes requested for the schema
                break;
            case \PHPSchemaManager\Objects\Schema::ACTIONDELETE:
                $this->dropDatabase($schema);

                // Since the schema was dropped, we don't need to do anything below
                // with the tables
                return true;
            case \PHPSchemaManager\Objects\Schema::STATUSSYNCED:
                // nothing to do
                break;
            default:
                $msg = "Action {$schema->getAction()} is not implemented by this library";
                throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
        }

        // schema is now ready to be used
        $schema->persisted();

        // switch to the schema, so the tables can be also persisted
        $this->selectDb($schema->getName());

        // flush tables
        foreach ($schema->getTables() as $table) {
            /* @var $table \SchemaManager\Objects\Table */
            switch ($table->getAction()) {
                case \PHPSchemaManager\Objects\Table::ACTIONALTER:
                    $this->alterTable($table);
                    $table->persisted();
                    break;
                case \PHPSchemaManager\Objects\Table::ACTIONCREATE:
                    $this->createTable($table);

                    // after creating the table, refresh the Indexes, because of the SERIAL type
                    $this->getIndexes($table);
                    $table->persisted();
                    break;
                case \PHPSchemaManager\Objects\Table::ACTIONDELETE:
                    $this->deleteTable($table);
                    break;
                case \PHPSchemaManager\Objects\Table::STATUSSYNCED:
                    // nothing to do
                    break;
                default:
                    $msg = "Action {$table->getAction()} is not implemented by this library";
                    throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
            }

        }

        // the schema and its tables are now persisted
        return true;
    }

    public function getVersion()
    {
        $sql = "SHOW VARIABLES LIKE '%version%'";
        $res = $this->dbQuery($sql);

        while ($row = mysql_fetch_assoc($res)) {
            if ($row['Variable_name'] == 'version') {
                return $row['Value'];
            }
        }

        return "Not found";
    }

    public function checkLowerCaseTableNames()
    {
        //http://dev.mysql.com/doc/refman/5.0/en/identifier-case-sensitivity.html
        $sql = "SHOW VARIABLES LIKE 'lower_case_table_names'";

        $res = $this->dbQuery($sql);
        $row = mysql_fetch_assoc($res);

        //if 1 or 2, turnCaseSensitiveNamesOff otherwise, turnCaseSensitiveNamesOn
        if (0 === $row['Variable_name']) {
            return true;
        } else {
            return false;
        }
    }

    // Methods specific for MySQL
    protected function getColumns(\PHPSchemaManager\Objects\Table $table)
    {

        // describe the tables from the database
        $sql = "DESC `{$table}`";
        $resCol = $this->dbQuery($sql);

        while ($row = mysql_fetch_assoc($resCol)) {
            // create a new column object
            $column = new \PHPSchemaManager\Objects\Column($row['Field']);

            $mysqlColumn = new DriverMysqlColumn($column);

            // to get the type we have to first separate the type name from its size, if any.
            $matches = '';
            $regex = "/([a-zA-Z]+)(\(?([']?[0-9a-zA-Z_]*[']?([,]?[']?[0-9a-zA-Z][']?)*)\)?)( ([a-zA-Z]+))?/";
            preg_match($regex, strtolower($row['Type']), $matches);

            if (empty($matches[0])) {
                $msg = "Malformed column type {$row['Type']}. Most probably, a not implemented case";
                throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
            }

            // if the type is auto_increment let's use the generic type SERIAL
            if ('auto_increment' == strtolower($row['Extra'])) {
                $column->setType(\PHPSchemaManager\Objects\Column::SERIAL);
            } else {
                $mysqlColumn->setType($matches[1]);
            }

            // set the size of the field, if any
            if (!empty($matches[3])) {
                $size = $matches[3];

                // if the type is ENUM or SET, get the biggest value on it
                if ('enum' == strtolower($matches[1]) || 'set' == strtolower($matches[1])) {
                    $size = str_replace("'", "", $size);
                    $max = 0;
                    foreach (explode(",", $size) as $word) {
                        if (mb_strlen($word) > $max) {
                            $max = mb_strlen($word);
                        }
                    }
                    $size = $max;
                }

                $column->setSize($size);
            }

            // check if there is the unsigned instruction
            if (in_array('unsigned', $matches)) {
                $column->unsigned();
            } else {
                $column->signed();
            }

            // check if the column can receive null values, by default this library assumes it can't
            if ('yes' == strtolower($row['Null'])) {
                $column->allowsNull();
            } else {
                $column->forbidsNull();
            }

            // set the default value of the column
            $column->setDefaultValue($row['Default']);

            // add the column in the table
            $table->addColumn($column);
        }
    }

    public function getIndexes(\PHPSchemaManager\Objects\Table $table)
    {
        //TODO find another way to get the indexes from mysql tables, to support older versions of MySQL
        try {
            // stores the current database, so its can be selected again at the end of this method
            $currentSelectedDb = $this->getDatabaseSelected();

            // get the index info from the information_schema database
            $this->selectDb('information_schema');
        } catch (\Exception $e) {
            $msg = "While trying to get the indexes for table '$table', the information_schema database wasn't found." .
                    " Most probably because your MySQL is older than version 5.1";
            throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
        }

        // select the indexes (non-primary) created for the database
        $sql = "SELECT * FROM STATISTICS
                WHERE TABLE_SCHEMA = '$currentSelectedDb' AND
                TABLE_NAME = '{$table}'
                ORDER BY SEQ_IN_INDEX ASC";

        $res = $this->dbQuery($sql);

        $indexes = array();
        while ($row = mysql_fetch_assoc($res)) {
            $indexes[$row['INDEX_NAME']][] = $row;
        }

        foreach ($indexes as $indexName => $values) {
            $index = new \PHPSchemaManager\Objects\Index($indexName);
            foreach ($values as $idx) {
                if (!$column = $table->hasColumn($idx['COLUMN_NAME'])) {
                    $msg = "Trying to create an index with a non-existent column ({$idx['COLUMN_NAME']})";
                    throw new \PHPSchemaManager\Exceptions\MysqlException($msg);
                }
                $index->addColumn($column, $idx['SEQ_IN_INDEX']);

                $index->setType(\PHPSchemaManager\Objects\Index::REGULAR);
                if ("PRIMARY" == $idx['INDEX_NAME']) {
                    $index->setType(\PHPSchemaManager\Objects\Index::PRIMARYKEY);
                } elseif ("0" == $idx['NON_UNIQUE']) {
                    $index->setType(\PHPSchemaManager\Objects\Index::UNIQUE);
                }

            }

            // add the index into the table
            $table->addIndex($index);
        }

        // select the previous selected database again
        $this->selectDb($currentSelectedDb);
    }

    public function getSpecifics(\PHPSchemaManager\Objects\Table $table)
    {
        // array to hold all the specifics for this table - in the future there will more than only the engine
        $conf = array();

        // get info from the table
        $sql = "SHOW TABLE STATUS WHERE NAME = '$table'";
        $res = $this->dbQuery($sql);

        while($row = mysql_fetch_assoc($res)) {
            if ('Engine' == key($row)) {
                $conf['engine'] = current($row);
                break;
            }
        }

        if (!empty($conf)) {
            $specifics = new TableSpecificMysql();

            switch ($conf['engine']) {
                case TableSpecificMysql::MYISAM:
                    $specifics->markAsMyIsam();
                    break;

                case TableSpecificMysql::INNODB:
                    $specifics->markAsInnoDb();
                    break;

                case TableSpecificMysql::CSV:
                    $specifics->markAsCsv();
                    break;

                case TableSpecificMysql::MEMORY:
                    $specifics->markAsMemory();
                    break;

                case TableSpecificMysql::BLACKHOLE:
                    $specifics->markAsBlackhole();
                    break;
            }

            $table->addSpecificConfiguration($specifics);
        }
    }

    protected function alterTable(\PHPSchemaManager\Objects\Table $table)
    {
        $this->selectDb();
        $sql = "ALTER TABLE `{$table}`" . PHP_EOL;
        $sqlParts[] = rtrim($this->alterTableColumns($table), PHP_EOL);
        $sqlParts[] = rtrim($this->alterTableIndexes($table), PHP_EOL);

        $fkSql = rtrim($this->tableForeignKeysInstruction($table), PHP_EOL);
        $fkSql = empty($fkSql) ? "" : "ADD $fkSql";

        $sqlParts[] = $fkSql;


        // normalizes the query to avoid issues
        $sql .= trim(implode("," . PHP_EOL, $sqlParts), "," . PHP_EOL) . PHP_EOL;

        $this->dbQuery($sql);
    }

    protected function alterTableColumns(\PHPSchemaManager\Objects\Table $table)
    {
        $i = 0;
        $instruction = array();

        foreach ($table->getColumns() as $column) {

            if ($column->isSynced()) {
                continue;
            } elseif ($column->shouldDelete()) {
                $instruction[$i] = "DROP COLUMN $column" . PHP_EOL;
            } else {

                if ($column->shouldCreate()) {
                    $instruction[$i] = "ADD COLUMN ";
                } elseif ($column->shouldAlter()) {
                    $instruction[$i] = "MODIFY COLUMN ";
                }

                $col = new DriverMysqlColumn($column);
                $instruction[$i] .= $col->getDataDefinition();
            }

            $i++;
        }

        return empty($instruction) ? "" : implode(", " . PHP_EOL, $instruction);
    }

    protected function alterTableIndexes(\PHPSchemaManager\Objects\Table $table)
    {
        $i = 0;
        $instruction = array();

        foreach ($table->getIndexes() as $index) {
            /* @var $index \PHPSchemaManager\Objects\Index */

            // Mysql doesn't support index modification. @see http://dev.mysql.com/doc/refman/5.0/en/alter-table.html
            if ($index->shouldAlter()) {
                // so we first remove the index...
                $this->dbQuery("DROP INDEX $index ON $table");

                // ... and them mark the index to be recreated
                $index->markForCreation();
            }

            // if it is SYNCED, do nothing
            if ($index->isSynced()) {
                continue;
            } elseif ($index->shouldDelete()) {
                // check if the index should be deleted
                $instruction[$i] = "DROP INDEX $index" . PHP_EOL;
                $index->markAsDeleted();
                $index->destroy();
            } elseif ($index->shouldCreate()) {
                // check if the index should be created
                $columns = array();
                foreach ($index->getColumns() as $column) {
                    $columns[] = "$column";
                }
                $columnsString = implode(", ", $columns);

                // check if it is a unique key
                $unique = $index->isUniqueKey() ? "UNIQUE " : "";

                $instruction[$i] = "ADD ";

                if ($index->isPrimaryKey()) {
                    $instruction[$i] .= "PRIMARY KEY";
                } else {
                    $instruction[$i] .= "{$unique}INDEX $index";
                }

                $instruction[$i] .= "($columnsString)" . PHP_EOL;
            }

            $i++;

        }

        return empty($instruction) ? "" : implode(", " . PHP_EOL, $instruction);
    }

    protected function tableForeignKeysInstruction(\PHPSchemaManager\Objects\Table $table)
    {
        $instruction = array();
        $fkInstruction = '';

        foreach ($table->getColumns() as $column) {
            /** @var $column \PHPSchemaManager\Objects\Column */

            if ($column->shouldCreate() && $column->isFK()) {
                /** @var $referencedTable \PHPSchemaManager\Objects\Column */
                $referencedTable = $column->getReferencedColumn()->getFather();
                $index = $referencedTable->getName();

                if (empty($instruction[$index])) {
                    $instruction[$index]['instructionColumn'] = "";
                    $instruction[$index]['instructionReferencedColumn'] = "";
                }

                $instruction[$index]['instructionColumn'] .= "$column, ";
                $instruction[$index]['instructionReferencedColumn'] .= $column->getReferencedColumn() . ", ";
                $instruction[$index]['deleteAction'] = $this->getReferenceOptionDescription($column->getReference()->getActionOnDelete());
                $instruction[$index]['updateAction'] = $this->getReferenceOptionDescription($column->getReference()->getActionOnUpdate());
            }

        }

        foreach($instruction as $referencedTable => $item) {
            $instructionColumn = rtrim($item['instructionColumn'], ", ");
            $instructionReferencedColumn = rtrim($item['instructionReferencedColumn'], ", ");
            $fkInstruction .= "FOREIGN KEY ($instructionColumn)" . PHP_EOL .
                            "\tREFERENCES $referencedTable ($instructionReferencedColumn)" . PHP_EOL .
                            "\tON DELETE {$item['deleteAction']}" . PHP_EOL .
                            "\tON UPDATE {$item['updateAction']}, " . PHP_EOL;
        }

        if (!empty($fkInstruction)) {
            $fkInstruction = substr($fkInstruction, 0, -1 * (strlen(", " . PHP_EOL)));
        }

        return $fkInstruction;
    }

    protected function getReferenceOptionDescription($referenceOption = null)
    {
        switch ($referenceOption) {
            case \PHPSchemaManager\Objects\ColumnReference::NOACTION:
                $description = "NO ACTION";
                break;
            case \PHPSchemaManager\Objects\ColumnReference::RESTRICT:
                $description = "RESTRICT";
                break;
            case \PHPSchemaManager\Objects\ColumnReference::SETNULL:
                $description = "SET NULL";
                break;
            case \PHPSchemaManager\Objects\ColumnReference::CASCADE:
            default:
                $description = "CASCADE";
        }

        return $description;
    }


    protected function deleteTable(\PHPSchemaManager\Objects\Table $table)
    {
        $this->selectDb();
        $sql = "DROP TABLE `{$table}`";
        $this->dbQuery($sql);
        $table->markAsDeleted();
        $table->destroy();
    }

    protected function createTable(\PHPSchemaManager\Objects\Table $table)
    {
        $this->selectDb();
        $sql = "CREATE TABLE `{$table}` (" . PHP_EOL;

        foreach ($table->getColumns() as $column) {
            $col = new DriverMysqlColumn($column);
            $sql .= $col->getDataDefinition() . "," . PHP_EOL;
        }

        // get the instruction to create the foreign keys
        $fkSql = $this->tableForeignKeysInstruction($table);
        $fkSql = empty($fkSql) ? "" : "$fkSql," . PHP_EOL;
        $sql .= $fkSql;

        // removes the last comma + EOL from the clause. SQL is not like PHP...
        $sql = substr($sql, 0, (strlen(PHP_EOL)+1)*-1);

        $sql .= PHP_EOL . ")";

        // check if there is any specific configuration
        if ($specifics = $this->getMysqlTableSpecifics($table)) {
            if ($specifics->isInnoDb()) {
                $sql .= " ENGINE=InnoDb";
            }elseif($specifics->isMyIsam()) {
                $sql .= " ENGINE=MYISAM";
            }elseif($specifics->isCsv()) {
                $sql .= " ENGINE=CSV";
            }elseif($specifics->isMemory()) {
                $sql .= " ENGINE=MEMORY";
            }elseif($specifics->isBlackhole()) {
                $sql .= " ENGINE=BLACKHOLE";
            }
        }

        $this->dbQuery($sql);
    }

    /**
     * Create database
     *
     * @param string $dbName
     */
    protected function createDatabase($dbName)
    {
        $sql = "CREATE DATABASE `{$dbName}`";
        try {
            $this->dbQuery($sql);
        } catch (\PHPSchemaManager\Exceptions\MysqlException $e) {
            //do nothing, if the database is already created, no problem
        }
    }

    protected function dropDatabase(\PHPSchemaManager\Objects\Schema $schema)
    {
        $sql = "DROP DATABASE `{$schema}`";
        $this->dbQuery($sql);
        $schema->markAsDeleted();
        $schema->destroy();
    }

    protected function getMysqlTableSpecifics(\PHPSchemaManager\Objects\Table $table)
    {
        foreach($table->getSpecificsConfiguration() as $specific) {
            if ($specific instanceof \PHPSchemaManager\Objects\TableSpecificMysql) {
                return $specific;
            }
        }

        return false;
    }

    protected function getForeignKeys(\PHPSchemaManager\Objects\Schema $schema)
    {

        $sql = "SELECT kcu.table_name AS origin_table, kcu.column_name AS fk_name, " . PHP_EOL .
                "  kcu.referenced_table_name, kcu.referenced_column_name, rc.update_rule, rc.delete_rule" . PHP_EOL .
                "FROM information_schema.key_column_usage AS kcu" . PHP_EOL .
                "INNER JOIN information_schema.referential_constraints AS rc" . PHP_EOL .
                "  ON rc.table_name = kcu.table_name AND rc.constraint_name = kcu.constraint_name" . PHP_EOL .
                "WHERE kcu.referenced_table_name IS NOT NULL AND kcu.table_schema = '{$schema}'";
        $res = $this->dbQuery($sql);

        while($row = mysql_fetch_assoc($res)) {
            $originTable = $schema->hasTable($row['origin_table']);
            $fkColumn = $originTable->hasColumn($row['fk_name']);
            if (!empty($fkColumn)) {
                $referencedTable = $schema->hasTable($row['referenced_table_name'])
                    ->hasColumn($row['referenced_column_name']);
                if (!empty($referencedTable)) {
                    $reference = $fkColumn->references($referencedTable);

                    // check wich cascade rule should be associated to this column
                    switch ($row['update_rule']) {
                        case "CASCADE":
                            $reference->actionOnUpdate(\PHPSchemaManager\Objects\ColumnReference::CASCADE);
                            break;
                        case "NO ACTION":
                            $reference->actionOnUpdate(\PHPSchemaManager\Objects\ColumnReference::NOACTION);
                            break;
                        case "RESTRICT":
                            $reference->actionOnUpdate(\PHPSchemaManager\Objects\ColumnReference::RESTRICT);
                            break;
                        case "SETNULL":
                            $reference->actionOnUpdate(\PHPSchemaManager\Objects\ColumnReference::SETNULL);
                            break;
                    }

                    // check wich cascade rule should be associated to this column
                    switch ($row['delete_rule']) {
                        case "CASCADE":
                            $reference->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::CASCADE);
                            break;
                        case "NO ACTION":
                            $reference->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::NOACTION);
                            break;
                        case "RESTRICT":
                            $reference->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::RESTRICT);
                            break;
                        case "SETNULL":
                            $reference->actionOnDelete(\PHPSchemaManager\Objects\ColumnReference::SETNULL);
                            break;
                    }

                    // mark the table as persisted
                    $originTable->persisted();

                }
            }
        }
    }
}
