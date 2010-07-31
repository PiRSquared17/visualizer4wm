<?php
/** @file visualizer4wm-motionchart.php
 ** @brief Visualize data published on a wikimedia project
 ** @details Visualize data using MediaWiki and charting APIs.
 ** It offers visualization of data published on a wikipage.
 ** Authors include [[w:fr:User:Al Maghi]] and Xavier Marcelet.
 **/


/**
 ** @brief Get the wikitable lines from the page content
 ** @param p_content Source code of the wikipage (string)
 ** @details Return an array of the wikitable lines:
 */
function MotionchartGenerator($p_pageContent, $p_projectUrl, $p_pageName)
{
  $l_javascriptRows = motionChart_generateJsFromContent($p_pageContent);
  if (null==$l_javascriptRows) {
    $l_displayedPageName = str_replace('_',' ',$p_pageName);
    exit("Sorry, the page <a href=\"http://$p_projectUrl/wiki/$p_pageName\">$l_displayedPageName</a> is not using correctly {{dataset}} and {{visualize}}.<br />Check the template parameter <tt>tpl=$l_templateName</tt>");
  }
  $l_jscode = motionChart_setJs($l_javascriptRows);
  return $l_jscode;
}



/**
 ** @brief motionChart only - Generate javascript rows of data from the page content
 ** @param $p_content The raw content of the page
 ** @details For motion chart:
 ** {{motionchart|
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
  $pattern = sprintf("/{{\s*motionchart\s*\|.*?(%s)+.*}}/misSU", $datasetPatten);
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
 ** @brief motionChart only - Set the js to be printed
 ** @param $p_javaScriptRows,
 ** @param $p_groupName,
 ** @param $p_xAxisCaption,
 ** @param $p_yAxisCaption
 ** @details For motion chart: return the js to be printed.
 **
 */
function motionChart_setJs($p_javascriptRows)
{
  $l_xAxisCaption  = get("x",     "x axis");
  $l_yAxisCaption  = get("y",     "y axis");
  $l_groupName     = get("group", "Labels");
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
          data.addColumn('number', '$l_xAxisCaption');
          data.addColumn('number', '$l_yAxisCaption');
          data.addColumn('string', '$l_groupName');
          data.addRows([
$p_javascriptRows
          ]);
          var chart = new google.visualization.MotionChart(document.getElementById('chart_div'));
          chart.draw(data, {width: 600, height:300});
      }
    </script>
MYJSCODE;
  return $l_jscode;
}