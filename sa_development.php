<?php

/*
Added parse error catching regardless of error reporting level
Fixed issue with backtracing object classes

Added force on global
*/

/*
* @Plugin Name: sa_development
* @Description: Provides alterative debug console
* @Version: 0.6
* @Author: Shawn Alverson
* @Author URI: http://tablatronix.com/getsimple-cms/sa-dev-plugin/
*/

// global to force console on even when not logged in
$SA_DEV_ON = isset($SA_DEV_ON) ? $SA_DEV_ON : false;

define('SA_DEBUG',true); // sa dev plugin debug
# define('GS_DEV',false); // global development constant

$PLUGIN_ID  = "sa_development";
$PLUGINPATH = $SITEURL.'plugins/sa_development/';
$sa_url     = 'http://tablatronix.com/getsimple-cms/sa-dev-plugin/';

# get correct id for plugin
$thisfile    = basename(__FILE__, ".php");// Plugin File
$sa_pname    = 'SA Development';          //Plugin name
$sa_pversion = '0.6';                     //Plugin version
$sa_pauthor  = 'Shawn Alverson';          //Plugin author
$sa_purl     =  $sa_url;                  //author website
$sa_pdesc    =  'SA Development Suite';   //Plugin description
$sa_ptype    =  '';                       //page type - on which admin tab to display
$sa_pfunc    =  '';                       //main function (administration)
  
# register plugin
register_plugin($thisfile,$sa_pname,$sa_pversion,$sa_pauthor,$sa_url,$sa_pdesc,$sa_ptype,$sa_pfunc);

// INCLUDES
require_once('sa_development/hooks.php');
require_once('sa_development/sa_dev_functions.php');
  
// init timer
$stopwatch = new StopWatch(); 

if(SA_DEBUG==true){
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
}

// enable only when logged in
if(sa_user_is_admin() || $SA_DEV_ON || (get_filename_id() != 'install' && get_filename_id() != 'setup' && get_filename_id() != 'update')){
  add_action('index-posttemplate', 'sa_debugConsole');
  if(SA_DEBUG==true) add_action('footer', 'sa_debugtest'); // debug logging
  add_action('footer', 'sa_debugConsole');
  if(SA_DEBUG==true) add_action('sa_dev_menu','sa_dev_menu_hook'); // debug dev menus hooks

  // asset queing
  // 
  // use header hook if older than 3.1
  if(floatval(GSVERSION) < 3.1){
    add_action('header', 'sa_dev_executeheader');
    $owner = "SA_dev_";
  }  
  else{ sa_dev_executeheader(); }

}

// GLOBALS
$debugLogFunc = '_debugLog';

$SA_DEV_GLOBALS = array();
$SA_DEV_GLOBALS['show_filters']      = sa_getFlag('sa_sf');  // print filters
$SA_DEV_GLOBALS['show_hooks_front']  = sa_getFlag('sa_shf');  // print hooks frontend
$SA_DEV_GLOBALS['show_hooks_back']   = sa_getFlag('sa_shb');  // print hooks backend
$SA_DEV_GLOBALS['bmark_hooks_front'] = sa_getFlag('sa_bhf');  // benchmark hooks frontend
$SA_DEV_GLOBALS['bmark_hooks_back']  = sa_getFlag('sa_bhb');  // benchmark hooks backend
$SA_DEV_GLOBALS['live_hooks']        = sa_getFlag('sa_lh');  // live hooks dump
$SA_DEV_GLOBALS['php_dump']          = sa_getFlag('sa_php');  // php dump

$SA_DEV_BUTTONS = array();

$sa_console_sent = false;

$sa_phperr_init = error_reporting();
$sa_phperr = error_reporting();

if(sa_showingFilters()) add_action('common','create_pagesxml',array(true));

// INIT
sa_initHookDebug();
sa_initFilterDebug();

// FUNCTIONS

// ARG LOGIC
function sa_showingFilters(){
  // are we showing filters
  GLOBAL $SA_DEV_GLOBALS;
  // return true;
  return $SA_DEV_GLOBALS['show_filters'];
}

function sa_showingHooks(){
  // are we showing hooks
  GLOBAL $SA_DEV_GLOBALS;
  return $SA_DEV_GLOBALS['show_hooks_front'] || $SA_DEV_GLOBALS['show_hooks_back'];
}

function sa_bmarkingHooks(){
  // are we bmarking hooks
  GLOBAL $SA_DEV_GLOBALS;  
  return $SA_DEV_GLOBALS['bmark_hooks_front'] || $SA_DEV_GLOBALS['bmark_hooks_back'];
}

function sa_liveHooks(){
  // are we bmarking hooks
  GLOBAL $SA_DEV_GLOBALS;  
  return $SA_DEV_GLOBALS['live_hooks'];
}

function sa_phpDump(){
  // are we dumping php
  GLOBAL $SA_DEV_GLOBALS;  
  return $SA_DEV_GLOBALS['php_dump'];
}

function sa_initHookDebug(){
  // add hooks for showing and bmarking them
  GLOBAL $SA_DEV_GLOBALS, $FRONT_END_HOOKS, $BACK_END_HOOKS; 

  if(sa_bmarkingHooks()){
    # debugTitle('Debugging Hooks');
  }
  
  if(sa_showingHooks() || sa_bmarkingHooks()){
    foreach($FRONT_END_HOOKS as $key=>$value){
      if($SA_DEV_GLOBALS['bmark_hooks_front']) add_action($key, 'sa_bmark_hook_debug',array($key));
      if($SA_DEV_GLOBALS['show_hooks_front'])  add_action($key, 'sa_echo_hook',array($key));
    }

    foreach($BACK_END_HOOKS as $key=>$value){
      if($SA_DEV_GLOBALS['bmark_hooks_back']) add_action($key, 'sa_bmark_hook_debug',array($key));
      if($SA_DEV_GLOBALS['show_hooks_back'])  add_action($key, 'sa_echo_hook',array($key));  
    }
  }
}

function sa_initFilterDebug(){
  // add hooks for showing and bmarking them
  GLOBAL $SA_DEV_GLOBALS, $FILTERS, $filters; 

  if(sa_showingFilters()){
    debugTitle('Debugging Filters');
    _debugLog(__FUNCTION__);
    foreach($FILTERS as $key=>$value){
     // _debugLog(__FUNCTION__,$key);
     add_filter($key, 'sa_echo_filter',array($key));
    }
  }
}

function sa_debugMenu(){ // outputs the dev menu
  GLOBAL $SA_DEV_GLOBALS, $SA_DEV_BUTTONS;
    
  $site = pageIsFrontend() ? 'front' : 'back';
  $sitecode = pageIsFrontend() ? 'f' : 'b';
  $sh = '?'.get_toggleqstring('show_hooks_'.$site,'sa_sh'.$sitecode).'#sa_debug_title';
  $bh = '?'.get_toggleqstring('bmark_hooks_'.$site,'sa_bh'.$sitecode).'#sa_debug_title';
  $lh = '?'.get_toggleqstring('live_hooks','sa_lh').'#sa_debug_title';
  $pd = '?'.get_toggleqstring('php_dump','sa_php').'#sa_debug_title';
  
  $reset = sa_dev_qstring('sa_sh'.$sitecode);
  $reset = '?'.sa_dev_qstring('sa_bh'.$sitecode,null,$reset);
    
  # debugLog($reset);
  # debugLog($sh);
  # debugLog($bh);
   
  $local_menu = array();
  $local_menu[] = array('title'=>'Reset','url'=> $reset);
  $local_menu[] = array('title'=>'Show Hooks','url'=> $sh,'on'=>$SA_DEV_GLOBALS['show_hooks_'.$site],'about'=>'Show hooks on page');
  $local_menu[] = array('title'=>'Time Hooks','url'=> $bh,'on'=>$SA_DEV_GLOBALS['bmark_hooks_'.$site],'about'=>'Log hook becnhmark times');
  $local_menu[] = array('title'=>'Live Hooks','url'=> $lh,'on'=>$SA_DEV_GLOBALS['live_hooks'],'about'=>'Log registered hooks');
  $local_menu[] = array('title'=>'Dump PHP','url'=> $pd,'on'=>$SA_DEV_GLOBALS['php_dump'],'about'=>'Dump PHP enviroment');

  echo '<div id="sa_dev_menu"><ul>';
  echo sa_dev_makebuttons($local_menu);
  exec_action('sa_dev_menu');  
  if(count($SA_DEV_BUTTONS) > 0){
    echo '<li><b>|</b> </li>';
    echo sa_dev_makebuttons($SA_DEV_BUTTONS,true,10);
  }  
  echo '</ul></div>';
  
}

function sa_dev_makebuttons($buttons,$custom=false,$startid = 0){ // creates individual dev buttons
  $buttonstr = '';
  $classon = '_on';
  $classcustom = '_custom';
  $id = $startid;
  
  foreach($buttons as $button){
    $class = 'class="sa_dev';
    $about = $button['title'];
    if($custom) $class.= $classcustom;
    if(isset($button['on'])) $class.= $button['on'] ? $classon : ''; 
    if(isset($button['about'])) $about= $button['about'] ; 
    $buttonstr.='<li><a id="dev_but_'.$id.'" '.$class.'" href="'.$button['url'].'" title="'.$about.'">'.$button['title'].'</a></li>';
    $id++;
  }
  
  return $buttonstr;
}

function sa_dev_menu_hook(){ // debug for dev menu hook
  GLOBAL $SA_DEV_BUTTONS;
  $SA_DEV_BUTTONS[] = array('title'=>'Hooked Button off','url'=>'#','on'=>true);
  $SA_DEV_BUTTONS[] = array('title'=>'Hooked Button on','url'=>'#','on'=>false);
}


function sa_dev_executeheader(){ // assigns assets to queue or header
  GLOBAL $PLUGIN_ID, $PLUGINPATH, $owner;

  # debugLog("sa_dev_executeheader");
  
  $regscript = $owner."register_script";
  $regstyle  = $owner."register_style";
  $quescript = $owner."queue_script";
  $questyle  = $owner."queue_style";

  $regstyle($PLUGIN_ID, $PLUGINPATH.'css/sa_dev_style.css', '0.1', 'screen');
  $questyle($PLUGIN_ID,GSBOTH);   

  queue_script('jquery',GSBOTH);
}

function sa_logRequests(){
  if(isset($_POST) and count($_POST) > 0){
    _debuglog('PHP $_POST variables',$_POST);
  }
  
  if(isset($_GET) and count($_GET) > 0){
    _debuglog('PHP $_GET variables',$_GET);
  }
  
}

function sa_debugConsole(){  // Display the log
  global $GS_debug,$stopwatch,$sa_console_sent,$sa_phperr_init;            
  
  if(!$sa_console_sent){
    sa_logRequests();
    
    if(sa_liveHooks()){
      # debugTitle('Debugging Hooks');
      sa_dumpLiveHooks();
    }

    if(sa_phpDump()){
      # debugTitle('PHP Dump');
      sa_dump_php();
    }  

    sa_finalCallout();
  }
  
  # // tie to debugmode deprecated
  # if(defined('GSDEBUG') and !pageIsFrontend()) return;
    
    echo '<script type="text/javascript">'."\n";    
    echo 'jQuery(document).ready(function() {'."\n";    
    echo '$("h2:contains(\''. i18n_r('DEBUG_CONSOLE') .'\'):not(\'#sa_debug_title\')").remove();';
    
    $collapse = true;
    
    if($collapse){
      echo '
          //toggle the componenet with class msg_body
          $("#sa_gsdebug .titlebar").click(function(){
          
            if($(this).next().next(".sa_collapse").css("display")=="none"){
              $(this).next(".sa_expand").removeClass("sa_icon_closed").addClass("sa_icon_open");
            }
            
            $(this).next().next(".sa_collapse").slideToggle(200,function(){
              if($(this).css("display")=="none"){
                  $(this).prev(".sa_expand").removeClass("sa_icon_open").addClass("sa_icon_closed");
              }    
            });  
          });
      ';
    }
    
    echo '});';    
    echo '</script>';
    
    echo '<div id="sa_gsdebug-wrapper">
    <div class="sa_gsdebug-wrap">';
    
    if(!$sa_console_sent){
      echo '<span id="sa_debug_sig"><a href="http://tablatronix.com/getsimple-cms/sa-dev-plugin" target="_blank">sa_development</a></span>
      <h2 id="sa_debug_title">'.i18n_r('DEBUG_CONSOLE').'</h2>
      ';
      echo sa_debugMenu();
    }
    
    echo "\n";
    echo'<div id="sa_gsdebug" class="cm-s-monokai">';
       
    echo '<pre>';

    if(!$sa_console_sent){    
      echo 'GS Debug mode is: ' . ((defined('GSDEBUG') and GSDEBUG == 1) ? '<span class="cm-tag"><b>ON</b></span>' : '<span class="cm-error"><b>OFF</b></span>') . '<br />';
      echo 'PHP Error Level: <small><span class="cm-comment">(' . $sa_phperr_init . ') ' .error_level_tostring($sa_phperr_init,'|') . "</span></small><span class='divider cm-comment'></span>";
    }else{
      echo 'Post footer alerts<br />';
    }

    if(count($GS_debug) == 0){
      echo('Log is empty');
    } 
    else{
      foreach ($GS_debug as $log){
        if(gettype($log) == 'array'){ echo _debugReturn("array found in debugLog",$log); }
        else if(gettype($log) == 'object'){ echo _debugReturn("object found in debugLog",$log); }
        else if(preg_match('/^(Array\n\().*/',$log)){
          echo _debugReturn("print_r output found in debuglog",$log);
          # echo nl2br($log);
        }
        # if(gettype($log) == 'array'){ echo _debugReturn("array found in debugLog()",$log); } // todo: causes arg parsing on function name in quotes
        else{ echo($log.'<br />');}
      }
    }
    echo '</pre>';
    echo '</div>';
    
    if($sa_console_sent != true){
      echo '
        <div id="sa_debug_footer">
          <span class="sa_icon_wrap"><span class="sa_icon sa_icon_time"></span>Runtime~: '. number_format(round(($stopwatch->elapsed()*1000),3),3) .' ms</span>
          <span class="sa_icon_wrap"><span class="sa_icon sa_icon_files"></span>Includes: '. count(get_required_files()) .'</span>
          <span class="sa_icon_wrap"><span class="sa_icon sa_icon_mempeak"></span>Peak Memory: '. byteSizeConvert(memory_get_peak_usage()) .'</span>
          <span class="sa_icon_wrap"><span class="sa_icon sa_icon_memlimit"></span>Mem Avail: '. ini_get('memory_limit') .'</span>
          <span class="sa_icon_wrap"><span class="sa_icon sa_icon_diskfree"></span>Disk Avail: '. byteSizeConvert(disk_free_space("/")) .' / ' . byteSizeConvert(disk_total_space("/")) .'</span>
        </div>';
    }
    echo '</div></div>';

  $sa_console_sent = true;
}

/**
 * Uses dom to add node appending to simplexml
 */
function sxml_append(SimpleXMLElement $to, SimpleXMLElement $from) {
    $toDom = dom_import_simplexml($to);
    $fromDom = dom_import_simplexml($from);
    $toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
}

// FILTER DEBUGGING
function sa_echo_filter($unfiltered,$args = array()){
  // echoes filters onto pages
  GLOBAL $FILTER,$SITEURL;

  $filterid = $args[0];

  // _debugLog(__FUNCTION__, $filterid);

  $pagesitem = <<<XML
  <item>
    <url>pagecache_filtered</url>
    <pubDate><![CDATA[Sun, 27 Oct 2013 09:30:11 -0500]]></pubDate>
    <title><![CDATA[pagecache filter]]></title>
    <url><![CDATA[pagecache_filtered]]></url>
    <meta><![CDATA[pagecache_filtered_1,pagecache_filtered_2]]></meta>
    <metad><![CDATA[pagecache_filtred]]></metad>
    <menu><![CDATA[pagecachefiltered]]></menu>
    <menuOrder><![CDATA[]]></menuOrder>
    <menuStatus><![CDATA[Y]]></menuStatus>
    <template><![CDATA[template.php]]></template>
    <parent><![CDATA[]]></parent>
    <private><![CDATA[]]></private>
    <author><![CDATA[pagecachefiltered]]></author>
    <slug><![CDATA[pagecache_filtered]]></slug>
    <filename><![CDATA[index.xml]]></filename>
  </item>
XML;

  $pagesitem_xml = simplexml_load_string($pagesitem);
  // _debugLog($pagesitem_xml);

  $sitemapitem = <<<XML
    <url>
      <loc>$SITEURL/sitemap_filtered</loc>
      <lastmod>2013-10-27T09:29:57+00:00</lastmod>
      <changefreq>weekly</changefreq>
      <priority>0.5</priority>
    </url>
XML;

  $sitemap_xml = simplexml_load_string($sitemapitem);

  $style = 'style="background-color:#FFCCCC;font-size:12px;color:000;border:1px solid #CCC;padding:2px;margin:2px"';

  $filtercontent = array(
    'content'   => '<span '.$style.' title="' . $filter_id . '">This is content filtered</span>',
    'menuitems' => '<li><a href="#" '.$style.'>this is menutitems filtered</a></li>',
    'pagecache' => $pagesitem_xml,
    'sitemap'   => $sitemap_xml,
    'indexid'   => '404'
  );

  if(isset($filtercontent[$filterid])){   
    if( is_object($filtercontent[$filterid]) && get_class($filtercontent[$filterid]) == 'SimpleXMLElement')  sxml_append($unfiltered,$filtercontent[$filterid]);
    else $unfiltered .= " " .$filtercontent[$filterid];
    // _debugLog($unfiltered);
    return $unfiltered;
  }
  else return $unfiltered;
  
}

// HOOK DEBUGGING
function sa_echo_hook($hook_id){
  // echoes hooks onto pages
  GLOBAL $FRONT_END_HOOKS, $BACK_END_HOOKS;
  $all_hooks = array_merge($FRONT_END_HOOKS, $BACK_END_HOOKS);
  echo '<span style="background-color:#FFCCCC;font-size:12px;color:000;border:1px solid #CCC;padding:2px;margin:2px" title="' . $all_hooks[$hook_id] . '">hook: '.$hook_id.'</span>';
}

function sa_bmark_hook_debug($hook_id){
  // benchmark hook call times to debug console
  sa_bmark_debug('hook: ' . $hook_id);
}

function sa_bmark_hook_print($hook_id){
  // benchmark hook call times to page
  sa_bmark_print($hook_id);
}

// TIMING BENCHMARKING FUNCTIONS
class StopWatch { 
    public $total; 
    public $time; 
    
    public function __construct() { 
        $this->total = $this->time = microtime(true); 
    } 
    
    public function clock() { 
        return -$this->time + ($this->time = microtime(true)); 
    } 
    
    public function elapsed() { 
        return microtime(true) - $this->total; 
    } 
    
    public function reset() { 
        $this->total=$this->time=microtime(true); 
    } 
} 

function sa_bmark_print($msg){
    GLOBAL $stopwatch;
    echo("<span id=\"pagetime\">bmark: " . $msg . ": " . round($stopwatch->clock(),5) . " / " . round($stopwatch->elapsed(),5) ." seconds</span>"); 
}

function sa_bmark_debug($msg = ""){
    GLOBAL $stopwatch;
    debugLog('<span class="titlebar sad_bmark"><span class="sad_key">bmark</span> : ' . number_format(round($stopwatch->elapsed(),5),5) . "<b> &#711;</b>" . number_format(round($stopwatch->clock(),5),5) . " " . $msg . '</span>');
}

function sa_bmark_reset(){
  $stopwatch->reset();
}


// CORE FUNCTIONS
function _debugLog(){ 
  /* variable arguments */
  if(sa_getErrorChanged()){
    debugTitle('PHP Error Level changed: <small>(' . error_reporting() . ') ' .error_level_tostring(error_reporting(),'|') . '</small>','notice');  
  } 
  debugLog(vdump(func_get_args()));
}

function _debugReturn(){
  return vdump(func_get_args());
}

function vdump($args){
    
    GLOBAL $debugLogFunc;
    
    $debugstr = ''; // for local debugging because we can create infinite loops by using debuglog inside debuglogs
    
    if(isset($args) and gettype($args)!='array'){
      $args = func_get_args();
      $numargs = func_num_args();      
    }else{
      $numargs = count($args);
    }   
    
    // ! backtrace arguments are passed by reference !  
    // todo: make this totally safe with no chance of modifying arguments.
    
    $backtrace = debug_backtrace();
    # echo "<pre>".print_r($backtrace,true)."</pre>"; 
    $lineidx =  sadev_btGetFuncIndex($backtrace,$debugLogFunc);   
    if(!isset($lineidx)) $lineidx = 1;
    $funcname = $backtrace[$lineidx]['function'];
    $file = $backtrace[$lineidx]['file'];
    //todo: handle evald code eg. [file] => /hsphere/local/home/salverso/tablatronix.com/getsimple_dev/plugins/i18n_base/frontend.class.php(127) : eval()'d code
    $line = $backtrace[$lineidx]['line'];
    $code = @file($file);    
    $codeline = $code!=false ? trim($code[$line-1]) : 'anon function call';
    
    /* Finding our originating call in the backtrace so we can extract the code line and argument nesting depth
     *
     * If using custom function, we have to remove all the get_func_arg array wrappers n deep
     * where n is the depth path the normal _debuglog function in the backtrace
     * each get_func_args wraps another array around the argument array
     * so we reduce it by as many levels as we need to get it back to the original args
     * we use a global function name to do this.
     * Still trying to figure out a way to figure out the originating call_user_func
     * it might be impossible since people might create a very advanced wrapper using debug levels arguments and args
     *
     */
    
    // reduce array depth and adjust arg count
    if($lineidx>1){
      for($i=0;$i<$lineidx-1;$i++){
        $args = $args[0]; 
      } 
      $numargs = count($args);  // redo numargs, else it will stay the 1 from func_get_args
    } 
        
    $arg1 = isset($args[0]) ? $args[0] : ''; // avoids constant isset checking in some logic below.
    
    #$argnames = preg_replace('/'. __FUNCTION__ .'\((.*)\)\s?;/',"$1",$codeline);
    $argstr = preg_replace('/.*'.$funcname.'\((.*)\)\s?;.*/',"$1",$codeline);
    $argnames = array();
    $argnames = sa_parseFuncArgs($argstr);
    $argn = 0;  
    
    # debugLog(print_r($argstr,true));
    # debugLog(print_r($argnames,true));
    
    $collapsestr= '<span class="sa_expand sa_icon_open"></span><span class="sa_collapse">';  
    $bmark_str = bmark_line();
    $str = "";
    
    if($numargs > 1 and gettype($arg1)=='string' and ( gettype($args[1])!='string' or strpos($argnames[1],'$') === 0)){
      // if a string and more arguments, we treat first argumentstring as title
      $str.=('<span class="cm-default titlebar special" title="(' . sa_get_path_rel($file) . ' ' . $line . ')">'.htmlspecialchars($arg1).$bmark_str.'</span>');
      array_shift($args);
      array_shift($argnames);
      $numargs--;
      $str.= $collapsestr;      
    }    
    elseif($numargs > 1 || ( $numargs == 1 and (gettype($arg1)=='array' or gettype($arg1)=='object')) ){
      // if multiple arguments or an array, we add a header for the rows
      $str.=('<span class="cm-default titlebar array object multi" title="(' . sa_get_path_rel($file) . ' ' . $line . ')">'.htmlspecialchars($codeline).$bmark_str.'</span>');
      $str.= $collapsestr;      
    }
    elseif($numargs == 1 and gettype($arg1)=='string' and strpos($argnames[0],'$') === false){
      // if string debug, basic echo, todo: this also catches functions oops
      $str=('<span class="string" title="(' . sa_get_path_rel($file) . ' ' . $line . ')">'.$arg1.'</span>');
      $str.= '<span>';      
      return $str;
    }    
    elseif($numargs == 0){
      $str.=('<span class="cm-default titlebar" title="(' . sa_get_path_rel($file) . ' ' . $line . ')">'. htmlspecialchars($codeline).$bmark_str .'</span>');
      $str.= $collapsestr;
      $str.= '<b>Backtrace</b> &rarr;<br />';
      $str.= nl2br(sa_debug_backtrace(2));    
      $str.= '</span>';      
      return $str;
    }
    else{
      // we add a slight divider for single line traces
      $str.="<span class='divider cm-comment'></span>";
    }
        
    ob_start();
    
      foreach ($args as $arg){
        # if($argn > 0) print("\n");
        if(isset($argnames[$argn])){
          echo '<span class="cm-variable"><b>' . trim($argnames[$argn]) . "</b></span> <span class='cm-tag'>&rarr;</span> ";
          if(gettype($arg) == 'array' and count($arg)>0) echo "\n";
        }  
        htmlspecialchars(var_dump($arg));
        $argn++;
      }  

    $str .= ob_get_contents();
  
    ob_end_clean();

   // cannot use this as it container partial html from the collapse and headers from above
   // make new debug debuging
   #  debugLog("default output: " . $str."<br/>");  
    
    $str = sa_dev_highlighting($str);
    $str = trim($str);
    return nl2br($str).'</span>';       
    
    // debug with backtrace output
    // return nl2br($str).'<br>'.nl2br(sa_debug_backtrace(null,$backtrace)).'</span>';   
    // return nl2br($str).'</span><pre>'.nl2br(print_r($backtrace,true)).'</pre>';     
}


function sa_dev_highlighting($str){
    // added &? to datatypes for new reference output from var_dump
    // indented are for print_r outputs
    
    $str = preg_replace('/=>(\s+)/', ' => ', $str); // remove whitespace
    $str = preg_replace('/=> NULL/', '=> <span class="cm-def">NULL</span>', $str);
    $str = preg_replace('/(?!=> )NULL/', '<span class="cm-def">NULL</span>', $str);
    $str = preg_replace('/}\n(\s+)\[/', "}\n\n".'$1[', $str);
    $str = preg_replace('/(&?float|&?int)\((\-?[\d\.\-E]+)\)/',    " <span class='cm-default'>$1</span> <span class='cm-number'>$2</span>", $str);
    $str = preg_replace('/&?array\((\d+)\) {\s+}\n/',            "<span class='cm-default'>array&bull;$1</span> <span class='cm-bracket'><b>[]</b></span>", $str);
    $str = preg_replace('/&?array\((\d+)\) {\n/',                "<span class='cm-default'>array&bull;$1</span> <span class='cm-bracket'>{</span>\n<span class='codeindent'>", $str);
      $str = preg_replace('/Array\n\(\n/',                "\n<span class='cm-default'>array</span> <span class='cm-bracket'>(</span>\n<span class='codeindent'>", $str);
      $str = preg_replace('/Array\n\s+\(\n/',                "<span class='cm-default'>array</span> <span class='cm-bracket'>(</span>\n<span class='codeindent'>", $str);
      $str = preg_replace('/Object\n\s+\(\n/',                "<span class='cm-default'>object</span> <span class='cm-bracket'>(</span>\n<span class='codeindent'>", $str);
    $str = preg_replace('/&?string\((\d+)\) \"(.*)\"/',          "<span class='cm-default'>str&bull;$1</span> <span class='cm-string'>'$2'</span>", $str);
    $str = preg_replace('/\[\"(.+)\"\] => /',                    "<span style='color:#666'>'<span class='cm-string'>$1</span>'</span> <span class='cm-tag'>&rarr;</span> ", $str);
      $str = preg_replace('/\[([a-zA-Z\s_]+)\]  => /',                    "<span style='color:#666'>'<span class='cm-string'>$1</span>'</span> <span class='cm-tag'>&rarr;</span> ", $str);
      $str = preg_replace('/\[(\d+)\]  => /',                    "<span style='color:#666'>[<span class='cm-string'>$1</span>]</span> <span class='cm-tag'>&rarr;</span> ", $str);
    $str = preg_replace('/\[(\d+)\] => /',                    "<span style='color:#666'>[<span class='cm-string'>$1</span>]</span> <span class='cm-tag'>&rarr;</span> ", $str);
    $str = preg_replace('/&?object\((\S+)\)#(\d+) \((\d+)\) {\s+}\n/', "<span class='cm-default'>obj&bull;$2</span> <span class='cm-keyword'>$1[$3]</span> <span class='cm-keyword'>{}</span>", $str);
    $str = preg_replace('/&?object\((\S+)\)#(\d+) \((\d+)\) {\n/', "<span class='cm-default'>obj&bull;$2</span> <span class='cm-keyword'>$1[$3]</span> <span class='cm-keyword'>{</span>\n<span class='codeindent'>", $str);
    $str = str_replace('bool(false)',                          "<span class='cm-default'>bool&bull;</span><span class='cm-number'><b>false</b></span>", $str);
    $str = str_replace('&bool(false)',                          "<span class='cm-default'>bool&bull;</span><span class='cm-number'><b>false</b></span>", $str);
    $str = str_replace('bool(true)',                           "<span class='cm-default'>bool&bull;</span><span class='cm-number'><b>true</b></span>", $str);
    $str = str_replace('&bool(true)',                           "<span class='cm-default'>bool&bull;</span><span class='cm-number'><b>true</b></span>", $str);
    $str = preg_replace('/}\n/',                "</span>\n<span class='cm-bracket'>}</span>\n", $str);
      $str = preg_replace('/\)\n/',                "</span>\n<span class='cm-bracket'>)</span>\n", $str);
    $str = str_replace("\n\n","\n",$str);
    # if($argn == 1) $str = str_replace("\n","",$str);
    return $str;
} 

function sa_finalCallout(){
}

function sa_dev_ErrorHandler($errno, $errstr='', $errfile='', $errline='',$errcontext=array()){
    GLOBAL $sa_phperr_init;
    
    # _debugLog(error_reporting(),$errno, $errstr, $errfile, $errline);
    
    /*  Of particular note is that error_reporting() value will be 0 if
     *  the statement that caused the error was prepended by the @ error-control operator.
     */ 
    
    $errorReporting = error_reporting();
    
    // handle supressed errors
    $debugSuppressed = false;
    
    $showingSuppressed = false;
    
    if((defined('GSDEBUG') and GSDEBUG == 1) and $debugSuppressed == true){
      #$errorReporting = -1;
      #$errno=0;
      $showingSuppressed = true;
      $errno = 0;
    }
    
    # _debugLog(error_reporting(),$errno, $errstr, $errfile, $errline);
    
    // Ignore if error reporting is off, unless parse error
    if (!($errorReporting & $errno) and $errno!=E_PARSE and $showingSuppressed != true) {
        // This error code is not included in error_reporting
        // unless parse error , then we want user to know
        return;
    }
    
    // check if function has been called by an exception
    if(func_num_args() == 5) {
        // called by trigger_error()
        #$exception = null;
        #list($errno, $errstr, $errfile, $errline) = func_get_args();

        # $backtrace = array_reverse(debug_backtrace());

    }else {
        // caught exception
        $exc = func_get_arg(0);
        $errno = $exc->getCode();
        $errstr = $exc->getMessage();
        $errfile = $exc->getFile();
        $errline = $exc->getLine();

        # $backtrace = $exc->getTrace();
    }   
    
    $errorType = array (
               0                => 'SUPPRESSED',            // 0  
               E_ERROR          => 'ERROR',                 // 1
               E_WARNING        => 'WARNING',               // 2
               E_PARSE          => 'PARSING ERROR',         // 4
               E_NOTICE         => 'NOTICE',                // 8
               E_CORE_ERROR     => 'CORE ERROR',            // 16
               E_CORE_WARNING   => 'CORE WARNING',          // 32
               E_COMPILE_ERROR  => 'COMPILE ERROR',         // 64
               E_COMPILE_WARNING => 'COMPILE WARNING',      // 128
               E_USER_ERROR     => 'USER ERROR',            // 256
               E_USER_WARNING   => 'USER WARNING',          // 512
               E_USER_NOTICE    => 'USER NOTICE',           // 1024
               E_STRICT         => 'STRICT NOTICE',         // 2048
               E_RECOVERABLE_ERROR  => 'RECOVERABLE ERROR'  // 4096
               );

    // create error message
    if (array_key_exists($errno, $errorType)) {
        $err = $errorType[$errno];
    } else {
        $err = 'CAUGHT EXCEPTION';
    }              
        
    /* Don't execute PHP internal error handler */
    $collapsestr= '<span class="sa_expand sa_icon_open"></span><span class="sa_collapse">';     
    $str = '<span class="titlebar '.strtolower($err).'" title="(' . sa_get_path_rel($errfile) . ' ' . $errline . ')">PHP '.$err.bmark_line().'</span>'; 
    $str.= $collapsestr;
    $err = sa_debug_handler($errno, $errstr, $errfile, $errline, $errcontext);    
    debugLog($str.$err);
    
    $backtraceall = true;
    if( ($errno!== E_USER_NOTICE and $errno!== E_NOTICE) or $backtraceall == true){
      debugLog('<span class="cm-default"><b>Backtrace</b></span><span class="cm-tag"> &rarr; </span>');
      $backtrace = nl2br(sa_debug_backtrace(3));
      debugLog($backtrace == '' ? 'backtrace not available' : $backtrace);
    }
    debugLog('</span>');
    # _debugLog("ERROR context",$errcontext); 

    switch ($errno) {
        case 0:
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_WARNING:
        case E_USER_WARNING:
            return;
            break;

        default:
          # exit();
    }
    
    return true;
}

function sa_debug_handler($errno, $errstr, $errfile, $errline, $errcontext){
        $ret = '<span class="cm-default">'
        .'<span class="cm-keyword">'.$errstr.'</span>'
        .'<span class="cm-comment"> in </span>'
        .'<span class="cm-bracket">[</span>'
        .'<span class="cm-atom" title="'.$errfile.'">'. sa_get_path_rel($errfile) .'</span>'
        .':'
        .'<span class="cm-string">'. $errline .'</span>'
        .'<span class="cm-bracket">]</span>' 
        . '</span>';
    return $ret;
}

function sa_dev_handleShutdown() {
    GLOBAL $GS_debug,$sa_console_sent;
    $error = error_get_last();
    if($error !== NULL){
      if($sa_console_sent == true) $GS_debug = array();
      sa_dev_ErrorHandler($error['type'], $error['message'], $error['file'], $error['line'],array());
      sa_emptyDoc($error);
    }else {
      # echo "shutdown"; 
    }

    return true;
}

function sa_emptyDoc($error){
  GLOBAL $sa_console_sent;
  if(isset($error['type']) and ($error['type'] === E_ERROR or $error['type'] === E_USER_ERROR)){
    $errorclass = 'sa_dev_error';
  } else { 
    $errorclass='';
  }
  
  if(!$sa_console_sent){
    echo '<!DOCTYPE html>
      <html lang="en">
      <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"  />
        <title>GETSIMPLE DEVELOPMENT ERROR HANDLER</title>
        <link href="http://tablatronix.com/getsimple_dev/plugins/sa_development/css/sa_dev_style.css?v=0.1" rel="stylesheet" media="screen">
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js?v=1.7.1"></script>
      </head>
      <body id="load" class="'.$errorclass .'">';
    sa_debugConsole();  
    echo '</body></html>';
  }else { 
    sa_debugConsole();  
  } 
    
}

if(sa_user_is_admin()){
  register_shutdown_function('sa_dev_handleShutdown');
  set_error_handler("sa_dev_ErrorHandler"); 
}

// unsorted functions

function error_level_tostring($intval, $separator){
    // credit to the_bug_the_bug @ php.net    
    $errorlevels = array(
        // 4096  => 'E_RECOVERABLE_ERROR',
        2048  => 'STRICT',
        2047  => 'ALL',
        1024  => 'USER_NOTICE',
        512   => 'USER_WARNING',
        256   => 'USER_ERROR',
        128   => 'COMPILE_WARNING',
        64    => 'COMPILE_ERROR',
        32    => 'CORE_WARNING',
        16    => 'CORE_ERROR',
        8     => 'NOTICE',
        4     => 'PARSE',
        2     => 'WARNING',
        1     => 'ERROR');
    $result = '';
    foreach($errorlevels as $number => $name)
    {
        if (($intval & $number) == $number) {
            $result .= ($result != '' ? $separator : '').$name; }
    }
    return $result == '' ? 'NONE' : $result;
}

function sa_getErrorReporting(){
  // credit to DarkGool @ php.net
  $bit = ini_get('error_reporting'); 
  while ($bit > 0) { 
      for($i = 0, $n = 0; $i <= $bit; $i = 1 * pow(2, $n), $n++) { 
          $end = $i; 
      } 
      $res[] = $end; 
      $bit = $bit - $end; 
  } 
  return $res;
}

function sa_setErrorReporting($int = 0){
  // credit to feroz Zahid @ php.net
  
  // set PHP error reporting 
  switch($int) { 
    case 0: error_reporting(0); break;                                          # 0 - Turn off all error reporting 
    case 1: error_reporting(E_ERROR | E_WARNING | E_PARSE); break;              # 1 - Running errors 
    case 2: error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE); break;   # 2 - Running errors + notices 
    case 3: error_reporting(E_ALL ^ (E_NOTICE | E_WARNING)); break;             # 3 - All errors except notices and warnings 
    case 4: error_reporting(E_ALL ^ E_NOTICE); break;                           # 4 - All errors except notices 
    case 5: error_reporting(E_ALL); break;                                      # 5 - All errors 
    default: 
        error_reporting(E_ALL);                                                 # DEFAULT to all errors
    } 
}

function sa_getErrorChanged(){
  GLOBAL $sa_phperr;
  if($sa_phperr != error_reporting()){
    $sa_phperr = error_reporting();
    return true;
  }
}

?>
