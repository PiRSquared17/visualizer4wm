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
 ** @brief Set the system messages
 ** @details Get language from url argument and return its messages
 */
function setMessages()
{
  $lang=get("lang", "en");
  require_once("./visualizer4wm.i18n.php");
  return $messages[$lang];
}


/**
 ** @brief Get the page content with MediaWiki API
 ** @param $p_pageName The page name
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org
 ** @details Return the raw content of the page.
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
    exit("Sorry, the page <tt>[[<a href=\"http://$p_projectUrl/wiki/$p_pageName\">$p_pageName</a>]]</tt> does not exist on $p_projectUrl. (You may want to <a href=\"http://$p_projectUrl/w/index.php?title=$p_pageName&amp;action=edit\">start the page <em>$p_pageName</em></a>.)");
  }

  $l_pageSourceCode = substr($l_pageSourceCode,
					  strlen('<rev xml:space="preserve">'),
					  -strlen('</rev></revisions></page></pages></query></api>') );
  return $l_pageSourceCode;
}


/**
 ** @brief Get the wikitable lines from the page content
 ** @param p_content Source code of the wikipage (string)
 ** @param p_templateName The template name
 ** @details Return an array of the wikitable lines:
 ** {|
 ** |+ {{visualizer}}
 ** ! ''en'' !! 2003/0/1 !! East
 ** |-
 ** | [[France]] || 68465 || 26843 
 ** |}
 **
 ** -> to
 **
 ** line[0]:  ! en !! 2003/0/1 !! East
 ** line[1]:  |France || 68465 || 26843
 */
function getWikiTableFromContent($p_content, $p_templateName)
{
  // Remove everything before the template.
  $l_tableContent = strstr($p_content, "{{".ucfirst($p_templateName));
  if (false==$l_tableContent)
  {
    $l_tableContent = strstr($p_content, "{{".lcfirst($p_templateName));
  }
  if (false==$l_tableContent)
  {
    return "error1";
  }

  // Handle multiple tables per page
  $l_id=get('id',1);
  if (1!=$l_id) {
    for ($i=1; $i<$l_id; $i++) {
	$l_tableContent = substr($l_tableContent, 1);
        $l_tableContent = strstr($l_tableContent, "{{".ucfirst($p_templateName));
        if (false==$l_tableContent)
        {
          $l_tableContent = strstr($l_tableContent, "{{".lcfirst($p_templateName));
        }
       if (false==$l_tableContent)
       {
         return "error1";
       }
    }
  }

  // Remove the template.
  $l_tableContent = strstr($l_tableContent, "!");
  if (false==$l_tableContent) exit("Error: Wikicode \"!\" not found in the wikitable after the template $p_templateName. The table columns should have titles using \"!\" instead of \"|\".");

  // Remove everything after the wikitable.
  $l_endingContent = strstr( $l_tableContent, "\n|}");
  if (false==$l_endingContent)
  {
      return "error2";
  }
  $l_tableContent=substr( $l_tableContent, 0, -strlen($l_endingContent) );

  // Clean the wikitable wikisyntax.
  $l_tableContent=cleanWikitableContent($l_tableContent);

  // Return the wikitable lines.
  $l_lines = explode("|-", $l_tableContent);
  return $l_lines;

}


/**
 ** @brief Remove the unwanted wiki syntax 
 ** @param $p_input String to clean
 ** @details Return the input without wikilinks, references, etc.
 **
 */
function cleanWikitableContent($p_input)
{
  // Manage its wikisyntax: remove references, comments, style and alignment.
  $l_regexps = array("&lt;ref(.*)&gt;(.*)&lt;\/ref&gt;",
		      "&lt;ref(.*)\/&gt;",
		      "&lt;!--(.*)--&gt;",
		      "align=(.*)\|",
		      "style=(.*)\|",
		      "width=(.*)\|"
  );
  foreach($l_regexps as $l_regexp) {
    $p_input=removeRegexpMatch($l_regexp,$p_input);
  }

  // Manage its wikisyntax: row separators "|-".
  $p_input=str_replace("----","-",$p_input);
  $p_input=str_replace("---","-",$p_input);
  $p_input=str_replace("--","-",$p_input);

  // Manage its wikisyntax: remove formatting.
  $l_remove = array ("'''","''","{{formatnum:", "&lt;small&gt;", "&lt;/small&gt;");
  foreach($l_remove as $s) {
    $p_input=str_replace( $s,'',$p_input);
  }

  // Manage its wikisyntax: wikilinks.
  $p_regexp = "\[\[(.*)\]\]";
  if(preg_match_all("/$p_regexp/siU", $p_input, $matches, PREG_SET_ORDER)) {
    foreach($matches as $match) {
      $l_linktext = strstr($match[1], "|");
      if (false==$l_linktext) {
	$p_input=str_replace( $match[0],$match[1],$p_input);
      }
      else {
	$l_linktext = trim(substr($l_linktext, 1));
	$p_input=str_replace( $match[0],$l_linktext,$p_input);
      }
    }
  }

  $p_input=str_replace("'","\'",$p_input);

  return $p_input;
}

/**
 ** @brief Remove regexp matches
 ** @param $p_regexp Regular expression
 ** @param $p_input String to clean
 ** @details Return a clean string.
 **
 */
function removeRegexpMatch($p_regexp,$p_input)
{
  $l_remove = array ();
  if(preg_match_all("/$p_regexp/siU", $p_input, $matches, PREG_SET_ORDER)) {
    foreach($matches as $match) {
      if ( !in_array( $match[0], $l_remove)) {
	array_push($l_remove, $match[0]);
      }
    }
  }

  foreach($l_remove as $s) {
    $p_input=str_replace( $s,'',$p_input);
  }

  return $p_input;
}

/**
 ** @brief Clean the chart title
 ** @param $p_input The chart title from the url argument title.
 ** @details Return a clean title.
 **
 */
function cleanChartTitle($p_input)
{

  // Remove any QINU error.
  $l_regexp = "UNIQ(.*)QINU";
  $p_input = removeRegexpMatch($l_regexp,$p_input);

  // Manage its wikisyntax: remove links and formatting.
  $l_remove = array ("[[","]]","'''","''", "<small>", "</small>");
  foreach($l_remove as $s) {
    $p_input=str_replace( $s,'',$p_input);
  }

  // Manage its wikisyntax: replace <br> with space.
  $l_remove = array ("<br />", "<br>");
  foreach($l_remove as $s) {
    $p_input=str_replace( $s," ",$p_input);
  }

  // Manage its simple quotes.
  $p_input=str_replace("'","\'",$p_input);

  return $p_input;
}


/**
 ** @brief Generate the chart from the table lines
 ** @param $p_dataLines Array of lines from the wikitable,
 ** @param $p_ct the chart type,
 ** @param $p_chartTitle the chart title for UI,
 ** @details Return an array of the html and js to be printed.
 **
 */
function generateChartFromTableLines($p_dataLines,$p_ct, $p_chartTitle)
{
  # Get the data.
  $l_data=getDataFromWikitableLines($p_dataLines);

  # Set the numbers of rows and cols.
  $l_nbOfRows = count($l_data)-1;
  $l_nbOfCols = get("columns",count($l_data[0]));
  if (''==$l_nbOfCols ||'all'==$l_nbOfCols) {
    $l_nbOfCols = count($l_data[0]);
  }

  $ChartPackage = 'corechart';
  $l_firstColType = 'string';
  $l_firstColSeparator = "'";
  $firstColumnEnd='';
  $columnEnd ='';

  # Set the chart name and its axis.
  switch ($p_ct) {
      case "pie":
	  $ChartType = 'PieChart';
	  break;
      case "bar":
	  $ChartType = 'BarChart';
	  break;
      case "col":
	  $ChartType = 'ColumnChart';
	  break;
      case "line":
	  $ChartType = 'LineChart';
	  break;
      case "scatter":
	  $ChartType = 'ScatterChart';
	  $l_firstColType = 'number';
	  $l_firstColSeparator = "";
	  $l_hTitle=trim($l_data[0][0]);
	  $l_vTitle=trim($l_data[0][1]);
	  $l_chartAxis =",
		  hAxis: {title: '$l_hTitle'},
		  vAxis: {title: '$l_vTitle'},
		  legend: 'none'";
	  break;
      case "area":
	  $ChartType = 'AreaChart';
	  $l_hTitle=trim($l_data[0][0]);
	  $l_chartAxis =",
		    hAxis: {title: '$l_hTitle'}";
	  break;
      case "geomap":
	  $ChartPackage='geomap';
	  $ChartType = 'GeoMap';
	  break;
      case "intensitymap":
	  $ChartPackage='intensitymap';
	  $ChartType = 'IntensityMap';
	  $firstColumnEnd=", 'Country'";
	  $columnEnd=array('a','b','c','d','e','f','g','h','i','j','k','l');
	  break;
      case "sparkline":
	  $ChartPackage='imagesparkline';
	  $ChartType = 'ImageSparkLine';
	  $l_firstColType = 'number';
	  $l_firstColSeparator = "";
	  $l_chartAxis = ", showAxisLines: true,  showValueLabels: true, labelPosition: 'right'";
  }

  # Set the columns of data.
  $javascriptColumns = Array();
  $jsCol = sprintf("data.addColumn('%s', '%s'%s)", $l_firstColType, trim($l_data[0][0]), $firstColumnEnd);
  array_push($javascriptColumns, $jsCol);
  for ($i = 1; $i < $l_nbOfCols; $i++)
  {
    if (is_array($columnEnd)) {
      $addColumnEnd=", '".$columnEnd[$i-1]."'";
    }
    $jsCol = sprintf("data.addColumn('number', '%s'%s)", trim($l_data[0][$i]), $addColumnEnd);
    array_push($javascriptColumns, $jsCol);

  }
  $l_cols = implode(";\n", $javascriptColumns).';';


  # Clean the rows of data to get float values.
  $l_colStarter = 1;
  if ('number'==$l_firstColType) {
  $l_colStarter = 0;
  }
  for ($i = 0; $i < $l_nbOfRows; $i++)
  {
    for ($j = $l_colStarter; $j < $l_nbOfCols; $j++)
    {
    $l_data[$i+1][$j] = str_replace(",", ".", trim($l_data[$i+1][$j]));
    $l_data[$i+1][$j] = str_replace(" ", "", $l_data[$i+1][$j]);
    $l_data[$i+1][$j] = floatval($l_data[$i+1][$j]);
    }
  }

  # Set the rows of data.
  $javascriptRows = Array();
  for ($i = 0; $i < $l_nbOfRows; $i++)
  {
    $j = 0;
    $jsRow = sprintf("data.setValue(%s, %s, %s%s%s)", $i, $j, $l_firstColSeparator, trim($l_data[$i+1][$j]), $l_firstColSeparator );
    array_push($javascriptRows, $jsRow);

    for ($j = 1; $j < $l_nbOfCols; $j++)
    {
    $jsRow = sprintf("data.setValue(%s, %s, %s)", $i, $j, trim($l_data[$i+1][$j]) );
    array_push($javascriptRows, $jsRow);
    }
  }
  $l_rows = implode(";\n", $javascriptRows).";";

  $l_height = get("height",'500');
  $l_width = get("width",'1000');

  # Set the javaScript.
  $l_jsChart = <<<MYJSCODE
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["$ChartPackage"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = new google.visualization.DataTable();
        $l_cols
        data.addRows($l_nbOfRows);
	$l_rows

        var chart = new google.visualization.$ChartType(document.getElementById('chart_div'));
        chart.draw(data, {width: $l_width, height:$l_height,
			  title: '$p_chartTitle'$l_chartAxis
			  });
      }
    </script>
MYJSCODE;

  # Set the html.
  $l_htmlChart = '<div id="chart_div"></div>';

  return array($l_jsChart, $l_htmlChart);
}

/**
 ** @brief Get data from the wikitable lines
 ** @param $p_dataLines Array of lines from the wikitable,
 ** @details Return an array of the data.
 **
 */
function getDataFromWikitableLines($p_dataLines)
{
  $l_data=array();
  $l_lineIndex = -1;
  foreach ($p_dataLines as $l_line)
  {
    if (""==$l_line) continue; // Empty lines are ignored.
    $l_lineIndex += 1;

    $l_line=substr(trim($l_line),1); // Remove the starting "|" or "!".

    if ($l_lineIndex == 0) // First line.
    {
      $l_data[$l_lineIndex] = explode("!!", $l_line);
      if (count($l_data[$l_lineIndex]) < 2)
      {
	$l_data[$l_lineIndex] = explode("\n!", $l_line);
	if (count($l_data[$l_lineIndex]) < 2) continue;
      }
    } 
    else		// Other lines.
    {
      $l_data[$l_lineIndex] = explode("||", $l_line);
      if (count($l_data[$l_lineIndex]) < 2)
      {
	$l_data[$l_lineIndex] = explode("\n|", $l_line);
      }
    }
  }
  return $l_data;
}


/**
 ** @brief motionChart only - Generate javascript rows of data from the page content
 ** @param $p_content The raw content of the page
 ** @details For motion chart:
 ** {{visualize|
 **  {{dataset| en | 2003/0/1 | 10000 | 20000 | East }}
 **  {{dataset| fr | 2003/0/1 | 5000 | 10000 | West }}
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
 ** @brief motionChart only - Set the js and the html to be printed
 ** @param $p_pageName,
 ** @param $p_displayedPageName,
 ** @param $p_projectUrl,
 ** @param $p_javaScriptRows,
 ** @param $p_groupName,
 ** @param $p_xAxisCaption,
 ** @param $p_yAxisCaption
 ** @details For motion chart: return an array of the js and html to be printed.
 **
 */
function motionChart_setJsAndHtml($p_pageName, $p_displayedPageName, $p_projectUrl, $p_javascriptRows, $p_groupName, $p_xAxisCaption, $p_yAxisCaption)
{
  $l_htmlcode = <<<MYHMTLCODE
    <div id="info" class="noLinkDecoration">
      Data source is
      <a href="http://$p_projectUrl/wiki/$p_pageName">$p_displayedPageName</a> on $p_projectUrl.
    </div>
    <div id="chart_div"></div>
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
 ** @brief Echo HTML document
 ** @param $p_javaScriptCode 
 ** @param $p_htmlCode
 ** @param $p_title Document title and header
 ** @details Print valid XHTML
 **
 */
function printHTML($p_javaScriptCode="",
                   $p_htmlCode="", $p_title="Wikitable Visualizer")
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
	<a href="?page=User:Al_Maghi/Visualize_Wikipedias_growth_up_to_2010&amp;project=en.wikipedia.org&amp;tpl=visualize&amp;y=Bytes+per+article&amp;x=Articles&amp;group=Wikipedias">
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
    <link rel="stylesheet" href="./visualizer4wm.css" type="text/css" media="screen" />
  </head>
  <body>
    <div id="mw_header"><a href="./visualizer4wm.php">$p_title</a><br /><span>Wikimedia toolserver</span></div>
    <div id="main">$p_htmlCode</div>
    <div id="documentation">
    	<tt>{{<a href="./index.html">Visualizer</a>}}</tt> &nbsp;
      &#8734; &nbsp; The data visualizer tool is kindly served to you by the <span class="hidden"><a href="http://toolserver.org/">Wikimedia Toolserver</a>.
      	It uses the <a href="http://mediawiki.org/wiki/API">MediaWiki</a>
      	and the <a href="http://code.google.com/intl/fr-FR/apis/visualization/interactive_charts.html">Vizualisation</a></span> APIs.
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
  $l_displayedPageName = str_replace('_',' ',$l_pageName);

  $l_msg=setMessages();
  $l_title= $l_msg['visualizer4mw'];

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

    'chart types'		=>	array('pie','bar', 'col', 'line', 'scatter', 'area', 'geomap', 'intensitymap', 'sparkline'),
  );

  # Get the project url and check its domain name.
  $l_projectUrl = get("project", "en.wikipedia.org");
  $l_projectDomain = substr(strstr($l_projectUrl, '.'),1);
  if ( !in_array( $l_projectDomain, $l_parameters['authorised domains'])) {
    exit("Sorry but <a href=\"http://$l_projectDomain\">$l_projectDomain</a> is not an authorised domain name. (Contact us if you want to authorise a new domain name.)<br />The <tt>project</tt> parameter should be something such as en.wikipedia.org.");
  }

  # Try to get content from MediaWiki or exit.
  $l_pageContent = getContentFromMediaWiki($l_pageName,$l_projectUrl);

  # Get the template name.
  $l_templateName = get("tpl", "visualize");

  # Generate the chart and print it.
  if ("visualize"==$l_templateName)
  {
    $l_javascriptRows = motionChart_generateJsFromContent($l_pageContent);
    if (null==$l_javascriptRows) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> is not using correctly {{dataset}} and {{visualize}}.<br />Check the template parameter <tt>tpl=$l_templateName</tt>");
    }
    $l_xAxisCaption  = get("x",     "x axis");
    $l_yAxisCaption  = get("y",     "y axis");
    $l_groupName     = get("group", "Labels");
    $l_jsHtml = motionChart_setJsAndHtml($l_pageName, $l_displayedPageName, $l_projectUrl, $l_javascriptRows, $l_groupName, $l_xAxisCaption, $l_yAxisCaption);
    printHTML($l_jsHtml['js'], $l_jsHtml['html']);
  }
  else
  {
    # Get the chart type and check it.
    $l_chartType  = get("ct", "pie");
    if ( !in_array( $l_chartType, $l_parameters['chart types'])) {
      exit("Sorry but the chart type \"$l_chartType\" is not valid.");
    }

    # Get the chart title.
    $l_chartTitle = get("title", $l_displayedPageName);
    if ( $l_chartTitle != $l_displayedPageName)
    {
      $l_chartTitle=cleanChartTitle($l_chartTitle);
    }

    # Try to get data from content or return an error.
    $l_dataLines = getWikiTableFromContent($l_pageContent,$l_templateName);
    if ('error1'==$l_dataLines) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> does not contain the string: <tt>{{".$l_templateName."</tt><br />Check the template parameter <tt>tpl=</tt>");
    }
    if ('error2'==$l_dataLines) {
      exit("Sorry, the page <a href=\"http://$l_projectUrl/wiki/$l_pageName\">$l_displayedPageName</a> does not contain the line: <tt>|}</tt>");
    }

    # Generate the chart js and html.   
    $l_Chart=generateChartFromTableLines($l_dataLines, $l_chartType, $l_chartTitle);

    $l_htmlChart=$l_Chart[1];
    $l_jscode=$l_Chart[0];

    $l_info=str_replace('$1', "<a href='http://$l_projectUrl/wiki/$l_pageName'>$l_displayedPageName</a>", $l_msg['visualizer4mw-info']);
    $l_info=str_replace('$2', $l_projectUrl, $l_info);

    $l_htmlcode = <<<MYHMTLCODE
    <div id="info" class="noLinkDecoration">$l_info</div>
    $l_htmlChart
MYHMTLCODE;

    printHTML($l_jscode,$l_htmlcode,$l_title);
  }
}

main();
