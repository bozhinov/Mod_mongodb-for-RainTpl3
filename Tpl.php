<?php

/**
 *  RainTPL
 *  --------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under GNU/LGPL 3 License
 *
 *  @version 3.0 Alpha milestone: https://github.com/rainphp/raintpl3/issues/milestones?with_issues=no
 *
 *  mod_MongoDb 1.0 (unofficial)
 *  maintained by Momchil Bozhinov (momchil@bojinov.info)
 *  ------------
 *  - Removed plugins
 *  - Removed blacklist
 *  - Removed the option for extra tags
 *  - Removed SyntaxException
 *  - Removed autoload, replaced with a simple class include
 *  - Simplified config (see examples for usage)
 *  - Parser code somewhat reorganized
 *  - Cache is stored in MongoDb GridFS
 *  - Added 'production' option in case all templates are already in cache
 */

namespace Rain;

require_once("Parser.php");
require_once("Exceptions.php");
require_once("MongoDb.php");

class Tpl {

	// variables
	public $vars = array();

	// configuration
	protected $config = array(
		'charset' => 'UTF-8',
		'debug' => false, # will compile the template every single run
		'production' => false, # will skip udpate check and load tpl directly from db
		'tpl_dir' => 'templates/',
		'tpl_ext' => 'html',
		'php_enabled' => false,
		'auto_escape' => true,
		'remove_comments' => false
	);

	public function configure($my_conf){
		(!is_array($my_conf)) AND die("Invalid config");
		
		foreach ($my_conf as $my=>$val){
			if (isset($this->config[$my])){
				$this->config[$my] = $val;
			}
		}
		
		// Do the check here. No need to check if default
		(substr($this->config['tpl_dir'], -1) != '/') AND die("config option tpl_dir needs a trailing slash");
	}
		
	/**
	 * Count number of templates in the templates folder
	 * Used only in the Test-Production-Ready script
	 * Test-Production-Ready.php needs to be placed in the Rain folder
	 * assuming that the templates folder is a one level up
	 *
	 * @returns array
	 */
	public function countTemplates(){
			
		$tpls = array();
		$tpls['count'] = 0;
		$tpls['names'] = array();
		
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("../".$this->config['tpl_dir'])) as $fileInfo) {
			if($fileInfo->isFile()){
				$tpls['count']++;
				$tpls['names'][] = substr($fileInfo->getPath(), 3)."\\".$fileInfo->getFilename();
			}			
		}
		
		return $tpls;
	}

	/**
	 * Draw the template
	 *
	 * @param string $filePath: name of the template file or echo the output
	 *
	 */
	public function draw($filePath) {
				
		extract($this->vars);
		
		ob_start();

		// Not security wise either way
		#include 'data:text/plain,' . $html; # requires allow_url_fopen to be allowed
		eval('?>' . $this->checkTemplate($filePath));
		#echo $this->checkTemplate($filePath);
		
		echo ob_get_clean();

	}
	
	 /**
	 * Check if the template exist and compile it if necessary
	 *
	 * @param string $filePath: the path to the template file
	 *
	 * @throw \Rain\NotFoundException the file doesn't exists
	 */
	protected function checkTemplate($filePath) {
		
		$db = new Db;
		
		$filePath = $this->config['tpl_dir'] . $filePath . '.' . $this->config['tpl_ext'];
					
		// get template from Db
		list($html,$md5_stored) = $db->getTemplate($filePath);

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
				// compile the template
				$html = (new Parser)->compileFile($this->config, $filePath);
				// store the update in Db
				$db->storeTemplate($html, $filePath, $md5_current);
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
	 */
	public function assign($variable, $value = null) {
		if (is_array($variable)){
			$this->vars = $variable + $this->vars;
		} else {
			$this->vars[$variable] = $value;
		}
	}

}