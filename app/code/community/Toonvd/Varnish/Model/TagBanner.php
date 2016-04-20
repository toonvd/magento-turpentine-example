<?php

/**
 * Class Toonvd_Varnish_Model_TagBanner
 */
class Toonvd_Varnish_Model_TagBanner extends Mage_Core_Model_Abstract
{

    /**
     * @var array
     */
    protected $objectsToBan;

    /**
     * @var
     */
    protected $isObjectNew;

    /**
     * @var array
     */
    protected $tagPrefixes;

    /**
     *
     */
    public function __construct()
    {
        $this->objectsToBan = get_object_vars(Mage::getConfig()->getNode('varnish/objects_to_ban')->children());
        $this->tagPrefixes = get_object_vars(Mage::getConfig()->getNode('varnish/tag_prefixes')->children());
    }

    /**
     * @param $observer
     * @param $isObjectNew
     * @return $this
     */
    public function banTags($observer, $isObjectNew)
    {
        $this->isObjectNew = $isObjectNew;
        $object = $observer->getEvent()->getObject();
        $className = strtolower(get_class($object));
        if (array_key_exists($className, $this->objectsToBan)) {
            $options = $this->objectsToBan[$className];
            $prefix = (string)$this->tagPrefixes[$className];
            $this->banByHeader('obj.http.X-Object-Tags', $prefix . $object->getId());
            if (isset($options->callback)) {
                call_user_func(
                    array((string)$options->callback->class, (string)$options->callback->function),
                    array($object, $prefix)
                );
            }
        }

        return $this;
    }

    /**
     * @param $params
     * @return $this
     */
    public function categoryBanCallback($params)
    {
        $category = $params[0];
        if ($this->isObjectNew &&
            (bool)$category->getIncludeInMenu() &&
            (bool)$category->getIsActive()
        ) {
            $this->banByHeader('obj.http.X-Turpentine-Block', 'catalog.topnav');
        } elseif ((bool)$category->hasDataChanges()) {
            $name = $category->getData('name');
            $origName = $category->getOrigData('name');
            if ($name != $origName) {
                $this->banByHeader('obj.http.X-Turpentine-Block', 'catalog.topnav');
            }
        }

        return $this;
    }

    /**
     * @param $params
     * @return $this
     */
    public function productBanCallback($params)
    {
        $product = $params[0];
        if ($this->isObjectNew &&
            (bool)$product->getStatus() &&
            (bool)$product->hasCategoryIds()
        ) {
            foreach ($product->getCategoryIds() as $categoryId) {
                $this->banByHeader('obj.http.X-Object-Tags', 'C' . $categoryId);
            }
        }

        return $this;
    }

    /**
     * @param $header
     * @param $contents
     * @return $this
     */
    protected function banByHeader($header, $contents)
    {
        $errors = '';
        foreach (Mage::helper('turpentine/varnish')->getSockets() as $socket) {
            $socketName = $socket->getConnectionString();
            try {
                $socket->ban($header, '~', $contents);
            } catch (Mage_Core_Exception $e) {
                $errors .= 'Error for socketName: ' . $socketName . ' ' . $e->getMessage() . PHP_EOL;
                continue;
            }
        }
        if ($errors != '') {
            Mage::getSingleton('adminhtml/session')->addError($errors);
        }

        return $this;
    }
}