<?php
/** @file visualizer4wm.php
 ** @brief Visualize data published on a wikimedia project
 ** @details Visualize data using MediaWiki and charting APIs.
 ** It offers visualization of data published on a wikipage using some authorised templates.
 ** Authors include [[w:fr:User:Al Maghi]] and Xavier Marcelet.
 **/


/**
 ** @brief get url parameter
 ** @param p_name name of arg
 ** @param p_default default value of arg
 ** @details get parameter from url arguments
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
 ** @param $p_pageName	 The page name
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org
 ** @details Return raw content.
 **
 */
function getContentFromMediaWiki ($p_pageName,$p_projectUrl)
{
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
    exit("Sorry, the page <tt>[[<a href=\"http://$p_projectUrl/wiki/$p_pageName\">$p_pageName</a>]]</tt> does not exist on $p_projectUrl. (You may want to <a href=\"http://$p_projectUrl/w/index?title=$p_pageName&amp;action=edit\">start the page <em>$p_pageName</em></a>.)");
  }

  $l_pageSourceCode = substr($l_pageSourceCode,
					  strlen('<rev xml:space="preserve">'),
					  -strlen('</rev></revisions></page></pages></query></api>') );
  return $l_pageSourceCode;
}


/**
 ** @brief motionChart only - Return javascript rows of data
 ** @param $p_content page content
 ** @details
 ** MediaWiki template sample:
 **
 ** {{visualize|
 **  {{dataset| en | 2003/0/1 | 10000 | 20000 | East }}
 **  {{dataset| fr | 2003/0/1 | 5000 | 10000 | West }}
 ** }}
 **
 **     to ->
 **
 ** ['fr',new Date (2003,0,1),5000,10000,'West'],
 ** ['it',new Date (2003,0,1),3000,7000,'East'],
 */
function motionChart_generateJsFromContent($p_content)
{

  $datasetPatten = "{{\s*dataset\s*\|\s*([^|]*?)\s*\|\s*([^|]*?)\s*\|\s*([^|]*?)\s*\|\s*([^|]*?)\s*\|\s*([^|]*?)\s*}}";
  $pattern = sprintf("/{{\s*visualize\s*\|.*?(%s)+.*}}/misSU", $datasetPatten);
  preg_match_all($pattern, $p_content, $matches);
  if (!count($matches))
    return null;
  if (!count($matches[0]))
    return null;
  preg_match_all(sprintf("/%s/misSu", $datasetPatten), $matches[0][0], $matchedData, PREG_SET_ORDER);

  $l_pregMatchedData=$matchedData;

  if (null==$l_pregMatchedData)
      return null;

  $javascriptRows = Array();

  for ($i = 0; $i < count($l_pregMatchedData); $i++)
  {
    $id = trim($l_pregMatchedData[$i][1]);
    $date = trim($l_pregMatchedData[$i][2]);
    $xData = trim($l_pregMatchedData[$i][3]);
    $yData = trim($l_pregMatchedData[$i][4]);
    $label = trim($l_pregMatchedData[$i][5]);
    sscanf($date, "%d/%d/%d", $dateYear, $dateMonth, $dateDay);
    $jsRow = sprintf("['%s',new Date(%d,%d,%d),%d,%d,'%s']", $id, $dateYear, $dateMonth, $dateDay, $xData, $yData, $label);
    array_push($javascriptRows, $jsRow);
  }
  return implode(",\n", $javascriptRows);
}


/**
 ** @brief motionChart only - Set the js and the html to be printed.
 ** @param $p_pageName
 ** @param $p_projectUrl,
 ** @param $p_javaScriptRows,
 ** @param $p_groupName,
 ** @param $p_xAxisCaption,
 ** @param $p_yAxisCaption
 ** @details Retrun an array of js and html.
 **
 */
function motionChart_setJsAndHtml($p_pageName, $p_projectUrl, $p_javascriptRows, $p_groupName, $p_xAxisCaption, $p_yAxisCaption)
{
  $p_displayedPageName = str_replace('_', ' ', $p_pageName);

  $l_htmlcode = <<<MYHMTLCODE
    <div id="chart_div"></div>
    <p></p>
    <div id="info" class="noLinkDecoration">
      Data source is
      <a href="http://$p_projectUrl/wiki/$p_pageName">$p_displayedPageName</a> on $p_projectUrl.
    </div>
MYHMTLCODE;

  $l_jscode = <<<MYJSCODE
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>

    <script type="text/javascript">
        google.load('visualization', '1', {'packages':['motionchart']});

        google.setOnLoadCallback(drawChart);

        function drawChart()
        {
          var data = new google.visualization.DataTable();
          data.addColumn('string', 'Project');
          data.addColumn('date', 'Date');
          data.addColumn('number', '$p_xAxisCaption');
          data.addColumn('number', '$p_yAxisCaption');
          data.addColumn('string', '$p_groupName');
          data.addRows([
$p_javascriptRows
          ]);
          var chart = new google.visualization.MotionChart(document.getElementById('chart_div'));
          chart.draw(data, {width: 600, height:300});
      }
    </script>
MYJSCODE;
	      return array('html'=>$l_htmlcode,'js'=>$l_jscode);
}


/**
 ** @brief Return an array of data from the wiki source code
 ** @param p_content source code of the wikipage (string)
 ** @param p_templateName template name
 ** @details
 ** {|
 ** |+ text {{visualizer| }}
 ** ! en !! 2003/0/1 !! East
 ** |-
 ** | dataset || 68465 || 26843 
 ** |}
 **
 ** -> to
 **
 ** line[0]:  {{visualizer|text}} ! date !! A !! B !! C
 ** line[1]:  |2009/10/01 || 2464585 || 2325667 || 857585 
 */
function getDataFromContent($p_content, $p_templateName)
{

    $l_tableContent = strstr($p_content, "{{".ucfirst($p_templateName));
    if (false==$l_tableContent)
    {
      $l_tableContent = strstr($p_content, "{{".lcfirst($p_templateName));
    }
    if (false==$l_tableContent)
    {
      return "error1";
    }

    $l_endingContent = strstr( $l_tableContent, "\n|}");
    if (false==$l_endingContent)
    {
       return "error2";
    }
    $l_tableContent=substr( $l_tableContent, 0, -strlen($l_endingContent) );

    $l_line = explode("|-", $l_tableContent);
    return $l_line;

}


/**
 ** @brief Print HTML
 ** @param $p_javaScriptCode 
 ** @param $p_htmlCode 
 ** @details Print valid XHTML
 **
 */
function printHTML($p_javaScriptCode="",
                   $p_htmlCode="")
{

  if (''==$p_htmlCode) {
    $p_htmlCode='<div id="index">Welcome on this tool. It runs just as a proof of concept. <a href="?page=User:Al_Maghi/Visualize_Wikipedias_growth_up_to_2010&amp;project=en.wikipedia.org&amp;tpl=visualize&amp;y=Bytes+per+article&amp;x=Articles&amp;group=Wikipedias">
		  See it in action</a>.</div>';
  }

  header('Content-type: text/html; charset=UTF-8');

  echo <<<MYHMTLPAGE
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8"/>
    <title>Data Visualizer: visualize the data published on a wikipage</title>
    $p_javaScriptCode
    <link rel="stylesheet" href="./visualizer4wm.css" type="text/css" media="screen" />
  </head>
  <body>
    <h3>Data Visualizer</h3>
    $p_htmlCode
    <div id="documentation">
    	<tt>{{<a href="http://meta.wikipedia.org/wiki/visualizer4wm">Visualizer</a>}}</tt> &nbsp;
      &#8734; &nbsp; The data visualizer tool is kindly served to you by the <span class="hidden"><a href="http://toolserver.org/">Wikimedia Toolserver</a>.
      	It uses the <a href="http://mediawiki.org/wiki/API">MediaWiki</a>
      	and the <a href="http://code.google.com/intl/fr-FR/apis/visualization/documentation/gallery/motionchart.html">Vizualisation</a></span> APIs.
    </div>
  </body>
</html>
MYHMTLPAGE;
}


/**
 ** @brief The main function
 ** @details Set user_agent, fetch url args, check parameters, get source, get data from source, generate JavaScript and html, print valid xhtml.
 */
function main()
{

  ini_set('user_agent', 'Al Maghi\'s visualizer4wm.php script');

  # Get the page name or print default html.
  $l_pageName   = str_replace(' ','_',get("page", "_"));
  if ("_"==$l_pageName || ""==$l_pageName) {
    exit(printHTML());
  }

  # Set the local parameters.
  $l_parameters = array(
    'authorised domains'	=>	array('wikipedia.org',
					  'wikimedia.org',
					  'wikibooks.org',
					  'wikiquote.org',
					  'mediawiki.org',
					  'wikinews.org',
					  'wiktionary.org',
					  'wikisource.org',
					  'wikiversity.org'),

    'templates'		=>	array('visualize', 'visualizer'),

     // Example types are found here: http://code.google.com/intl/fr-FR/apis/chart/docs/gallery/chart_gall.html
    'chart types'		=>	array('pie',
					  // Bars
					  'bhs', 'bvs', 'bhg', 'bvg', 'bvo',
					  // Lines
					  'lines',
					  // Venne
					  'venne',
					  // Motion
					  'gglemotion',
					),
    'apis'			=>	array(
					    // Google Chart image api
					    'gc',
					    // Google Visualization interactive api
					    'gv',
					    // PHP chart api
					    'pchart',
					 ),
  );

  # Get the project url and check its domain name.
  $l_projectUrl = get("project", "en.wikipedia.org");
  $l_projectDomain = substr(strstr($l_projectUrl, '.'),1);
  if ( !in_array( $l_projectDomain, $l_parameters['authorised domains'])) {
    exit("Sorry but <a href=\"http://$l_projectDomain\">$l_projectDomain</a> is not an authorised domain name. (Contact us if you want to authorise a new domain name.)<br />The <tt>project</tt> parameter should be something such as en.wikipedia.org.");
  }

  # Get the template type and check it.
  $l_templateType  = get("tpl", "visualize");
  if ( !in_array( $l_templateType, $l_parameters['templates'])) {
    exit("Sorry but the template parameter <tt>tpl=$l_templateType</tt> is not an authorised template.");
  }


  # Try to get content from MediaWiki or exit.
  $l_pageContent = getContentFromMediaWiki($l_pageName,$l_projectUrl);


  $l_displayedPageName = str_replace('_',' ',$l_pageName);
  # Generate the chart and print it.
  if ("visualize"==$l_templateType)
  {
    $l_javascriptRows = motionChart_generateJsFromContent($l_pageContent);
    if (null==$l_javascriptRows) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> is not using correctly {{dataset}} and {{visualize}}.<br />Check the template parameter <tt>tpl=$l_templateType</tt>");
    }
    $l_xAxisCaption  = get("x",     "x axis");
    $l_yAxisCaption  = get("y",     "y axis");
    $l_groupName     = get("group", "Labels");
    $l_jsHtml = motionChart_setJsAndHtml($l_pageName, $l_projectUrl, $l_javascriptRows, $l_groupName, $l_xAxisCaption, $l_yAxisCaption);
    printHTML($l_jsHtml['js'], $l_jsHtml['html']);
  }
  else
  {
    # Try to get data from content or return an error.
    $l_dataLines = getDataFromContent($l_pageContent,$l_templateType);
    if ('error1'==$l_dataLines) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> does not contain the string: <tt>{{Visualizer</tt><br />Check the template parameter <tt>tpl=$l_templateType</tt>");
    }
    if ('error2'==$l_dataLines) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> does not contain the line: <tt>|}</tt>");
    }

    /*
    # Get the chart type and check it.
    $l_chartType  = get("ct", "pie");
    if ( !in_array( $l_chartType, $l_parameters['chart types'])) {
      exit("Sorry but the chart type \"$l_chartType\" is not valid.");
    }

    # Get the api and check if it handles the chart type.
    $l_apiType  = get("api", "gc");
    if ( !in_array( $l_apiType, $l_parameters['apis'])) {
      exit("Sorry but the api \"$l_apiType\" is not valid.");
    }
    */
  $l_htmlcode = <<<MYHMTLCODE
    <p>$l_dataLines[0]<br/>$l_dataLines[1]</p>
    <div id="info" class="noLinkDecoration">
      Data source is
      <a href="http://$l_projectUrl/wiki/$l_pageName">$l_displayedPageName</a> on $l_projectUrl.
    </div>
MYHMTLCODE;

    printHTML('',$l_htmlcode);
  }
}

main();
?>
