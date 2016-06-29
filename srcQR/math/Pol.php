<?php
namespace aae\qr\math {
	class Pol {
		public function __construct() {
			$this->_a_matrix = array();
			$this->_array_size["x"] = 0;
			$this->_array_size["y"] = 0;
		}
		public function mult(\aae\math\Pol &$a, \aae\math\Pol &$b) {
			$result = new \aae\math\Pol();
			for ($ya = 0; $ya <= $a->_array_size["y"]; $ya++) {
				for ($xa = 0; $xa <= $a->_array_size["x"]; $xa++) {
					if (isset($a->_a_matrix[$xa][$ya])) {
						// matching with b
						for ($yb = 0; $yb <= $b->_array_size["y"]; $yb++) {
							for ($xb = 0; $xb <= $b->_array_size["x"]; $xb++) {
								if (isset($b->_a_matrix[$xb][$yb])) {
									$coeff = $a->_a_matrix[$xa][$ya] * $b->_a_matrix[$xb][$yb];
									$result->add_term($coeff, ( $xa + $xb ), ( $ya + $yb) );
									//echo "\n".$a->_a_matrix[$xa][$ya]."x^".$xa."y^".$ya." * ".$b->_a_matrix[$xb][$yb]."x^".$xb."y^".$yb." = ".$coeff."x^".($xa + $xb)."y^".($ya + $yb)."\n";
								}
							}
						}
						
								
					}
				}
			}
			return $result;
		}
		
		public function xor_mult(\aae\math\Pol &$a, \aae\math\Pol &$b) {
			global $log_table;
			if (!isset($log_table)) {
				$log_table = new \GF2N\Log_Tables();
			}
			$result = new \aae\math\Pol();
			for ($ya = 0; $ya <= $a->_array_size["y"]; $ya++) {
				for ($xa = 0; $xa <= $a->_array_size["x"]; $xa++) {
					if (isset($a->_a_matrix[$xa][$ya])) {
						// matching with b
						for ($yb = 0; $yb <= $b->_array_size["y"]; $yb++) {
							for ($xb = 0; $xb <= $b->_array_size["x"]; $xb++) {
								if (isset($b->_a_matrix[$xb][$yb])) {
									$coeff = $a->_a_matrix[$xa][$ya] * $b->_a_matrix[$xb][$yb];
									$result->xor_add_term($coeff, ( $xa + $xb ), ( $ya + $yb) );
									//echo "\n".$a->_a_matrix[$xa][$ya]."x^".$xa."y^".$ya." * ".$b->_a_matrix[$xb][$yb]."x^".$xb."y^".$yb." = ".$coeff."x^".($xa + $xb)."y^".($ya + $yb)."\n";
								}
							}
						}
						
								
					}
				}
			}
			return $result;
		}
		
		public function xor_add_term($coeff, $x_exp = 0, $y_exp = 0) {
			if ($x_exp > $this->_array_size["x"]) $this->_array_size["x"] = $x_exp;
			if ($y_exp > $this->_array_size["y"]) $this->_array_size["y"] = $y_exp;
			if (isset($this->_a_matrix[$x_exp][$y_exp])) {
				$this->_a_matrix[$x_exp][$y_exp] += $coeff;
			} else $this->_a_matrix[$x_exp][$y_exp] = $coeff;
		}
		
		// if term is zero, sets it, if not, adds to the existing term
		public function add_term($coeff, $x_exp = 0, $y_exp = 0) {
			if ($x_exp > $this->_array_size["x"]) $this->_array_size["x"] = $x_exp;
			if ($y_exp > $this->_array_size["y"]) $this->_array_size["y"] = $y_exp;
			if (isset($this->_a_matrix[$x_exp][$y_exp])) {
				$this->_a_matrix[$x_exp][$y_exp] += $coeff;
			} else $this->_a_matrix[$x_exp][$y_exp] = $coeff;
		}
		// sets the term, overwrites if it exists already
		public function set_term($coeff, $x_exp = 0, $y_exp = 0) {
			if ($x_exp > $this->_array_size["x"]) $this->_array_size["x"] = $x_exp;
			if ($y_exp > $this->_array_size["y"]) $this->_array_size["y"] = $y_exp;
			$this->_a_matrix[$x_exp][$y_exp] = $coeff;
		}
		public function __toString() {
			$return_string = NULL;
			$return_string_2 = NULL;
			for ($y = 0; $y <= $this->_array_size["y"]; $y++) {
				for ($x = 0; $x <= $this->_array_size["x"]; $x++) {
					if (isset($this->_a_matrix[$x][$y])) {
						$return_string .= (int)$this->_a_matrix[$x][$y];
						if ((int)$this->_a_matrix[$x][$y] !== 0) {
							$x_val = NULL;
							$y_val = NULL;
							$sign = "+";
							if ((int)$this->_a_matrix[$x][$y] < 0) $sign = "";
							if ($x !== 0) { 
								$x_val .= "a";
								if ($x !== 1) $x_val .= "^".$x;
							}
							if ($y !== 0) { 
								$y_val .= "x";
								if ($y !== 1) $y_val .= "^".$y;
							}
							
							$return_string_2 .= $sign.(int)$this->_a_matrix[$x][$y].$x_val.$y_val;
						}
						
					} else $return_string .= "0";
						
						
				}
				$return_string .= "\n";
				
			}
			if ( $return_string_2 === NULL ) return "EMPTY";
			return $return_string_2;
		}
		
		private $_array_size;
		private $_a_matrix;
	}
}