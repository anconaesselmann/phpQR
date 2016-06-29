<?php
namespace aae\qr\math {
	// both the sum and difference between two numbers in GF(2^N) arithmetic 
	// is the exclusive-or of their corresponding bits
	function sum ($a, $b) { return ($a ^ $b); } 
	function difference ($a, $b) { return ($a ^ $b); }
	// the Product of two values is the antilog of the mod (GF-1) sum of their logs
	function product ($a, $b) { 
	if (($a == 0) || ($b == 0)) return (0); 
		else return ($alog[($log[$a] + $log[$b]) % (GF-1)]); 
	}
	// the Quotient of two values is the antilog of the mod (GF-1) 
	// difference between their logs
	function quotient ($a, $b) { // namely A divided by B 
		if ($b == 0) return (1-GF); // signifying an error! 
		else if ($a == 0) return (0); 
		else return ($alog[($log[$a] - $log[$b] + (GF-1)) % (GF-1)]); 
	} 
	const GF = 256; // define the Size & Prime Polynomial of this Galois field 
	const PP = 285; // for QR codes
	//const PP = 301; // for everyting else
	


	// fill the Log[] and ALog[] arrays with appropriate integer values
	class Log_Tables {
		public function __construct() {
			$this->_fill_log_arrays();
		}
		public function __toString() {
			return $this->show_log_tables();
		}
		public function show_log_tables() {
			$return_str = NULL;
			$return_str .= "Log tables:\n";
			for ($i = 0; $i < count($this->log); $i++) {
				$return_str .= "alog[$i]: ".$this->alog[$i]."\tlog[$i]: ".$this->log[$i]."\n";
			}
			return $return_str;
		}
		private function _fill_log_arrays() { 
			//$this->log, $this->alog; // establish global Log and Antilog arrays 
			$this->log[0] = 1-GF; $this->alog[0] = 1; 
					
			for ($i=1; $i<GF; $i++) { 
				$this->alog[$i] = $this->alog[$i-1] * 2; 
				if ($this->alog[$i] >= GF) $this->alog[$i] ^= PP; 
				$this->log[$this->alog[$i]] = $i; 
			} 
			//$this->log[0] = 0;
			$this->log[1] = 0; //??
			$this->alog[-255] = 0; // coefficient is zero, alpha becomes this wheird thing? Does this get me in trouble later?
		}
		
		public $log;
		public $alog;
	}
}