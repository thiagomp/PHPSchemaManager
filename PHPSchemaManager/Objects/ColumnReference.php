<?php
namespace PHPSchemaManager\Objects;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ColumnReference
 *
 * @author thiago
 */
class ColumnReference extends Objects implements ObjectEventsInterface
{
    const RESTRICT  = 'restrict';
    const CASCADE  = 'cascade';
    const SETNULL = 'setnull';
    const NOACTION = 'noaction';

    protected $actionOnUpdate;
    protected $actionOnDelete;

    public function __construct($referenceName)
    {
        $this->setName($referenceName);
        $this->actionOnUpdate(self::CASCADE);
        $this->actionOnDelete(self::CASCADE);
    }

    public function actionOnUpdate($referenceOption = self::NOACTION)
    {
        $this->setAction(__FUNCTION__, $referenceOption);
    }

    public function actionOnDelete($referenceOption = self::NOACTION)
    {
        $this->setAction(__FUNCTION__, $referenceOption);
    }

    protected function setAction($action, $referenceOption)
    {
        if ($this->isReferenceOptionsValid($referenceOption)) {
            $this->{$action} = $referenceOption;
        }
    }

    /**
     *
     * @return string
     */
    public function getActionOnUpdate()
    {
        return $this->actionOnUpdate;
    }

    /**
     *
     * @return string
     */
    public function getActionOnDelete()
    {
        return $this->actionOnDelete;
    }


    protected function isReferenceOptionsValid($option) {
        if (FALSE !== array_search($option, array(self::CASCADE, self::NOACTION, self::RESTRICT, self::SETNULL))) {
            return TRUE;
        }
        return FALSE;
    }

    public function onDelete()
    {
        $this->getFather()->markForAlter();
    }

    public function onDestroy()
    {
        //do nothing
    }
}
