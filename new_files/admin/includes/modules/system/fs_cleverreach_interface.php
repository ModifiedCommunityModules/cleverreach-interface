<?php
defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

use RobinTheHood\ModifiedStdModule\Classes\StdModule;
require_once DIR_FS_DOCUMENT_ROOT . '/vendor-no-composer/autoload.php';

class fs_cleverreach_interface extends StdModule
{
    public function __construct()
    {
        $this->init('MODULE_FS_CLEVERREACH_INTERFACE');
        $this->addKey('CLIENT_ID');
        $this->addKey('USERNAME');
        $this->addKey('PASSWORD');
        $this->addKey('IMPORT_SUBSCRIBERS');
        $this->addKey('IMPORT_BUYERS');
        $this->addKey('GROUP_ID');
    }

    public function display()
    {
        return [
            'text' => '<br /><div align="center">' .    xtc_button(BUTTON_SAVE). 
                                                        xtc_button_link(BUTTON_EXPORT, xtc_href_link("../interface/fs_cleverreach_interface.php")) .
                                                        xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=fs_cleverreach_interface')) . "</div>"
        ];
    }

    public function install()
    {

        $this->addConfiguration('CLIENT_ID', '', 6, 16);
        $this->addConfiguration('USERNAME', '', 6, 17);
        $this->addConfiguration('PASSWORD', '', 6, 18);
        $this->addConfiguration('IMPORT_SUBSCRIBERS', 'true', 6, 19, 'xtc_cfg_select_option(array(\'true\', \'false\'),');
        $this->addConfiguration('IMPORT_BUYERS', 'false', 6, 20, 'xtc_cfg_select_option(array(\'true\', \'false\'),');
        $this->addConfiguration('GROUP_ID', '', 6, 21);
        parent::install();
    }

    public function remove()
    {
        parent::remove();
        $this->deleteConfiguration('CLIENT_ID');
        $this->deleteConfiguration('USERNAME');
        $this->deleteConfiguration('PASSWORD');
        $this->deleteConfiguration('IMPORT_SUBSCRIBERS');
        $this->deleteConfiguration('IMPORT_BUYERS');
        $this->deleteConfiguration('GROUP_ID');
    }
}