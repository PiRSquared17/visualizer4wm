<?php
/** @file visualizer4wmChart.php
 ** @brief Chart generator
 ** @details Generate the javascript to visualize a wikitable.
 **/


/**
 ** @brief Generate the chart from the page content
 ** @param p_pageContent The raw content of the wikipage.
 ** @param p_templateName The visualizer template name.
 ** @param $p_projectUrl The project url, eg. en.wikipedia.org.
 ** @param $p_pageName The page name.
 ** @return the javaScript chart code.
 */
function ChartGenerator($p_pageContent, $p_templateName, $p_projectUrl, $p_pageName)
{
  $l_displayedPageName = str_replace('_',' ',$p_pageName);

  # Get the chart type and check it.
  $l_chartType  = get("ct", "pie");
  $l_chartTypes = array('pie','bar', 'col', 'line', 'scatter', 'area', 'geomap', 'intensitymap', 'sparkline');
  if ( !in_array( $l_chartType, $l_chartTypes)) {
    exit("Sorry but the chart type \"$l_chartType\" is not valid.");
  }

  # Try to get data from content or exit.
  $l_dataLines = getWikiTableFromContent($p_pageContent,$p_templateName);
  if (!is_array($l_dataLines))
  {
    switch ($l_dataLines) {
      case 'no template':
	exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not contain the string: <tt>{{".$p_templateName."</tt><br />Check the template parameter <tt>tpl=</tt>");
	break;
      case 'no table ending':
	exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not contain the line: <tt>|}</tt>");
	break;
      case 'not enough templates':
	exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> does not include the template ".$p_templateName." <b>as many times as requested</b>.<br />Check the template id parameter <tt>id=</tt>");
	break;
    }
  }
  # Generate the js code.
  $l_jscode = generateChartFromTableLines($l_dataLines, $l_chartType, $l_displayedPageName);
  # Return the js code.
  return $l_jscode;
}


/**
 ** @brief Get the wikitable lines from the page content
 ** @param p_pageContent The raw content of the wikipage.
 ** @param p_templateName The visualizer template name.
 ** @details Return an array of the wikitable lines:
 **
 ** <code>|+ {{visualizer}}
 ** ! en !! 2003/0/1 !! East</code>
 **
 ** to
 **
 ** line[0]:  ! en !! 2003/0/1 !! East
 ** line[1]:  ...
 */
function getWikiTableFromContent($p_pageContent, $p_templateName)
{
  // Remove everything before the template.
  $l_tableContent = strstr($p_pageContent, "{{".ucfirst($p_templateName));
  if (false==$l_tableContent)
  {
    $l_tableContent = strstr($p_pageContent, "{{".lcfirst($p_templateName));
    if (false==$l_tableContent) { return "no template"; }
  }

  // Handle multiple tables per page
  $l_id=get('id',1);
  for ($i=1; $i<$l_id; $i++)
  {
    $l_tableContent = substr($l_tableContent, 1);
    $l_tableContent = strstr($l_tableContent, "{{".ucfirst($p_templateName));
    if (false==$l_tableContent)
    {
      $l_tableContent = strstr($l_tableContent, "{{".lcfirst($p_templateName));
      if (false==$l_tableContent) { return "not enough templates"; }
    }
  }

  // Remove the template.
  $l_tableContent = strstr($l_tableContent, "!");
  if (false==$l_tableContent) exit("Error: Wikicode \"!\" not found in the wikitable after the template $p_templateName. The table columns should have titles using \"!\" instead of \"|\".");

  // Remove everything after the wikitable.
  $l_endingContent = strstr( $l_tableContent, "\n|}");
  if (false==$l_endingContent) { return "no table ending"; }
  $l_tableContent=substr( $l_tableContent, 0, -strlen($l_endingContent) );

  // Clean the wikitable wikisyntax.
  $l_tableContent=cleanWikitableContent($l_tableContent);

  // Return the wikitable lines.
  $l_lines = explode("|-", $l_tableContent);
  return $l_lines;
}


/**
 ** @brief Remove the unwanted wiki syntax 
 ** @param $p_input The string to clean.
 ** @details Return the input without wikilinks, references, etc.
 **
 */
function cleanWikitableContent($p_input)
{
  // Manage its wikisyntax: remove references, comments, style, alignment and sub/sup.
  $l_regexps = array("&lt;ref(.*)&gt;(.*)&lt;\/ref&gt;",
		      "&lt;ref(.*)\/&gt;",
		      "&lt;!--(.*)--&gt;",
		      "align=(.*)\|",
		      "style=(.*)\|",
		      "width=(.*)\|",
		      "scope=(.*)\|",
		      "&lt;sup&gt;(.*)&lt;\/sup&gt;",
		      "&lt;sub&gt;(.*)&lt;\/sub&gt;"
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
 ** @param $p_regexp Regular expression.
 ** @param $p_input The string to clean.
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
 ** @brief Generate the chart from the table lines
 ** @param $p_dataLines Array of lines from the wikitable.
 ** @param $p_ct the chart type.
 ** @param $p_displayedPageName The displayed page name.
 ** @details Return the js to be printed.
 **
 */
function generateChartFromTableLines($p_dataLines,$p_ct, $p_displayedPageName)
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

  # Get the chart size and title.
  $l_chartTitle = getChartTitle($p_displayedPageName);
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
			  title: '$l_chartTitle'$l_chartAxis
			  });
      }
    </script>
MYJSCODE;

  return $l_jsChart;
}

/**
 ** @brief Get data from the wikitable lines
 ** @param $p_dataLines Array of lines from the wikitable.
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
 ** @brief Get the chart title
 ** @param $p_default The default chart title.
 ** @details Return the chart title.
 **
 */
function getChartTitle($p_default)
{
  $l_chartTitle = get("title", $p_default);
  if ( $l_chartTitle != $p_default)
  {
    // Remove any QINU error.
    $l_regexp = "UNIQ(.*)QINU";
    $l_chartTitle = removeRegexpMatch($l_regexp,$l_chartTitle);

    // Manage its wikisyntax: remove links and formatting.
    $l_remove = array ("[[","]]","'''","''", "<small>", "</small>");
    foreach($l_remove as $s) {
      $l_chartTitle=str_replace( $s,'',$l_chartTitle);
    }
    // Manage its wikisyntax: replace <br> with space.
    $l_remove = array ("<br />", "<br>");
    foreach($l_remove as $s) {
      $l_chartTitle=str_replace( $s," ",$l_chartTitle);
    }
    // Manage its simple quotes.
    $l_chartTitle=str_replace("'","\'",$l_chartTitle);
  }
  return $l_chartTitle;
}