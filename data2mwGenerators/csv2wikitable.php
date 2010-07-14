<?php
/** @file csv2wikitable.php
 ** @brief Generate mediawiki formated data.
 ** @details Generate a mediawiki formated table.
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
      <h3>cvs2wikitable - Generate a MediaWiki-formatted table from a csv file</h3>

      <div id="main">
	<p>An example csv content would be:</p>
	<div class="pre">
	    id, date, Xvalue, Yvalue, group<br />
	    Paris, december 2003, 2846546, 654645, Europe<br />
	    London, 2006-7-35 22:00:00, 546544, 5646548, Europe<br />
	</div>
	<span class="info">Semicolons can replace commas. Double quotes will be removed.</span>

	<form method="post" enctype="multipart/form-data" action="csv2wikitable.php">
		<p>
		<input type="file" name="csvFile" size="40" />
		<input type="submit" name="uploadcsv" value="Send csv file" />
		</p>
	</form>
      </div>  
	    ';
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
    <title>cvs2wikitable - Generate a wikitable from a csv file</title>
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
 ** {| class="wikitable sortable"
 ** | fr || 2010/1/1 || 46554 || 5416546 || group
 */
function wikifyCsv($p_cvsContent)
{
  $l_wikiString = "{| class=\"wikitable sortable\"\n";
      
  $l_lineList = lineListFromCsv($p_cvsContent);

  $l_lineIndex = 0;
  foreach ($l_lineList as $l_line)
  {

    if (""==$l_line) continue; // Empty lines are ignored (there is usually one at the end).

    $l_wikiLine = str_replace(',',' || ',$l_line);

    $l_wikiString .= sprintf("|-\n|%s\n", $l_wikiLine);

  }
  return $l_wikiString."|}";
}

/** 
 ** Lines to Array("line1","line2")
 */
function lineListFromCsv($p_anyCsvContent) {

	$l_anyCsvContent = str_replace('"','',$p_anyCsvContent); // Remove "
	$l_anyCsvContent = str_replace(';',',',$l_anyCsvContent); // Replace ;

	$l_lineList = explode("\n", $l_anyCsvContent);
		
	return $l_lineList;
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
  else
  {
    $l_PrintedContent ="";
  }
  printHTML($l_PrintedContent);
}

main();
?>