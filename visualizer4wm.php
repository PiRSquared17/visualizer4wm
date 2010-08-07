<?php
/** @file visualizer4wm.php
 ** @brief The entry to visualizer4wm
 ** @author Al Maghi and Xavier Marcelet.
 **/

/**
 * @mainpage Visualizer4wm code documentation
 * <center>
 * For more information, see: http://meta.wikimedia.org/wiki/Visualizer4wm
 * </center>
 *
 * @htmlonly
 * <style type="text/css">h2{position: relative;left: -15px;font-size: 140%;}</style>
 * @endhtmlonly
 *
 * @section entry Entry
 * 
 * File : visualizer4wm.php
 *
 * - Run at http://toolserver.org/~al/visualizer4wm.php
 *
 *
 * @section chart Chart module
 * 
 * File : visualizer4wmChart.php
 *
 *
 * @section motionchart Motionchart module
 * 
 * File : visualizer4wmMotionchart.php
 *
 * <br>
 */

/**
 ** @brief Get parameter
 ** @param p_name Name of the url argument.
 ** @param p_default Default value for the parameter.
 ** @details Get parameter from url arguments.
 */
function get($p_name, $p_default=null)
{
  if (isset($_GET[$p_name]))
     return $_GET[$p_name];
    if ($p_default != null)
       return $p_default;
    die(sprintf("Could not find _GET variable '%s'", $p_name));
}


/**
 ** @brief Get the page content with MediaWiki API
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org.
 ** @param $p_pageName The page name.
 ** @details Return the raw content of the wikipage.
 **
 */
function getContentFromMediaWiki ($p_projectUrl,$p_pageName)
{
  ini_set('user_agent', 'Al Maghi\'s visualizer4wm.php script');
  $l_sourceUrl     = sprintf('http://%s/w/api.php?action=query&prop=revisions&titles=%s&rvprop=content&format=xml',
												  $p_projectUrl,
												  $p_pageName);
  $l_rawPageSourceCode = file_get_contents($l_sourceUrl);
  if (false==$l_rawPageSourceCode) {
    exit("Could not get <a href=\"$l_sourceUrl\">contents from MediaWiki api</a> on $p_projectUrl/w/api.php.");
  }
  $l_pageSourceCode = strstr($l_rawPageSourceCode, '<rev xml:space="preserve">');
  if (false==$l_pageSourceCode)
  {
    exit("Sorry, the page <tt>[[<a href=\"http://$p_projectUrl/wiki/$p_pageName\">$p_pageName</a>]]</tt> does not exist on $p_projectUrl. (You may want to <a href=\"http://$p_projectUrl/w/index.php?title=$p_pageName&amp;action=edit\">start the page <em>$p_pageName</em></a>.)");
  }
  $l_pageSourceCode = substr($l_pageSourceCode,
					  strlen('<rev xml:space="preserve">'),
					  -strlen('</rev></revisions></page></pages></query></api>') );
  return $l_pageSourceCode;
}


/**
 ** @brief Run the visualizer
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org.
 ** @param $p_pageName The page name.
 ** @return the javaScript chart code.
 ** @details Get the template name, query MediaWiki API, parse source and generate JavaScript.
 */
function run_visualizer($p_projectUrl, $p_pageName)
{
  # Get the template name or exit.
  $l_templateName = get("tpl", "_");
  if ("_"==$l_templateName || ""==$l_templateName) { exit("Add the visualizer template name to your http request: <tt>&tpl=templateName</tt>"); }

  # Try to get content from MediaWiki or exit.
  $l_pageContent = getContentFromMediaWiki($p_projectUrl, $p_pageName);

  # Generate the chart or exit.
  if ("motionchart"==$l_templateName)
  {
    require_once("./visualizer4wmMotionchart.php");
    $l_chartcode = MotionchartGenerator($l_pageContent, $p_projectUrl, $p_pageName);
  }
  else
  {
    require_once("./visualizer4wmChart.php");
    $l_chartcode = ChartGenerator($l_pageContent, $l_templateName, $p_projectUrl, $p_pageName);
  }

  return $l_chartcode;
}


/**
 ** @brief Set the system messages
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org.
 ** @param $p_pageName The page name.
 ** @details Get language and return its messages.
 */
function setMessages($p_projectUrl,$p_pageName,$lang)
{
  require_once("./visualizer4wm.i18n.php");
  $l_rtlCode="";

  if(isset($messages[$lang])) {
    $l_msg = $messages[$lang];
    if ('yes'==$l_msg['is_rtl']) { $l_rtlCode=".rtl"; }
  } else {
    $l_msg = $messages['en'];
  }
  $l_msg['is_rtl']=$l_rtlCode;
  $l_displayedPageName = str_replace('_',' ',$p_pageName);
  $l_msg['visualizer4mw-info'] = str_replace('$1', "<a href='http://$p_projectUrl/wiki/$p_pageName'>$l_displayedPageName</a>", $l_msg['visualizer4mw-info']);
  $l_msg['visualizer4mw-info'] = str_replace('$2', $p_projectUrl, $l_msg['visualizer4mw-info']);
  $l_msg['visualizer4mw-info'] = '<div id="info" class="noLinkDecoration">'.$l_msg['visualizer4mw-info'].'</div>
      <div id="chart_div"></div>';
  return $l_msg;
}


/**
 ** @brief Echo HTML document
 ** @param $p_javaScriptCode The document javaScript. 
 ** @param $p_htmlCode The document main html division.
 ** @param $p_title The document title and header.
 ** @param $p_ltr The language direction: ".rtl" or empty.
 ** @details Print valid XHTML
 **
 */
function printHTML($p_javaScriptCode="",
                   $p_htmlCode="", $p_title="Wikitable Visualizer", $p_ltr="")
{
  if (''==$p_htmlCode) {
    $p_htmlCode='<div id="index"><br />Welcome on the wikitable visualizer tool.<br /><br />
		  See it in action with
	<a href="?page=Template:Visualizer&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=pie">
		  pie</a>,
	<a href="?page=Template:Visualizer&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=bar">
		  bar</a>,
	<a href="?page=Template:Visualizer/Test&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=col">
		  column</a>,
	<a href="?page=Template:Visualizer/Test&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=line">
		  line</a>,
	<a href="?page=Template:Visualizer/Scatter&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=scatter">
		  scatter</a>,
	<a href="?page=Template:Visualizer/Scatter&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=sparkline&amp;title=">
		  sparkline</a>,
	<a href="?page=Template:Visualizer/Area&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=area">
		  area</a>,
	<a href="?page=Template:Visualizer/GeoMap&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=geomap">
		  geomap</a>,
	<a href="?page=Template:Visualizer/IntensityMap&amp;project=en.wikipedia.org&amp;tpl=visualizer&amp;ct=intensitymap">
		  intensitymap</a> or
	<a href="?page=User:Al_Maghi/Visualize_Wikipedias_growth_up_to_2010&amp;project=en.wikipedia.org&amp;tpl=motionchart&amp;y=Bytes+per+article&amp;x=Articles&amp;group=Wikipedias">
		  motion</a> charts.
      </div>';
  }

  header('Content-type: text/html; charset=UTF-8');

  echo <<<MYHMTLPAGE
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8"/>
    <title>$p_title - visualize the data published on a wikipage</title>
    $p_javaScriptCode
    <link rel="stylesheet" href="./css/visualizer4wm$p_ltr.css" type="text/css" media="screen" />
  </head>
  <body>
    <div id="mw_header"><a href="./visualizer4wm.php">$p_title</a><br /><span>Wikimedia toolserver</span></div>
    <div id="main">$p_htmlCode</div>
    <div id="documentation">
    	<span class="hidden"><tt>{{<a href="./index.html">Visualizer</a>}}</tt> &nbsp;
      &#8734; &nbsp; <a href="http://meta.wikimedia.org/wiki/visualizer4wm">documentation</a> &nbsp;
      &#8734; &nbsp; The visualizer tool is kindly served to you by the <a href="http://toolserver.org/">Wikimedia Toolserver</a>.
      	It uses the <a href="http://mediawiki.org/wiki/API">MediaWiki</a>
      	and the <a href="http://code.google.com/intl/en-EN/apis/visualization/interactive_charts.html">Visualization</a></span> APIs.
    </div>
  </body>
</html>
MYHMTLPAGE;
}


/**
 ** @brief The main function
 ** @details Print default html or run visualizer, set messages and print chart.
 */
function main()
{
  # Get the page name or print default html.
  $l_pageName   = str_replace(' ','_',get("page", "_"));
  if ("_"==$l_pageName || ""==$l_pageName) {
    exit(printHTML());
  }

  # Get the project url and check its domain name.
  $l_projectUrl = get("project", "en.wikipedia.org");
  $l_projectDomain = substr(strstr($l_projectUrl, '.'),1);
  $l_domains = array('wikipedia.org',
		    'wikimedia.org',
		    'wikibooks.org',
		    'wikiquote.org',
		    'mediawiki.org',
		    'wikinews.org',
		    'wiktionary.org',
		    'wikisource.org',
		    'wikiversity.org');
  if ( !in_array( $l_projectDomain, $l_domains)) {
    exit("Sorry but <a href=\"http://$l_projectDomain\">$l_projectDomain</a> is not an authorised domain name. (Contact us if you want to authorise a new domain name.)<br />The <tt>project</tt> parameter should be something such as en.wikipedia.org.");
  }

  # Run the visualizer.
  $l_visualization = run_visualizer($l_projectUrl, $l_pageName);

  # api
  $lang=get("lang", "en");
  if ("API"==$lang) exit($l_visualization.'<div id="chart_div"></div>');

  # Set the i18n messages.
  $l_msg = setMessages($l_projectUrl, $l_pageName, $lang);

  # Print HTML.
  printHTML($l_visualization, $l_msg['visualizer4mw-info'], $l_msg['visualizer4mw'], $l_msg['is_rtl']);
}

main();