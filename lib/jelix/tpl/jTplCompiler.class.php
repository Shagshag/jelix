<?php
/**
* @package     jelix
* @subpackage  jtpl
* @author      Laurent Jouanneau
* @contributor Mathaud Loic (version standalone)
* @copyright   2005-2007 Laurent Jouanneau
* @copyright   2006 Mathaud Loic
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
 * This is the compiler of templates: it converts a template into a php file.
 * @package     jelix
 * @subpackage  jtpl
 */
class jTplCompiler
#ifnot JTPL_STANDALONE
    implements jISimpleCompiler {
#else
    {

    private $_locales;
#endif
    private $_literals;

    private  $_vartype = array(T_CHARACTER, T_CONSTANT_ENCAPSED_STRING, T_DNUMBER,
    T_ENCAPSED_AND_WHITESPACE, T_LNUMBER, T_OBJECT_OPERATOR, T_STRING, T_WHITESPACE,T_ARRAY);

    private  $_assignOp = array(T_AND_EQUAL, T_DIV_EQUAL, T_MINUS_EQUAL, T_MOD_EQUAL,
    T_MUL_EQUAL, T_OR_EQUAL, T_PLUS_EQUAL, T_PLUS_EQUAL, T_SL_EQUAL,
    T_SR_EQUAL, T_XOR_EQUAL);

    private  $_op = array(T_BOOLEAN_AND, T_BOOLEAN_OR, T_EMPTY, T_INC, T_DEC, T_ISSET,
    T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL,
    T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_LOGICAL_AND,
    T_LOGICAL_OR, T_LOGICAL_XOR, T_SR, T_SL, T_DOUBLE_ARROW);

    private $_inLocaleOk = array(T_STRING, T_ABSTRACT, T_AS, T_BREAK, T_CASE, T_CATCH, T_CLASS, T_CLONE,
       T_CONST, T_CONTINUE, T_DECLARE, T_DEFAULT, T_DO, T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY,
       T_EXIT, T_FINAL, T_FOR, T_FOREACH, T_FUNCTION, T_GLOBAL, T_IF, T_IMPLEMENTS, T_INSTANCEOF,
       T_INTERFACE, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR, T_NEW, T_PRIVATE, T_PUBLIC,
       T_PROTECTED, T_RETURN, T_STATIC, T_SWITCH, T_THROW, T_TRY, T_USE, T_VAR, T_WHILE,
       T_DNUMBER, T_LNUMBER);

    protected $_allowedInVar;
    protected $_allowedInExpr;
    protected $_allowedAssign;
    protected $_allowedInForeach;

    protected $_allowedConstants = array('TRUE','FALSE','NULL', 'M_1_PI', 'M_2_PI', 'M_2_SQRTPI', 'M_E', 
        'M_LN10', 'M_LN2', 'M_LOG10E', 'M_LOG2E', 'M_PI','M_PI_2','M_PI_4','M_SQRT1_2','M_SQRT2');

    private $_pluginPath=array();
    private $_metaBody = '';

    private $_modifier = array('upper'=>'strtoupper', 'lower'=>'strtolower',
        'escxml'=>'htmlspecialchars', 'strip_tags'=>'strip_tags', 'escurl'=>'rawurlencode',
        'capitalize'=>'ucwords', 'stripslashes'=>'stripslashes'
    );

    private $_blockStack=array();

    private $_sourceFile;
    private $_currentTag;
    private $_outputType;

    /**
     * Initialize some properties
     */
    function __construct(){
        $this->_allowedInVar = array_merge($this->_vartype, $this->_op);
        $this->_allowedInExpr = array_merge($this->_vartype, $this->_op);
        $this->_allowedAssign = array_merge($this->_vartype, $this->_assignOp, $this->_op);
        $this->_allowedInForeach = array_merge($this->_vartype, array(T_AS, T_DOUBLE_ARROW));

#if JTPL_STANDALONE
        require_once(JTPL_LOCALES_PATH.$GLOBALS['jTplConfig']['lang'].'.php');
        $this->_locales = $GLOBALS['jTplConfig']['locales'];
#endif
    }


#if JTPL_STANDALONE
    /**
     * Launch the compilation of a template
     *
     * Store the result (a php content) into a cache file inside the cache directory
     * @param string $tplfile the file name that contains the template
     * @return boolean true if ok
     */
    public function compile($tplFile, $outputtype){
        $this->_sourceFile = $tplFile;
        $cachefile = JTPL_CACHE_PATH . basename($tplFile);
        $this->_outputType = ($outputtype==''?'html':$outputtype);
#else
    /**
     * Launch the compilation of a template
     *
     * Store the result (a php content) into a cache file given by the selector.
     * @param jSelectorTpl $selector the template selector
     * @return boolean true if ok
     */
    public function compile($selector){
        $this->_sourceFile = $selector->getPath();
        $cachefile = $selector->getCompiledFilePath();
        $this->_outputType = $selector->outputType;
        jContext::push($selector->module);
#endif

        if(!file_exists($this->_sourceFile)){
            $this->doError0('errors.tpl.not.found');
        }

        $tplcontent = file_get_contents ( $this->_sourceFile);

        preg_match_all("!{literal}(.*?){/literal}!s", $tplcontent, $_match);

        $this->_literals = $_match[1];

        $tplcontent = preg_replace("!{literal}(.*?){/literal}!s", '{literal}', $tplcontent);

        $result = preg_replace_callback("/{((.).*?)}/s", array($this,'_callback'), $tplcontent);

        $header ="<?php \n";
        foreach($this->_pluginPath as $path=>$ok){
            $header.=' require_once(\''.$path."');\n";
        }

#if JTPL_STANDALONE
        $header.='function template_meta_'.md5($tplFile).'($t){';
#else
        $header.='function template_meta_'.md5($selector->module.'_'.$selector->resource).'($t){';
#endif
        $header .="\n".$this->_metaBody."\nreturn \$t->_meta;\n}\n";

#if JTPL_STANDALONE
        $header.='function template_'.md5($tplFile).'($t){'."\n?>";
#else
        $header.='function template_'.md5($selector->module.'_'.$selector->resource).'($t){'."\n?>";
#endif
        $result = $header.$result."<?php \n}\n?>";

        $result = preg_replace('/\?>\n?<\?php/', '', $result);
        //$result = preg_replace('/<\?php\b+\? >/', '', $result);

#if JTPL_STANDALONE
        $_dirname = dirname($cachefile);
        if (!@is_writable($_dirname)) {
            // cache_dir not writable, see if it exists
            if (!@is_dir($_dirname)) {
                trigger_error (sprintf($this->_locales['file.directory.notexists'], $_dirname), E_USER_ERROR);
                return false;
            }
            trigger_error (sprintf($this->_locales['file.directory.notwritable'], $cachefile, $_dirname), E_USER_ERROR);
            return false;
        }

        // write to tmp file, then rename it to avoid
        // file locking race condition
        $_tmp_file = tempnam($_dirname, 'wrt');

        if (!($fd = @fopen($_tmp_file, 'wb'))) {
            $_tmp_file = $_dirname . '/' . uniqid('wrt');
            if (!($fd = @fopen($_tmp_file, 'wb'))) {
                trigger_error(sprintf($this->_locales['file.write.error'], $cachefile, $_tmp_file), E_USER_ERROR);
                return false;
            }
        }

        fwrite($fd, $result);
        fclose($fd);

        // Delete the file if it already exists (this is needed on Win,
        // because it cannot overwrite files with rename()
        if (substr(PHP_OS,0,3) == 'WIN' && file_exists($cachefile)) {
            @unlink($cachefile);
        }

        @rename($_tmp_file, $cachefile);
        @chmod($cachefile, 0664);
#else
        jFile::write($cachefile, $result);

        jContext::pop();
#endif
        return true;
    }

    /**
     * function called during the parsing of the template by a preg_replace_callback function
     * It is called on each template tag {xxxx }
     * @param array $matches a matched item
     * @return string the corresponding php code of the tag (with php tag).
     */
    public function _callback($matches){
        list(,$tag, $firstcar) = $matches;

        // test du premier caractère
        if (!preg_match('/^\$|@|\*|[a-zA-Z\/]$/',$firstcar)) {
#if JTPL_STANDALONE
            trigger_error(sprintf($this->_locales['errors.tpl.tag.syntax.invalid'], $tag, $this->_sourceFile),E_USER_ERROR);
            return '';
#else
            throw new jException('jelix~errors.tpl.tag.syntax.invalid',array($tag,$this->_sourceFile));
#endif
        }
        $this->_currentTag = $tag;
        if ($firstcar == '$' || $firstcar == '@') {
            return  '<?php echo '.$this->_parseVariable($tag).'; ?>';
        } elseif ($firstcar == '*') {
            return '';
        } else {
            if (!preg_match('/^(\/?[a-zA-Z0-9_]+)(?:(?:\s+(.*))|(?:\((.*)\)))?$/',$tag,$m)) {
#if JTPL_STANDALONE
                trigger_error(sprintf($this->_locales['errors.tpl.tag.function.invalid'], $tag, $this->_sourceFile),E_USER_ERROR);
                return '';
#else
                throw new jException('jelix~errors.tpl.tag.function.invalid',array($tag,$this->_sourceFile));
#endif
            }
            if(count($m) == 4){
                $m[2] = $m[3];
            }
            if(!isset($m[2])) $m[2]='';
            if($m[1] == 'ldelim') return '{';
            if($m[1] == 'rdelim') return '}';
            return '<?php '.$this->_parseFunction($m[1],$m[2]).'?>';
        }
    }

    /**
    * analyse an "echo" tag : {$..} or {@..}
    * @param string $expr the content of the tag
    * @return string the corresponding php instruction
    */
    protected function _parseVariable($expr){
        $tok = explode('|',$expr);
        $res = $this->_parseFinal(array_shift($tok),$this->_allowedInVar);

        foreach($tok as $modifier){
            if(!preg_match('/^(\w+)(?:\:(.*))?$/',$modifier,$m)){
                $this->doError2('errors.tpl.tag.modifier.invalid',$this->_currentTag, $modifier);
                return '';
            }

            $targs=array($res);

            if( ! $path = $this->_getPlugin('modifier',$m[1])){
                if(isset($this->_modifier[$m[1]])){
                    $res = $this->_modifier[$m[1]].'('.$res.')';
                } else {
                    $this->doError2('errors.tpl.tag.modifier.unknow',$this->_currentTag, $m[1]);
                    return '';
                }
            } else {
                if(isset($m[2])){
                    $args = explode(':',$m[2]);

                    foreach($args as $arg){
                        $targs[] = $this->_parseFinal($arg,$this->_allowedInVar);
                    }
                }
                $res = 'jtpl_modifier_'.$m[1].'('.implode(',',$targs).')';
                $this->_pluginPath[$path] = true;
            }
        }
        return $res;
    }

    /**
     * analyse the tag which have a name
     * @param string $name the name of the tag
     * @param string $args the content that follow the name in the tag
     * @return string the corresponding php instructions
     */
    protected function _parseFunction($name,$args){
        $res='';
        switch($name) {
            case 'if':
                $res = 'if('.$this->_parseFinal($args,$this->_allowedInExpr).'):';
                array_push($this->_blockStack,'if');
                break;
            case 'else':
                if (end($this->_blockStack) !='if') {
                    $this->doError1('errors.tpl.tag.block.end.missing', end($this->_blockStack));
                }else
                    $res = 'else:';
                break;
            case 'elseif':
                if (end($this->_blockStack) !='if') {
                    $this->doError1('errors.tpl.tag.block.end.missing', end($this->_blockStack));
                }else
                    $res = 'elseif('.$this->_parseFinal($args,$this->_allowedInExpr).'):';
                break;
            case 'foreach':
                $res = 'foreach('.$this->_parseFinal($args,$this->_allowedInForeach, array(';','!')).'):';
                array_push($this->_blockStack,'foreach');
                break;
            case 'while':
                $res = 'while('.$this->_parseFinal($args,$this->_allowedInExpr).'):';
                array_push($this->_blockStack,'while');
                break;
            case 'for':
                $res = 'for('. $this->_parseFinal($args, $this->_allowedInExpr, array()) .'):';
                array_push($this->_blockStack,'for');
                break;

            case '/foreach':
            case '/for':
            case '/if':
            case '/while':
                $short = substr($name,1);
                if (end($this->_blockStack) !=$short) {
                    $this->doError1('errors.tpl.tag.block.end.missing', end($this->_blockStack));
                 }else{
                    array_pop($this->_blockStack);
                    $res='end'.$short.';';
                 }
                break;

            case 'assign':
                $res = $this->_parseFinal($args,$this->_allowedAssign).';';
                break;
            case 'literal':
                if (count($this->_literals)) {
                    $res = '?>'.array_shift($this->_literals).'<?php ';
                } else {
                    $this->doError1('errors.tpl.tag.block.end.missing','literal');
                }
                break;
            case '/literal':
                $this->doError1('errors.tpl.tag.block.begin.missing','literal');
                break;
            case 'meta':
                $this->_parseMeta($args);
                $res='';
                break;
            default:
                if(preg_match('!^/(\w+)$!',$name,$m)){
                    if (end($this->_blockStack) !=$m[1]) {
                        $this->doError1('errors.tpl.tag.block.end.missing',end($this->_blockStack));
                    }else{
                        array_pop($this->_blockStack);
                        $fct = 'jtpl_block_'.$m[1];
                        if(!function_exists($fct)){
                            $this->doError1('errors.tpl.tag.block.begin.missing',$m[1]);
                        }else
                            $res = $fct($this,false,null);
                    }
                }else if(preg_match('/^meta_(\w+)$/',$name,$m)){
                     if ( ! $path = $this->_getPlugin('meta',$m[1])) {
                        $this->doError1('errors.tpl.tag.meta.unknow',$m[1]);
                    }else{
                        $this->_parseMeta($args,$m[1]);
                        $this->_pluginPath[$path] = true;
                    }
                    $res='';

                }else if ( $path = $this->_getPlugin('block',$name)) {
                    require_once($path);
                    $argfct=$this->_parseFinal($args,$this->_allowedAssign, array(';'),true);
                    $fct = 'jtpl_block_'.$name;
                    $res = $fct($this,true,$argfct);
                    array_push($this->_blockStack,$name);

                }else if ( $path = $this->_getPlugin('function',$name)) {

                    $argfct=$this->_parseFinal($args,$this->_allowedAssign);
                    $res = 'jtpl_function_'.$name.'( $t'.(trim($argfct)!=''?','.$argfct:'').');';
                    $this->_pluginPath[$path] = true;

                } else {
                    $this->doError1('errors.tpl.tag.function.unknow',$name);
                }
        }
        return $res;
    }


    /**
     * sub-function which analyse an expression
     * @param string $string the expression
     * @param array $allowed list of allowed php token
     * @param array $exceptchar list of forbidden characters
     * @param boolean $splitArgIntoArray true: split the results on coma
     * @return array|string
     */
    protected function _parseFinal($string, $allowed=array(), $exceptchar=array(';'), $splitArgIntoArray=false){
        $tokens = token_get_all('<?php '.$string.'?>');

        $results=array();
        $result ='';
        $first = true;
        $inLocale = false;
        $locale='';
        $bracketcount=$sqbracketcount=0;
        $firstok = array_shift($tokens);

        // il y a un bug, parfois le premier token n'est pas T_OPEN_TAG...
        if ($firstok== '<' && $tokens[0] == '?' && is_array($tokens[1])
            && $tokens[1][0] == T_STRING && $tokens[1][1] == 'php') {
            array_shift($tokens);
            array_shift($tokens);
        }

        foreach($tokens as $tok) {
            if (is_array($tok)) {
                list($type,$str)= $tok;
                $first=false;
                if ($type== T_CLOSE_TAG) {
                    continue;
                }
                if($inLocale && in_array($type,$this->_inLocaleOk)){
                    $locale.=$str;
                }elseif($type == T_VARIABLE && $inLocale){
                    $locale.='\'.$t->_vars[\''.substr($str,1).'\'].\'';
                }elseif($type == T_VARIABLE){
                    $result.='$t->_vars[\''.substr($str,1).'\']';
                }elseif($type == T_WHITESPACE || in_array($type, $allowed)){
                    if($type == T_STRING && defined($str) && !in_array(strtoupper($str),$this->_allowedConstants)){
                        $this->doError2('errors.tpl.tag.constant.notallowed', $this->_currentTag, $str);
                    }
                    $result.=$str;
                }else{
                    $this->doError2('errors.tpl.tag.phpsyntax.invalid', $this->_currentTag, $str);
                    return '';
                }
            } else {
                if ($tok == '@') {
                    if ($inLocale) {
                        $inLocale = false;
                        if ($locale=='') {
                            $this->doError1('errors.tpl.tag.locale.invalid', $this->_currentTag);
                            return '';
                        } else {
#if JTPL_STANDALONE
                            $result.='${$GLOBALS[\'jTplConfig\'][\'localesGetter\']}(\''.$locale.'\')';
#else
                            $result.='jLocale::get(\''.$locale.'\')';
#endif
                            $locale='';
                        }
                    } else {
                        $inLocale=true;
                    }
                } elseif ($inLocale && ($tok=='.' || $tok =='~') ) {
                    $locale.=$tok;
                } elseif ($inLocale || in_array($tok,$exceptchar) || ($first && $tok !='!')) {
                    $this->doError2('errors.tpl.tag.character.invalid', $this->_currentTag, $tok);
                    return '';
                } elseif ($tok =='(') {
                    $bracketcount++;$result.=$tok;
                } elseif ($tok ==')') {
                    $bracketcount--;$result.=$tok;
                } elseif ($tok =='[') {
                    $sqbracketcount++;$result.=$tok;
                } elseif ($tok ==']') {
                    $sqbracketcount--;$result.=$tok;
                } elseif( $splitArgIntoArray && $tok ==',' && $bracketcount==0 && $sqbracketcount==0){
                   $results[]=$result;
                   $result='';
                } else {
                    $result.=$tok;
                }
                $first=false;
            }

        }

        if ($inLocale) {
            $this->doError1('errors.tpl.tag.locale.end.missing', $this->_currentTag);
        }

        if ($bracketcount != 0 || $sqbracketcount !=0) {
            $this->doError1('errors.tpl.tag.bracket.error', $this->_currentTag);
        }

        if( $splitArgIntoArray){
            if($result !='') $results[]=$result;
            return $results;
        }else{
            return $result;
        }
    }

    protected function _parseMeta($args, $fct=''){
        if(preg_match("/^(\w+)\s+(.*)$/",$args,$m)){
            $argfct=$this->_parseFinal($m[2],$this->_allowedInExpr);
            if($fct!=''){
                $this->_metaBody.= 'jtpl_meta_'.$fct.'( $t,'."'".$m[1]."',".$argfct.");\n";
            }else{
                $this->_metaBody.= "\$t->_meta['".$m[1]."']=".$argfct.";\n";
            }
        }else{
            $this->doError1('errors.tpl.tag.meta.invalid', $this->_currentTag);
        }
    }

    /**
     * try to find a plugin
     * @param string $type type of plugin (function, modifier...)
     * @param string $name the plugin name
     * @return string the path of the plugin, or '' if not found
     */
    protected function _getPlugin($type, $name){
        $foundPath='';

#if JTPL_STANDALONE
        if(isset($GLOBALS['jTplConfig']['tplpluginsPathList'][$this->_outputType])){
            foreach($GLOBALS['jTplConfig']['tplpluginsPathList'][$this->_outputType] as $path){
#else
        global $gJConfig;
        if(isset($gJConfig->{'_tplpluginsPathList_'.$this->_outputType})){
            foreach($gJConfig->{'_tplpluginsPathList_'.$this->_outputType} as $path){
#endif
                $foundPath=$path.$type.'.'.$name.'.php';

                if(file_exists($foundPath)){
                    return $foundPath;
                }
            }
        }
#if JTPL_STANDALONE
        if(isset($GLOBALS['jTplConfig']['tplpluginsPathList']['common'])){
            foreach($GLOBALS['jTplConfig']['tplpluginsPathList']['common'] as $path){
#else
        if(isset($gJConfig->_tplpluginsPathList_common)){
            foreach($gJConfig->_tplpluginsPathList_common as $path){
#endif
                $foundPath=$path.$type.'.'.$name.'.php';
                if(file_exists($foundPath)){
                    return $foundPath;
                }
            }
        }
        return '';
    }

    public function doError0($err){
#if JTPL_STANDALONE
        trigger_error(sprintf($this->_locales[$err], $this->_sourceFile),E_USER_ERROR);
#else
        throw new jException('jelix~'.$err,array($this->_sourceFile));
#endif
    }

    public function doError1($err, $arg){
#if JTPL_STANDALONE
        trigger_error(sprintf($this->_locales[$err], $arg, $this->_sourceFile),E_USER_ERROR);
#else
        throw new jException('jelix~'.$err,array($arg, $this->_sourceFile));
#endif
    }

    public function doError2($err, $arg1, $arg2){
#if JTPL_STANDALONE
        trigger_error(sprintf($this->_locales[$err], $arg1, $arg2, $this->_sourceFile),E_USER_ERROR);
#else
        throw new jException('jelix~'.$err,array($arg1, $arg2, $this->_sourceFile));
#endif
    }

}



#if DEBUGJTPL

function showtokens($arr){

echo '<table border="1" style="font-size:0.7em">';
foreach($arr as $tok){

   if(is_array($tok)){
      echo '<tr><td>',token_name($tok[0]), '</td><td>',htmlspecialchars($tok[1]),"</td></tr>\n";
   }else
      echo '<tr><td colspan="2">',$tok, "</td></tr>\n";

}
echo '</table><hr/>';

}

function showtoken($tok){

echo '<table border="1" style="font-size:0.7em">';
   if(is_array($tok)){
      echo '<tr><td>',token_name($tok[0]), '</td><td>',htmlspecialchars($tok[1]),"</td></tr>\n";
   }else
      echo '<tr><td colspan="2">',$tok, "</td></tr>\n";
echo '</table><hr/>';

}

#endif

?>
