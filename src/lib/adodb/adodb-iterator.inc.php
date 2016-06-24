<?php

/*
  V4.50 6 July 2004  (c) 2000-2004 John Lim (jlim@natsoft.com.my). All rights reserved.
  Released under both BSD license and Lesser GPL library license. 
  Whenever there is any discrepancy between the two licenses, 
  the BSD license will take precedence.
  
  Set tabs to 4.
  
  Declares the ADODB Base Class for PHP5 "ADODB_BASE_RS", and supports iteration with 
  the ADODB_Iterator class.
  
  		$rs = $db->Execute("select * from adoxyz");
		foreach($rs as $k => $v) {
			echo $k; print_r($v); echo "<br>";
		}
		
		
	Iterator code based on http://cvs.php.net/cvs.php/php-src/ext/spl/examples/cachingiterator.inc?login=2
 */
 

 class ADODB_Iterator implements Iterator {

    private $rs;

    function __construct($rs) 
	{
        $this->rs = $rs;
    }
    function rewind() 
	{
        $this->rs->MoveFirst();
    }

	function valid() 
	{
        return !$this->rs->EOF;
    }
	
    function key() 
	{
        return $this->rs->_currentRow;
    }
	
    function current() 
	{
        return $this->rs->fields;
    }
	
    function next() 
	{
        $this->rs->MoveNext();
    }
	
	function __call($func, $params)
	{
		return call_user_func_array(array($this->rs, $func), $params);
	}
	
	function __toString()
	{
		if (isset($rs->databaseType)) $s = ' for '.$rs->databaseType;
		else $s = '';
		
		return 'ADODB Iterator'.$s;
	}
	
	function hasMore()
	{
		return !$this->rs->EOF;
	}

}


class ADODB_BASE_RS implements IteratorAggregate {
    function getIterator() {
        return new ADODB_Iterator($this);
    }
} 

?>