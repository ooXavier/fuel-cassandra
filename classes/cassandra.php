<?php
/*
 * This is a PHP  wrapper that handles calling a PHP library : PHP Cassa.
 *
 * Copyright (c) 2012
 * AUTHORS:
 *   Xavier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
* PHP Cassa integration with Fuel
*
* @package     Fuel
* @subpackage  Packages
* @category    DB
* @author      mooxavier
*/

namespace Cassandra;
use phpcassa\ColumnFamily;
use phpcassa\SuperColumnFamily;
use phpcassa\ColumnSlice;
use phpcassa\Connection\ConnectionPool;
use phpcassa\KsDef;
use phpcassa\SystemManager;
use phpcassa\Index\IndexExpression;
use phpcassa\Index\IndexClause;
use phpcassa\Schema\DataType\LongType;

//class CassandraException extends \FuelException {}

class Cassandra
{
 
  /**
	 * Holds the current Cassandra connection object
	 *
	 * @var  Cassandra
	 */
	protected $connection = false;
 	
	/**
	 * All the Cassandra instances
	 *
	 * @var  array
	 */
	protected static $instances = array();

	/**
	 * Acts as a Multiton.  Will return the requested instance, or will create
	 * a new one if it does not exist.
	 *
	 * @param   string    $name  The instance name
	 * @return  Cassandra
	 */
	 
	public static function instance($name = 'default')
	{
		if (\array_key_exists($name, static::$instances))
		{
			return static::$instances[$name];
		}

		if (empty(static::$instances))
		{
			\Config::load('cassandra', true);
		}
    
		if ( ! ($config = \Config::get('cassandra.cassandra.'.$name)))
		{
			throw new CassandraException('Invalid instance name given.');
		}

		static::$instances[$name] = new static($config);

		return static::$instances[$name];
	}

	/**
	 *	The class constructor
	 *	Generate the connection string and establish a connection to Cassandra DB.
	 *
	 *	@param	array	$config		an array of config values
	 */
	public function __construct(array $config = array())
	{ 
    if (empty($config['keyspace']))
		{
			throw new CassandraException("The keyspace must be set to connect to Cassandra");
		}
    
    if (count($config['servers']) < 1)
		{
			throw new CassandraException("The server pool must be set to connect to Cassandra");
		}
    
    $this->connection = new ConnectionPool($config['keyspace'], $config['servers']);

		if ( ! $this->connection)
		{
			throw new CassandraException($errstr, $errno);
		}
	}
  
  public function strToHex($string)
  {
    $hex = '';
    for ($i=0; $i < strlen($string); $i++) {
      $hex .= dechex(ord($string[$i]));  //ord returns the ascii equivalent of given char
    }
    return $hex;
  }
  
	/**
    * Execute a CQL Query
    *
  	*	@param	string	 $query		    CQL Query to execute
  **/
  public function execute($query, $compression = \cassandra_Compression::NONE) 
  {
    $raw = $this->connection->get();
    $resultSet = $raw->client->execute_cql_query($query, $compression);
    $this->connection->return_connection($raw);
    unset($raw);
    if ($resultSet->type == 1) {
      $res = array();
      foreach ($resultSet->rows as $rowIndex => $row) {
        foreach ($row->columns as $colIndex => $column) {
          $res[$row->key][$column->name] = $column->value;
        }
      }
    }
    else
    {
      $res = null;
    } 
    unset($resultSet);
    return $res;
  }

	/**
    * Select a Column Family
    *
  	*	@param  string    $family    Column Family to use
  	* @return  ColumnFamily
  **/  
  public function useCf($family/*, $option=null*/) {
     $cf = new ColumnFamily($this->connection, $family);
     /*if ($option === 'array') {
       $cf->insert_format = ColumnFamily::ARRAY_FORMAT;
       $cf->return_format = ColumnFamily::ARRAY_FORMAT;
     }*/
     return $cf;
  }

  /**
   * Select a Super Column Family
   *
   *	@param  string    $family    Super Column Family to use
   * @return  ColumnFamily
  **/  
  public function useSCf($family) {
    return new SuperColumnFamily($this->connection, $family);
  }

	/**
    * Get an uuid()
    *
  	* @return  TimeUUID
  **/
  public function uuid() {
    return \phpcassa\UUID::uuid1();
  }
  
  /**
    * Wrappers for phpcassa
  **/
  
  public static function makeUUID($timestamp) {
    return new \phpcassa\UUID($timestamp);
  }
  public static function uuid1($node=null, $time=null) {
    return \phpcassa\UUID::uuid1($node, $time);
  }
  public static function ColumnSlice($start="", $finish="", $count=self::DEFAULT_COLUMN_COUNT, $reversed=False) {
    return new \phpcassa\ColumnSlice($start, $finish, $count, $reversed);
  }
  public static function IndexExpression($column_name, $value, $op='EQ') {
    return new \phpcassa\Index\IndexExpression($column_name, $value, $op);
  }
  public static function IndexClause($expr_list, $start_key='', $count=ColumnFamily::DEFAULT_ROW_COUNT) {
    return new \phpcassa\Index\IndexClause($expr_list, $start_key, $count);
  }
}
