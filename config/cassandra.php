<?php

return array(
	'path' => 'phpcassa',
	
	'cassandra' => array(
		'default' => array(
			'keyspace'  => 'Test1',
			'servers' => array (
			  '127.0.0.1:9160',
  			'127.0.0.2:9160',
			)
		)
	),
);