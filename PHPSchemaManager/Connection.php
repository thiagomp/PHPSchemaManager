<?php
namespace PHPSchemaManager;

class Connection {
  
  protected $data;
  
  function __construct($connectionName = 'default') {
    $this->data = array(
      'dbms'           => null,
      'username'       => null,
      'password'       => null,
      'hostname'       => null,
      'port'           => null,
      'driver'         => null,
      'connectionName' => $connectionName,
    );
  }
  
  function __set($name, $value) {
    if (array_key_exists($name, $this->data)) {

      $this->data[$name] = $value;
      
    }
    else {    
      
      $trace = debug_backtrace();
      trigger_error(
        'Undefined property via __set(): *' . $name . '*' . 
        ' in ' . $trace[0]['file'] .
        ' on line ' . $trace[0]['line'],
        E_USER_NOTICE
      );
      
      return null;
    }
  }
  
  function __get($name) {
    if (array_key_exists($name, $this->data)) {
      
      return $this->data[$name];
      
    }
      
    $trace = debug_backtrace();
    trigger_error(
      'Undefined property via __get(): *' . $name . '*' .
      ' in ' . $trace[0]['file'] .
      ' on line ' . $trace[0]['line'],
      E_USER_NOTICE
    );
    
    return null;

  }
  
  public function __toString() {
    return $this->data['connectionName'];
  }
}