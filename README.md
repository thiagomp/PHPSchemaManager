# PHPSchemaManager

PHPSchemaManager is a library that helps you to write simple (yet powerful) code to manage MySQL databases.
PHPSchemaManager tries to use a simple language to perform the desired taks and transformations in the schemas.
This library is in its very beginning and will evolve to support other DBMSs too.
In case you find an issue, please report it or send a Pull Request with the fix.

## Getting Started

### System Requirements

You will need **PHP >= 5.3.0**.
These are the required PHP libraries:
* mbstring
* mysql

**MySQL >= 5** is needed.

### Simple Tutorial

Include the PHPSchemaManager library:
```php
<?php
require('PHPSchemaManager\PHPSchemaManager.php');
```

Register the PHPSchemaManager's autoloader:
```php
<?php
\PHPSchemaManager\PHPSchemaManager::registerAutoload();
```

Setup the connection to your database (change the parameters to connect in your server)
```php
<?php
// get the connection object
$connection = new \PHPSchemaManager\Connection();

// configure how to connect in the server
$connection->dbms = 'mysql';
$connection->username = 'username';
$connection->password = 'pasword';
$connection->hostname = '127.0.0.1';
$connection->port = '3306';
```

Get the manager instance
```php
<?php
$manager = \PHPSchemaManager\PHPSchemaManager::getManager($connection);
```

Print tables from a Database in the screen (change the table name to you that exists in your database)
```php
<?php
echo $manager->hasSchema('test')->printTxt();
```

You should see something like this
```
Tables from test (1 tables found) [synced]
table1 [synced]
  id: serial(10), NOT NULL,  [synced]
  columnA: varchar(20), NULL, 'NULL' [synced]
  columnB: char(5), NULL, 'NULL' [synced]
  columnC: timestamp(0), NOT NULL, CURRENT_TIMESTAMP [synced]
  ............................
  indexes
  PRIMARY: pk (id) [synced]
------------------------------
```

## TODO

Items to be developed. Not necessarily in this order.  

[] Create a driver to connect with mysqli extension  
[] Create a driver to connect with PDO  
[] Integrate with sqlite  
[] Integrate with PostgreSQL  
[] Get information from [Constraints](http://dev.mysql.com/doc/refman/5.0/en/create-table-foreign-keys.html)  

## Documentation

You can get more info in the [PHPSchemaManager's Wiki](https://github.com/thiagomp/PHPSchemaManager/wiki/Documentation)

## License

The PHPSchemaManager library is released under the MIT public license:

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.