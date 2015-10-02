<?php

class APIUnitTest
{
  public $Name;
  
  public function __construct(&$oTest, &$oSuite = null, $nIndex = -1)
  {
    $this->m_oTest = $oTest;
    $this->m_oSuite = $oSuite;
    $this->m_nIndex = $nIndex;
  }
  
  public function parse(&$oBase)
  {
    // could be an array of tests, which is fine
    if (is_array($this->m_oTest))
    {
      $sCode = "";
      
      for ($n = count($this->m_oTest), $i = 0; $i < $n; $i ++)
      {
        $rt = new APIUnitTest($this->m_oTest[$i], $this->m_oSuite, $i);
        
        $sCode .= $rt->parse($oBase);
        
        if ($i == 0)
          $this->Name = $rt->Name;
      }
      
      return $sCode;
    }
    
    else
    {
      $o = &$this->m_oTest;
      $oSuite = isset($this->m_oSuite) ? $this->m_oSuite : null;
      
      // construct or reference name
      if (!empty($o->name))
        $this->Name = $o->name;
      
      else
      {
        $this->Name = ucfirst($o->method);
      
        if (!empty($o->uri))
        {
          $n = strpos($o->uri, "/");
          
          if ($n === false)
            $this->Name .= ucfirst($o->uri);
          
          else
            $this->Name .= ucfirst(substr($o->uri, 0, $n));
        }
      }
      
      if (!strncmp($this->Name, "test", 4))
        $this->Name = substr($this->Name, 4);
      
      // make sure the name is unique within suite or group
      $sThis = "Test $this->Name";
      
      if (isset($oSuite))
      {
        if (!isset($oSuite->testnames))
          $oSuite->testnames = array();
        
        if (in_array($this->Name, $oSuite->testnames))
          throw new APIUnitException($sThis." does not have a unique name");
        
        $oSuite->testnames[] = $this->Name;
        
        $sThis = $oSuite->Name.":$this->Name";
      }
      
      else
      {
        if (!isset($oBase->testnames))
          $oBase->testnames = array();
        
        if (in_array($this->Name, $oBase->testnames))
          throw new APIUnitException($sThis." does not have a unique name");
        
        $oBase->testnames[] = $this->Name;
      }
        
      // validate method
      if (empty($o->method))
        throw new APIUnitException($sThis." has no method");
        
      $o->method = strtoupper($o->method);

      // if the first character is an underscore, this is a placeholder and should be ignored
      if ($o->method{0} == "_")
        return "";
      
      switch ($o->method)
      {
        case "PUT":
        case "GET":
        case "POST":
        case "DELETE":
        case "HEAD":
          
          break;
          
        default:
          
          throw new APIUnitException($sThis." has unrecognized method (".$o->method.")");
      }

      // validate / construct uri
      $sBaseURI = "";
      
      $this->getLowestLevel($oBase, "baseuri");
      
      $sBaseURI = isset($o->baseuri) ? $o->baseuri : "";
      
      unset($o->baseuri);
      
      if ((!empty($sBaseURI)) && ((!isset($o->uri)) || (strncmp($o->uri, "http", 4))))
      {
        $sURI = $sBaseURI;
        
        if (isset($o->uri)) 
          $sURI .= $o->uri;
      }
      
      else
      {
        if (empty($o->uri))
          throw new APIUnitException($sThis." has no uri");
        
        $sURI = $o->uri;
      }
      
      // replace any overrideables with explicits
      $o->uri = $sURI;
      $o->name = $this->Name;

      // many things can come from the test, suite level or top level (in that order of priority)
      $this->getLowestLevel($oBase, "username");
      $this->getLowestLevel($oBase, "password");
      $this->getLowestLevel($oBase, "headers");
      $this->getLowestLevel($oBase, "output");
      $this->getLowestLevel($oBase, "requestheaders");
      
      // output the test code
      return "  public function test".$this->Name."()\n".
             "  {\n".
             '    $this->go(\''.str_replace("'", "\\'", json_encode($o))."');\n".
             "  }\n";
    }
  }
  
  private function getLowestLevel(&$oBase, $sValue)
  {
    if (empty($this->m_oTest->$sValue))
    {
      if ((isset($this->m_oSuite)) && (!empty($this->m_oSuite->$sValue)))
        $this->m_oTest->$sValue = $this->m_oSuite->$sValue;
    
      else if (!empty($oBase->$sValue))
        $this->m_oTest->$sValue = $oBase->$sValue;
    }
  }
  
  private $m_oTest;
  private $m_oSuite;
  private $m_nIndex;
}

?>
