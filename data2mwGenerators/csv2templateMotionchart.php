<?php
/** @file csv2templateMotionchart.php
 ** @brief Generate mediawiki formated data.
 ** @details Generate mediawiki formated data using {{Motionchart}} and {{dataset}}.
 ** Authors include [[w:fr:User:Al Maghi]] and Xavier Marcelet.
 **/

/**
 ** @brief Print HTML
 ** @details Print valid XHTML
 **
 */
function printHTML($p_PrintedContent)
{
  header('Content-type: text/html; charset=UTF-8');

  if ("" == $p_PrintedContent) 
  {
    $p_PrintedContent = '
    <h3>csv2templateMotionchart - Convert a csv file to formated data for {{<a href="http://en.wikipedia.org/wiki/Template:Motionchart">Motionchart</a>}}</h3>

    <div id="main">
      <p>Your table should have 4 or 5 columns: <tt>id</tt>, <tt>date</tt>, <tt>x</tt>, <tt>y,</tt> <tt>group</tt>. An example csv content would be:</p>
      <div class="pre">
	  id, date, Xvalue, Yvalue, group<br />
	  Paris, december 2003, 2846546, 654645, Europe<br />
	  London, 2006-7-35 22:00:00, 546544, 5646548, Europe<br />
      </div>
      <span class="info">The first line of your file will be ignored.</span>
      <span class="info">Dates should be in english or in digit.</span>
      <span class="info">The fifth column (<tt>group</tt>) is optional. If none, group value will be equal to id.</span>
      <span class="info">Semicolons can replace commas. Double quotes will be removed.</span>

      <form method="post" enctype="multipart/form-data" action="csv2templateMotionchart.php">
	<p>
	<input type="file" name="csvFile" size="40" />
	<input type="submit" name="uploadcsv" value="Send csv file" />
	</p>
      </form>
    </div>
	  <hr />
    <div id="alternative">
	<h3>- OR - convert two files: one with X axis data and one with Y axis data.</h3>
	    <p>Your two tables should look similar. An example csv content would be:</p>
	<div class="pre" id="second-csv-example">
		date, id1, id2, id3, etc.<br />
		december 2003, 921015,51621,54113,etc.
	</div>

	<form method="post" enctype="multipart/form-data" action="csv2templateMotionchart.php">
	    <p>
	    <span class="info">X axis data file: <input type="file" name="csvXFile" size="55" /></span>
	    <span class="info">Y axis data file: <input type="file" name="csvYFile" size="55" /><input type="submit" name="upload2csv" value="Send two csv files" /></span>
	    </p>
	</form>
    </div>';

  } else {
      
    $p_PrintedContent =  str_replace("  ",'&nbsp;&nbsp;',$p_PrintedContent);
    $p_PrintedContent =  str_replace("\n",'<br />',$p_PrintedContent);
    
    $p_PrintedContent = 'Thank you for using this tool!
    <div class="pre" id="mediawiki">
    '.$p_PrintedContent.'
    </div>';        
  }

  echo <<<MYHMTLCODE
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8"/>
    <title>cvs2templateMotionchart - Generate wikidata Motionchart</title>
    <link rel="stylesheet" href="./generators.css" type="text/css" media="screen" />
  </head>

  <body>
    $p_PrintedContent
  </body>
</html>
MYHMTLCODE;
}


function manageUploadedFile($p_file)
{
    $l_filePath = $_FILES[$p_file]['tmp_name'];

    if( !is_uploaded_file($l_filePath) )
    {
        exit("No file selected.");
    }

    $l_fileName = $_FILES[$p_file]['name'];
    $type_file = $_FILES[$p_file]['type'];

    if(!strstr($type_file, 'csv') && !strstr($type_file, 'text'))
    {
        if( substr($l_fileName, -4) != '.csv' && substr($l_fileName, -4) != '.txt')
        {
        exit("The file should be named <tt>*.csv</tt> or <tt>*.txt</tt> and its MIME type should be text/csv.");
        }
    }


    // Security regexp:
    if( preg_match('#[\x00-\x1F\x7F-\x9F/\\\\]#', $l_fileName) )
    {
        exit("The file name is not valid.");
    }
    $l_fileHandle = fopen($l_filePath, "r");
    $l_fileContent = fread($l_fileHandle, filesize($l_filePath));
    fclose($l_fileHandle);

    return array('content' => $l_fileContent, 'title' => $l_fileName);
}


/**
 ** ID, date, x, y, group?
 ** fr,2007-05-06 12:07,486237,246683,group?
 ** fr,2007-05-06 12:07,486237,246683,group?
 **
 ** -> to
 **
 ** {{dataset| fr | 2010/1/1 | 46554 | 5416546 | group}}
 */
function wikifyCsv($p_cvsContent)
{
	$l_wikiString = "{{Motionchart
	   | x = X axis caption
	   | y = Y axis caption
	   | group = Groups caption |\n";
	   
	$l_lineList = lineListFromCsv($p_cvsContent);
	
	$l_lineIndex = 0;
	foreach ($l_lineList as $l_line)
	{
		
		$l_lineIndex += 1;
		if ($l_lineIndex == 1) continue; // First line is ignored.

		if (""==$l_line) continue; // Empty lines are ignored (there is usually one at the end).

		$l_data = explode(",", $l_line);


		if (count($l_data) < 4)
		{
			die(sprintf("boggus csv - Not enough commas (or semicolons) on line %d. There should be at least 3 cell separators:<br /><br /><tt>%s</tt><br /><br /><br /><em>Use something as <a href=\"http://openoffice.org\">OOo</a> to manipulate your csv data and <a href=\"?\">retry</a></em>.", $l_lineIndex, $l_line));
		}

		$l_id = trim($l_data[0]);
		$l_dateTimestamp = strtotime(trim($l_data[1]));

		if ($l_dateTimestamp === false)
		{
			die(sprintf("boggus csv - Wrong date format on line %d. Second cell: <tt>%s</tt> should be a date.<br /><br /><tt>%s</tt><br /><br />Date must be readable by <a href=\"http://php.net/manual/fr/function.strtotime.php\">strtotime()</a>.<br /><br /><em>Use something as <a href=\"http://openoffice.org\">OOo</a> to manipulate your csv data and <a href=\"?\">retry</a></em>.", $l_lineIndex, trim($l_data[1]), $l_line));
		}

		$l_xAxis = trim($l_data[2]);
		$l_yAxis = trim($l_data[3]);
		$l_date = strftime("%Y/%m/%d", $l_dateTimestamp);

		$l_label = "default";
		if (count($l_data) > 4)
		{
			$l_label = trim($l_data[4]);
		} else {
			$l_label = $l_id;
		}

		$l_wikiString .= sprintf("  {{dataset|%s|%s|%s|%s|%s}}\n", $l_id, $l_date, $l_xAxis, $l_yAxis, $l_label);
	}
	return $l_wikiString."}}";
}

/** 
 ** date, id1, id2, id3
 ** 2007-05-06 12:07, 465498, 24883, 54654241
 **
 ** -> to
 **
 ** Array( "line1", "line2")
 */
function lineListFromCsv($p_anyCsvContent) {

	$l_anyCsvContent = str_replace('"','',$p_anyCsvContent); // Remove "
	$l_anyCsvContent = str_replace(';',',',$l_anyCsvContent); // Replace ;

	$l_lineList = explode("\n", $l_anyCsvContent);
		
	return $l_lineList;
}

function DataFromCsv($p_csv){

	$l_lines=lineListFromCsv($p_csv);

	$l_lineIndex = 0;
	$l_data = array();

	foreach ($l_lines as $l_line)
	{
		if (""==$l_line) continue; // Empty lines are ignored (there is usually one at the end).

  		$l_data[$l_lineIndex] = explode(",", $l_line);

		$l_lineIndex += 1;
	}
	return $l_data;
}
/**   X axis csv...
 **
 ** date, id1, id2, id3
 ** 2007-05-06 12:07, 465498, 24883, 54654241
 ** 2007-05-06 12:07, 486237, 265483, 98765432
 **
 **   ...and Y axis csv
 **
 ** date, id1, id2, id3
 ** 2007-05-06 12:07, 465498, 24883, 54654241
 ** 2007-05-06 12:07, 486237, 265483, 98765432
  **
 ** -> to
 **
 ** {{dataset| id1 | 2010/1/1 | X1 | Y1 | id1}}
 */
function combineCsv($p_Xfile,$p_Yfile) 
{
	$l_wikiString = "";

	// Get data from file content	
	$l_Xcsv = $p_Xfile['content'];
	$l_Ycsv = $p_Yfile['content'];
	
	$l_Xdata = DataFromCsv($l_Xcsv);
	$l_Ydata = DataFromCsv($l_Ycsv);

	// Check file similarities.
	if ( count($l_Xdata)!=count($l_Ydata) ) {
		$l_wikiString .= "<b>Check your files! They have different numbers of lines.</b>\n\n";
	}
	if ( count($l_Xdata[1])!=count($l_Ydata[1]) ) {
		$l_wikiString .= "<b>Check your files! They have different numbers of columns.</b>\n\n";
	}
	if ( $l_Xdata[0][1]!=$l_Ydata[0][1] ) {
		$l_wikiString .= "<b>Check your files! Their second-column titles are different.</b>\n\n";
	}
	if ( $l_Xdata[1][0]!=$l_Ydata[1][0] ) {
		$l_wikiString .= "<b>Check your files! Their first dates are different.</b>\n\n";
	}

	// Set idArray from data
	$l_idArray = array();
	$l_idIndex = 0;
	foreach ($l_Xdata[0] as $l_stringId)
	{
		$l_idIndex += 1;
		if ($l_idIndex == 1) continue; // First cell is ignored as it should be the date caption.

		$l_idArray[$l_idIndex-2] = trim($l_stringId);
	}

	// Set dateArray from data
	$l_dateArray = array();
	for ($i = 0; $i < count($l_Xdata)-1; $i++) {

	    $l_dateArray[$i] = trim($l_Xdata[$i+1][0]);
	}

	// Start $l_wikiString...
	$l_wikiString .= "{{Motionchart
	   | x = ".substr($p_Xfile['title'], 0, -4)."
	   | y = ".substr($p_Yfile['title'], 0, -4)."
	   | group = Groups caption |";

	$l_idCount=0;
	foreach ($l_idArray as $l_idValue)
	{
		$l_idCount+=1; // idCount starts to 1.
		$l_dateCount=0;
		foreach ( $l_dateArray as $l_dateValue )
		{
			$l_dateCount+=1; // dateCount starts to 1.

			$l_dateTimestamp = strtotime(trim($l_dateValue));

			if ($l_dateTimestamp === false)
			{
				die(sprintf("boggus csv - Wrong date format on line %d: <tt>%s</tt> should be a date.<br /><br /><br />Date must be readable by <a href=\"http://php.net/manual/fr/function.strtotime.php\">strtotime()</a>.<br /><br /><em>Use something as <a href=\"http://openoffice.org\">OOo</a> to manipulate your csv data and <a href=\"?\">retry</a></em>.", $l_dateCount, trim($l_dateValue)));
			}
			$l_datePrinted = strftime("%Y/%m/%d", $l_dateTimestamp);

			$l_xValue=trim($l_Xdata[$l_dateCount][$l_idCount]);
			$l_yValue=trim($l_Ydata[$l_dateCount][$l_idCount]);

				/***** {{dataset|      id1     |    2010/1/1       |      X1     |      Y1   | id1}}  *****/

			$l_wikiString .= sprintf("\n  {{dataset|%s|%s|%s|%s|%s}}",$l_idValue,$l_datePrinted,$l_xValue,$l_yValue,$l_idValue);
		}
	}

	
	return $l_wikiString."\n}}";	
}


/**
 ** @brief The main function
 ** @details Manage upload, wikify csv and print valid xhtml.
 */
function main()
{
    if( isset($_POST['uploadcsv']) ) // if csv has been posted.
    {
        $l_csvFile = manageUploadedFile('csvFile');
				$l_csvContent = $l_csvFile['content'];
				
        $l_PrintedContent = wikifyCsv($l_csvContent);

    }
    elseif( isset($_POST['upload2csv']) )
    {
        $l_XcvsFile = manageUploadedFile('csvXFile');
        $l_YcvsFile = manageUploadedFile('csvYFile');
				$l_PrintedContent = combineCsv($l_XcvsFile,$l_YcvsFile);
    }
    else
    {
			$l_PrintedContent ="";
    }

    printHTML($l_PrintedContent);
}


main();

?>
