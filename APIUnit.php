#!/usr/bin/php
<?php

require_once("APIUnitSuite.php");
require_once("APIUnitTest.php");
require_once("APIUnitException.php");

$sPrefix = "";
$sOutputDir = "tests";
$sCmd = $argv[0];
$sVersion = "0.1";

function usage()
{
  global $sCmd, $sPrefix, $sOutputDir;
  
  echo "Usage: $sCmd [options] [input file]\n\n".
       "   -o <dir>     specify output directory (default '$sOutputDir')\n".
       "   -p <prefix>  specify test filename prefix (default '$sPrefix')\n".
       "   -h           this help\n\n";

  die;
}

// process command-line arguments
$sOptions = "oph";

for ($i = 1; $i < $argc; $i ++)
{
  $sArg = &$argv[$i];
  
  unset($asMatches);
  
  if (preg_match('/^-([opvhc])(=.*){0,1}$/', $sArg, $asMatches))
  {
    if ($asMatches[1] == 'c')
    {
      $sCmd = "apiunit";
      continue;
    }
    
    else if ($asMatches[1] == 'v')
    {
      echo "APIUnit $sVersion by Jon Vance\n"; 
      die;
    }
    
    else if ($asMatches[1] == 'h')
      usage();
    
    // the rest have arguments
    if (count($asMatches) == 2)
      $sVal = $argv[++$i];
    
    else
      $sVal = $asMatches[2];
    
    switch ($asMatches[1])
    {
      case 'o': $sOutputDir = $sVal; break;
      case 'p': $sPrefix = $sVal; break;
    }
  }

  else
  {
    if (empty($sInputFile))
      $sInputFile = $sArg;
    
    else
      usage();
  }
  
} // loop through command-line arguments

// at this point, should be a valid output dir
if (empty($sOutputDir))
  usage();

else 
{
  // replace Windows \ with / and remove any trailing slash
  $sOutputDir = rtrim(str_replace("\\", "/", $sOutputDir), '/');
  
  // offer to create directory
  if (!file_exists($sOutputDir))
    mkdir($sOutputDir);

  // make sure it isn't a file
  else if (!is_dir($sOutputDir))
    die("Cannot write to $sOutputDir\n");
}

// if no input file was specified, prompt for one
if (!isset($sInputFile))
{
  echo "\nEnter input file name [apiunit.json]: ";
  
  $sInputFile = trim(fgets(STDIN));
  
  if (strlen($sInputFile) == 0)
    $sInputFile = "apiunit.json";
}

// check for input file
if (!file_exists($sInputFile))
  die("Input file '$sInputFile' does not exist\n");

// open and parse file
$oInput = json_decode(file_get_contents($sInputFile));

// validate input
if (empty($oInput))
  die("There is a problem with your input file; is it well-formatted JSON?");

// could be a suite, an array of suites, a test or an array of tests
$oInput->outputdir = $sOutputDir;
$oInput->thisdir = str_replace("\\", "/", __DIR__);

try
{
  if (empty($oInput->suite))
  {
    if (empty($oInput->test))
      die("There are no tests defined in your input file");
  
    $rt = new APIUnitTest($oInput->test);
    
    $sTestCode = $rt->parse($oInput);
    
    $sFilename = $rt->Name."Test";
    
    // output the test file
    file_put_contents
    (
      $oInput->outputdir."/".$rt->Name."Test.php",
      
      "<?php\n".
      "\n".
      "require_once('".$oInput->thisdir."/APIUnitTester.php');\n".
      "\n".
      "class ".$rt->Name."Test extends APIUnitTester\n".
      "{\n".
        $sTestCode.
      "};\n".
      "\n".
      "?>"
    );
  }
  
  else
  {
    $rs = new APIUnitSuite($oInput->suite);
  
    $rs->parse($oInput);
  }
}

catch (APIUnitException $ex)
{
  die($ex->getMessage());
}


