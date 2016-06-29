<?php
namespace aae\qr\math {
	function alpha_pol_xor_mult(AlphaPol &$a, AlphaPol &$b) {
		$math = new AlphaPol();
		return $math->xor_mult($a, $b);
	}
	class AlphaPol {
		public function __construct() {
			$this->_data = array();
		}
		public function __clone() {
			$this->_data = $this->_data;
		}
		protected function deepCopy($object){ 
       		return unserialize(serialize($object)); 
   		}
		public function set_term($alpha, $exp) {
			$added = false;
			$i = 0;
			while (!$added) {
				if ($i < count($this->_data)) {
					if ($this->_data[$i][1] == (int)$exp) {
						$this->_data[$i][0] = $alpha;
						$added = true;
					}
					$i++;
				} else {
					$added = true;
					//if (  (($alpha === -255) && (count($this->_data) === 0)) ) $alpha = 0;
					if ( ! (($alpha === -255) && (count($this->_data) === 0)) ) { // changed
						$term = array();
						$term[] = (int)$alpha;
						$term[] = (int)$exp;
						$this->_data[] = $term;
					}
				}
			}
		}
		public function xor_add_term($alpha, $exp) {
			$added = false;
			$i = 0;
			while (!$added) {
				if ($i < count($this->_data)) {
					if ($this->_data[$i][1] == (int)$exp) {
						global $log_table;
						if (!isset($log_table)) {
							$log_table = new \GF2N\Log_Tables();
						}
						$int_1 = $log_table->alog[$alpha];
						$int_2 = $log_table->alog[$this->_data[$i][0]];
						$new_alpha = $log_table->log[$int_1 ^ $int_2];
						$this->_data[$i][0] = $new_alpha;
						$added = true;
					}
					$i++;
				} else {
					$added = true;
					$term = array();
					$term[] = (int)$alpha;
					$term[] = (int)$exp;
					$this->_data[] = $term;
				}
			}
		}
		public function xor_mult(AlphaPol &$a, AlphaPol &$b) {
			$result = new AlphaPol();
			for ($i = 0; $i < count($a->_data); $i++) {
				for ($j = 0; $j < count($b->_data); $j++) {
					$new_alpha = $a->_data[$i][0] + $b->_data[$j][0];
					if ($new_alpha > 255) $new_alpha = $this->_large_exp_mod($new_alpha);
					$new_exp = $a->_data[$i][1] + $b->_data[$j][1];
					if ($new_exp > 255) $new_exp = $this->_large_exp_mod($new_exp);
					$result->xor_add_term($new_alpha, $new_exp);
				}
			}
			return $result;
		}
		// multiplies the whole function by one term, if the exponent becomes larger than 255, it uses mod 255.
		public function mod_mult($alpha, $exponent) {
			for ($i = 0; $i < count($this->_data); $i++) {
				$result = $this->_data[$i][0] + $alpha;
				if ($result > 255) $result = $result % 255;
				$this->_data[$i][0] = $result;
				
				$result = $this->_data[$i][1] + $exponent;
				if ($result > 255) $result = $result % 255;
				$this->_data[$i][1] = $result;
			}
		}
		public function __toString() {
			$return_str = NULL;
			$bold1 = NULL;
			$bold2 = NULL;
			for ($i = 0; $i < count($this->_data); $i++) {
				if ($i === count($this->_data) - 1) {
					$bold1 = "<strong style=\"font-size:15;\">";
					$bold2 = "</strong>";
				}
				
				$temp_str = '&alpha;'."<FONT COLOR=\"#B00000\"><sup>".$this->_data[$i][0]."</sup></FONT>x<FONT COLOR=\"#009900\"><sup>$bold1".$this->_data[$i][1]."$bold2</sup></FONT>";
				$return_str .= $temp_str;
				if ($i < count($this->_data) - 1) {
					$return_str.=" + ";
				}
			}
			return $return_str;
		}
		public function toIntString($disp = NULL) {
			$return_str = NULL;
			$bold1 = NULL;
			$bold2 = NULL;
			global $log_table;
			for ($i = 0; $i < count($this->_data); $i++) {
				if ($i === count($this->_data) - 1) {
					$bold1 = "<strong style=\"font-size:15;\">";
					$bold2 = "</strong>";
				}
				if ($disp !== NULL) echo "alpha value: ".$this->_data[$i][0].", int value: ".$log_table->alog[$this->_data[$i][0]]."\n";
				$temp_str = "<FONT COLOR=\"#000099\">".$log_table->alog[$this->_data[$i][0]]."</FONT>x<FONT COLOR=\"#009900\"><sup>$bold1".$this->_data[$i][1]."$bold2</sup></FONT>";
				$return_str .= $temp_str;
				if ($i < count($this->_data) - 1) {
					$return_str.=" + ";
				}
			}
			return $return_str;
		}
		public function get_alpha($i) {
			if ($i < count($this->_data)) {
				return $this->_data[$i][0];
			} else return false;
		}
		// returns the integer coefficient for the respective alpha
		public function get_int_coeff($i) {
			if ($i < count($this->_data)) {
				global $log_table;
				if (!isset($log_table)) {
					$log_table = new \GF2N\Log_Tables();
				}
				return $log_table->alog[$this->_data[$i][0]];
				
			} else return false;
		}
		public function get_exp($i) {
			if ($i < count($this->_data)) {
				if (isset($this->_data[$i][1])) {
					return $this->_data[$i][1];
				} else {
					echo "returning exponent 0 for index $i ".'in AlphaPol::get_exp($i)'."<br />";
					return 0;
				}
			} else return false;
		}
		public function size() {
			return count($this->_data);
		}
		private function _large_exp_mod($exponent) {
			return ($exponent % 256) + floor($exponent / 256);
		}
		
		private $_data;
	}
}