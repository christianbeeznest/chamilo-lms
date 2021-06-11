<?php
/* For license terms, see /license.txt */

/**
 * Description of Lti1p3Demo
 *
 * @author Christian Beeznest
 */
class Lti1p3DemoPlugin extends Plugin
{
    protected function __construct()
    {
        $version = '1.0';
        $author = 'Christian Beeznest';

        parent::__construct($version, $author);

    }
    
    /**
     * Get the class instance
     * @staticvar Lti1p3Plugin $result
     * @return Lti1p3Plugin
     */
    public static function create()
    {
        static $result = null;

        return $result ?: $result = new self();
    }

    /**
     * Get the plugin directory name
     */
    public function get_name()
    {
        return 'lti1p3_demo';
    }
    
    /**
     * Save configuration for plugin.
     *
     * Generate a new key pair for platform when enabling plugin.
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @return $this|Plugin
     */
    public function performActionsAfterConfigure()
    {

        if (isset($_REQUEST['config_json'])) {              
            $config_file = api_get_path(SYS_PLUGIN_PATH).'lti1p3_demo/db/configs/local.json';            
            if (!is_writeable($config_file)) {
                chmod($config_file, 0775);
            }            
            $fp = fopen($config_file, 'w');
            fwrite($fp, $_REQUEST['config_json']);
            fclose($fp);
        }

        return $this;
    }
}

