<?php

/**
 *  RainTPL
 *  --------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under GNU/LGPL 3 License
 *
 *  @version 3.0 Alpha milestone: https://github.com/rainphp/raintpl3/issues/milestones?with_issues=no
 */

namespace Rain;

/**
 * Basic Rain tpl exception.
 */
class Exception extends \Exception {

    /**
     * Path of template file with error.
     */
    protected $templateFile = '';

    /**
     * Handles path of template file with error.
     *
     * @param string | null $templateFile
     * @return \Rain\Tpl_Exception | string
     */  	
    public function templateFile($templateFile){
        if(is_null($templateFile))
            return $this->templateFile;

        $this->templateFile = (string) $templateFile;
        return $this;
    }
}

/**
 * Exception thrown when template file does not exists.
 */
class NotFoundException extends Exception {
    
}

/**
 * Exception thrown when syntax error occurs.
 */
class SyntaxException extends Exception {

    /**
     * Line in template file where error has occured.
     *
     * @var int | null
     */
    protected $templateLine = null;

    /**
     * Tag which caused an error.
     *
     * @var string | null
     */
    protected $tag = null;

    /**
     * Handles the line in template file
     * where error has occured
     * 
     * @param int | null $line
     *  	
     * @return \Rain\SyntaxException | int | null
     */
    public function templateLine($line){
        if(is_null($line))
            return $this->templateLine;

        $this->templateLine = (int) $line;
        return $this;
    }
  	
    /**
     * Handles the tag which caused an error.
     *
     * @param string | null $tag
     *
     * @return \Rain\Tpl_SyntaxException | string | null
     */	  	
    public function tag($tag=null){
        if(is_null($tag))
            return $this->tag;

        $this->tag = (string) $tag;
        return $this;
     }
}

// -- end
