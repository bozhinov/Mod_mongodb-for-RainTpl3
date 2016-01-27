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
 *  - Simplified config (check examples for usage)
 *  - Removed option for extra tags
 *  - Removed autoload, replaced with simple class include
 *  - Cache is stored in MongoDb
 *  - Added 'production' option in case all templates are already in cache
 *  - Using templates from subfolders of the templates folder is accepted (see examples for usage)
 */

namespace Rain;

class Parser {

    // variables
    public $var = array();
	public $filePath;
	public $config;
	
	protected $loopLevel = 0;
	protected $code;

    // tags natively supported
    protected static $tags = array(
        'loop' => array(
            '({loop.*?})',
            '/{loop="(?<variable>\${0,1}[^"]*)"(?: as (?<key>\$.*?)(?: => (?<value>\$.*?)){0,1}){0,1}}/'
        ),
        'loop_close' => array('({\/loop})', '/{\/loop}/'),
        'loop_break' => array('({break})', '/{break}/'),
        'loop_continue' => array('({continue})', '/{continue}/'),
        'if' => array('({if.*?})', '/{if="([^"]*)"}/'),
        'elseif' => array('({elseif.*?})', '/{elseif="([^"]*)"}/'),
        'else' => array('({else})', '/{else}/'),
        'if_close' => array('({\/if})', '/{\/if}/'),
        'autoescape' => array('({autoescape.*?})', '/{autoescape="([^"]*)"}/'),
        'autoescape_close' => array('({\/autoescape})', '/{\/autoescape}/'),
        'noparse' => array('({noparse})', '/{noparse}/'),
        'noparse_close' => array('({\/noparse})', '/{\/noparse}/'),
        'ignore' => array('({ignore}|{\*)', '/{ignore}|{\*/'),
        'ignore_close' => array('({\/ignore}|\*})', '/{\/ignore}|\*}/'),
        'include' => array('({include.*?})', '/{include="([^"]*)"}/'),
        'function' => array(
            '({function.*?})',
            '/{function="([a-zA-Z_][a-zA-Z_0-9\:]*)(\(.*\)){0,1}"}/'
        ),
        'ternary' => array('({.[^{?]*?\?.*?\:.*?})', '/{(.[^{?]*?)\?(.*?)\:(.*?)}/'),
        'variable' => array('({\$.*?})', '/{(\$.*?)}/'),
        'constant' => array('({#.*?})', '/{#(.*?)#{0,1}}/'),
    );

    // black list of functions and variables
    protected static $black_list = array(
        'exec', 'shell_exec', 'pcntl_exec', 'passthru', 'proc_open', 'system', 'pcntl_fork', 'php_uname',
        'phpinfo', 'popen', 'file_get_contents', 'file_put_contents', 'rmdir',
        'mkdir', 'unlink', 'highlight_contents', 'symlink',
        'apache_child_terminate', 'apache_setenv', 'define_syslog_variables',
        'escapeshellarg', 'escapeshellcmd', 'eval', 'fp', 'fput', 'highlight_file', 'ini_alter',
        'ini_get_all', 'ini_restore', 'inject_code',
        'openlog', 'passthru', 'php_uname', 'phpAds_remoteInfo',
        'phpAds_XmlRpc', 'phpAds_xmlrpcDecode', 'phpAds_xmlrpcEncode','proc_close',
        'proc_get_status', 'proc_nice', 'proc_open', 'proc_terminate',
        'syslog', 'xmlrpc_entity_decode'
    );

    /**
    * Compile the file and save it in the cache
	*
	* @param string $config: global config
	* @param string $filePath: full path to the template to be compiled
	* @param string $md5_current: MD5 checksum of the template to be compiled
	 */
  
    public function compileFile($config, $filePath, $md5_current) {
		
		$this->config = $config;
		$this->filePath = $filePath;

		// read the file // store second copy in the class var for the blacklist for what ever reason
		$this->code = $parsedCode = file_get_contents($this->filePath);

		// disable php tag // the xml code seems to be important only if the php tag is disabled.
		if (!$this->config['php_enabled']) {
			// xml substitution
			$parsedCode = preg_replace("/<\?xml(.*?)\?>/s", /*<?*/ "##XML\\1XML##", $parsedCode);

			// disable php tag
			$parsedCode = str_replace(array("<?", "?>"), array("&lt;?", "?&gt;"), $parsedCode);

			// xml re-substitution
			$parsedCode = preg_replace_callback("/##XML(.*?)XML##/s", function( $match ) {
					return "<?php echo '<?xml " . stripslashes($match[1]) . " ?>'; ?>";
				}, $parsedCode);
		}
		
		$parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $this->compileTemplate($parsedCode);

		// fix the php-eating-newline-after-closing-tag-problem
		$parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

		(new Db)->storeTemplate($parsedCode, $filePath, $md5_current);
		
		return $parsedCode;
    }

    /**
     * Compile template
     * @access protected
     *
     * @param string $code: code to compile
     */
    protected function compileTemplate($code) {

        // set tags
        foreach (static::$tags as $tag => $tagArray) {
            list($split, $match) = $tagArray;
            $tagSplit[$tag] = $split;
            $tagMatch[$tag] = $match;
        }

        //Remove comments
        if ($this->config['remove_comments']) {
            $code = preg_replace('/<!--(.*)-->/Uis', '', $code);
        }

        //split the code with the tags regexp
        $codeSplit = preg_split("/" . implode("|", $tagSplit) . "/", $code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        //variables initialization
        $parsedCode = $commentIsOpen = $ignoreIsOpen = NULL;
        $openIf = 0;

        // if the template is not empty
        if ($codeSplit)

            //read all parsed code
            foreach ($codeSplit as $html) {
				
				switch(true){
					//close ignore tag
					case (!$commentIsOpen && preg_match($tagMatch['ignore_close'], $html)): 
						$ignoreIsOpen = FALSE;
						break;
					//code between tag ignore id deleted
					case ($ignoreIsOpen): 
						break;
					//close no parse tag
					case (preg_match($tagMatch['noparse_close'], $html)):
						$commentIsOpen = FALSE;
						break;
					//code between tag noparse is not compiled
					case ($commentIsOpen):
						$parsedCode .= $html;
						break;	
					//ignore
					case (preg_match($tagMatch['ignore'], $html)):
						$ignoreIsOpen = TRUE;
						break;
					//noparse
					case (preg_match($tagMatch['noparse'], $html)):
						$commentIsOpen = TRUE;
						break;
					 //include tag
					case (preg_match($tagMatch['include'], $html, $matches)):

						// reduce the path
						$includeTemplate = $this->reducePath($this->varReplace($matches[1]));
						
						//dynamic include
						if ((strpos($matches[1], '$') !== false)) {
							$parsedCode .= '<?php echo $this->checkTemplate(' . $includeTemplate . ');?>';
						} else {
							$parsedCode .= '<?php echo $this->checkTemplate("' . $includeTemplate . '");?>';
						}
						break;
					//loop
					case(preg_match($tagMatch['loop'], $html, $matches)):
					
						//replace the variable in the loop
						$var = $this->varReplace($matches['variable'], FALSE);
					
						$this->loopLevel++; // increase the loop counter

						if (preg_match('#\(#', $var)) {
							$newvar = "\$newvar{$this->loopLevel}";
							$assignNewVar = "$newvar=$var;";
						} else {
							$newvar = $var;
							$assignNewVar = null;
						}

						$this->blackList($var); // check black list

						//loop variables
						$counter = "\$counter{$this->loopLevel}";       // count iteration

						if (isset($matches['key']) && isset($matches['value'])) {
							$key = $matches['key'];
							$value = $matches['value'];
						} elseif (isset($matches['key'])) {
							$key = "\$key{$this->loopLevel}";               // key
							$value = $matches['key'];
						} else {
							$key = "\$key{$this->loopLevel}";               // key
							$value = "\$value{$this->loopLevel}";           // value
						}

						//loop code
						$parsedCode .= "<?php $counter=-1; $assignNewVar if( isset($newvar) && ( is_array($newvar) || $newvar instanceof Traversable ) && sizeof($newvar) ) foreach( $newvar as $key => $value ){ $counter++; ?>";
						break;
					//close loop tag
					case (preg_match($tagMatch['loop_close'], $html)):
						$counter = "\$counter{$this->loopLevel}"; //iterator
						$this->loopLevel--; //decrease the loop counter
						$parsedCode .= "<?php } ?>"; //close loop code
						break;
				    //break loop tag
					case (preg_match($tagMatch['loop_break'], $html)):
						$parsedCode .= "<?php break; ?>"; //close loop code
						break;
					//continue loop tag
					case (preg_match($tagMatch['loop_continue'], $html)):
						$parsedCode .= "<?php continue; ?>"; //close loop code
						break;
					//if
					case (preg_match($tagMatch['if'], $html, $matches)):
						$openIf++; //increase open if counter (for intendation)
						$this->blackList($matches[1]); // check black list
						//variable substitution into condition (no delimiter into the condition)
						$parsedCondition = $this->varReplace($matches[1], FALSE);
						$parsedCode .= "<?php if( $parsedCondition ){ ?>"; //if code
						break;
					//elseif
					case (preg_match($tagMatch['elseif'], $html, $matches)):
						$this->blackList($matches[1]); // check black list
						//variable substitution into condition (no delimiter into the condition)
						$parsedCondition = $this->varReplace($matches[1], FALSE);
						$parsedCode .= "<?php }elseif( $parsedCondition ){ ?>"; //elseif code
						break;
					//else
					case (preg_match($tagMatch['else'], $html)):
						$parsedCode .= '<?php }else{ ?>'; //else code
						break;
					//close if tag
					case (preg_match($tagMatch['if_close'], $html)):
						$openIf--; //decrease if counter
						$parsedCode .= '<?php } ?>'; // close if code
						break;
					// autoescape off
					case (preg_match($tagMatch['autoescape'], $html, $matches)):
						$this->config['auto_escape_old'] = $this->config['auto_escape'];
						$this->config['auto_escape'] = ($matches[1] == 'off' or $matches[1] == 'false' or $matches[1] == '0' or $matches[1] == null) ? FALSE : TRUE;
						break;
					// autoescape on
					case (preg_match($tagMatch['autoescape_close'], $html, $matches)):
						$this->config['auto_escape'] = $this->config['auto_escape_old'];
						unset($this->config['auto_escape_old']);
						break;
					// function
					case (preg_match($tagMatch['function'], $html, $matches)):
						$parsedFunction = $matches[1] . ((isset($matches[2])) ? $this->varReplace($matches[2], FALSE) : "()");
						$this->blackList($parsedFunction); // check black list
						$parsedCode .= "<?php echo $parsedFunction; ?>"; // function
						break;
					//ternary
					case (preg_match($tagMatch['ternary'], $html, $matches)):
						$parsedCode .= "<?php echo " . '(' . $this->varReplace($matches[1]) . '?' . $this->varReplace($matches[2]) . ':' . $this->varReplace($matches[3]) . ')' . "; ?>";
						break;
					//variables
					case (preg_match($tagMatch['variable'], $html, $matches)):
						//variables substitution (es. {$title})
						$parsedCode .= "<?php " . $this->varReplace($matches[1], TRUE, TRUE) . "; ?>";
						break;
					//constants
					case (preg_match($tagMatch['constant'], $html, $matches)):
						//$parsedCode .= "<?php echo " . $this->conReplace($matches[1], $this->loopLevel) . "; 
						//Issue recorded as: https://github.com/rainphp/raintpl3/issues/178
						$parsedCode .= "<?php echo " . $this->modifierReplace($matches[1]) . "; ?>";
						break;
					default:
						$parsedCode .= $html;
                }

            }

		if ($openIf > 0) {
			$e = new SyntaxException("Error! You need to close an {if} tag in ".$this->filePath." template");
			throw $e->templateFile($this->filePath);
		}

		if ($this->loopLevel > 0) {
			$e = new SyntaxException("Error! You need to close the {loop} tag in ".$this->filePath." template");
			throw $e->templateFile($this->filePath);
		}

        return $parsedCode;
    }

    protected function varReplace($html, $escape = TRUE, $echo = FALSE) {

        // change variable name if loop level
        $html = preg_replace(array('/(\$key)\b/', '/(\$value)\b/', '/(\$counter)\b/'), array('${1}' . $this->loopLevel, '${1}' . $this->loopLevel, '${1}' . $this->loopLevel), $html);

        // if it is a variable
        if (preg_match_all('/(\$[a-z_A-Z][^\s]*)/', $html, $matches)) {
            // substitute . and [] with [" "]
            for ($i = 0; $i < count($matches[1]); $i++) {

                $rep = preg_replace('/\[(\${0,1}[a-zA-Z_0-9]*)\]/', '["$1"]', $matches[1][$i]);
                //$rep = preg_replace('/\.(\${0,1}[a-zA-Z_0-9]*)/', '["$1"]', $rep);
                $rep = preg_replace( '/\.(\${0,1}[a-zA-Z_0-9]*(?![a-zA-Z_0-9]*(\'|\")))/', '["$1"]', $rep );
                $html = str_replace($matches[0][$i], $rep, $html);
            }

            // update modifier
            $html = $this->modifierReplace($html);

            // if does not initialize a value, e.g. {$a = 1}
            if (!preg_match('/\$.*=.*/', $html)) {

                // escape character
                if ($this->config['auto_escape'] && $escape)
                    //$html = "htmlspecialchars( $html )";
                    $html = "htmlspecialchars( $html, ENT_COMPAT, '" . $this->config['charset'] . "', FALSE )";

                // if is an assignment it doesn't add echo
                if ($echo)
                    $html = "echo " . $html;
            }
        }

        return $html;
    }

    protected function modifierReplace($html) {

        $this->blackList($html);
        if (strpos($html,'|') !== false && substr($html,strpos($html,'|')+1,1) != "|") {
            preg_match('/([\$a-z_A-Z0-9\(\),\[\]"->]+)\|([\$a-z_A-Z0-9\(\):,\[\]"->]+)/i', $html,$result);

            $function_params = $result[1];
            $explode = explode(":",$result[2]);
            $function = $explode[0];
            $params = isset($explode[1]) ? "," . $explode[1] : null;

            $html = str_replace($result[0],$function . "(" . $function_params . "$params)",$html);

            if (strpos($html,'|') !== false && substr($html,strpos($html,'|')+1,1) != "|") {
                $html = $this->modifierReplace($html);
            }
        }

        return $html;
    }

    protected function blackList($html) {
		// The return of this function isn't really checked anywhere
		// I doubt anyone actually handles those exceptions, so by default it is better if the script dies
		
        if (!$this->config['sandbox'])
            return;

        if (empty($this->config['black_list_preg']))
            $this->config['black_list_preg'] = '#[\W\s]*' . implode('[\W\s]*|[\W\s]*', static::$black_list) . '[\W\s]*#';

        // check if the function is in the black list (or not in white list)
        if (preg_match($this->config['black_list_preg'], $html, $match)) {

            // find the line of the error
            $line = 0;
            $rows = explode("\n", $this->code);
            while (!strpos($rows[$line], $html) && $line + 1 < count($rows))
                $line++;

            // stop the execution of the script
			$e = new SyntaxException('Syntax ' . $match[0] . ' not allowed in template: ' . $this->filePath . ' at line ' . $line);
			throw $e->templateFile($this->filePath)
				->tag($match[0])
				->templateLine($line);
        }
    }

    public static function reducePath($path){
        // reduce the path
		
		$path = preg_replace(array("#(://(*SKIP)(*FAIL))|(/{2,})#", "#(/\./+)#", "#\\\#"), array("/", "/","\\\\\\"), $path);		
		
        while(preg_match('#\w+\.\./#', $path)) {
            $path = preg_replace('#\w+/\.\./#', '', $path);
        }
		
		 // the extra slash is so we can double the one after TPL_DIR
		 // this may or may not work on anything but Windows. 
		 // If it does not work on Unix just change TPL_DIR from templates/ to templates\. That should do the trick
		 // it is either that or we need to drag TPL_DIR here and then check if it was already prepended in the TPL\checkTemplate function
		$path = DIRECTORY_SEPARATOR.$path;
		
        return str_replace("/", DIRECTORY_SEPARATOR, $path);

    }
}