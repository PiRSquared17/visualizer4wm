<?php
/** @file visualizer4wm.php
 ** @brief Visualize data published on a wikimedia project
 ** @details Visualize data using MediaWiki and charting APIs.
 ** It offers visualization of data published on a wikipage.
 ** Authors include [[w:fr:User:Al Maghi]] and Xavier Marcelet.
 **/


/**
 ** @brief Get parameter
 ** @param p_name Name of the url arg
 ** @param p_default Default value for the parameter
 ** @details Get parameter from url arguments
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
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org
 ** @param $p_pageName The page name
 ** @details Return the raw content of the page.
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
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org
 ** @param $p_pageName The page name
 ** @details Get the template name, query MediaWiki API, parse source and generate JavaScript.
 */
function run_visualizer($p_projectUrl, $p_pageName)
{
  $l_displayedPageName = str_replace('_',' ',$p_pageName);

  # Get the template name or exit.
  $l_templateName = get("tpl", "_");
  if ("_"==$l_templateName || ""==$l_templateName) { exit("Add the visualizer template name to your http request: <tt>&tpl=templateName</tt>"); }

  # Try to get content from MediaWiki or exit.
  $l_pageContent = getContentFromMediaWiki($p_projectUrl, $p_pageName);

  # Run...
  if ("motionchart"==$l_templateName)
  {
    require_once("./visualizer4wm-motionchart.php");

    # Generate the js code for motion chart.
    $l_javascriptRows = motionChart_generateJsFromContent($l_pageContent);
    if (null==$l_javascriptRows) {
      exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> is not using correctly {{dataset}} and {{visualize}}.<br />Check the template parameter <tt>tpl=$l_templateName</tt>");
    }
    $l_jscode = motionChart_setJs($l_javascriptRows);
  }
  else
  {
    # Get the chart type and check it.
    $l_chartType  = get("ct", "pie");
    $l_chartTypes = array('pie','bar', 'col', 'line', 'scatter', 'area', 'geomap', 'intensitymap', 'sparkline');
    if ( !in_array( $l_chartType, $l_chartTypes)) {
      exit("Sorry but the chart type \"$l_chartType\" is not valid.");
    }

    require_once("./visualizer4wm-chart.php");

    # Try to get data from content or exit.
    $l_dataLines = getWikiTableFromContent($l_pageContent,$l_templateName);
    if (!is_array($l_dataLines))
    {
      switch ($l_dataLines) {
	case 'no template':
	  exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not contain the string: <tt>{{".$l_templateName."</tt><br />Check the template parameter <tt>tpl=</tt>");
	  break;
	case 'no table ending':
	  exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not contain the line: <tt>|}</tt>");
	  break;
	case 'not enough templates':
	  exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not include the template ".$l_templateName." <b>as many times as requested</b>.<br />Check the template id parameter <tt>id=</tt>");
	  break;
      }
    }
    # Generate the js code.
    $l_jscode = generateChartFromTableLines($l_dataLines, $l_chartType, $l_displayedPageName);
  }

  # Return the js code.
  return $l_jscode;
}


/**
 ** @brief Set the system messages
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org
 ** @param $p_pageName The page name
 ** @details Get language and return its messages
 */
function setMessages($p_projectUrl,$p_pageName)
{
  require_once("./visualizer4wm.i18n.php");
  $lang=get("lang", "en");
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
 ** @param $p_javaScriptCode 
 ** @param $p_htmlCode
 ** @param $p_title Document title and header
 ** @param $p_ltr left-to-right or rtl language
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
    <link rel="stylesheet" href="./visualizer4wm$p_ltr.css" type="text/css" media="screen" />
  </head>
  <body>
    <div id="mw_header"><a href="./visualizer4wm.php">$p_title</a><br /><span>Wikimedia toolserver</span></div>
    <div id="main">$p_htmlCode</div>
    <div id="documentation">
    	<span class="hidden"><tt>{{<a href="./index.html">Visualizer</a>}}</tt> &nbsp;
      &#8734; &nbsp; <a href="http://meta.wikimedia.org/wiki/visualizer4wm">documentation</a> &nbsp;
      &#8734; &nbsp; The visualizer tool is kindly served to you by the <a href="http://toolserver.org/">Wikimedia Toolserver</a>.
      	It uses the <a href="http://mediawiki.org/wiki/API">MediaWiki</a>
      	and the <a href="http://code.google.com/intl/en-EN/apis/visualization/interactive_charts.html">Vizualisation</a></span> APIs.
    </div>
  </body>
</html>
MYHMTLPAGE;
}


/**
 ** @brief The main function
 ** @details Print default html or run visualizer, set messages and print valid xhtml.
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
  $l_jscode = run_visualizer($l_projectUrl, $l_pageName);

  # Set the i18n messages.
  $l_msg = setMessages($l_projectUrl, $l_pageName);

  # Print HTML.
  printHTML($l_jscode,$l_msg['visualizer4mw-info'],$l_msg['visualizer4mw'],$l_msg['is_rtl']);
}

main();