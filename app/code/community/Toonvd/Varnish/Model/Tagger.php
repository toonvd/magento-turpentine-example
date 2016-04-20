<?php

/**
 * Class Toonvd_Varnish_Model_Tagger
 */
class Toonvd_Varnish_Model_Tagger extends Mage_Core_Model_Abstract
{
    /**
     * @var array
     */
    protected $blocksToProcess;

    /**
     * @var array
     */
    protected $tagPrefixes;

    /**
     * Create blocks to process
     */
    public function __construct()
    {
        $this->blocksToProcess = get_object_vars(Mage::getConfig()->getNode('varnish/objects_to_tag')->children());
        $this->tagPrefixes = get_object_vars(Mage::getConfig()->getNode('varnish/tag_prefixes')->children());
    }

    /**
     * @param $block
     * @return array|string
     */
    public function processBlockForTags($block)
    {
        $className = strtolower(get_class($block));
        if (array_key_exists($className, $this->blocksToProcess)) {
            return $this->processLoadedObjects($block, $className);
        }

        return array();
    }

    /**
     * @param $block
     * @param $className
     * @return array|string
     */
    protected function processLoadedObjects($block, $className)
    {
        $tags = array();
        $options = $this->blocksToProcess[$className];
        $loadedObject = $this->getObjectToProcess($block, (string)$options->function_to_call);

        if ((string)$options->loop == '1' && $loadedObject->getSize() > 0) {
            $objectClass = null;
            foreach ($loadedObject as $object) {
                if (!$objectClass) {
                    $objectClass = strtolower(get_class($object));
                }
                $tags[] = (string)$this->tagPrefixes[$objectClass] . $object->getId();
            }
        } elseif (is_object($loadedObject) && (string)$options->loop == '0') {
            $objectClass = strtolower(get_class($loadedObject));
            $tags[] = (string)$this->tagPrefixes[$objectClass] . $loadedObject->getId();
        } else{
            if(is_numeric($loadedObject)){
                $tags[] = "Blck" . $loadedObject;
            }
        }




        return $tags;
    }

    /**
     * @param $block
     * @param $functionName
     * @return mixed
     */
    protected function getObjectToProcess($block, $functionName)
    {
        return call_user_func(array($block, $functionName));
    }
}