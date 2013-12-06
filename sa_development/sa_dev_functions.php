<?php

//GS FUNCTIONS
//

// COMPATABILITY
# Backwards Compatability for 3.0 Script Queing
function SA_dev_register_style($handle, $src, $ver){echo '<link rel="stylesheet" href="'.$src.'" type="text/css" charset="utf-8" />'."\n";}
function SA_dev_queue_style($name,$where){}
function SA_dev_register_script($handle, $src, $ver, $in_footer=FALSE){echo '<script type="text/javascript" src="'.$src.'"></script>'."\n";}
function SA_dev_queue_script($name,$where){}

function sa_get_hook_id(){
  // get hook callee from backtrace, since hooks don't identify themselves to their callouts.
  $bt = debug_backtrace();
  foreach($bt as $func){
    if($func['function'] == 'exec_action'){
      return $func['args'][0];
    }
  }
}

function sa_user_is_admin(){
  GLOBAL $USR;
    
  if (isset($USR) && $USR == get_cookie('GS_ADMIN_USERNAME')) {
    return true;
  }
}

function pageIsFrontend() {
  // the core function for this is broken until 3.2
	$path = basename(htmlentities($_SERVER['PHP_SELF'], ENT_QUOTES));
	$file = basename($path,".php");
	return $file == 'index';
}

// SUPPORTING FUNCTIONS
// 

function sa_get_path_rel($path){
  return str_replace($_SERVER['DOCUMENT_ROOT'],'',$path);
}

function get_toggleqstring($arg,$str){ // returns querystring with toggled flag
  GLOBAL $SA_DEV_GLOBALS;
  $argstr = $SA_DEV_GLOBALS[$arg] ? 0 : 1;
  return sa_dev_qstring($str,$argstr);
}

function sa_dev_qstring($arg=null,$value=null,$qstring=null){
  // add or remove arg from querystring, optional user querystring
  // null value removes arg from querystring
  $query_string = array();
  if($qstring==null) $qstring = $_SERVER['QUERY_STRING'];
  parse_str($qstring, $query_string);  
  if(!empty($arg)) $query_string[$arg] = $value;
  $new_str = http_build_query($query_string,'','&amp;');
  return $new_str;
}

function sa_getFlag($flag){ // returns bool query string flag value
  return (isset($_REQUEST[$flag]) and $_REQUEST[$flag]==1) ? true : false;
}

function byteSizeConvert($size){ // returns formatted byte string
  $unit=array('b','kb','mb','gb','tb','pb');
  return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

function arr_to_csv_line($arr) { // returns array as comma list of args
		// todo: fix handlng of object classes
    $line = array();
		foreach ($arr as $v) {
				# _debugLog($v);
				$line[] = ( is_array($v) or is_object($v) ) ? 'array(' . arr_to_csv_line($v) . ')' : '"' . str_replace('"', '""', $v) . '"';
		}
		return implode(",", $line);
}

function sa_array_index($ary,$idx){ // handles all the isset error avoidance bullshit when checking an array for a key that might not exist
  if( isset($ary) and isset($idx) and isset($ary[$idx]) ) return $ary[$idx];
}


function sa_parseFuncArgs($argstr){ // parses arguments from a functions arg string arg,arg, supports inline functions
        $entries = array();
        $filteredData = $argstr;
        $userland = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
        if (preg_match_all("/$userland\(([^)]*)\)/", $argstr, $matches)) {
            $entries = $matches[0];
            $filteredData = preg_replace("/$userland\(([^)]*)\)/", "-function-", $argstr);
        }

        $arr = array_map("trim", explode(",", $filteredData));

        if (!$entries) {
          return $arr;
        }

        $j = 0;
        foreach ($arr as $i => $entry) {
          if ($entry != "-function-") {
                  continue;
          }

          $arr[$i] = $entries[$j];
          $j++;
        }

        return $arr;
}

// PHP
//
function sa_getVarName(&$var, $scope=0){ // Gets variables name via reference, by generating unique value then searcing scope for it.
  $old = $var;
  if (($key = array_search($var = 'unique'.rand().'value', !$scope ? $GLOBALS : $scope)) && $var = $old) return $key;  
}


// DEBUG FUNCTIONS
//

// php debugs
function sa_phpInfoLite() // Returns loaded PHP extensions and versions
{
    $values = array(
        'php'        => phpversion(),
        'os'         => php_uname(),
        'extensions' => get_loaded_extensions(),
    );

    // assign extension version if available
    if ($values['extensions']) {
      foreach ($values['extensions'] as $lkey => $extension) {
        $values['extensions'][$lkey] = phpversion($extension) ? $extension. 
                ' ('. phpversion($extension). ')' : $extension;
      }
    }

    return $values;
}

function sa_dump_php(){ // Debug dump php enviroment information
  # _debugLog('Local Variables',get_defined_vars()); // LOCAL VARS
  _debugLog('PHP User Constants',sa_array_index(get_defined_constants(true),'user')); //PHP USER CONSTANTS
  _debugLog('PHP Includes',get_required_files()); // INCLUDES
  _debugLog('PHP Extensions',sa_phpInfoLite()); // PHP LOADED EXTENSIONS
  _debugLog('PHP All Constants',get_defined_constants(true)); // PHP ALL CONSTANTS
}  

// backtracing
function sa_debug_backtrace($skip = null,$backtrace=null){
    $traces = isset($backtrace) ? $backtrace : debug_backtrace();
    # _debugLog($traces);
    $ret = array();
    foreach($traces as $i => $call){
        if (isset($skip) and $i < $skip) {
            continue;
        }

        if (!isset ($call['args']))
        {
            $call['args'] = array();
        }				
				
        if (!isset ($call['file']))
        {
            $call['file'] = '';
        }

        if (!isset ($call['line']))
        {
            $call['line'] = '';
        }        
        
        $object = '';
        if (isset($call['class'])) {
            $object = $call['class'].$call['type'];
            if (is_array($call['args'])) {
                foreach ($call['args'] as &$arg) {
                    sa_get_bt_arg($arg);
                }
            }
        }        
        
        $ret[] = '<span class="cm-default"><span class="cm-default">#'.str_pad($i - $skip, 3, ' ') . '</span> '
        .'<span class="cm-variable">' . $object.$call['function'] . '</span>'
        .'<span class="cm-bracket">(</span>'
        .'<span class="">' . arr_to_csv_line($call['args']) .'</span>'
        .'<span class="cm-bracket">)</span>'
        .'<span class="cm-comment"> called at </span>'
        .'<span class="cm-bracket">[</span>'
        .'<span class="cm-atom" title="'.$call['file'].'">'. sa_get_path_rel($call['file']) .'</span>'
        .':'
        .'<span class="cm-string">'. $call['line'] .'</span>'
        .'<span class="cm-bracket">]</span>' . '</span>';
    }

    return implode("\n",$ret);
}

function sa_get_bt_arg(&$arg) { // retreives backtrace arguments
    if (is_object($arg)) {
        $arr = (array)$arg;
        $args = array();
        foreach($arr as $key => $value) {
            if (strpos($key, chr(0)) !== false) {
                $key = '';    // Private variable found
            }
            $args[] =  '['.$key.'] => '.sa_get_bt_arg($value);
        }

        $arg = get_class($arg) . ' Object ('.implode(',', $args).')';
    }
}

function sa_btprint(){   
    return sa_debug_backtrace(1);
} 

function sadev_btGetFuncIndex($backtrace,$funcname){
	// get the backtrace index for the specified functionname
	foreach($backtrace as $key=>$bt){
		if(isset($bt['function']) and $bt['function'] == $funcname){
			return $key;
		}
	}
}

// GS debugs
//

function bmark_line(){
  GLOBAL $stopwatch;
	return '<span class="bmark">'.number_format(round(($stopwatch->elapsed()*1000),2),2) . ' ms </span><span class="bmark">' . byteSizeConvert(memory_get_usage()) . '</span>';
}

function sa_dumpHooks($hookname = NULL,$exclude = false,$actions = false){
  // dumps live hooks to debuglog , can filter or exclude
	global $plugins;
  $sa_plugins = $plugins;
  $collapsestr= '<span class="sa_expand sa_icon_open"></span><span class="sa_collapse">';            
	$bmark_str = bmark_line();
  $hookdump = '<span class="titlebar">Dumping live hooks: ' . (isset($hookname) ? $hookname : 'All') .$bmark_str.'</span>'.$collapsestr;
  
  asort($sa_plugins);
    
  foreach ($sa_plugins as $hook)	{
		if(substr($hook['hook'],-8,9) == '-sidebar'){
			$thishook = 'sidebar';
		}else
		{
			$thishook = $hook['hook'];
		}
	
    # _debugLog($hook);
    if(isset($hookname) and $thishook != $hookname and $exclude==false) continue;  
    if(isset($hookname) and $thishook == $hookname and $exclude==true) continue;  
    if($actions == true and ( $thishook == 'sidebar' or $thishook == 'nav-tab')) continue;  
    
    if($hook['file']=='sa_development.php') continue; // remove noisy debug hooks
					
    # debugPair($hook['hook'],implode(', ',$hook['args']));     

        $return = '<span class="cm-default"><span><b>'.$hook['hook'] .'</b><span class="cm-tag"> &rarr; </span></span>'
        .'<span class="cm-variable">' . $hook['function'] . '</span>'
        .'<span class="cm-bracket">(</span>'
        .'<span class="">' . arr_to_csv_line($hook['args']) .'</span>'
        .'<span class="cm-bracket">)</span>'
        .'<span class="cm-comment"> File </span>'
        .'<span class="cm-bracket">[</span>'
        .'<span class="cm-atom" title="'.$hook['file'].'">'. sa_get_path_rel($hook['file']) .'</span>'
        .':'
        .'<span class="cm-string">'. $hook['line'] .'</span>'
        .'<span class="cm-bracket">]</span>' . '</span>';
    
        $hookdump.=$return.'<br/>';
  }
  return $hookdump.'</span>';
}

function sa_dumpFilters($filterName = NULL,$exclude = false){
  // dumps live hooks to debuglog , can filter or exclude
  global $filters;

  // _debugLog($filters);
  $sa_filters = $filters;
  $collapsestr= '<span class="sa_expand sa_icon_open"></span><span class="sa_collapse">';            
  $hookdump = '<span class="titlebar">Dumping live filters: ' . (isset($filter) ? $filterName : 'All') .'</span>'.$collapsestr;
  
  asort($sa_filters);
    
  foreach ($sa_filters as $filter)  {
     // _debugLog($filter);
    if(isset($filterName) and $filter['filter'] != $filterName and $exclude==false) continue;  
    if(isset($filterName) and $filter['filter'] == $filterName and $exclude==true) continue;  
    
        $return = '<span class="sa-default"><span><b>'.$filter['filter'] .'</b> &rarr; </span>'
        .'<span class="cm-keyword">' . $filter['function'] . '</span>';    
        $hookdump.=$return.'<br/>';
  }
  return $hookdump.'</span>';
}

function sa_dumpLiveHooks(){
  // dump all registered hooks
  // sorted, and grouped
  _debugLog(sa_dumpHooks('sidebar')); // sidemenus
  _debugLog(sa_dumpHooks('nav-tab'));   // nav tabs
  _debugLog(sa_dumpHooks(NULL,false,true));  // other
  _debugLog(sa_dumpFilters(NULL,false));  // filters 
}

function sa_dumpPhpVars(){ // dumps local vars
  _debugLog(get_defined_vars());
}

function debugTitle($title,$class=''){ // debuglog as title
  debugLog("<span class='titlebar $class'>$title</span>");
}

function debugPair($key,$value){ // debuglog as key pair
  debugLog("<b>$key</b> -> $value");
}


// DEBUGGING
// unit tests
function sa_debugtest(){

  // vdump($plugins); 
  
  // object to console
  $book               = new stdClass;
  $book->title        = "Harry Potter and the Prisoner of Azkaban";
  $book->author       = "J. K. Rowling";
  $book->publisher    = "Arthur A. Levine Books";
  $book->amazon_link  = "http://www.amazon.com/dp/0439136369/";
    
  $tstring 	= "a string";
  $tint 		= 1;
  $tfloat 	= 1.2;
  $tdbl 		= 7E-10;
  $tnull 		= null;
  $tarray 	= array();
  
  $testary = array(
  'int' 				=> $tint,
  'float' 			=> $tfloat,
  'double' 			=> $tdbl,
  'string' 			=> $tstring,
  'null' 				=> $tnull,
  'empty array' => $tarray,
  'array' 			=> array(  
    'int' 				=> $tint,
    'float' 			=> $tfloat,
    'string' 			=> $tstring,
    'nested array'=> array(1,2,3),
  ),
  'bool true' 	=> true,
  'bool false' 	=> false,  
  'object' 			=> new stdClass,
  );  
  
	// debugLog(print_r($testary,true));
	
  _debugLog($testary);
  _debugLog($tstring);
  _debugLog($tint);
  _debugLog($tint,$tfloat,$tdbl,$tstring,$tnull,$tarray);
  _debugLog($tstring);
  _debugLog('A string of text');
  _debugLog('A string of text');
  _debugLog('A string of text');
  _debugLog('A string of text');
  _debugLog('A string of text');
  _debugLog('A string of text');
  _debugLog($tint);
  _debugLog($tfloat);
  _debugLog($tnull);
  
  #sa_bmark_debug('vdump BEGIN');
  #for($i=0;$i<50;$i++){ vdump($_SERVER);}
  #sa_bmark_debug('vdump END');        

_debugLog(nl2br("  
<span class=cm-default>default</span>
<span class=cm-comment>comment</span>
<span class=cm-atom>atom</span>
<span class=cm-number>number</span>
<span class=cm-property>property</span>
<span class=cm-attribute>attribute</span>
<span class=cm-keyword>keyword</span>
<span class=cm-string>string</span>
<span class=cm-variable>variable</span>
<span class=cm-variable-2>variable-2</span>
<span class=cm-def>def</span>
<span class=cm-error>error</span>
<span class=cm-bracket>bracket</span>
<span class=cm-tag>tag</span>
<span class=cm-link>link</span>
"));  

_debugLog('test');
_debugLog('test',myfunc('test','test'),$tstring);
_debugLog(my_func('test','test'));  

debugLog('string');
debugLog($tstring);
debugLog($testary);

_debugLog('test title array',$testary);
_debugLog('test title string','a string');
_debugLog('test title variable',$tstring);

_debugLog('test inline array',array());

_debugLog($testary);

trigger_error('This is a warning', E_USER_WARNING);
trigger_error('This is a Notice', E_USER_NOTICE);
trigger_error('This is a Fatal Error', E_USER_ERROR);

}      

function myfunc($a,$b){
  return $a;
}

function my_func($a='test',$b='test'){
  return array($a,$b);
}
  
  ?>