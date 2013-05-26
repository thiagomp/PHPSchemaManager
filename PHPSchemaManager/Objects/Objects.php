<?php
namespace PHPSchemaManager\Objects;

/**
 * State scheme for Objects
 * 
 *     -------------------------------------------- 
 *     |                                          |
 *     x                                          |
 * ----------       ----------                    |
 * | synced | <---- | create |                    |
 * ----------       ----------                    |
 *   |   ^  \                                     |
 *   |   |   ----------                           |
 *   |   |            |                           |
 *   v   |            v                           |
 * ---------       ----------       -----------   |
 * | alter | ----> | delete | ----> | deleted | ---
 * ---------       ----------       -----------
 *
 * @author thiago
 */
class Objects {

  const ACTIONALTER   = 'alter';
  const ACTIONCREATE  = 'create';
  const ACTIONDELETE  = 'delete';
  const STATUSSYNCED  = 'synced';
  const STATUSDELETED = 'deleted';
  
  protected $objectName = '';
  protected $action = self::ACTIONCREATE;
  protected $listeners;
  protected $caseSentiveNames = FALSE;
  protected $ignoreDeleted = FALSE;


  /* @var $father \PHPSchemaManager\Objects\Objects */
  protected $father = NULL;
  
  public function markForAlter() {
    if ($this->isSynced()) {
      $this->setAction(self::ACTIONALTER);
      
      // in case this object have a father, inform him about the change
      if ($father = $this->getFather()) {
        $father->informChange($this);
      }
    }
  }
  
  public function markForCreation() {
    if ($this->shouldCreate()) {
      $this->setAction(self::ACTIONCREATE);
    }
  }
  
  public function markForDeletion() {
    if ($this->isSynced() || $this->shouldAlter()) {
      $this->setAction(self::ACTIONDELETE);
      
      // trigger the onDelete method, in case the object implements it
      if ($this instanceof iObjectEvents) {
        $this->onDelete();
      }
    }
  }
  
  public function markAsSynced() {
    if (!$this->shouldDelete() || !$this->isDeleted()) {
      $this->setAction(self::STATUSSYNCED);
    }
  }
  
  public function markAsDeleted() {
    if ($this->shouldDelete()) {
      $this->setAction(self::STATUSDELETED);      
    }
  }
  
  public function shouldAlter() {
    return self::ACTIONALTER == $this->getAction() ? TRUE : FALSE;
  }

  public function shouldCreate() {
    return self::ACTIONCREATE == $this->getAction() ? TRUE : FALSE;
  }

  public function shouldDelete() {
    return self::ACTIONDELETE == $this->getAction() ? TRUE : FALSE;
  }

  public function isSynced() {
    return self::STATUSSYNCED == $this->getAction() ? TRUE : FALSE;
  }
  
  public function isDeleted() {
    return self::STATUSDELETED == $this->getAction() ? TRUE : FALSE;
  }
  
  public function persisted() {
    $this->markAsSynced();
  }
  
  /**
   * Mark this object to be dropped on the next flush
   */
  public function drop() {
    $this->markForDeletion();
  }
  
  public function destroy() {
    if (!$this->isDeleted()) {
      $this->shouldDelete();
      $this->requestFlush();
    }

    // trigger the onDestroy method, in case the object implements it
    if ($this instanceof iObjectEvents) {
      $this->onDestroy();
    }
    
    $this->getFather()->informDeletion($this);
  }
  
  public function getAction() {
    return $this->action;
  }

  public function turnCaseSensitiveNamesOn() {
    $this->caseSentiveNames = TRUE;
  }
  
  public function turnCaseSensitiveNamesOff() {
    $this->caseSentiveNames = FALSE;
  }
  
  public function isCaseSensitiveNamesOn() {
    if (!($this instanceof Manager)) {
      if ($father = $this->getFather()) {
        $father->isCaseSensitiveNamesOn();
      }      
    }

    return $this->caseSentiveNames;
  }
  
  public function setFather($father) {
    $this->father = $father;
  }
  
  /**
   * 
   * @return \PHPSchemaManager\Objects\Objects
   */
  public function getFather() {
    return $this->father;
  }
  
  public function getName() {
    return $this->objectName;
  }
  
  public function nameCompare($name) {
    if ($this->isCaseSensitiveNamesOn()) {
      return $this->getName() == $name;
    }
    
    return strtolower($this->getName()) == strtolower($name);
  }
  
  /**
   * Inform what should be done with this table when flush() is called
   * 
   * @param string $action
   * @throws \PHPSchemaManager\Exceptions\TableException
   */
  protected function setAction($action) {
    $expectedActions = array(self::ACTIONALTER, self::ACTIONCREATE, self::ACTIONDELETE, self::STATUSSYNCED, self::STATUSDELETED);
    
    if (!FALSE !== array_search($action, $expectedActions)) {
      $this->action = $action;
    }
    else {
      throw new \PHPSchemaManager\Exceptions\ObjectsException("Action $action is not recognized.");
    }
  }
  
  protected function setName($name) {
    $this->objectName = (string)$name;
  }
  
  protected function requestFlush() {
    $father = $this->getFather();
    if ($father instanceof Manager) {
      $father->flush();
    }
    else {
      $father->requestFlush();
    }
  }
  
  protected function getClassName() {
    $className = get_class($this);
    if ($lastNsPos = strripos($className, '\\')) {
      $className = substr($className, $lastNsPos + 1);
    }
    return $className;
  }
  
  protected function ignoreDeleted() {
    $this->ignoreDeleted = TRUE;
  }
  
  protected function regardDeleted() {
    $this->ignoreDeleted = FALSE;
  }
  
  protected function shouldIgnoreDeleted() {
    return $this->ignoreDeleted;
  }
}
