<?php

class APIUnitSuite
{
  public $Name;
  
  public function __construct(&$oSuite, $nIndex = -1)
  {
    $this->m_oSuite = $oSuite;
    $this->m_nIndex = $nIndex;
  }
  
  public function parse(&$oBase)
  {
    // could be an array of suites, which is fine
    if (is_array($this->m_oSuite))
    {
      for ($n = count($this->m_oSuite), $i = 0; $i < $n; $i ++)
      {
        $rs = new APIUnitSuite($this->m_oSuite[$i], $i);
        
        $rs->parse($oBase);
      }
    }
    
    else
    {
      // validate name and tests
      if (empty($this->m_oSuite->name))
        throw new APIUnitException(($this->m_nIndex == -1 ? "Suite" : "Suite[".$this->m_nIndex."]")." has no name");
      
      $this->Name = $this->m_oSuite->name;
      
      if (empty($this->m_oSuite->test))
        throw new APIUnitException("Suite ".$this->Name." has no tests");
      
      // loop and parse tests
      
      $rt = new APIUnitTest($this->m_oSuite->test);
        
      // output file
      file_put_contents
      (
        $oBase->outputdir."/".$this->Name."Test.php",
       
        "<?php\n".
        "\n".
        "require_once('".$oBase->thisdir."/APIUnitTester.php');\n".
        "\n".
        "class ".$this->Name."Test extends APIUnitTester\n".
        "{\n".
          $rt->parse($oBase).
        "};\n".
        "\n".
        "?>\n"
      );
    }
  }
  
  private $m_oSuite;
  private $m_nIndex;
}

?>
