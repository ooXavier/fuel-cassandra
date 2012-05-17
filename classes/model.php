<?php
use Orm\Model;

namespace Cassandra;

class Cassandra_Model implements \ArrayAccess, \Iterator {
  
  /* ---------------------------------------------------------------------------
	 * Static usage
	 * --------------------------------------------------------------------------- */
	 
	/**
	 * @var  array  name or names of the primary keys
	 */
	protected static $_primary_key = array('id');
	
  /**
	 * @var  array  cached properties
	 */
	protected static $_properties_cached = array();
	
	public static function forge($data = array(), $new = true, $view = null)
	{
		return new static($data, $new, $view);
	}
	
	/**
	 * Get the table name for this class
	 *
	 * @return  string
	 */
	public static function table()
	{
		$class = get_called_class();

		// Table name unknown
		if ( ! array_key_exists($class, static::$_table_names_cached))
		{
			// Table name set in Model
			if (property_exists($class, '_table_name'))
			{
				static::$_table_names_cached[$class] = static::$_table_name;
			}
			else
			{
				static::$_table_names_cached[$class] = \Inflector::tableize($class);
			}
		}

		return static::$_table_names_cached[$class];
	}

	/**
	 * Attempt to retrieve an earlier loaded object
	 *
	 * @param   array|Model  $obj
	 * @param   null|string  $class
	 * @return  Model|false
	 */
	public static function cached_object($obj, $class = null)
	{
		$class = $class ?: get_called_class();
		$id    = (is_int($obj) or is_string($obj)) ? (string) $obj : $class::implode_pk($obj);

		$result = ( ! empty(static::$_cached_objects[$class][$id])) ? static::$_cached_objects[$class][$id] : false;

		return $result;
	}

	/**
	 * Get the primary key(s) of this class
	 *
	 * @return  array
	 */
	public static function primary_key()
	{
		return static::$_primary_key;
	}

	/**
	 * Implode the primary keys within the data into a string
	 *
	 * @param   array
	 * @return  string
	 */
	public static function implode_pk($data)
	{
		if (count(static::$_primary_key) == 1)
		{
			$p = reset(static::$_primary_key);
			return (is_object($data)
				? strval($data->{$p})
				: (isset($data[$p])
					? strval($data[$p])
					: null));
		}

		$pk = '';
		foreach (static::$_primary_key as $p)
		{
			if (is_null((is_object($data) ? $data->{$p} : (isset($data[$p]) ? $data[$p] : null))))
			{
				return null;
			}
			$pk .= '['.(is_object($data) ? $data->{$p} : $data[$p]).']';
		}

		return $pk;
	}
  
  /**
	 * Get the class's properties
	 *
	 * @return  array
	 */
	public static function properties()
	{
		$class = get_called_class();

		// If already determined
		if (array_key_exists($class, static::$_properties_cached))
		{
			return static::$_properties_cached[$class];
		}

		// Try to grab the properties from the class...
		if (property_exists($class, '_properties'))
		{
			$properties = static::$_properties;
			foreach ($properties as $key => $p)
			{
				if (is_string($p))
				{
					unset($properties[$key]);
					$properties[$p] = array();
				}
			}
		}

		// ...if the above failed, run DB query to fetch properties
		if (empty($properties))
		{
		  throw new \FuelException('Make sure you have defined properties in your model.');
		}

		// cache the properties for next usage
		static::$_properties_cached[$class] = $properties;

		return static::$_properties_cached[$class];
	}
  
	/* ---------------------------------------------------------------------------
	 * Object usage
	 * --------------------------------------------------------------------------- */

	/**
	 * @var  bool  keeps track of whether it's a new object
	 */
	private $_is_new = true;

	/**
	 * @var  bool  keeps to object frozen
	 */
	private $_frozen = false;

	/**
	 * @var  array  keeps the current state of the object
	 */
	private $_data = array();

	/**
	 * @var  array  keeps a copy of the object as it was retrieved from the database
	 */
	private $_original = array();

	/**
	 * @var  array
	 */
	private $_data_relations = array();

	/**
	 * @var  array  keeps a copy of the relation ids that were originally retrieved from the database
	 */
	private $_original_relations = array();

	/**
	 * @var  string  view name when used
	 */
	private $_view;
	
	/**
	 * Constructor
	 *
	 * @param  array
	 * @param  bool
	 */
	public function __construct(array $data = array(), $new = true, $view = null)
	{  
  	//echo 'construct';
		// This is to deal with PHP's native hydration from that happens before constructor is called
		// for example using the DB's as_object() function
		if( ! empty($this->_data))
		{
			$this->_original = $this->_data;
			$new = false;
		}

		if ($new)
		{
			$properties = $this->properties();
			//print_r($properties);
			foreach ($properties as $prop => $settings)
			{
				if (array_key_exists($prop, $data))
				{
					$this->_data[$prop] = $data[$prop];
				}
				elseif (array_key_exists('default', $settings))
				{
					$this->_data[$prop] = $settings['default'];
				}
			}
		}
		else
		{
			$this->_update_original($data);
			$this->_data = array_merge($this->_data, $data);

			if ($view and array_key_exists($view, $this->views()))
			{
				$this->_view = $view;
			}
		}

		if ($new === false)
		{
			static::$_cached_objects[get_class($this)][static::implode_pk($data)] = $this;
			$this->_is_new = false;
		}
	}
	
	/**
	 * Fetch a property
	 *
	 * @param   string
	 * @return  mixed
	 */
	public function & __get($property)
	{
		return $this->get($property);
	}

	/**
	 * Set a property
	 *
	 * @param  string
	 * @param  mixed
	 */
	public function __set($property, $value)
	{
		return $this->set($property, $value);
	}

	/**
	 * Check whether a property exists, only return true for table columns
	 *
	 * @param   string  $property
	 * @return  bool
	 */
	public function __isset($property)
	{
		if (array_key_exists($property, static::properties()))
		{
			return true;
		}

		return false;
	}

	/**
	 * Empty a property
	 *
	 * @param   string  $property
	 */
	public function __unset($property)
	{
		if (array_key_exists($property, static::properties()))
		{
			$this->_data[$property] = null;
		}
	}

	/**
	 * Get
	 *
	 * Gets a property or relation from the object
	 *
	 * @access  public
	 * @param   string  $property
	 * @return  mixed
	 */
	public function & get($property)
	{
		if (array_key_exists($property, static::properties()))
		{
			if ( ! array_key_exists($property, $this->_data))
			{
				$this->_data[$property] = null;
			}

			return $this->_data[$property];
		}
		elseif ($this->_view and in_array($property, static::$_views_cached[get_class($this)][$this->_view]['columns']))
		{
			return $this->_data[$property];
		}
		else
		{
			throw new \OutOfBoundsException('Property "'.$property.'" not found for '.get_called_class().'.');
		}
	}

	/**
	 * Set
	 *
	 * Sets a property of the object
	 *
	 * @access  public
	 * @param   string  $property
	 * @param   string  $value
	 * @return  Orm\Model
	 */
	public function set($property, $value)
	{
		if ($this->_frozen)
		{
			throw new FrozenObject('No changes allowed.');
		}

		if (in_array($property, static::primary_key()) and $this->{$property} !== null)
		{
			throw new \FuelException('Primary key cannot be changed.');
		}
		if (array_key_exists($property, static::properties()))
		{
			$this->_data[$property] = $value;
		}
		else
		{
			throw new \OutOfBoundsException('Property "'.$property.'" not found for '.get_called_class().'.');
		}
		return $this;
	}

	/**
	 * Values
	 *
	 * Short way of setting the values for the object as opposed to setting each
	 * one individually
	 *
	 * @access  public
	 * @param   array  $values
	 * @return  Orm\Model
	 */
	public function values(Array $data)
	{
		foreach ($data as $property => $value)
		{
			$this->set($property, $value);
		}

		return $this;
	}
  
  /**
	 * Implementation of ArrayAccess
	 */

	public function offsetSet($offset, $value)
	{
		try
		{
			$this->__set($offset, $value);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}

	public function offsetUnset($offset)
	{
		$this->__unset($offset);
	}

	public function offsetGet($offset)
	{
		try
		{
			return $this->__get($offset);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Implementation of Iterable
	 */

	protected $_iterable = array();

	public function rewind()
	{
		$this->_iterable = array_merge($this->_data, $this->_data_relations);
		reset($this->_iterable);
	}

	public function current()
	{
		return current($this->_iterable);
	}

	public function key()
	{
		return key($this->_iterable);
	}

	public function next()
	{
		return next($this->_iterable);
	}

	public function valid()
	{
		return key($this->_iterable) !== null;
	}
}


?>