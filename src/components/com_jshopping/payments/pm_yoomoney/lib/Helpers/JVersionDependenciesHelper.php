<?php

namespace YooMoney\Helpers;

class JVersionDependenciesHelper
{
    private $joomlaVersion;

    public function __construct()
    {
        $this->joomlaVersion = (version_compare(JVERSION, '3.0', '<') == 1) ? 2 : 3;
        $this->joomlaVersion = (version_compare(JVERSION, '4.0', '<') == 1) ? $this->joomlaVersion : 4;
    }

    /**
     * @return int
     */
    public function getJoomlaVersion()
    {
        return $this->joomlaVersion;
    }

    public function getJsDate()
    {
        if ($this->joomlaVersion == 4) {
            return \JSHelper::getJsDate();
        }

        return getJsDate();
    }

    public function getAddonTableObj()
    {
        if (JVERSION == 4) {
            $app = \JFactory::getApplication();

            /** @var MVCFactoryInterface $factory */
            $factory = $app->bootComponent('com_jshopping')->getMVCFactory();
            return $factory->createTable('addon', 'Site');
        }
        return \JTable::getInstance('addon', 'jshop');
    }

    /**
     * @return string
     */
    public function getFilesVersionPostfix()
    {
        switch ($this->joomlaVersion) {
            case 2:
                return '2x';
            case 3:
                return '3x';
            default:
                return '';
        }
    }

    /**
     * @param string $eventName
     * @param array $listenerData
     */
    public function registerEventListener($eventName, $listenerData)
    {
        switch ($this->joomlaVersion) {
            case 2:
            case 3:
                $dispatcher = \JDispatcher::getInstance();
                $dispatcher->register($eventName, $listenerData);
                break;
            default:
                \JFactory::getApplication()->getDispatcher()->addListener($eventName, $listenerData);
        }
    }

    /**
     * @param string $link
     * @return mixed
     */
    public function getSefLink($link)
    {
        if ($this->joomlaVersion == 4) {
            return \JSHelper::SEFLink($link);
        }

        return SEFLink($link);
    }
}