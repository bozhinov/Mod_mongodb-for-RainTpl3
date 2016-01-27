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
 *  - Removed SyntaxException
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

// -- end
