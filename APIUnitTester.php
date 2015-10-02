<?php

class APIUnitTester extends PHPUnit_Framework_TestCase
{
  public function go($s)
  {
    // parse incoming test
    $o = json_decode($s);

    // prepare uri
    $sURI = $o->uri;

    if (!empty($o->query))
      $sURI .= "?".http_build_query($o->query);

    // replace $N with previous matches
    $this->fillMatchedValues($sURI);

    // initiate curl
    $ch = curl_init($sURI);
    $asHeaders = array();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // post and put requests just need data
    if ($o->method == "POST")
    {
      curl_setopt($ch, CURLOPT_POST, true);

      $this->postData($ch, $asHeaders, $o);
    }

    // otherwise add the method to the curl request
    else if ($o->method != "GET")
    {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $o->method);

      // and data if using put
      if ($o->method == "PUT")
        $this->postData($ch, $asHeaders, $o);
    }

    // add in authentication, if necessary
    if ((!empty($o->username)) && (!empty($o->password)))
      curl_setopt($ch, CURLOPT_USERPWD, $o->username.":".$o->password);

    // add in custom headers
    if (!empty($o->requestheaders))
    {
      foreach ($o->requestheaders as $sKey => $sValue)
        $asHeaders[] = "$sKey:$sValue";
    }
    
    // set headers, if any
    if (!empty($asHeaders))
      curl_setopt($ch, CURLOPT_HTTPHEADER, $asHeaders);
      
    // if there are any header checks, make sure to get the headers
    if (!empty($o->headers))
      curl_setopt($ch, CURLOPT_HEADER, true);
      
    // get results
    $sResult = curl_exec($ch);

    $this->assertNotEmpty($sResult);
    
    // perform header checks, if any
    if (!empty($o->headers))
    {
      foreach ($o->headers as $sHeader => $valid)
      {
        $sCompare = $this->compareValue("Header $sHeader", $this->getResponseHeader($sResult, $sHeader), $valid);
        
        if (!empty($sCompare))
          $this->assertTrue(false, $sCompare);
      }
      
      $n = strpos($sResult, "\r\n\r\n");
      
      if ($n === false)
        $n = strpos($sResult, "\n\n") + 2;
      
      else
        $n += 4;
      
      $sResult = substr($sResult, $n);
    }

    // if there is no output validation specified, then just getting something must be good enough
    if (!empty($o->output))
    {
      // could be a simple validator
      if (is_string($o->output))
        $this->assertEquals($o->output, $sResult);

      else
      {
        $oResult = json_decode($sResult);

        $sCompare = $this->compareObjects($oResult, $o->output);

        if (!empty($sCompare))
        {
          $stack = debug_backtrace();
          $firstFrame = $stack[0];
          $initialFile = $firstFrame['file'];

          file_put_contents(dirname($initialFile)."/".$o->name."Failed.json", $sResult);

          $this->assertTrue(false, $sCompare);
        }
      }
    }
  }

  /**
   * Replace $N in a string with previously matched regexp parenthesized values
   * 
   * @param string $s
   */
  private function fillMatchedValues(&$s)
  {
    if (preg_match_all('/\$([0-9]+)/', $s, $aMatches))
    {
      $a1 = &$aMatches[0];
      $a2 = &$aMatches[1];

      for ($i = count($a1) - 1; $i >= 0; $i --)
      {
        $n = intval($a2[$i]) - 1;

        $sReplace = isset(APIUnitTester::$s_asMatches[$n]) ? APIUnitTester::$s_asMatches[$n] : "";

        $s = str_replace($a1[$i], $sReplace, $s);
      }
    }
  }

  /**
   * Generate post data from an ambiguous source
   *
   * @param curl $ch                the curl handler
   * @param array $asHeaders        array of headers
   * @param string|object|array $o  the test definition
   * @return string
   */
  private function postData(&$ch, &$asHeaders, $o)
  {
    if (!empty($o->data))
    {
      $s = ((is_array($o->data)) || (is_object($o->data))) ? http_build_query($o->data) : (string)$o;

      // replace $N with previous matches
      $this->fillMatchedValues($s);
      
      curl_setopt($ch, CURLOPT_POSTFIELDS, $s);

      $asHeaders[] = "Content-length: ".strlen($s);
    }
  }

  /**
   * Compare a result object with the expected, valid output
   *
   * @param object $oResult
   * @param object $oValid
   * @param string $sParentKey
   * @return string           blank or the reason they don't match
   */
  private function compareObjects(&$oResult, &$oValid, $sParentKey = "")
  {
    $setChecked = array();

    if (!empty($sParentKey))
      $sParentKey = "$sParentKey:";

    foreach ($oResult as $sKey => $result)
    {
      // first check is the simplest - unexpected key
      if (!isset($oValid->$sKey))
        return $sParentKey.$sKey." in result is unexpected";

      // track which keys are checked
      $setChecked[$sKey] = true;

      // the validator may just require something
      $valid = &$oValid->$sKey;

      if ($valid == "<exists>")
        continue;

      $s = $this->compareValue($sParentKey.$sKey, $result, $valid);

      if (!empty($s))
        return $s;
    }

    // now make sure there is no data missing from the result
    foreach ($oValid as $sKey => $valid)
    {
      if (!isset($setChecked[$sKey]))
        return $sParentKey.$sKey." is missing from results";
    }
  }

  /**
   * Compare a result array with the expected, valid output
   *
   * @param string $sKey
   * @param array $aResult
   * @param array $aValid
   * @return string           blank or the reason they don't match
   */
  private function compareArrays($sKey, &$aResult, &$aValid)
  {
    $nResult = count($aResult);
    $nValid = count($aValid);

    // if the count is one, it is expected that a single record will be used for validating all results
    if ($nValid != 1)
    {
      if ($nValid != $nResult)
        return "$sKey in result has an unexpected number of elements";
    }

    for ($iResult = 0, $iValid = 0; $iResult < $nResult; $iResult ++)
    {
      $s = $this->compareValue($sKey."[$iResult]", $aResult[$iResult], $aValid[$iValid]);

      if (!empty($s))
        return $s;

      // only advance the result if expected
      if ($nValid != 1)
        $iValid ++;
    }
  }

  /**
   * Compare a single result field with the expected, valid output
   *
   * @param string $sKey
   * @param unknown $result
   * @param unknown $valid
   * @return string|Ambigous <string, NULL>|NULL
   */
  private function compareValue($sKey, &$result, &$valid)
  {
    // if the values are objects, recurse to check for equality
    if (is_object($result))
    {
      if (!is_object($valid))
        return "$sKey is an object in results, which is unexpected";

      return $this->compareObjects($result, $valid, $sKey);
    }

    else if (is_object($valid))
      return "$sKey is not an object in results, but should be";

    // if the values are arrays, recurse to check for equality
    if (is_array($result))
    {
      if (!is_array($valid))
        return "$sKey is an array in results, which is unexpected";

      return $this->compareArrays($sKey, $result, $valid);
    }

    else if (is_array($valid))
      return "$sKey is not an array in results, but should be";

    // the validator could be a regular expression
    if (($valid{0} == '/') && ($valid{strlen($valid) - 1} == '/'))
    {
      if (!preg_match($valid, $result, $asMatches))
        return "$sKey ($result) in result does not match regular expression $valid";

      else
      {
        // if there are any stored values, hold onto them for later
        if (count($asMatches) > 1)
          APIUnitTester::$s_asMatches = array_merge(APIUnitTester::$s_asMatches, $asMatches);

        return null;
      }
    }

    // or it could be a type check
    $b = true;

    if (($valid{0} == '<') && ($valid[strlen($valid) - 1] == '>'))
    {
      $sValid = strtolower(substr($valid, 1, strlen($valid) - 2));

      switch ($sValid)
      {
        case "<number>": $b = is_numeric($result); break;
        case "<email>": $b = filter_var($result, FILTER_VALIDATE_EMAIL); break;
        case "<string>": $b = is_string($result); break;

        default:

          // strings can have minimum and maximum lengths
          if (!strncmp($sValid, "string(", 7))
          {
            if (strpos($sValid, ',') > 0)
              list($nMin, $nMax) = explode(',', substr($sValid, 7));

            else
            {
              $nMin = 0;
              $nMax = intval(substr($sValid, 7));
            }

            $n = strlen($result);

            if (($n < $nMin) || ($n > $nMax))
              $b = false;
          }
      }
    }

    // last option is an exact match
    else if ($result != $valid)
      $b = false;

    if (!$b)
      return "$sKey ($result) in result does not match validator $valid";

    return null;
  }
  
  private function getResponseHeader($sResponse, $sGet) 
  {
    if (empty($this->m_asHeaders))
    {
      $this->m_asHeaders = array();
  
      $asResponse = explode("\n", $sResponse);
  
      foreach ($asResponse as $sHeader) 
      {
        $sHeader = trim($sHeader);
  
        if (strlen($sHeader) == 0)
          break;
  
        if (stripos($sHeader, 'HTTP/1.1') !== FALSE) 
        {
          list(, $nCode, $sStatus) = explode(' ', $sHeader);
  
          $this->m_asHeaders["httpstatuscode"] = intval($nCode);
          $this->m_asHeaders["httpstatusmessage"] = trim($sStatus);
        }
  
        else 
        {
          list($sName, $sValue) = explode(":", $sHeader, 2);
  
          $this->m_asHeaders[strtolower(trim($sName))] = trim($sValue);
        }
      }
    }
  
    $sGet = strtolower($sGet);
    
    return isset($this->m_asHeaders[$sGet]) ? $this->m_asHeaders[$sGet] : "";
  }
  
  public static function setUpBeforeClass()
  {
    APIUnitTester::$s_asMatches = array();
  }
  
  private $m_asHeaders = null;
  
  private static $s_asMatches = null;
}

?>
