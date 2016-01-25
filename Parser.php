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
	public $templateFilepath;
	public $config;
	
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
	* @param string $templateName: name with out the ext of the template to be compiled
	* @param string $templateFilepath: full path to the template to be compiled
	* @param string $md5_current: MD5 checksum of the template to be compiled
	 */
  
    public function compileFile($config, $templateName, $templateFilepath, $md5_current) {
		
		$this->config = $config;
		$this->templateFilepath = $templateFilepath;

		// read the file // store second copy in the class var for the blacklist for what ever reason
		$this->code = $parsedCode = file_get_contents($this->templateFilepath);

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
		
		$parsedCode = $this->compileTemplate($parsedCode);
		$parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $parsedCode;

		// fix the php-eating-newline-after-closing-tag-problem
		$parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

		(new Db)->storeTemplate($parsedCode, $templateName, $md5_current);
		
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
            list( $split, $match ) = $tagArray;
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
        $openIf = $loopLevel = 0;

        // if the template is not empty
        if ($codeSplit)

            //read all parsed code
            foreach ($codeSplit as $html) {

                //close ignore tag
                if (!$commentIsOpen && preg_match($tagMatch['ignore_close'], $html))
                    $ignoreIsOpen = FALSE;

                //code between tag ignore id deleted
                elseif ($ignoreIsOpen) {
                    //ignore the code
                }

                //close no parse tag
                elseif (preg_match($tagMatch['noparse_close'], $html))
                    $commentIsOpen = FALSE;

                //code between tag noparse is not compiled
                elseif ($commentIsOpen)
                    $parsedCode .= $html;

                //ignore
                elseif (preg_match($tagMatch['ignore'], $html))
                    $ignoreIsOpen = TRUE;

                //noparse
                elseif (preg_match($tagMatch['noparse'], $html))
                    $commentIsOpen = TRUE;

                //include tag
                elseif (preg_match($tagMatch['include'], $html, $matches)) {

                    //get the folder of the actual template
                    $actualFolder = $this->config['tpl_dir'];

                    //get the included template
                    if (strpos($matches[1], '$') !== false) {
                        $includeTemplate = "'$actualFolder'." . $this->varReplace($matches[1], $loopLevel);
                    } else {
                        $includeTemplate = $actualFolder . $this->varReplace($matches[1], $loopLevel);
                    }

                    // reduce the path
                    $includeTemplate = Parser::reducePath( $includeTemplate );

                    if (strpos($matches[1], '$') !== false) {
                        //dynamic include
                        $parsedCode .= '<?php require $this->checkTemplate(' . $includeTemplate . ');?>';

                    } else {
                        //dynamic include
                        $parsedCode .= '<?php require $this->checkTemplate("' . $includeTemplate . '");?>';
                    }

                }

                //loop
                elseif (preg_match($tagMatch['loop'], $html, $matches)) {

                    // increase the loop counter
                    $loopLevel++;

                    //replace the variable in the loop
                    $var = $this->varReplace($matches['variable'], $loopLevel - 1, $escape = FALSE);
                    if (preg_match('#\(#', $var)) {
                        $newvar = "\$newvar{$loopLevel}";
                        $assignNewVar = "$newvar=$var;";
                    } else {
                        $newvar = $var;
                        $assignNewVar = null;
                    }

                    // check black list
                    $this->blackList($var);

                    //loop variables
                    $counter = "\$counter$loopLevel";       // count iteration

                    if (isset($matches['key']) && isset($matches['value'])) {
                        $key = $matches['key'];
                        $value = $matches['value'];
                    } elseif (isset($matches['key'])) {
                        $key = "\$key$loopLevel";               // key
                        $value = $matches['key'];
                    } else {
                        $key = "\$key$loopLevel";               // key
                        $value = "\$value$loopLevel";           // value
                    }



                    //loop code
                    $parsedCode .= "<?php $counter=-1; $assignNewVar if( isset($newvar) && ( is_array($newvar) || $newvar instanceof Traversable ) && sizeof($newvar) ) foreach( $newvar as $key => $value ){ $counter++; ?>";
                }

                //close loop tag
                elseif (preg_match($tagMatch['loop_close'], $html)) {

                    //iterator
                    $counter = "\$counter$loopLevel";

                    //decrease the loop counter
                    $loopLevel--;

                    //close loop code
                    $parsedCode .= "<?php } ?>";
                }

                //break loop tag
                elseif (preg_match($tagMatch['loop_break'], $html)) {
                    //close loop code
                    $parsedCode .= "<?php break; ?>";
                }

                //continue loop tag
                elseif (preg_match($tagMatch['loop_continue'], $html)) {
                    //close loop code
                    $parsedCode .= "<?php continue; ?>";
                }

                //if
                elseif (preg_match($tagMatch['if'], $html, $matches)) {

                    //increase open if counter (for intendation)
                    $openIf++;

                    //tag
                    $tag = $matches[0];

                    //condition attribute
                    $condition = $matches[1];

                    // check black list
                    $this->blackList($condition);

                    //variable substitution into condition (no delimiter into the condition)
                    $parsedCondition = $this->varReplace($condition, $loopLevel, $escape = FALSE);

                    //if code
                    $parsedCode .= "<?php if( $parsedCondition ){ ?>";
                }

                //elseif
                elseif (preg_match($tagMatch['elseif'], $html, $matches)) {

                    //tag
                    $tag = $matches[0];

                    //condition attribute
                    $condition = $matches[1];

                    // check black list
                    $this->blackList($condition);

                    //variable substitution into condition (no delimiter into the condition)
                    $parsedCondition = $this->varReplace($condition, $loopLevel, $escape = FALSE);

                    //elseif code
                    $parsedCode .= "<?php }elseif( $parsedCondition ){ ?>";
                }

                //else
                elseif (preg_match($tagMatch['else'], $html)) {

                    //else code
                    $parsedCode .= '<?php }else{ ?>';
                }

                //close if tag
                elseif (preg_match($tagMatch['if_close'], $html)) {

                    //decrease if counter
                    $openIf--;

                    // close if code
                    $parsedCode .= '<?php } ?>';
                }

                // autoescape off
                elseif (preg_match($tagMatch['autoescape'], $html, $matches)) {

                    // get function
                    $mode = $matches[1];
                    $this->config['auto_escape_old'] = $this->config['auto_escape'];

                    if ($mode == 'off' or $mode == 'false' or $mode == '0' or $mode == null) {
                        $this->config['auto_escape'] = false;
                    } else {
                        $this->config['auto_escape'] = true;
                    }

                }

                // autoescape on
                elseif (preg_match($tagMatch['autoescape_close'], $html, $matches)) {
                    $this->config['auto_escape'] = $this->config['auto_escape_old'];
                    unset($this->config['auto_escape_old']);
                }

                // function
                elseif (preg_match($tagMatch['function'], $html, $matches)) {

                    // get function
                    $function = $matches[1];

                    // var replace
                    if (isset($matches[2]))
                        $parsedFunction = $function . $this->varReplace($matches[2], $loopLevel, $escape = FALSE, $echo = FALSE);
                    else
                        $parsedFunction = $function . "()";

                    // check black list
                    $this->blackList($parsedFunction);

                    // function
                    $parsedCode .= "<?php echo $parsedFunction; ?>";
                }

                //ternary
                elseif (preg_match($tagMatch['ternary'], $html, $matches)) {
                    $parsedCode .= "<?php echo " . '(' . $this->varReplace($matches[1], $loopLevel, $escape = TRUE, $echo = FALSE) . '?' . $this->varReplace($matches[2], $loopLevel, $escape = TRUE, $echo = FALSE) . ':' . $this->varReplace($matches[3], $loopLevel, $escape = TRUE, $echo = FALSE) . ')' . "; ?>";
                }

                //variables
                elseif (preg_match($tagMatch['variable'], $html, $matches)) {
                    //variables substitution (es. {$title})
                    $parsedCode .= "<?php " . $this->varReplace($matches[1], $loopLevel, $escape = TRUE, $echo = TRUE) . "; ?>";
                }


                //constants
                elseif (preg_match($tagMatch['constant'], $html, $matches)) {
                    //$parsedCode .= "<?php echo " . $this->conReplace($matches[1], $loopLevel) . "; 
					//Issue recorded as: https://github.com/rainphp/raintpl3/issues/178
					$parsedCode .= "<?php echo " . $this->modifierReplace($matches[1]) . "; ?>";
					
                }

                // was registered tags
                else {
					$parsedCode .= $html;
                }
            }

		if ($openIf > 0) {
			$e = new SyntaxException("Error! You need to close an {if} tag in ".$this->templateFilepath." template");
			throw $e->templateFile($this->templateFilepath);
		}

		if ($loopLevel > 0) {
			$e = new SyntaxException("Error! You need to close the {loop} tag in ".$this->templateFilepath." template");
			throw $e->templateFile($this->templateFilepath);
		}

        return $parsedCode;
    }

    protected function varReplace($html, $loopLevel = NULL, $escape = TRUE, $echo = FALSE) {

        // change variable name if loop level
        if (!empty($loopLevel))
            $html = preg_replace(array('/(\$key)\b/', '/(\$value)\b/', '/(\$counter)\b/'), array('${1}' . $loopLevel, '${1}' . $loopLevel, '${1}' . $loopLevel), $html);

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

	// that function is called only once at line 378 and with a second parameter at that
	// good thing I m not using constants
    //protected function conReplace($html) {
    //    return $this->modifierReplace($html);
    //}

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

        if (!$this->config['sandbox'])
            return true;

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
            $e = new SyntaxException('Syntax ' . $match[0] . ' not allowed in template: ' . $this->templateFilepath . ' at line ' . $line);
            throw $e->templateFile($this->templateFilepath)
                ->tag($match[0])
                ->templateLine($line);

            return false;
        }
    }

    public static function reducePath($path){
        // reduce the path
		$path = preg_replace( "#(://(*SKIP)(*FAIL))|(/{2,})#", "/", $path);
		$path = preg_replace( "#(/\./+)#", "/", $path);
		
        while(preg_match('#\w+\.\./#', $path)) {
            $path = preg_replace('#\w+/\.\./#', '', $path);
        }
        $path = str_replace("/", DIRECTORY_SEPARATOR, $path);

        return $path;
    }
}