<?php

/**
 *  RainTPL
 *  --------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under GNU/LGPL 3 License
 *
 *  @version 3.0 Alpha milestone: https://github.com/rainphp/raintpl3/issues/milestones?with_issues=no
 *
 *  mod_MongoDb (unofficial)
 *  maintained by Momchil Bozhinov (momchil@bojinov.info)
 *  ------------
 *  - Removed Plugins
 *  - Removed option for multiple template folders
 *  - Simplified config (see examples for usage)
 *  - Removed option for extra tags
 *  - Removed autoload, replaced with simple class include
 *  - Cache is stored in MongoDb
 *  - Added 'production' option in case all templates are already in cache
 *  - Using templates from subfolders of the templates folder is accepted (see examples for usage)
 */

namespace Rain;

require_once("Parser.php");
require_once("Exceptions.php");
require_once("MongoDb.php");

class Tpl {

    // variables
    public $var = array();

    // configuration
    protected $config = array(
        'charset' => 'UTF-8',
        'debug' => false, # will compile the template every single run
		'production' => false, # will skip udpate check and load tpl directly from db
        'tpl_dir' => 'templates/',
        'tpl_ext' => 'html',
		'base_dir' => '', # needed only for the countTemplates func. See examples
        'php_enabled' => false,
        'auto_escape' => true,
        'sandbox' => true, # required for the blacklist
        'remove_comments' => false
    );

	public function configure($my_conf){
		(!is_array($my_conf)) AND die("Invalid config");
		
		foreach ($my_conf as $my=>$val){
			if (isset($this->config[$my])){
				$this->config[$my] = $val;
			}
		}
	}
	
    /**
     * Count number of templates in the templates folder
     * Used only in the Test-Production-Ready script
     *
     * @returns array
     */
	public function countTemplates(){
		
		(strlen($this->config['base_dir']) == 0) AND die("Need to set base_dir");
		(substr($this->config['tpl_dir'], -1) != '/') AND die("config option tpl_dir needs a trailing slash");
		
		$tpls = array();
		$tpls['count'] = 0;
		$tpls['names'] = array();
		
		foreach ((new \DirectoryIterator($this->config['base_dir'].$this->config['tpl_dir'])) as $fileInfo) {
			if($fileInfo->isDot()) continue;
			$tpls['count']++;
			$tpls['names'][] = substr($fileInfo->getBasename(), 0,-1-strlen($this->config['tpl_ext'])); # this should haved worked without the substr ...
		}
		
		return $tpls;
	}

    /**
     * Draw the template
     *
     * @param string $templateFilePath: name of the template file
     * @param bool $toString: if the method should return a string
     * or echo the output
     *
     * @return void, string: depending of the $toString
     */
    public function draw($filePath) {
		
		(substr($this->config['tpl_dir'], -1) != '/') AND die("config option tpl_dir needs a trailing slash");
		
        extract($this->var);
        ob_start();

		// none of these two options is security wise
		#include 'data:text/plain,' . $html; # requires allow_url_fopen to be allowed
		eval('?>' . $this->checkTemplate($filePath));
		#echo $this->checkTemplate($filePath);
        echo ob_get_clean();

    }
	
	 /**
     * Check if the template exist and compile it if necessary
     *
     * @param string $template: name of the file of the template
     *
     * @throw \Rain\NotFoundException the file doesn't exists
     * @return string: full filepath that php must use to include
     */
    protected function checkTemplate($filePath) {
				
		$filePath = $this->config['tpl_dir'] . $filePath . '.' . $this->config['tpl_ext'];
					
        // get template from Db
		list($html,$md5_stored) = (new Db)->getTemplate($filePath);
		
		// in case of production option is true, the actual templates are not required
		// it is one step from here to removing templates after compilation
		if (!$this->config['production']){
			// For check templates are exists
			if (!file_exists($filePath)) {
				$e = new NotFoundException('Template ' . $filePath . ' not found!');
				throw $e->templateFile($filePath);
			}
			
			// Compile the template if the original has been updated
			$md5_current = md5_file($filePath);
			if ($this->config['debug'] || $md5_stored != $md5_current){
				$html = (new Parser)->compileFile($this->config, $filePath, $md5_current);
			}
		}
		
		return $html;
		
	}

    /**
     * Assign variable
     * eg.     $t->assign('name','mickey');
     *
     * @param mixed $variable Name of template variable or associative array name/value
     * @param mixed $value value assigned to this variable. Not set if variable_name is an associative array
     *
     * @return \Rain $this
     */
    public function assign($variable, $value = null) {
        if (is_array($variable)){
            $this->var = $variable + $this->var;
        } else {
            $this->var[$variable] = $value;
		}
        #return $this; # I see no reason for the return
    }

}