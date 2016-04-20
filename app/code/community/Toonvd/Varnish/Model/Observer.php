<?php

/**
 * Class Toonvd_Varnish_Model_Observer
 */
class Toonvd_Varnish_Model_Observer extends Varien_Event_Observer
{

    /**
     * @var bool
     */
    protected $isObjectNew = false;

    /**
     * @var array
     */
    protected $tagArray = array();

    /**
     * @param $observer
     * @return $this
     */
    public function coreBlockAbstractToHtmlAfter($observer)
    {

        if (!Mage::app()->getStore()->isAdmin()) {
            $newTags = Mage::getModel('toonvd_varnish/tagger')->processBlockForTags($observer->getBlock());
            if (count($newTags) > 0) {
                $this->tagArray = array_merge($this->tagArray, $newTags);
            }
        }
        return $this;
    }

    /**
     * @param $observer
     * @return $this
     */
    public function controllerActionPostDispatch($observer)
    {
        $response = $observer->getControllerAction()->getResponse();
        if (!Mage::app()->getStore()->isAdmin() &&
            $response->canSendHeaders() &&
            count($this->tagArray) > 0 &&
            Mage::helper('turpentine/varnish')->shouldResponseUseVarnish()
        ) {
            $response->setHeader('X-Object-Tags', implode(',', array_unique($this->tagArray)), true);
        }

        return $this;
    }

    public function modelSaveBefore($observer){
        if(!$observer->getEvent()->getObject()->getId()){
            $this->isObjectNew = true;
        }
    }
    /**
     * @param $observer
     * @return $this
     */
    public function modelSaveCommitAfter($observer)
    {
        Mage::getModel('toonvd_varnish/tagBanner')->banTags($observer, $this->isObjectNew);
        return $this;
    }
}