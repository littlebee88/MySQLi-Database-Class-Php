<?php
/**
 * MysqliDb Class
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Jeffery Way <jeffrey@jeffrey-way.com>
 * @author    Josh Campbell <jcampbell@ajillion.com>
 * @author    Stephanie Schmidt <littlebeehigbee@gmail.com>
 * @copyright Copyright (c) 2010
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   1.1
 **/

class MysqliDb extends mysqli
{
    /**
     * MySQLi instance
     *
     * @var mysqli
     */
    protected $mysqli;

    /**
     * The SQL query to be prepared and executed
     *
     * @var string
     */
    protected $query;

    /**
     * An array that holds where joins
     *
     * @var array
     */
    protected $join = array();

    /**
     * An array that holds where conditions 'fieldname' => 'value'
     *
     * @var array
     */
    protected $where = array();

    /**
     * A string that holds a custom where string
     *
     * @var string
     */
    protected $customWhere = '';

    /**
     * Dynamic type list for where condition values
     *
     * @var array
     */
    protected $whereTypeList;

    /**
     * Dynamic type list for order by condition value
     */
    protected $orderBy = array();

    /**
     * Dynamic type list for group by condition value
     */
    protected $groupBy = array();

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * Dynamic type list for table data values
     *
     * @var array
     */
    protected $paramTypeList;

    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter references
     *
     * @var array
     */
    protected $bindParams = array(''); // Create the empty 0 index

    /**
     * @var string
     */
    private $db_host;

    /**
     * @var string
     */
    private $db_name;

    /**
     * @var string
     */
    private $db_user;

    /**
     * @var string
     */
    private $db_pass;

    /**
     * @var string
     */
    private $db_port;

    /**
     * @var string
     */
    public $db_prefix;

    /**
     * @var mysqli_stmt
     */
    private $stmt;

    /**
     * @var
     */
    public $db_result;

    /**
     * Db results output can be set to an array or object
     *
     * @var
     */
    protected $output = 'object';

    /**
     * @var
     */
    protected $lastError;

    /**
     * @var
     */
    protected $lastQuery;

    /**
     * @var bool
     */
    protected $inTransaction = false;

    /**
     * @param null $db_host
     * @param null $db_name
     * @param null $db_user
     * @param null $db_pass
     * @param null $db_port
     * @param null $db_prefix
     */
    public function __construct($db_host, $db_name, $db_user, $db_pass, $db_port=null, $db_prefix=null)
    {
            $this->db_host = $db_host;
            $this->db_name = $db_name;
            $this->db_user = $db_user;
            $this->db_pass = $db_pass;
            $this->db_port = (!is_null($db_port)) ? $db_port : ini_get('mysqli.default_port');
            $this->db_prefix = (!is_null($db_prefix)) ? $db_prefix : '';

        $this->mysqli = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port);

        //check connection
        if ($this->mysqli->connect_errno) {
            printf("Failed to connect to MySQL: (" . $this->mysqli->connect_errno . ") " . $this->mysqli->connect_error);
            exit();
        }
        //$this->mysqli->set_charset('utf8');
        //$this->stmt = $this->mysqli->init();
    }

    /**
     * Reset states after an execution
     *
     * @return object Returns the current instance.
     */
    protected function reset()
    {
        $this->where = array();
        $this->customWhere = '';
        $this->join = array();
        $this->orderBy = array();
        $this->groupBy = array();
        $this->bindParams = array(''); // Create the empty 0 index
        $this->query = null;
        $this->whereTypeList = null;
        $this->paramTypeList = null;
        $this->output = null;
        $this->limit = null;
        $this->offset = 0;
    }

    /**
     * Pass in a raw query and an array containing the parameters to bind to the prepared statement.
     *
     * @param string $query Contains a user-provided query.
     * @param array $bindParams All variables to bind to the SQL statement.
     *
     * @return array Contains the returned rows from the query.
     */
    public function rawQuery($query, $bindParams = null)
    {
        $this->query = filter_var($query, FILTER_SANITIZE_STRING);
        //html entities added by filter have to be decoded or statement prepare will break
        $this->query = html_entity_decode($this->query);

        if (is_array($bindParams) === true) {
            $params = array(''); // Create the empty 0 index
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                array_push($params, $bindParams[$prop]);
            }
            call_user_func_array(array($this->stmt, 'bind_param'), $this->refValues($params));
        }
        $this->processQuery();
        return $this->db_result;
    }

    /**
     *
     * @param string $query Contains a user-provided select query.
     *
     * @return array Contains the returned rows from the query.
     */
    public function query($query)
    {
        $this->query = filter_var($query, FILTER_SANITIZE_STRING);
        //html entities added by filter have to be decoded or statement prepare will break
        $this->query = html_entity_decode($this->query);
        $this->processQuery();
        return $this->db_result;
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|array $columns The database columns to select.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function get($tableName, $columns = '*')
    {
        if (empty ($columns)) $columns = '*';
        $columns = is_array($columns) ? $columns : $this->multiExplode($columns, ',');
        $columns = implode(', ', $columns);
        $this->query = 'SELECT ' . $columns . ' FROM ' . $tableName;
        $this->processQuery();
        return $this->db_result;
    }

    /**
     * A convenient SELECT * function to get one column.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|array $columns The database columns to select.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function getOne($tableName, $columns = '*')
    {
        if (empty ($columns)) $columns = '*';
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        $this->query = 'SELECT ' . $column . ' FROM ' . $tableName;
        $this->limit(1);
        $this->processQuery();
        if(is_array($this->db_result) && !empty($this->db_result)){
            $this->db_result = $this->db_result[0];
        }
        return $this->db_result;
    }

    /**
     * A convenient SELECT * function to get one column.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|array $column The database column to select.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function getCol($tableName, $column)
    {
        $column = (is_array($column)) ? array_shift($column) : $column;
        $this->get($tableName, $column);
        $new_array = array();
        if(is_array($this->db_result) && !empty($this->db_result))
        {
            // Extract the column values
            foreach ($this->db_result as $r)
            {
                $new_array[] = $r->$column;
            }
        }
        $this->db_result = $new_array;
        return $this->db_result;
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string|array $columns The database columns to select.
     *
     * @return array Contains the returned rows from the select query.
     */
    public function getVar($tableName, $columns = '*')
    {
        if (empty ($columns)) $columns = '*';

        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        $this->query = 'SELECT ' . $column . ' FROM ' . $tableName;
        $this->limit(1);
        $this->output('array');
        $this->processQuery();
        if(is_array($this->db_result) && !empty($this->db_result))
        {
            $this->db_result = $this->db_result[0][$column];
        }
        return $this->db_result;
    }

    /**
     *
     * @param <string $tableName The name of the table.
     * @param array $tableData Data containing information for inserting into the DB.
     *
     * @return boolean Boolean indicating whether the insert query was completed succesfully.
     */
    public function insert($tableName, $tableData)
    {
        $this->query = 'INSERT INTO ' . $tableName;
        $this->processQuery();
        return $this->db_result;
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array $tableData Array of data to update the desired row.
     *
     * @return boolean
     */
    public function update($tableName, $tableData)
    {
        $this->query = 'UPDATE ' . $tableName . ' SET ';
        $this->processQuery();
        return $this->db_result;
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param string $tableName The name of the database table to work with.
     *
     * @return boolean Indicates success. 0 or 1.
     */
    public function delete($tableName)
    {
        $this->query = 'DELETE FROM ' . $tableName;
        $this->processQuery();
        return $this->db_result;

    }

    /**
     * @param null $tableData
     * @return array
     */
    public function processQuery($tableData = null)
    {
        $this->_buildQuery($tableData);
        $this->setLastQuery($this->query);

        //execute query
        if (!$this->stmt->execute())
        {
            $this->setLastError($this->mysqli->sqlstate . ' ' . $this->mysqli->error);
            $this->db_result = false;
            trigger_error("Problem preparing query ($this->query) " . $this->mysqli->sqlstate . ' ' . $this->mysqli->error);
        }
        $this->_dynamicBindResults();
        $this->reset();
        return $this;
    }



    /**
     * This method allows you to specify multiple (method chaining optional) WHERE statements for SQL queries.
     *
     * @uses $MySqliDb->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp The name of the database field.
     * @param mixed $whereValue The value of the database field.
     *
     * @return MySqliDb
     */
    public function where($whereProp, $whereValue)
    {
        $this->where[$whereProp] = $whereValue;
        return $this;
    }

    /**
     * @param $where
     * @return $this
     */
    public function customWhere($where)
    {
        $this->customWhere = $where;
        return $this;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @uses $MySqliDb->join('table1', 'field1 <> field2', 'LEFT')
     *
     * @param string $joinTable The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType 'LEFT', 'INNER' etc.
     *
     * @return MySqliDb
     */
    public function join($joinTable, $joinCondition, $joinType = '')
    {
        $allowedTypes = array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER');
        $joinType = strtoupper(trim($joinType));
        $joinTable = filter_var($joinTable, FILTER_SANITIZE_STRING);

        if ($joinType && !in_array($joinType, $allowedTypes))
            die ('Wrong JOIN type: ' . $joinType);

        $this->join[$joinType . " JOIN " . $joinTable] = $joinCondition;

        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @uses $MySqliDb->orderBy('id', 'desc')->orderBy('name', 'desc');
     *
     * @param string $orderByField The name of the database field.
     * @param string $orderbyDirection Order direction.
     *
     * @return MySqliDb
     */
    public function orderBy($orderByField, $orderbyDirection = "DESC")
    {
        $allowedDirection = Array("ASC", "DESC");
        $orderbyDirection = strtoupper(trim($orderbyDirection));
        $orderByField = filter_var($orderByField, FILTER_SANITIZE_STRING);

        if (empty($orderbyDirection) || !in_array($orderbyDirection, $allowedDirection))
            die ('Wrong order direction: ' . $orderbyDirection);

        $this->orderBy[$orderByField] = $orderbyDirection;
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) GROUP BY statements for SQL queries.
     *
     * @uses $MySqliDb->groupBy('name');
     *
     * @param string $groupByField The name of the database field.
     *
     * @return MySqliDb
     */
    public function groupBy($groupByField)
    {
        $groupByField = filter_var($groupByField, FILTER_SANITIZE_STRING);

        $this->groupBy[] = $groupByField;
        return $this;
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        $limit = filter_var($limit, FILTER_SANITIZE_NUMBER_INT);

        $this->limit = $limit;
        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */
    public function offset($offset)
    {
        $offset = filter_var($offset, FILTER_SANITIZE_NUMBER_INT);
        $this->offset = ($offset > 0) ? $offset : 0;
        return $this;
    }

    /**
     * @param $output
     * @return $this
     */
    public function output($output)
    {
        if (!in_array($output, array('object', 'array'))) {
            $output = 'object';
        }

        $this->output = $output;
        return $this;
    }

    /**
     * This methods returns the ID of the last inserted item
     *
     * @return integer The last inserted item ID.
     */
    public function getInsertId()
    {
        return $this->mysqli->insert_id;
    }

    /**
     * Escape harmful characters which might affect a query.
     *
     * @param string $str The string to escape.
     *
     * @return string The escaped string.
     */
    public function escape($str)
    {
        return $this->mysqli->real_escape_string($str);
    }

    /**
     * This method is needed for prepared statements. They require
     * the data type of the field to be bound with "i" s", etc.
     * This function takes the input, determines what type it is,
     * and then updates the param_type.
     *
     * @param mixed $item Input to determine the type.
     *
     * @return string The joined parameter types.
     */
    protected function _determineType($item)
    {
        switch (gettype($item)) {
            case 'NULL':
            case 'string':
                return 's';
                break;

            case 'integer':
                return 'i';
                break;

            case 'blob':
                return 'b';
                break;

            case 'double':
                return 'd';
                break;
        }
        return '';
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param array $tableData Should contain an array of data for updating the database.
     *
     * @return mysqli_stmt Returns the $this->stmt object.
     */
    protected function _buildQuery($tableData = null)
    {
        $hasTableData = is_array($tableData);
        $hasConditional = !empty($this->where);

        // Did the user call the "join" method?
        if (!empty($this->join)) {
            foreach ($this->join as $prop => $value) {
                $this->query .= " " . $prop . " ON " . $value;
            }
        }

        // Did the user call the "where" method?
        if (!empty($this->where)) {

            // if update data was passed, filter through and create the SQL query, accordingly.
            if ($hasTableData) {
                $pos = strpos($this->query, 'UPDATE');
                if ($pos !== false) {
                    foreach ($tableData as $prop => $value) {
                        // determines what data type the item is, for binding purposes.
                        $this->paramTypeList .= $this->_determineType($value);

                        // prepares the reset of the SQL query.
                        $this->query .= ($prop . ' = ?, ');
                    }
                    $this->query = rtrim($this->query, ', ');
                }
            }

            //Prepare the where portion of the query
            $this->query .= ' WHERE ';
            foreach ($this->where as $column => $value) {
                $comparison = ' = ? ';
                if (is_array($value)) {
                    // if the value is an array, then this isn't a basic = comparison
                    $key = key($value);
                    $val = $value[$key];
                    switch (strtolower($key)) {
                        case 'in':
                            $comparison = ' IN (';
                            foreach ($val as $v) {
                                $comparison .= ' ?,';
                                $this->whereTypeList .= $this->_determineType($v);
                            }
                            $comparison = rtrim($comparison, ',') . ' ) ';
                            break;
                        case 'between':
                            $comparison = ' BETWEEN ? AND ? ';
                            $this->whereTypeList .= $this->_determineType($val[0]);
                            $this->whereTypeList .= $this->_determineType($val[1]);
                            break;
                        default:
                            // We are using a comparison operator with only one parameter after it
                            $comparison = ' ' . $key . ' ? ';
                            // Determines what data type the where column is, for binding purposes.
                            $this->whereTypeList .= $this->_determineType($val);
                    }
                } else {
                    // Determines what data type the where column is, for binding purposes.
                    $this->whereTypeList .= $this->_determineType($value);
                }
                // Prepares the reset of the SQL query.
                $this->query .= ($column . $comparison . ' AND ');
            }
            $this->query = rtrim($this->query, ' AND ');
        }

        // Did the user call the "customWhere" method?
        if (!empty($this->customWhere)) {
            //is this the only "where"?
            $this->query .= (!empty($this->where)) ? ' AND ' : ' WHERE ';
            $this->query .= $this->customWhere;
        }

        // Did the user call the "groupBy" method?
        if (!empty($this->groupBy)) {
            $this->query .= " GROUP BY ";
            foreach ($this->groupBy as $key => $value) {
                // prepares the reset of the SQL query.
                $this->query .= $value . ", ";
            }
            $this->query = rtrim($this->query, ', ') . " ";
        }

        // Did the user call the "orderBy" method?
        if (!empty ($this->orderBy)) {
            $this->query .= " ORDER BY ";
            foreach ($this->orderBy as $prop => $value) {
                // prepares the reset of the SQL query.
                $this->query .= $prop . " " . $value . ", ";
            }
            $this->query = rtrim($this->query, ', ') . " ";
        }

        // Determine if is INSERT query
        if ($hasTableData) {
            $pos = strpos($this->query, 'INSERT');

            if ($pos !== false) {
                //is insert statement
                $keys = array_keys($tableData);
                $values = array_values($tableData);
                $num = count($keys);

                // wrap values in quotes
                foreach ($values as $key => $val) {
                    $values[$key] = "'{$val}'";
                    $this->paramTypeList .= $this->_determineType($val);
                }

                $this->query .= '(' . implode($keys, ', ') . ')';
                $this->query .= ' VALUES(';
                while ($num !== 0) {
                    $this->query .= '?, ';
                    $num--;
                }
                $this->query = rtrim($this->query, ', ');
                $this->query .= ')';
            }
        }

        // Did the user call the "limit" method?
        if (!is_null($this->limit)) {
            if($this->offset>0){
                $this->query .= ' LIMIT ' . (int)$this->offset . ',' . (int)$this->limit;
            } else {
                $this->query .= ' LIMIT ' . (int)$this->limit;
            }
        }

        // Prepare query
        if (!$this->stmt = $this->mysqli->prepare($this->query)) {
            $this->setLastError($this->mysqli->sqlstate . ' ' . $this->mysqli->error);
            $this->db_result = false;
            trigger_error("Problem preparing query ($this->query) " . $this->mysqli->sqlstate . ' ' . $this->mysqli->error);
        }

        // Prepare table data bind parameters
        if ($hasTableData) {
            $this->bindParams[0] = $this->paramTypeList;
            foreach ($tableData as $prop => $val) {
                array_push($this->bindParams, $tableData[$prop]);
            }
        }
        // Prepare where condition bind parameters
        if ($hasConditional) {
            if ($this->where) {
                $this->bindParams[0] .= $this->whereTypeList;
                foreach ($this->where as $prop => $val) {
                    if (!is_array($val)) {
                        array_push($this->bindParams, $this->where[$prop]);
                        continue;
                    }
                    // if val is an array, this is not a basic = comparison operator
                    $key = key($val);
                    $vals = $val[$key];
                    if (is_array($vals)) {
                        // if vals is an array, this comparison operator takes more than one parameter
                        foreach ($vals as $k => $v) {
                            array_push($this->bindParams, $this->where[$prop][$key][$k]);
                        }
                    } else {
                        // otherwise this comparison operator takes only one parameter
                        array_push($this->bindParams, $this->where[$prop][$key]);
                    }
                }
            }
        }

        // Bind parameters to statement
        if ($hasTableData || $hasConditional)
        {
            call_user_func_array(array($this->stmt, 'bind_param'), $this->refValues($this->bindParams));
        }
        return $this;
    }

    /**
     * This helper method takes care of prepared statements bind_result method when the number of variables to pass is unknown.
     *
     * @return array|object The results of the SQL fetch.
     */
    protected function _dynamicBindResults()
    {
        $parameters = array();
        $this->db_result = array();

        $meta = $this->stmt->result_metadata();

        // if $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if (!$meta && $this->stmt->sqlstate)
        {
            if(false!==strpos($this->query, 'SELECT'))
            {
                //it was a select statement that produced no results, so we return an empty result set
                $this->db_result = array();
            }
            elseif(false!==strpos($this->query, 'UPDATE'))
            {
                //return the number of affected rows if any, otherwise return true/false for success
                $this->db_result = ($this->stmt->affected_rows > 0) ? $this->stmt->affected_rows : $this->db_result;
            }
            elseif(false!==strpos($this->query, 'INSERT'))
            {
                //return the insert_id if available, otherwise return true/false for success
                $this->db_result = ($this->stmt->insert_id > 0) ? $this->stmt->insert_id : $this->db_result;
            }
            else
            {
                //there were no errors so we return true
                $this->db_result = true;
            }
            return $this;
        }

        $row = array();
        while ($field = $meta->fetch_field()) {
            $row[$field->name] = null;
            $parameters[] = & $row[$field->name];
        }

        call_user_func_array(array($this->stmt, 'bind_result'), $parameters);

        while ($this->stmt->fetch()) {
            if ($this->output == 'array') {
                //returns an array of records as associative arrays
                $x = array();
                foreach ($row as $key => $val) {
                    $x[$key] = $val;
                }
                array_push($this->db_result, $x);
            } else {
                //returns an array of records as objects
                $x = new stdClass();
                foreach ($row as $key => $val) {
                    $x->$key = $val;
                }
                array_push($this->db_result, $x);
            }

        }
        return $this;
    }

    /**
     * Close connection
     */
    public function __destruct()
    {
        $this->mysqli->close();
    }

    /**
     * @param array $arr
     * @return array
     */
    protected function refValues($arr)
    {
        //Reference is required for PHP 5.3+
        if (strnatcmp(phpversion(), '5.3') >= 0) {
            $refs = array();
            foreach ($arr as $key => $value) {
                $refs[$key] = &$arr[$key];
            }
            return $refs;
        }
        return $arr;
    }

    /**
     * @return mixed
     */
    public function beginTransaction() {
   		if ($ret = $this->autocommit(false)) {
   			$this->inTransaction = true;
   		}
   		else {
   			$this->inTransaction = false;
   		}
   		return $ret;
   	}

    /**
     * @return bool
     */
    public function commitTransaction() {
   		if ($this->inTransaction) {
   			$ret = $this->commit();
   			$this->autocommit(true);
   			$this->inTransaction = false;
   			return $ret;
   		}
   		else {
   			return false;
   		}
   	}

    /**
     * @return bool
     */
    public function rollbackTransaction() {
   		if ($this->inTransaction) {
   			$ret = $this->rollback();
   			$this->autocommit(true);
   			$this->inTransaction = false;
   			return $ret;
   		}
   		else {
   			return false;
   		}
   	}

    /**
     * @param mixed $lastQuery
     */
    public function setLastQuery($lastQuery)
    {
        $this->lastQuery = $lastQuery;
    }

    /**
     * Method returns last query
     *
     * @return string
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * @param mixed $lastError
     */
    public function setLastError($lastError)
    {
        $this->lastError = $lastError;
    }

    /**
     * Method returns last mysql error
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @param $string
     * @param array $deliminators
     * @return array
     */
    public function multiExplode($string, $deliminators = array())
    {
        if(empty($deliminators)){
            $deliminators = array(",",".","|",":","_");
        }

        //replace all deliminators with a single one
        $string = str_replace($deliminators, $deliminators[0], $string);
        $array = explode($deliminators[0], $string);
        $clean = array();
        foreach($array as $r){
            $clean[] = trim($r);
        }
        return $clean;
    }

}
