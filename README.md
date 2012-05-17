fuel-cassandra
==============

[Apache Cassandra](http://cassandra.apache.org) package for Fuel.
It includes a PHP client library for [Apache Cassandra](http://cassandra.apache.org) : phpcassa as a submodule.

## Cassandra
The [Apache Cassandra Project](http://cassandra.apache.org) develops a highly scalable second-generation distributed database, bringing together Dynamo's fully distributed design and Bigtable's ColumnFamily-based data model.

## Quick Start

### Add package in your FuelPHP application/website

Get this package in you app

    $ cd MYAPP/
    $ git submodule add git://github.com/ooXavier/fuel-cassandra.git fuel/packages/cassandra
    $ git submodule init
    $ git submodule update
    $ cd fuel/packages/cassandra/
    $ git submodule init
    $ git submodule update
    $ cd ../../..
    
Then simply add `cassandra` to your config.php `always_loaded.packages` config option.

    'always_load'  => array(
        'packages'  => array(
            // ...
        'cassandra',
        ),
        // ...
    
### Set-up your Apache Cassandra server(s) connection

Copy config file model into your application

    $ cp fuel/packages/cassandra/config/cassandra.php fuel/app/config/
    
Edit the file to set-up the connection to your cassandra server installation. Example :

    <?php
    return array(
        'path' => 'phpcassa',
        'cassandra' => array(
            'default' => array(
                'keyspace'  => 'CineMQstaging1',
                'servers' => array (
                    '127.0.0.1:9160',
                )
            )
        ),
    );

## Usage
    
### Establishing a connection

    // Get a an new instance of configured nodes on a defined keyspace
    $cass = Cassandra::instance('default');
  
### Creating a Keyspace

    // Creating a simple keyspace with replication factor 1
    $cass->

### Creating a Column Family

    // Creating a column family with a single validated column
    $cass->
    
    // Create an index on the name
    $cass->

### Inserting into a Column Family

    // Insert with FuelPHP DB Query
    $query = DB::insert('users', array('KEY', 'email'))->values(array('toto', 'toto@perdu.com'));
    $cass->
  
### Updating a Column Family

    // Update
    $cass->
  
### Selecting from a Column Family

    // Select all
    $cass->
    
    // Select just one user by id
    $cass->
    
    // Select just one user by indexed column
    $cass->
  
### Deleting from a Column Family

    // Delete the swarthy bastard Kevin
    $cass->