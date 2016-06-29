<?php
namespace aae\qr {
	class QrGrid extends \aae\qr\Grid {
		const MODE_NUM = 1;
		const MODE_ALP = 2;
		const MODE_BIN = 4;
		const MODE_JAP = 8;

		const ERROR_L = 1;
		const ERROR_M = 2;
		const ERROR_Q = 3;
		const ERROR_H = 4;

		public function __construct($mode, $error_level, $version = NULL) {
			$size = $this->_get_version_size($version);
			parent::__construct($size, $size);
			$this->_mode = $mode;
			$this->_error_level = $error_level;
			$this->_version = $version;
			$this->_data_clipped = false;
			$this->_forced_mask = NULL;
			
			// debugging
			$this->_debug_reporting = true;
			$this->_str_debug_reporting = NULL;
		}
		
		public function __toString() {
			return parent::__toString();
		}
		
		public function get_version() {
			return $this->_version;
		}
		public function get_info() {
			$return_str = NULL;
			$return_str .= "<p style=\"font-size:12; font-family:arial; margin:0;\">Message: ".$this->_data_raw."<br />";
			$return_str .= "Message in integers: ";
			for ($i = 0; $i < count($this->_data_encoded); $i++) {
				$return_str .= $this->_data_encoded[$i]." ";
			}
			
			$return_str .= "<br />";
			$return_str .= "Message in binary: ";
			$format = '%1$08b';
			$return_str .= "</p><div style=\"margin: 0px; padding-left:20px;font-size:12; font-family:arial\">";
			for ($i = 0; $i < count($this->_data_encoded); $i++) {
				$return_str .= sprintf($format, $this->_data_encoded[$i])." ";
				if (($i + 1) % 4 === 0) $return_str .= "</div><div style=\"margin: 0; padding-left:20px;font-size:12; font-family:arial\">";
			}
			$return_str .= "</div><p style=\"font-size:12; font-family:arial; margin:0;\">";
			$return_str .= "Message blocks: ".implode(", ", $this->_get_nbr_data_blocks())."<br />";
			$return_str .= "Error correction blocks: ".$this->_get_nbr_ec_blocks()."<br />";
			$return_str .= "Mask used: ".(int)$this->_mask."<br />";
			$return_str .= "Error Correction level used: ".$this->_error_level."<br />";
			$return_str .= "Version number: ".$this->get_version()." (".$this->size_x()." x ".$this->size_x().")<br /></p>";
			return $return_str;
		}
		// enter a data string (text, numbers)
		// Don't enter data that is supposed to be interpreted as binary data, even when in binary mode! A different function will be necessary for that.
		public function set_data($data) {
			if ($this->_mode === self::MODE_ALP)
				$this->_data_raw = strtoupper($data);
			else $this->_data_raw = $data;
			
			$this->_generate_bit_representation();
			$this->_generate_matrix_representation();
		}
		
		// set the QR-code version (1-40) TO DO: redo constructor call
		public function set_version($version) {
			$this->_version = $version;
		}
		
		public function get_max_data_length() {
			return $this->_get_max_message_length();
		}
		public function get_data_length() {
			return strlen($this->_data_raw);
		}
		public function get_data() {
			return $this->_data_raw;
		}
		
		public function data_is_clipped() {
			return $this->_data_clipped;
		}
		
		public function get_mask() {
			return $this->_mask;
		}
		
		public function get_debug_str() {
			return $this->_str_debug_reporting;
		}
		public function set_mask($mask) {
			if ( ($mask >= 0) && ($mask <= 7) ) {
				$this->_forced_mask = $mask;
			} else if ($mask === -1) {
				$this->_forced_mask = false;
			} else {
				$this->_mask = NULL;
				return false;
			}
		}
		
		public function get_ec() {
			return $this->_error_level;
		}
		public function get_mode() {
			switch ($this->_mode) {
				case 1: return "numeric";
				case 2: return "alpha-numeric";
				case 4: return "binary";
				case 8: return "japanese";
				default: return "unknown";
			}
		}
		
		// dummy function
		private function _calculate_version() {
			
		}
		
		/////////////////////////////////////////////////////////////////////////////////////
		// I) GENERATING BIT REPRESENTATION /////////////////////////////////////////////////
		/////////////////////////////////////////////////////////////////////////////////////
		
		private function _generate_bit_representation() {
			if ($this->_version === NULL) $this->_calculate_version(); // does not work yet
			$this->_generate_data_blocks();
			$this->_generate_ec_blocks();
			
			$nbr_blocks = $this->_get_nbr_blocks();
			// load data blocks
			for ($i = 0; $i < count($this->_data_block[0]); $i++) {
				for ($b = 0; $b < $nbr_blocks; $b++) {
					$this->_data_encoded[] = $this->_data_block[$b][$i];
				}
			}
			for ($i = count($this->_data_block[0]); $i < count($this->_data_block[$nbr_blocks-1]); $i++) {
				for ($b = 0; $b < $nbr_blocks; $b++) {
					if (isset($this->_data_block[$b][$i])) {
						$this->_data_encoded[] = $this->_data_block[$b][$i];
					}
				}
			}

			// load error correction blocks
			for ($i = 0; $i < count($this->_ec_block[$nbr_blocks-1]); $i++) {
				for ($b = 0; $b < $nbr_blocks; $b++) {
					if (isset($this->_ec_block[$b][$i])) {
						$this->_data_encoded[] = $this->_ec_block[$b][$i];
					} else {
						$this->_data_encoded[] = NULL;//$this->_ec_block[$b][$i];
						if ($this->_debug_reporting) {
							$this->_str_debug_reporting .= "no error block at index b: $b, i: $i, returning NULL<br />";
						}
					}
				}
			}
		}
		
		//////////////////////////////////////
		// 1) GENERATING MESSAGE CODE WORDS //
		//////////////////////////////////////
		
		// converts the raw user data into binary data blocks.
		// to do: other modes than numeric, alpha numeric and binary
		private function _generate_data_blocks() {
			$max_message_length = $this->_get_max_message_length();
			if (strlen($this->_data_raw) > $max_message_length) {
				// to do: decide whether to truncate or change version size
				$this->_data_raw = substr($this->_data_raw, 0, $max_message_length);
				$this->_data_clipped = true;
			}
			
			// the mode (numeric, alphanumeric, binary, japanese) is encoded with 4 bit
			$data_binary_string = $this->_get_mode_indicator();
			
			// indicates the length of the message
			$data_binary_string .= $this->_get_char_count_indicator();
		
			// encode the message according to its mode
			switch ($this->_mode) {
				case self::MODE_ALP: {
					$data_binary_string .= $this->_get_message_alpha_num();
					break;	
				}
				case self::MODE_BIN: {
					$data_binary_string .= $this->_get_message_binary();
					break;
				}
				case self::MODE_NUM: {
					$data_binary_string .= $this->_get_message_numeric();
					break;
				}
				
				// TO DO: do other modes
				default: die("_generate_data_blocks() ERROR: UNSUPPORTED MODE");
			}
			// terminate with zeros to fill 8 bit codewords
			$this->_append_terminatinator_bits($data_binary_string);
			
			// add fill code words
			$data_bits_max = $this->_data_bits();
			$count_fill_numbers = (int)($data_bits_max / 8) - (strlen($data_binary_string)/8);
			$data_binary_string .= $this->_get_fill_code_words($count_fill_numbers);
			
			// fill the data blocks with the content of the $data_binary_string
			$this->_fill_data_blocks($data_binary_string);
		}
		
		// Data Block: generates the mode indicator
		private function _get_mode_indicator() {
			// the mode (numeric, alphanumeric, binary, japanese) is encoded with 4 bit
			$format_mode = '%1$04b';
			return sprintf($format_mode, $this->_mode);
		}
		
		// Data Block: generates the character count indicator
		private function _get_char_count_indicator() {
			// the length of the data string is encoded with a certain amount of bits
			// that depend on the versin and the mode
			$lenth_bit_count = $this->_get_bit_length_data_length();
			$format_length = '%1$0'.$lenth_bit_count.'b';
			$length = strlen($this->_data_raw);
			return sprintf($format_length, $length);
		}
		
		// untested
		public function _get_message_numeric() {
			// tripplets of data are interpreted as 10 bit binary
			$data_binary_string = NULL;
			$bin_str_data = NULL;
			for($i = 0; $i < strlen($this->_data_raw); $i += 3) {
				if ($i + 3 <= strlen($this->_data_raw)) {
					// For complete tripplets
					$format_bin_str_data = '%1$010b';
					$data = (int)substr($this->_data_raw, $i, 3);
				} else if ($i + 2 <= strlen($this->_data_raw)) {
					// last part of message is only a pair
					$format_bin_str_data = '%1$07b';
					$data = (int)substr($this->_data_raw, $i, 2);
				} else {
					// last part of message is only one number
					$format_bin_str_data = '%1$04b';
					$data = (int)substr($this->_data_raw, $i, 1);
				}
				//echo $data."<br />";
				//echo sprintf($format_bin_str_data, $data)."<br />";
				$data_binary_string .= sprintf($format_bin_str_data, $data);
			}
			return $data_binary_string;
		}
		
		// Data Block: encode user message to binary alpha numeric string
		// to do: decide how to deal with unsupported characters (throw error? speciffic symbol?)
		private function _get_message_alpha_num() {
			// paris of data are converted to alphanumeric and encoded together with 11 bit.
			$data_binary_string = NULL;
			$format_bin_str_data = '%1$011b';
			$bin_str_data = NULL;
			for($i = 0; $i < strlen($this->_data_raw); $i += 2) {
				if ($i + 2 <= strlen($this->_data_raw)) {
					// For complete pairs, the first alphanumeric number
					//  is multiplied by 45 and added to the seccond.
					$first_char = $this->_char_to_alph_num($this->_data_raw[$i]);
					$seccond_char = $this->_char_to_alph_num($this->_data_raw[$i + 1]);
					$value = $first_char * 45 + $seccond_char;
					$data_binary_string .= sprintf($format_bin_str_data, $value);
				} else {
					// Incomplete pairs at the end are encoded with 6 bit
					$format_bin_str_data = '%1$06b';
					$value = $this->_char_to_alph_num($this->_data_raw[$i]);
					$data_binary_string .= sprintf($format_bin_str_data, $value);
				}
			}
			return $data_binary_string;
		}
		
		// converts a character to its alphanumeric value. 
		// Returns "false" if a character is not part of the alphanumeric character set
		private function _char_to_alph_num($char) {
			switch ($char) {
				case "0": return 0;
				case "1": return 1;
				case "2": return 2;
				case "3": return 3;
				case "4": return 4;
				case "5": return 5;
				case "6": return 6;
				case "7": return 7;
				case "8": return 8;
				case "9": return 9;
				case "A": return 10;
				case "B": return 11;
				case "C": return 12;
				case "D": return 13;
				case "E": return 14;
				case "F": return 15;
				case "G": return 16;
				case "H": return 17;
				case "I": return 18;
				case "J": return 19;
				case "K": return 20;
				case "L": return 21;
				case "M": return 22;
				case "N": return 23;
				case "O": return 24;
				case "P": return 25;
				case "Q": return 26;
				case "R": return 27;
				case "S": return 28;
				case "T": return 29;
				case "U": return 30;
				case "V": return 31;
				case "W": return 32;
				case "X": return 33;
				case "Y": return 34;
				case "Z": return 35;
				case " ": return 36;
				case "$": return 37;
				case "%": return 38;
				case "*": return 39;
				case "+": return 40;
				case "-": return 41;
				case ".": return 42;
				case "/": return 43;
				case ":": return 44;
				default: return false;
			}
		}
		
		// Data Block: encode user message to 8 bit ninary string
		private function _get_message_binary() {
			$data_binary_string = NULL;
			$format_bin_str_data= '%1$08b';
			$bin_str_data = NULL;
			// each byte of data is converted to binary
			for($i = 0; $i < strlen($this->_data_raw); $i++) {
				$value = ord($this->_data_raw[$i]);
				$value_binary = sprintf($format_bin_str_data, $value);
				$data_binary_string .= $value_binary;
			}
			return $data_binary_string;
		}
		
		// Data Block: terminates message to fit 8 bit codewords
		// The whole message that has been generated so far is passed by reference
		private function _append_terminatinator_bits(&$data_binary_string) {
			$data_bits = strlen($data_binary_string);
			$data_bits_max = $this->_data_bits();
			
			// terminate with 4 zeros, if the message is shorter than the required length
			for ($i = 0; $i < 4; $i++) {
				if ( ($data_bits_max - $data_bits) > 0 ) {
					$data_binary_string .= "0";
				}
				$data_bits = strlen($data_binary_string);
			}
			// add additional zeros to turn the message into all 8-bit codewords
			$zeros = 8 - strlen($data_binary_string) % 8;
			if ($zeros === 8) $zeros = 0;
			for ($i = 0; $i < $zeros; $i++) {
					$data_binary_string .="0";
			}
		}
		
		// Data Block: appends 8 bit fill code words
		private function _get_fill_code_words($count_fill_numbers) {
			$data_binary_string = NULL;
			$format_bin_str_data= '%1$08b';
			$fill_1 = 236;
			$fill_2 = 17;
			// until the required ammount of code wirds is reacht, 
			// the bytes 236 and 17 are alternately added the the binary string
			for ($i = 0; $i < $count_fill_numbers; $i++) {
				$data_binary_string .= sprintf($format_bin_str_data, $fill_1);
				$temp = $fill_1;
				$fill_1 = $fill_2;
				$fill_2 = $temp;
			}
			return $data_binary_string;
		}
			
		// Data Block: fills the data blocks. More description to come
		private function _fill_data_blocks(&$data_binary_string) {
			$count_blocks = $this->_get_nbr_blocks();
			$nuber_data_blocks = $this->_get_nbr_data_blocks();
			
			$str_pos = 0;
			for ($b = 0; $b < $count_blocks; $b++) {
				for ($i = 0; $i < $nuber_data_blocks[$b]; $i++) {
					$this->_data_block[$b][$i] = bindec(substr($data_binary_string,$str_pos,8));
					$str_pos += 8;
				}
			}
		}
		
		
		
		
		////////////////////////////////////
		// 2) GENERATING ERROR CODE WORDS //
		////////////////////////////////////
		
		// returns arrays of error code blocks
		// uses the arrays that _generator_pol() and _message_pol() create
		private function _generate_ec_blocks() {
			$block_nbr = 0;
			$generator_pol 	= new \aae\qr\math\AlphaPol();
			$xor 			= new \aae\qr\math\AlphaPol();
			$mult 			= new \aae\qr\math\AlphaPol();
			$message_pol_blocks = $this->_message_pol();
			
			$generator_pol 	= $this->_generator_pol(); // this has to change(it might have?)
			
			$nbr_blocks = $this->_get_nbr_blocks();
			$this->_ec_block = array();
			
			
			/*for ($b = 0; $b < $nbr_blocks; $b++) {//if ($b === 24) {
				for ($i = 0; $i < $message_pol_blocks[$b]->size(); $i++) {
					echo $message_pol_blocks[$b]->get_int_coeff($i).",";
					//echo $xor->get_int_coeff($i)." ";
				}echo "<br />";
			}//echo "<br /><br />";
			//}*/
			
			
			for ($b = 0; $b < $nbr_blocks; $b++) {
				$debug = false;
				
				$this->_ec_block[$b] = array();
				$xor = $message_pol_blocks[$b]; // holds the xord message pol
				$last_xor_exponent = $xor->get_exp($xor->size()-1);
				$count = 1;  // only necessary for debugging echo statements
				
				/*echo "<strong>Generator polynomial:</strong><br />";
				echo $generator_pol[$b]."<br /><br />";
				echo "<strong>Message polynomial:</strong><br />";
				echo $xor->toIntString()."<br /><br />";*/
				
				while ($last_xor_exponent > 0) {
					//if ($b === 24) $debug = true;
					$subcount = 1; // only necessary for debugging echo statements
					// finding the first nonzero alpha of xor result
					$alpha = $xor->get_alpha(0);
					
					// make the first term exponents of the generator polinomial match 
					// the first term of the xor result, so that they cancel out in the 
					// next execution of this loop
					while ($xor->get_exp($xor->size()-1) < $generator_pol[$b]->get_exp($xor->size()-1)) {
						$generator_pol[$b]->mod_mult(0,-1);
					}
					
					// mult nonzero alpha and generator pol
					$mult = clone $generator_pol[$b];
					
					if ($debug) {
						echo "<strong>($b.$count.".$subcount++.") Convert the above polynomial to alpha notation:</strong><br />";
						echo $xor."<br /><br />";
						echo "<strong>($b.$count.".$subcount++.") Multiply generator polynomial by first coefficient of the above polynomial:</strong><br />";
						echo "generator exponent: ".$generator_pol[$b]->get_exp($xor->size()-1).", message exponent: ".$xor->get_exp($xor->size()-1)."<br />";
						echo "First coefficient of the above polynomial: a^".$alpha."<br />";
					}
					
					$mult->mod_mult($alpha, 0);
					
					if ($debug) {
						echo "The generator polynomial<br />";
						echo $generator_pol[$b]."<br /><br />";
						echo "The result of the multiply step:<br />";
						echo $mult."<br /><br />";
						echo "<strong>($b.$count.".$subcount++.") Convert the alphas of the multiply result and the alphas of the mesasge polynomial to integers:</strong><br />";
						echo "The resulting polynomial from the last step with integer terms: <br />";
						echo $mult->toIntString()."<br />";
						echo "The Polinomial from the XOR step in integer terms:<br />";
						echo $xor->toIntString(); 
						echo "<br /><br />";
					}
					
					// XOR multiply result and old xor result
					$xor = $this->_xor_pol($mult, $xor);
					
					if ($debug) {
						echo "<strong>($b.$count.".$subcount++.") XOR each term of the two above polynomials:</strong><br />";
						echo "XOR result with all leading zero terms removed:<br />";
						echo $xor->toIntString()."<br /><br />";
					}
					
					
					$last_xor_exponent = $xor->get_exp($xor->size()-1);
					$count++;  // only necessary for debugging echo statements
				}
				
				if ($xor->size() < $this->_get_nbr_ec_blocks()) {
					$this->_ec_block[$b][] = 0;
					if ($this->_debug_reporting) {
						$this->_str_debug_reporting .= "inside exception for b=$b: ".'$xor->size() < $this->_get_nbr_ec_blocks()'."<br />";
					}
				}
				
				for ($i = 0; $i < $xor->size(); $i++) {
					$this->_ec_block[$b][] = $xor->get_int_coeff($i);
				}
			}
		}
		
		// polinomial must have the same highest polinomial, must be sorted with the highest 
		// pol first. Automatically removes leading terms that have zero as integer coefficient 
		// (!not alpha zeros! Those are ones as integers)
		private function _xor_pol(\aae\qr\math\ALphaPol $p1, \aae\qr\math\ALphaPol $p2) {
			$size = $p1->size();
			$size2 = $p2->size();
			if ($size2 > $size) $size = $size2;
			$result = new \aae\qr\math\AlphaPol();
			global $log_table;
			if (!isset($log_table)) {
				$log_table = new \aae\qr\math\Log_Tables();
			}
			for ($i = 0; $i < $size; $i++) {
				// retrieving the alpha values and ververting them into integers
				if ($p1->get_alpha($i) !== false)
					$int_1 = $log_table->alog[($p1->get_alpha($i))];
				else $int_1 = 0;
				if ($p2->get_alpha($i) !== false)
					$int_2 = $log_table->alog[($p2->get_alpha($i))];
				else $int_2 = 0;
				
				$xor_int = $int_1 ^ $int_2;
				//echo "".$int_1." ^ ".$int_2." = ".$xor_int."\t(as alpha: ".$log_table->log[$xor_int].")\n";
				if ( ! (( $xor_int == 0 ) && ($result->size() < 1)) ) {
					$new_alpha = $log_table->log[$xor_int];
					if ($p1->get_alpha($i) !== false)
						$exp_new = $p1->get_exp($i);
					else $exp_new = $p2->get_exp($i);
					$exp_1 = $p1->get_exp($i);
					$exp_2 = $p2->get_exp($i);
					//echo "exponents: ".$exp_1.", ".$exp_2."\n";
					
					$result->set_term($new_alpha, $exp_new);
				}
			}
			return $result;
		}
		// 
		private function _generator_pol() {
			$nbr_ec_blocks = $this->_get_nbr_ec_blocks();
			$mult = new \aae\qr\math\AlphaPol();
			$result = new \aae\qr\math\AlphaPol();
			$result->set_term(0,1);
			$result->set_term(0,0);
			for ($i = 0; $i < ($nbr_ec_blocks - 1); $i++) {
				$mult->set_term(0,1);
				$mult->set_term($i + 1,0);
				$result = \aae\qr\math\alpha_pol_xor_mult($mult, $result);
			}
			$count_blocks = $this->_get_nbr_blocks();
			$nbr_data_blocks = $this->_get_nbr_data_blocks(); 
			$generator_pol = array();
			for ($b = 0; $b < $count_blocks; $b++) {
				$generator_pol[$b] = new \aae\qr\math\AlphaPol();
				// The exponent of the first term is: 
				$exponent =  $nbr_data_blocks[$b] - 1; 
				$exponent_adjust = new \aae\qr\math\AlphaPol();
				$exponent_adjust->set_term(0,$exponent);
				$generator_pol[$b] = \aae\qr\math\alpha_pol_xor_mult($exponent_adjust, $result);
			}
			return $generator_pol;
		}
		private function _message_pol() {
			$message_pol = array();
			global $log_table;
			if (!isset($log_table)) {
				$log_table = new \aae\qr\math\Log_Tables();
			}
			$count_blocks = $this->_get_nbr_blocks();
			$nbr_data_blocks = $this->_get_nbr_data_blocks();
			$nbr_ec_blocks = $this->_get_nbr_ec_blocks();
			for ($b = 0; $b < $count_blocks; $b++) {
				$message_pol[$b] = new \aae\qr\math\AlphaPol();
				// The exponent of the first term is: 
				$exponent =  $nbr_data_blocks[$b] + $nbr_ec_blocks - 1;
				for ($i = 0; $i < count($this->_data_block[$b]); $i++) {
					$message_pol[$b]->set_term($log_table->log[(int)$this->_data_block[$b][$i]], $exponent--);
				}
			}
			return $message_pol;
		}
		
		
		
		/////////////////////////////////////////////////////////////////////////////////////
		// II) GENERATING MATRIX REPRESENTATION /////////////////////////////////////////////
		/////////////////////////////////////////////////////////////////////////////////////
		
		private function _generate_matrix_representation() {
			$binary = NULL;
			$format = '%1$08b';
			for ($i = 0; $i < count($this->_data_encoded); $i++) {
				$binary .= sprintf($format, $this->_data_encoded[$i]);
			}
			//$binary = substr($binary, 0, 74);//version 7: 1525// 315 //73 //256
			
			// setting all data and setting which mask was used
			$this->_mask = $this->_set_data_bits($binary);
		}
		
		///////////////////////////////////
		// 1) SETTING ALL BASIC PATTERNS //
		private function _set_basic_patterns() {
			$this->_set_position_detection_patterns();
			$this->_set_position_adjustment_groups();
			$this->_set_version_info();
			$this->_set_timing_patterns();
			// setting black pixel
			$this->set(8, $this->size_y() - 8, true);
		}
		
		// sets position detection pattern in the grid 
		private function _set_position_detection_patterns() {
			$this->_set_pdp(0, 0);
			$this->_set_pdp($this->size_x() - 7, 0);
			$this->_set_pdp(0, $this->size_y() - 7);
		}
		private function _set_pdp($x, $y) {
			$this->set_square($x-1	, $y-1	, 9, 9, false);
			$this->set_square($x	, $y	, 7, 7, true);
			$this->set_square($x+1	, $y+1	, 5, 5, false);
			$this->set_square($x+2	, $y+2	, 3, 3, true, 	true);
		}
		
		// sets position adjustments
		private function _set_position_adjustment_groups() {
			$a_pa = $this->_get_pa_locations();
			$sets = count($a_pa);
			for ($s = 0; $s < $sets; $s++) {
				for ($i = 0; $i < $sets; $i++) {
					for ($j = 0; $j < $sets; $j++) {
						$is_left_upper = (($a_pa[$j] === 6) && ($a_pa[$i] === 6));
						$is_left_lower = (($a_pa[$j] === 6) && ($i === ($sets - 1)));
						$is_right_upper = (($a_pa[$i] === 6) && ($j === ($sets - 1)));
						if (!$is_left_upper && !($is_left_lower) && !($is_right_upper))
						$this->_set_pa($a_pa[$j], $a_pa[$i]);
					}
				}
			}
		}
		private function _set_pa($x, $y) {
			$x_adj = $x - 2;
			$y_adj = $y - 2;
			$this->set_square($x_adj	, $y_adj	, 5, 5, true);
			$this->set_square($x_adj+1	, $y_adj+1	, 3, 3, false);
			$this->set_square($x_adj+2	, $y_adj+2	, 1, 1, true, 	true);
		}
		
		// sets timing patterns.
		private function _set_timing_patterns() {
			$this->_set_timing_pattern_horizontal();
			$this->_set_timing_pattern_vertical();
		}
		private function _set_timing_pattern_horizontal() {
			$set = true;
			for ($i = 8; $i < $this->size_x() - 8; $i++) {
				$this->set($i, 6, $set);
				if ($set === true) {
					$set = false;
				} else {
					$set = true;
				}
			}
		}
		private function _set_timing_pattern_vertical() {
			$set = true;
			for ($i = 8; $i < $this->size_y() - 8; $i++) {
				$this->set(6, $i, $set);
				if ($set === true) {
					$set = false;
				} else {
					$set = true;
				}
			}
		}
		
		// sets version info for versions 7-40 
		// next to the upper right and lower left position detection patterns
		private function _set_version_info() {
			if ($this->_version > 6) {
				$version_string = $this->_get_version_info();
				$str_len = strlen($version_string);
				
				// writing the version info by the upper right position detection pattern
				$str_pos = 0;
				$x = $this->size_x() - 11;
				$y = 0;
				while ($str_pos < $str_len - 1) {
					$this->_set_data($x, $y++, $version_string, $str_pos, 3, 1, 0);
				}
				
				// writing the version info by the lower left position detection pattern
				$str_pos = 0;
				$x = 0;
				$y = $this->size_y() - 11;
				while ($str_pos < $str_len - 1) {
					$this->_set_data($x++, $y, $version_string, $str_pos, 3, 0, 1);
				}
			}
		}

		// writes one line of data.
		// - $x_direction and $y_direction can be positive, zero or negative. 
		// 		Data is written in the respective x and y direction
		// - $x and $y give the position for the first bit.
		// - The bit string to be written is passed with $str.
		// - The position in the string is passed by $str_pos, which gets incremented as bits are written.
		// - how many bits are set by the function in one row is given by $word_length
		// returns false if one of the bits could not be set
		private function _set_data($x, $y, &$str, &$str_pos, $word_length, $x_direction = 1, $y_direction = 0, $mask = NULL) {
			// $x_direction and $y_direction need to be -1, 0, 1 
			// in order to correctly increment thier respective values
			if ($x_direction > 0) $x_direction = 1;
			else if ($x_direction < 0) $x_direction = -1;
			if ($y_direction > 0) $y_direction = 1;
			else if ($y_direction < 0) $y_direction = -1;
			$x_offset = 0;
			$y_offset = 0;
			$success = true;
			for ($i = 0; $i < $word_length; $i++ ) {
				if ($str_pos < strlen($str)) {
					$adjusted_x = $x + $x_offset;
					$adjusted_y = $y + $y_offset;
					
					$bit = (int)substr($str, $str_pos, 1);
					
					// mask the bit, if a mask was provided
					if ($mask !== NULL) $bit = $this->_mask($adjusted_x, $adjusted_y, $bit, $mask);
					
					// attempt to set the bit. $status will reflect success or failure
					$status = $this->set($adjusted_x, $adjusted_y, $bit);
					
					// increment offsets and string position
					$x_offset = $x_offset + $x_direction;
					$y_offset = $y_offset + $y_direction;
					if ($status === true) $str_pos++;
				}
			}
		}
		
		///////////////////////////////////////
		// 2) SETTING ALL SPECIFFIC PATTERNS //
		
		// format information is located under the upper position detection patterns 
		// and to the right of the left position detection patterns.
		// It depends on the error correction level and mask used
		private function _set_format_information($ec_level, $mask) {
			$type_info = (string)$this->_get_type_information_bits($ec_level, $mask);
			// writing horizontally
			for ($i = 0; $i < 6; $i++) {
				if ($type_info[$i] === "1") {
					$this->set($i, 8, true);
				} else {
					$this->set($i, 8, false);
				}
			}
			for ($i = 6; $i < 7; $i++) {
				$i_new = $i+1;
				if ($type_info[$i] === "1") {
					$this->set($i_new, 8, true);
				} else {
					$this->set($i_new, 8, false);
				}
			}
			for ($i = 7; $i < 15; $i++) {
				$i_new = ($this->size_x() - 15) + $i;
				if ($type_info[$i] === "1") {
					$this->set($i_new, 8, true);
				} else {
					$this->set($i_new, 8, false);
				}
			}
			// writing vertically
			for ($i = 0; $i < 7; $i++) {
				$vert_i = $this->size_y() - ($i +1);
				if ($type_info[$i] === "1") {
					$this->set(8, $vert_i, true);
				} else {
					$this->set(8, $vert_i, false);
				}
			}
			for ($i = 7; $i < 9; $i++) {
				$vert_i = 15 - $i;
				if ($type_info[$i] === "1") {
					$this->set(8, $vert_i, true);
				} else {
					$this->set(8, $vert_i, false);
				}
			}
			for ($i = 9; $i < 15; $i++) {
				$vert_i = 14 - $i;
				if ($type_info[$i] === "1") {
					$this->set(8, $vert_i, true);
				} else {
					$this->set(8, $vert_i, false);
				}
			}
		}
		
		// sets all data bits, masked with the correct mask
	// to do: allow for a speciffic mask to be used, if specified
		private function _set_data_bits($binary) {
			$ec_level = $this->_error_level;
			$final_mask = 0;
			$final_ec	= -1;
			if ($this->_forced_mask === NULL) {
			// decide which mask has the lowest error score. TO DO: if speciffic mask was given, not necessary
				for ($j = 0; $j < 8; $j++) {
					$mask_nbr = $j;
					$this->_set_data_bits_mask_speciffic($binary, $ec_level, $j);
					$penalty_score = $this->_get_penalty_score();
			
					if ( ($final_ec < 0) || ($penalty_score < $final_ec) ) {
						$final_ec = $penalty_score;
						$final_mask = $mask_nbr;
					}
				}
			} else $final_mask = $this->_forced_mask;
			//echo "mask: ".(int)$final_mask."<br />";
			// set mask with lowest error score or mask that was selected by user
			$this->_set_data_bits_mask_speciffic($binary, $ec_level, $final_mask);
			
			return $final_mask;
		}
		
		// to do: catch endless loop when data stream is too long for matrix
		private function _set_data_bits_mask_speciffic($binary, $ec_level, $mask_nbr) {
			// prepare the matrix with all basic patterns
			$this->clear_all();
			$this->_set_basic_patterns();
			$this->_set_format_information($ec_level, $mask_nbr);
			
			// write data speciffic patterns
			$down = -1; // when positive, bits are written from top to bottom, if negetive, bottom to top
			$penalty_score = 0;
			$str_pos = 0;
			$str_len = strlen($binary);
			$x_shift = 0; // holds the x-coordinate from seen from the right edge
			$x = $this->size_x() - 1;
			while ( ($x_shift < $this->size_x() + 2) && ($str_pos < $str_len)) {
				// skip the seventh column, (timing pattern)
				if ( ($this->size_x() - $x_shift) === 7) $x_shift++;
				
				// set the y coordinate according to writing direction
				if ($down > 0) $y = 0;
				else $y = $this->size_y() - 1;
				
				// write data pairs up or down. Write around basic patterns already set
				for ($i = 0; $i < $this->size_y(); $i++ ) { // goes into endless loop if more bits than space TO DO: fix it
					$this->_set_data($x - $x_shift, $y, $binary, $str_pos, 2, -1, 0, $mask_nbr);
					if ($down < 0) $y--; else $y++;
				}
				$x_shift += 2; // change adjust x-coordinate for next line
				$down = -$down; // change writing direction from up to down and viece versa
			}
			// filling the rest of the last column with masked zeros
			$bit = false;
			for ($i = 0; $i < $this->size_y() - 7; $i++) {
				$masked_bit = $this->_mask(0, $i, $bit, $mask_nbr);
				$this->set(0, $i, $masked_bit);
				$masked_bit = $this->_mask(1, $i, $bit, $mask_nbr);
				$this->set(1, $i, $masked_bit);
			}
		}
		
		// mask $bit with the mask provided with $mask. 
		// $x and $y are the x and y coordinates of the bit in the matrix
		private function _mask($x, $y, $bit, $mask) {
			if ($this->_forced_mask === false) return (int)$bit;
			//$bit = 0;
			switch ($mask) {
				case 0: {
					if ( ( ($y + $x) % 2 ) == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 1: {
					if ( ( ($y) % 2 ) == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 2: {
					if ( ( ($x) % 3 ) == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 3: {
					if ( ( ($y + $x) % 3 ) == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 4: {
					if ( ( floor($y / 2) + floor($x / 3) ) % 2 == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 5: {
					if ( (($y * $x) % 2) + (($y * $x) % 3)  == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 6: {
					if ( ( (($y * $x) % 2) + (($y * $x) % 3) ) % 2 == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				case 7: {
					if ( ( (($y + $x) % 2) + (($y * $x) % 3) ) % 2 == 0) {
						if ((bool)$bit == 0) return 1;
						if ((bool)$bit == 1) return 0;
					} else return $bit;
				}
				default: die( "ERROR: wrong mask type used in _mask()" );
			}
		}

		//////////////////////////////////
		// 3) GENERATING PENALTY SCORES //
		//////////////////////////////////
		private function _get_penalty_score() {
			$score = 0;
			$score += $this->_penalty_1();
			//echo $score."<br />";
			$score += $this->_penalty_2();
			//echo $score."<br />";
			$score += $this->_penalty_3();
			//echo $score."<br />";
			$score += $this->_penalty_4();
			//echo $score."<br />";
			return $score;
		}
		private function _penalty_1() {
			$score = $this->_p1_horizontal();
			$score += $this->_p1_vertical();
			return $score;
		}
		private function _p1_horizontal() {
			$score = 0;
			$current = NULL;
			$previous = NULL;
			for ($y = 0; $y < $this->size_y(); $y++) {
				$row_socre = 0;
				$previous = $this->get(0, $y);
				$count = 0;
				for ($x = 1; $x < $this->size_x(); $x++) {
					$current = $this->get($x, $y);
					if ($current === $previous) {
						$count++;
						if ($count == 4 ) $row_socre += 3;
						else if ($count > 4) $row_socre++;
					} else {
						$count = 0;
					}
					$previous = $current;
				}
				$score += $row_socre;
			}
			return $score;
		}
		private function _p1_vertical() {
			$score = 0;
			$current = NULL;
			$previous = NULL;
			for ($x = 0; $x < $this->size_x(); $x++) {
				$row_socre = 0;
				$previous = $this->get($x, 0);
				$count = 0;
				for ($y = 1; $y < $this->size_y(); $y++) {
					$current = $this->get($x, $y);
					if ($current === $previous) {
						$count++;
						if ($count == 4 ) $row_socre += 3;
						else if ($count > 4) $row_socre++;
					} else {
						$count = 0;
					}
					$previous = $current;
				}
				$score += $row_socre;
			}
			return $score;
		}
		
		private function _penalty_2() {
			$score = 0;
			$current = NULL;
			$previous = NULL;
			for ($y = 0; $y < $this->size_y() - 1; $y++) {
				$row_socre = 0;
				$previous = $this->get(0, $y);
				$count = 0;
				for ($x = 1; $x < $this->size_x(); $x++) {
					$current = (int)$this->get($x, $y);
					if ($current === $previous) {
						$count++;
						$next_y_current  = (int)$this->get($x	, $y + 1);
						$next_y_previous = (int)$this->get($x - 1, $y + 1);
						if ( ($current === $next_y_current ) && 
							 ($current === $next_y_previous)	) {
								$row_socre++;
						}
					} else {
						$count = 0;
					}
					$previous = $current;
				}
				$score += $row_socre;
			}
			$score *= 3;
			return $score;
		}
		
		private function _penalty_3() {
			$score = $this->_p3_horizontal();
			$score += $this->_p3_vertical();
			return $score;
		}
		// minimaly tested (both horizontal and vertical), to do: Test more
		private function _p3_horizontal() {
			$score = 0;
			$current = NULL;
			$previous = NULL;
			for ($y = 0; $y < $this->size_y() - 1; $y++) {
				$row_score = 0;
				$count = 0;
				for ($x = 0; $x < $this->size_x(); $x++) {
					// test if pixel is white
					if (!$this->get($x, $y)) {
						$count++;
					} else $count = 0;
					// at least 4 white in a row
					if ($count > 3) {
						// test if pattern is before white spot
						if ($x > 6) {
							$is_pattern = true;
							if 		($this->get($x - 4, $y) !== true ) $is_pattern = false;
							else if ($this->get($x - 5, $y) !== false) $is_pattern = false;
							else if ($this->get($x - 6, $y) !== true ) $is_pattern = false;
							else if ($this->get($x - 7, $y) !== true ) $is_pattern = false;
							else if ($this->get($x - 8, $y) !== true ) $is_pattern = false;
							else if ($this->get($x - 9, $y) !== false) $is_pattern = false;
							else if ($this->get($x - 10, $y) !== true ) $is_pattern = false;
								
							if ($is_pattern) $row_score += 40;
						}
						// test if pattern follows white spot
						if ( $x < ($this->size_x() - 7) ) {
							$is_pattern = true;
							if 		($this->get($x + 1, $y) !== true ) $is_pattern = false;
							else if ($this->get($x + 2, $y) !== false) $is_pattern = false;
							else if ($this->get($x + 3, $y) !== true ) $is_pattern = false;
							else if ($this->get($x + 4, $y) !== true ) $is_pattern = false;
							else if ($this->get($x + 5, $y) !== true ) $is_pattern = false;
							else if ($this->get($x + 6, $y) !== false) $is_pattern = false;
							else if ($this->get($x + 7, $y) !== true ) $is_pattern = false;
		
							if ($is_pattern) $row_score += 40;
						}
					}
					
				}
				//echo "row: ".$y.": ".(int)$row_score."<br />";
				$score += $row_score;
			}
			return $score;
		}
		private function _p3_vertical() {
			$score = 0;
			$current = NULL;
			$previous = NULL;
			for ($x = 0; $x < $this->size_x() - 1; $x++) {
				$row_score = 0;
				$count = 0;
				for ($y = 0; $y < $this->size_y(); $y++) {
					// test if pixel is white
					if (!$this->get($x, $y)) {
						$count++;
					} else $count = 0;
					// at least 4 white in a row
					if ($count > 3) {
						// test if pattern is before white spot
						if ($y > 6) {
							$is_pattern = true;
							if 		($this->get($x, $y - 4) !== true ) $is_pattern = false;
							else if ($this->get($x, $y - 5) !== false) $is_pattern = false;
							else if ($this->get($x, $y - 6) !== true ) $is_pattern = false;
							else if ($this->get($x, $y - 7) !== true ) $is_pattern = false;
							else if ($this->get($x, $y - 8) !== true ) $is_pattern = false;
							else if ($this->get($x, $y - 9) !== false) $is_pattern = false;
							else if ($this->get($x, $y - 10) !== true ) $is_pattern = false;
								
							if ($is_pattern) $row_score += 40;
						}
						// test if pattern follows white spot
						if ( $y < ($this->size_y() - 7) ) {
							$is_pattern = true;
							if 		($this->get($x, $y + 1) !== true ) $is_pattern = false;
							else if ($this->get($x, $y + 2) !== false) $is_pattern = false;
							else if ($this->get($x, $y + 3) !== true ) $is_pattern = false;
							else if ($this->get($x, $y + 4) !== true ) $is_pattern = false;
							else if ($this->get($x, $y + 5) !== true ) $is_pattern = false;
							else if ($this->get($x, $y + 6) !== false) $is_pattern = false;
							else if ($this->get($x, $y + 7) !== true ) $is_pattern = false;
		
							if ($is_pattern) $row_score += 40;
						}
					}
					
				}
				//echo "col: ".$x.": ".(int)$row_score."<br />";
				$score += $row_score;
			}
			return $score;
		}
		
		private function _penalty_4() {
			$score = 0;
			$dark = $this->nonzero_bit_count();
			$total = ($this->size_x() * $this->size_y());
			$light = $total - $dark;
			
			$score = $dark / $total;
			$score *=100;
			$score -=50;
			$score = (int)abs($score);
			$score = ($score / 5) * 10;
			return $score;
		}
		
		
		/////////////////////////////////////////////////////////////////////////////////////
		// ISO SPECIFFICATIONS //////////////////////////////////////////////////////////////
		/////////////////////////////////////////////////////////////////////////////////////
		
		private function _get_pa_locations() {
			switch ($this->_version) {
				case 1:  $a_pa = array(); break;
				case 2:  $a_pa = array(18); break;
				case 3:  $a_pa = array(22); break;
				case 4:  $a_pa = array(26); break;
				case 5:  $a_pa = array(30); break;
				case 6:  $a_pa = array(34); break;
				case 7:  $a_pa = array(6, 22, 38); break;
				case 8:  $a_pa = array(6, 24, 42); break;
				case 9:  $a_pa = array(6, 26, 46); break;
				case 10: $a_pa = array(6, 28, 50); break;
				case 11: $a_pa = array(6, 30, 54); break;
				case 12: $a_pa = array(6, 32, 58); break;
				case 13: $a_pa = array(6, 34, 62); break;
				case 14: $a_pa = array(6, 26, 46, 66); break;
				case 15: $a_pa = array(6, 26, 48, 70); break;
				case 16: $a_pa = array(6, 26, 50, 74); break;
				case 17: $a_pa = array(6, 30, 54, 78); break;
				case 18: $a_pa = array(6, 30, 56, 82); break;
				case 19: $a_pa = array(6, 30, 58, 86); break;
				case 20: $a_pa = array(6, 34, 62, 90); break;
				case 21: $a_pa = array(6, 28, 50, 72, 94); break;
				case 22: $a_pa = array(6, 26, 50, 74, 98); break;
				case 23: $a_pa = array(6, 30, 54, 78, 102); break;
				case 24: $a_pa = array(6, 28, 54, 80, 106); break;
				case 25: $a_pa = array(6, 32, 58, 84, 110); break;
				case 26: $a_pa = array(6, 30, 58, 86, 114); break;
				case 27: $a_pa = array(6, 34, 62, 90, 118); break;
				case 28: $a_pa = array(6, 26, 50, 74, 98,  122); break;
				case 29: $a_pa = array(6, 30, 54, 78, 102, 126); break;
				case 30: $a_pa = array(6, 26, 52, 78, 104, 130); break;
				case 31: $a_pa = array(6, 30, 56, 82, 108, 134); break;
				case 32: $a_pa = array(6, 34, 60, 86, 112, 138); break;
				case 33: $a_pa = array(6, 30, 58, 86, 114, 142); break;
				case 34: $a_pa = array(6, 34, 62, 90, 118, 146); break;
				case 35: $a_pa = array(6, 30, 54, 78, 102, 126, 150); break;
				case 36: $a_pa = array(6, 24, 50, 76, 102, 128, 154); break;
				case 37: $a_pa = array(6, 28, 54, 80, 106, 132, 158); break;
				case 38: $a_pa = array(6, 32, 58, 84, 110, 136, 162); break;
				case 39: $a_pa = array(6, 26, 54, 82, 110, 138, 166); break;
				case 40: $a_pa = array(6, 30, 58, 86, 114, 142, 170); break;
			}
			return $a_pa;
		}
		
		// returns a bit string for the version info next to the upper right and lower left 
		// position detection patterns for versions 7-40
		private function _get_version_info() {
			switch ($this->_version) {
				case 7:  return strrev("000111110010010100"); 
				case 8:  return strrev("001000010110111100"); 
				case 9:  return strrev("001001101010011001"); 
				case 10:  return strrev("001010010011010011"); 
				case 11:  return strrev("001011101111110110"); 
				case 12:  return strrev("001100011101100010"); 
				case 13:  return strrev("001101100001000111"); 
				case 14:  return strrev("001110011000001101"); 
				case 15:  return strrev("001111100100101000"); 
				case 16:  return strrev("010000101101111000"); 
				case 17:  return strrev("010001010001011101"); 
				case 18:  return strrev("010010101000010111"); 
				case 19:  return strrev("010011010100110010"); 
				case 20:  return strrev("010100100110100110"); 
				case 21:  return strrev("010101011010000011"); 
				case 22:  return strrev("010110100011001001"); 
				case 23:  return strrev("010111011111101100"); 
				case 24:  return strrev("011000111011000100"); 
				case 25:  return strrev("011001000111100001"); 
				case 26:  return strrev("011010111110101011"); 
				case 27:  return strrev("011011000010001110"); 
				case 28:  return strrev("011100110000011010"); 
				case 29:  return strrev("011101001100111111"); 
				case 30:  return strrev("011110110101110101"); 
				case 31:  return strrev("011111001001010000"); 
				case 32:  return strrev("100000100111010101"); 
				case 33:  return strrev("100001011011110000"); 
				case 34:  return strrev("100010100010111010"); 
				case 35:  return strrev("100011011110011111"); 
				case 36:  return strrev("100100101100001011"); 
				case 37:  return strrev("100101010000101110"); 
				case 38:  return strrev("100110101001100100"); 
				case 39:  return strrev("100111010101000001"); 
				case 40:  return strrev("101000110001101001");
				
				default: return NULL;
			}
		}
		
		// returns the bit string for the error correction level and version number
		// TO DO: not all tested, test all, write correct error throw
		private function _get_type_information_bits($ec_level = self::ERROR_Q, $mask = 0) {
			switch ($ec_level) {
				case self::ERROR_L: {
					switch ($mask) {
						case 0:	return "111011111000100";
						case 1:	return "111001011110011";
						case 2:	return "111110110101010";
						case 3:	return "111100010011101";
						case 4:	return "110011000101111";
						case 5:	return "110001100011000";
						case 6:	return "110110001000001";
						case 7:	return "110100101110110";
					}
				}
				case self::ERROR_M: {
					switch ($mask) {
						case 0:	return "101010000010010";
						case 1:	return "101000100100101";
						case 2:	return "101111001111100";
						case 3:	return "101101101001011";
						case 4:	return "100010111111001";
						case 5:	return "100000011001110";
						case 6:	return "100111110010111";
						case 7:	return "100101010100000";
					}
				}
				case self::ERROR_Q: {
					switch ($mask) {
						case 0:	return "011010101011111";
						case 1:	return "011000001101000";
						case 2:	return "011111100110001";
						case 3:	return "011101000000110";
						case 4:	return "010010010110100";
						case 5:	return "010000110000011";
						case 6:	return "010111011011010";
						case 7:	return "010101111101101";
					}
				}
				case self::ERROR_H: {
					switch ($mask) {
						case 0:	return "001011010001001";
						case 1:	return "001001110111110";
						case 2:	return "001110011100111";
						case 3:	return "001100111010000";
						case 4:	return "000011101100010";
						case 5:	return "000001001010101";
						case 6:	return "000110100001100";
						case 7:	return "000100000111011";
					}
				}
				
				default: throw "not a valid error correction level";
			}
		}
		
		// returns the number of error code words in each error code word block // changes untested
		protected function _get_nbr_ec_blocks() {
			switch ($this->_error_level) {
				case self::ERROR_L: return $this->_get_nbr_ec_blocks_l();
				case self::ERROR_M: return $this->_get_nbr_ec_blocks_m();
				case self::ERROR_Q: return $this->_get_nbr_ec_blocks_q();
				case self::ERROR_H: return $this->_get_nbr_ec_blocks_h();
				
				default: die("ERROR: _get_nbr_ec_blocks() was given an incorrect error level");
			}
		}
		// untested
		protected function _get_nbr_ec_blocks_l() {
			switch ($this->_version) {
				case 1:  return 7;
				case 2:  return 10;
				case 3:  return 15;
				case 4:  return 20;
				case 5:  return 26;
				case 6:  return 18;
				case 7:  return 20;
				case 8:  return 24;
				case 9:  return 30;
				case 10: return 18;
				case 11: return 20;
				case 12: return 24;
				case 13: return 26;
				case 14: return 30;
				case 15: return 22;
				case 16: return 24;
				case 17: return 28;
				case 18: return 30;
				case 19: return 28;
				case 20: return 28;
				case 21: return 28;
				case 22: return 28;
				case 23: return 30;
				case 24: return 30;
				case 25: return 26;
				case 26: return 28;
				
				default: {
					if ($this->_version < 41) return 30;
					else die("ERROR: _get_nbr_ec_blocks_l() was passed a wrong version nbr");
				}
			}
		}
		// untested
		protected function _get_nbr_ec_blocks_m() {
			switch ($this->_version) {
				case 1:  return 10;
				case 2:  return 16;
				case 3:  return 26;
				case 4:  return 18;
				case 5:  return 24;
				case 6:  return 16;
				case 7:  return 18;
				case 8:  return 22;
				case 9:  return 22;
				case 10: return 26;
				case 11: return 30;
				case 12: return 22;
				case 13: return 22;
				case 14: return 24;
				case 15: return 24;
				case 16: return 28;
				case 17: return 28;
				case 18: return 26;
				case 19: return 26;
				case 20: return 26;
				case 21: return 26;
				
				default: {
					if ($this->_version < 41) return 28;
					else die("ERROR: _get_nbr_ec_blocks_m() was passed a wrong version nbr");
				}
			}
		}
		protected function _get_nbr_ec_blocks_q() {
			switch ($this->_version) {
				case 1:  return 13;
				case 2:  return 22;
				case 3:  return 18;
				case 4:  return 26;
				case 5:  return 22;
				case 6:  return 24;
				case 7:  return 18;
				case 8:  return 22;
				case 9:  return 20;
				case 10: return 24;
				case 11: return 28;
				case 12: return 26;
				case 13: return 24;
				case 14: return 20;
				case 15: return 30;
				case 16: return 24;
				case 17: return 28;
				case 18: return 28;
				case 19: return 26;
				case 20: return 30;
				case 21: return 28;
				case 22: return 30;
				case 23: return 30;
				case 24: return 30;
				case 25: return 30;
				case 26: return 28;
				case 27: return 30;
				case 28: return 30;
				case 29: return 30;
				case 30: return 30;
				case 31: return 30;
				case 32: return 30;
				case 33: return 30;
				case 34: return 30;
				case 35: return 30;
				case 36: return 30;
				case 37: return 30;
				case 38: return 30;
				case 39: return 30;
				case 40: return 30;
				
				default: die("ERROR: _get_nbr_ec_blocks_q() was passed a wrong version nbr");
			}
		}
		// untested
		protected function _get_nbr_ec_blocks_h() {
			switch ($this->_version) {
				case 1:  return 17;
				case 2:  return 28;
				case 3:  return 22;
				case 4:  return 16;
				case 5:  return 22;
				case 6:  return 28;
				case 7:  return 26;
				case 8:  return 26;
				case 9:  return 24;
				case 10: return 28;
				case 11: return 24;
				case 12: return 28;
				case 13: return 22;
				case 14: return 24;
				case 15: return 24;
				case 16: return 30;
				case 17: return 28;
				case 18: return 28;
				case 19: return 26;
				case 20: return 28;
				case 21: return 30;
				case 22: return 24;
				
				default: {
					if ($this->_version < 41) return 30;
					else die("ERROR: _get_nbr_ec_blocks_h() was passed a wrong version nbr");
				}
			}
		}
		
		protected function _get_bit_length_data_length() {
			if ($this->_version < 10) {
				switch ($this->_mode) {
					case self::MODE_NUM: return 10;
					case self::MODE_ALP: return 9;
					case self::MODE_BIN: return 8;
					case self::MODE_JAP: return 8;
				}
			} else if ($this->_version < 27 ) {
				switch ($this->_mode) {
					case self::MODE_NUM: return 12;
					case self::MODE_ALP: return 11;
					case self::MODE_BIN: return 16;
					case self::MODE_JAP: return 10;
				}
			} else {
				switch ($this->_mode) {
					case self::MODE_NUM: return 14;
					case self::MODE_ALP: return 13;
					case self::MODE_BIN: return 16;
					case self::MODE_JAP: return 12;
				}
			}
		}
		
		// changes untested
		protected function _data_bits() {
			switch ($this->_error_level) {
				case self::ERROR_L: return $this->_data_bits_l();
				case self::ERROR_M: return $this->_data_bits_m();
				case self::ERROR_Q: return $this->_data_bits_q();
				case self::ERROR_H: return $this->_data_bits_h();
				
				default: die("ERROR: _data_bits() was given an incorrect error level");
			}
		}
		// untested
		private function _data_bits_l() {
			switch ($this->_version) {
				case 1:  return 152;
				case 2:  return 272;
				case 3:  return 440;
				case 4:  return 640;
				case 5:  return 864;
				case 6:  return 1088;
				case 7:  return 1248;
				case 8:  return 1552;
				case 9:  return 1856;
				case 10: return 2192;
				case 11: return 2592;
				case 12: return 2960;
				case 13: return 3424;
				case 14: return 3688;
				case 15: return 4184;
				case 16: return 4712;
				case 17: return 5176;
				case 18: return 5768;
				case 19: return 6360;
				case 20: return 6888;
				case 21: return 7456;
				case 22: return 8048;
				case 23: return 8752;
				case 24: return 9392;
				case 25: return 10208;
				case 26: return 10960;
				case 27: return 11744;
				case 28: return 12248;
				case 29: return 13048;
				case 30: return 13880;
				case 31: return 14744;
				case 32: return 15640;
				case 33: return 16568;
				case 34: return 17528;
				case 35: return 18448;
				case 36: return 19472;
				case 37: return 20528;
				case 38: return 21616;
				case 39: return 22496;
				case 40: return 23648;
			
				default: die("ERROR: wrong version in _data_bits_l()");
			}
		}
		// untested
		private function _data_bits_m() {
			switch ($this->_version) {
				case 1:  return 128;
				case 2:  return 224;
				case 3:  return 352;
				case 4:  return 512;
				case 5:  return 688;
				case 6:  return 864;
				case 7:  return 992;
				case 8:  return 1232;
				case 9:  return 1456;
				case 10: return 1728;
				case 11: return 2032;
				case 12: return 2320;
				case 13: return 2672;
				case 14: return 2920;
				case 15: return 3320;
				case 16: return 3624;
				case 17: return 4056;
				case 18: return 4504;
				case 19: return 5016;
				case 20: return 5352;
				case 21: return 5712;
				case 22: return 6256;
				case 23: return 6880;
				case 24: return 7312;
				case 25: return 8000;
				case 26: return 8496;
				case 27: return 9024;
				case 28: return 9544;
				case 29: return 10136;
				case 30: return 10984;
				case 31: return 11640;
				case 32: return 12328;
				case 33: return 13048;
				case 34: return 13800;
				case 35: return 14496;
				case 36: return 15312;
				case 37: return 15936;
				case 38: return 16816;
				case 39: return 17728;
				case 40: return 18672;
			
				default: die("ERROR: wrong version in _data_bits_m()");
			}
		}
		private function _data_bits_q() {
			switch ($this->_version) {
				case 1:  return 104;
				case 2:  return 176;
				case 3:  return 272;
				case 4:  return 384;
				case 5:  return 496;
				case 6:  return 608;
				case 7:  return 704;
				case 8:  return 880;
				case 9:  return 1056;
				case 10: return 1232;
				case 11: return 1440;
				case 12: return 1648;
				case 13: return 1952;
				case 14: return 2088;
				case 15: return 2360;
				case 16: return 2600;
				case 17: return 2936;
				case 18: return 3176;
				case 19: return 3560;
				case 20: return 3880;
				case 21: return 4096;
				case 22: return 4544;
				case 23: return 4912;
				case 24: return 5312;
				case 25: return 5744;
				case 26: return 6032;
				case 27: return 6464;
				case 28: return 6968;
				case 29: return 7288;
				case 30: return 7880;
				case 31: return 8264;
				case 32: return 8920;
				case 33: return 9368;
				case 34: return 9848;
				case 35: return 10288;
				case 36: return 10832;
				case 37: return 11408;
				case 38: return 12016;
				case 39: return 12656;
				case 40: return 13328;
				
				default: die("ERROR: wrong version in _data_bits_q()");
			}
		}
		// untested
		private function _data_bits_h() {
			switch ($this->_version) {
				case 1:  return 72;
				case 2:  return 128;
				case 3:  return 208;
				case 4:  return 288;
				case 5:  return 368;
				case 6:  return 480;
				case 7:  return 528;
				case 8:  return 688;
				case 9:  return 800;
				case 10: return 976;
				case 11: return 1120;
				case 12: return 1264;
				case 13: return 1440;
				case 14: return 1576;
				case 15: return 1784;
				case 16: return 2024;
				case 17: return 2264;
				case 18: return 2504;
				case 19: return 2728;
				case 20: return 3080;
				case 21: return 3248;
				case 22: return 3536;
				case 23: return 3712;
				case 24: return 4112;
				case 25: return 4304;
				case 26: return 4768;
				case 27: return 5024;
				case 28: return 5288;
				case 29: return 5608;
				case 30: return 5960;
				case 31: return 6344;
				case 32: return 6760;
				case 33: return 7208;
				case 34: return 7688;
				case 35: return 7888;
				case 36: return 8432;
				case 37: return 8768;
				case 38: return 9136;
				case 39: return 9776;
				case 40: return 10208;
			
				default: die("ERROR: wrong version in _data_bits_h()");
			}
		}
		
		protected function _get_nbr_data_blocks() {
			$version = $this->_version;
			$ec = $this->_error_level;
			switch ($ec) {
				case self::ERROR_L: return $this->_get_nbr_data_blocks_l($version);
				case self::ERROR_M: return $this->_get_nbr_data_blocks_m($version);
				case self::ERROR_Q: return $this->_get_nbr_data_blocks_q($version);
				case self::ERROR_H: return $this->_get_nbr_data_blocks_h($version);
				default: die("ERROR: Unsupoported error-correction");
			}
		}
		
		// untested
		protected function _get_nbr_data_blocks_l($version) {
			$a_ec_blocks = array();
			switch ($version) {
				case 1: 
					$a_ec_blocks[0] = 19;
					return $a_ec_blocks;
				case 2: 
					$a_ec_blocks[0] = 34;
					return $a_ec_blocks;
				case 3: 
					$a_ec_blocks[0] = 55;
					return $a_ec_blocks;
				case 4: 
					$a_ec_blocks[0] = 80;
					return $a_ec_blocks;
				case 5: 
					$a_ec_blocks[0] = 108;
					return $a_ec_blocks;
				case 6: 
					$a_ec_blocks[0] = 68;
					$a_ec_blocks[1] = 68;
					return $a_ec_blocks;
				case 7: 
					$a_ec_blocks[0] = 78;
					$a_ec_blocks[1] = 78;
					return $a_ec_blocks;
				case 8: 
					$a_ec_blocks[0] = 97;
					$a_ec_blocks[1] = 97;
					return $a_ec_blocks;
				case 9: 
					$a_ec_blocks[0] = 116;
					$a_ec_blocks[1] = 116;
					return $a_ec_blocks;	
				case 10: 
					$count_b1 = 2;
					$count_b2 = 2;
					$words_b1 = 68;
					$words_b2 = 69;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 11: 
					$count_b1 = 4;
					$words_b1 = 81;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 12: 
					$count_b1 = 2;
					$count_b2 = 2;
					$words_b1 = 92;
					$words_b2 = 93;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 13: 
					$count_b1 = 4;
					$words_b1 = 107;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 14: 
					$count_b1 = 3;
					$count_b2 = 1;
					$words_b1 = 115;
					$words_b2 = 116;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 15: 
					$count_b1 = 5;
					$count_b2 = 1;
					$words_b1 = 87;
					$words_b2 = 88;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 16: 
					$count_b1 = 5;
					$count_b2 = 1;
					$words_b1 = 98;
					$words_b2 = 99;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 17: 
					$count_b1 = 1;
					$count_b2 = 5;
					$words_b1 = 107;
					$words_b2 = 108;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 18: 
					$count_b1 = 5;
					$count_b2 = 1;
					$words_b1 = 120;
					$words_b2 = 121;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 19: 
					$count_b1 = 3;
					$count_b2 = 4;
					$words_b1 = 113;
					$words_b2 = 114;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 20: 
					$count_b1 = 3;
					$count_b2 = 5;
					$words_b1 = 107;
					$words_b2 = 108;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 21: 
					$count_b1 = 4;
					$count_b2 = 4;
					$words_b1 = 116;
					$words_b2 = 117;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 22: 
					$count_b1 = 2;
					$count_b2 = 7;
					$words_b1 = 111;
					$words_b2 = 112;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 23: 
					$count_b1 = 4;
					$count_b2 = 5;
					$words_b1 = 121;
					$words_b2 = 122;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 24: 
					$count_b1 = 6;
					$count_b2 = 4;
					$words_b1 = 117;
					$words_b2 = 118;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 25: 
					$count_b1 = 8;
					$count_b2 = 4;
					$words_b1 = 106;
					$words_b2 = 107;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 26: 
					$count_b1 = 10;
					$count_b2 = 2;
					$words_b1 = 114;
					$words_b2 = 115;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 27: 
					$count_b1 = 8;
					$count_b2 = 4;
					$words_b1 = 122;
					$words_b2 = 123;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 28: 
					$count_b1 = 3;
					$count_b2 = 10;
					$words_b1 = 117;
					$words_b2 = 118;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 29: 
					$count_b1 = 7;
					$count_b2 = 7;
					$words_b1 = 116;
					$words_b2 = 117;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 30: 
					$count_b1 = 5;
					$count_b2 = 10;
					$words_b1 = 115;
					$words_b2 = 116;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 31: 
					$count_b1 = 13;
					$count_b2 = 3;
					$words_b1 = 115;
					$words_b2 = 116;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 32: 
					$count_b1 = 17;
					$words_b1 = 115;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 33: 
					$count_b1 = 17;
					$count_b2 = 1;
					$words_b1 = 115;
					$words_b2 = 116;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 34: 
					$count_b1 = 13;
					$count_b2 = 6;
					$words_b1 = 115;
					$words_b2 = 116;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 35: 
					$count_b1 = 12;
					$count_b2 = 7;
					$words_b1 = 121;
					$words_b2 = 122;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 36: 
					$count_b1 = 6;
					$count_b2 = 14;
					$words_b1 = 121;
					$words_b2 = 122;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 37: 
					$count_b1 = 17;
					$count_b2 = 4;
					$words_b1 = 122;
					$words_b2 = 123;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 38: 
					$count_b1 = 4;
					$count_b2 = 18;
					$words_b1 = 122;
					$words_b2 = 123;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 39: 
					$count_b1 = 20;
					$count_b2 = 4;
					$words_b1 = 117;
					$words_b2 = 118;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 40: 
					$count_b1 = 19;
					$count_b2 = 6;
					$words_b1 = 118;
					$words_b2 = 119;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
					
				default: die("ERROR: the version and error correction level are not supported in _get_nbr_data_blocks() and thier subfunctions");
			}
		}
		// untested
		protected function _get_nbr_data_blocks_m($version) {
			$a_ec_blocks = array();
			switch ($version) {
				case 1: 
					$a_ec_blocks[0] = 16;
					return $a_ec_blocks;
				case 2: 
					$a_ec_blocks[0] = 28;
					return $a_ec_blocks;
				case 3: 
					$a_ec_blocks[0] = 44;
					return $a_ec_blocks;
				case 4: 
					$a_ec_blocks[0] = 32;
					$a_ec_blocks[1] = 32;
					return $a_ec_blocks;
				case 5: 
					$a_ec_blocks[0] = 43;
					$a_ec_blocks[1] = 43;
					return $a_ec_blocks;
				case 6: 
					$count_b1 = 4;
					$words_b1 = 27;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 7: 
					$count_b1 = 4;
					$words_b1 = 31;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 8: 
					$count_b1 = 2;
					$count_b2 = 2;
					$words_b1 = 38;
					$words_b2 = 39;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 9: 
					$count_b1 = 3;
					$count_b2 = 2;
					$words_b1 = 36;
					$words_b2 = 37;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 10: 
					$count_b1 = 4;
					$count_b2 = 1;
					$words_b1 = 43;
					$words_b2 = 44;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 11: 
					$count_b1 = 1;
					$count_b2 = 4;
					$words_b1 = 50;
					$words_b2 = 51;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 12: 
					$count_b1 = 6;
					$count_b2 = 2;
					$words_b1 = 36;
					$words_b2 = 37;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 13: 
					$count_b1 = 8;
					$count_b2 = 1;
					$words_b1 = 37;
					$words_b2 = 38;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 14: 
					$count_b1 = 4;
					$count_b2 = 5;
					$words_b1 = 40;
					$words_b2 = 41;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 15: 
					$count_b1 = 5;
					$count_b2 = 5;
					$words_b1 = 41;
					$words_b2 = 42;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 16: 
					$count_b1 = 7;
					$count_b2 = 3;
					$words_b1 = 45;
					$words_b2 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 17: 
					$count_b1 = 10;
					$count_b2 = 1;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 18: 
					$count_b1 = 9;
					$count_b2 = 4;
					$words_b1 = 43;
					$words_b2 = 44;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 19: 
					$count_b1 = 3;
					$count_b2 = 11;
					$words_b1 = 44;
					$words_b2 = 45;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 20: 
					$count_b1 = 3;
					$count_b2 = 13;
					$words_b1 = 41;
					$words_b2 = 42;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 21: 
					$count_b1 = 17;
					$words_b1 = 42;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 22: 
					$count_b1 = 17;
					$words_b1 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 23: 
					$count_b1 = 4;
					$count_b2 = 14;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 24: 
					$count_b1 = 6;
					$count_b2 = 14;
					$words_b1 = 45;
					$words_b2 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 25: 
					$count_b1 = 8;
					$count_b2 = 13;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 26: 
					$count_b1 = 19;
					$count_b2 = 4;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 27: 
					$count_b1 = 22;
					$count_b2 = 3;
					$words_b1 = 45;
					$words_b2 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 28: 
					$count_b1 = 3;
					$count_b2 = 23;
					$words_b1 = 45;
					$words_b2 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 29: 
					$count_b1 = 21;
					$count_b2 = 7;
					$words_b1 = 45;
					$words_b2 = 46;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 30: 
					$count_b1 = 19;
					$count_b2 = 10;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 31: 
					$count_b1 = 2;
					$count_b2 = 29;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 32: 
					$count_b1 = 10;
					$count_b2 = 23;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 33: 
					$count_b1 = 14;
					$count_b2 = 21;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 34: 
					$count_b1 = 14;
					$count_b2 = 23;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 35: 
					$count_b1 = 12;
					$count_b2 = 26;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 36: 
					$count_b1 = 6;
					$count_b2 = 34;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 37: 
					$count_b1 = 29;
					$count_b2 = 14;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 38: 
					$count_b1 = 13;
					$count_b2 = 32;
					$words_b1 = 46;
					$words_b2 = 47;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 39: 
					$count_b1 = 40;
					$count_b2 = 7;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 40: 
					$count_b1 = 18;
					$count_b2 = 31;
					$words_b1 = 47;
					$words_b2 = 48;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
					
				default: die("ERROR: the version and error correction level are not supported in _get_nbr_data_blocks() and thier subfunctions");
			}
		}
		protected function _get_nbr_data_blocks_q($version) {
			$a_ec_blocks = array();
			switch ($version) {
				case 1: 
					$a_ec_blocks[0] = 13;
					return $a_ec_blocks;
				case 2: 
					$a_ec_blocks[0] = 22;
					return $a_ec_blocks;
				case 3: 
					$a_ec_blocks[0] = 17;
					$a_ec_blocks[1] = 17;
					return $a_ec_blocks;
				case 4: 
					$a_ec_blocks[0] = 24;
					$a_ec_blocks[1] = 24;
					return $a_ec_blocks;
				case 5: 
					$a_ec_blocks[0] = 15;
					$a_ec_blocks[1] = 15;
					$a_ec_blocks[2] = 16;
					$a_ec_blocks[3] = 16;
					return $a_ec_blocks;
				case 6: 
					$a_ec_blocks[0] = 19;
					$a_ec_blocks[1] = 19;
					$a_ec_blocks[2] = 19;
					$a_ec_blocks[3] = 19;
					return $a_ec_blocks;
				case 7: 
					$count_b1 = 2;
					$count_b2 = 4;
					$words_b1 = 14;
					$words_b2 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 8: 
					$count_b1 = 4;
					$count_b2 = 2;
					$words_b1 = 18;
					$words_b2 = 19;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 9: 
					$count_b1 = 4;
					$count_b2 = 4;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 10: 
					$count_b1 = 6;
					$count_b2 = 2;
					$words_b1 = 19;
					$words_b2 = 20;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 11: 
					$count_b1 = 4;
					$count_b2 = 4;
					$words_b1 = 22;
					$words_b2 = 23;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 12: 
					$count_b1 = 4;
					$count_b2 = 6;
					$words_b1 = 20;
					$words_b2 = 21;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 13: 
					$count_b1 = 8;
					$count_b2 = 4;
					$words_b1 = 20;
					$words_b2 = 21;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 14: 
					$count_b1 = 11;
					$count_b2 = 5;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 15: 
					$count_b1 = 5;
					$count_b2 = 7;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 16: 
					$count_b1 = 15;
					$count_b2 = 2;
					$words_b1 = 19;
					$words_b2 = 20;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 17: 
					$count_b1 = 1;
					$count_b2 = 15;
					$words_b1 = 22;
					$words_b2 = 23;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 18: 
					$count_b1 = 17;
					$count_b2 = 1;
					$words_b1 = 22;
					$words_b2 = 23;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 19: 
					$count_b1 = 17;
					$count_b2 = 4;
					$words_b1 = 21;
					$words_b2 = 22;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 20: 
					$count_b1 = 15;
					$count_b2 = 5;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 21: 
					$count_b1 = 17;
					$count_b2 = 6;
					$words_b1 = 22;
					$words_b2 = 23;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 22: 
					$count_b1 = 7;
					$count_b2 = 16;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 23: 
					$count_b1 = 11;
					$count_b2 = 14;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 24: 
					$count_b1 = 11;
					$count_b2 = 16;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 25: 
					$count_b1 = 7;
					$count_b2 = 22;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 26: 
					$count_b1 = 28;
					$count_b2 = 6;
					$words_b1 = 22;
					$words_b2 = 23;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 27: 
					$count_b1 = 8;
					$count_b2 = 26;
					$words_b1 = 23;
					$words_b2 = 24;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 28: 
					$count_b1 = 4;
					$count_b2 = 31;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 29: 
					$count_b1 = 1;
					$count_b2 = 37;
					$words_b1 = 23;
					$words_b2 = 24;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 30: 
					$count_b1 = 15;
					$count_b2 = 25;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 31: 
					$count_b1 = 42;
					$count_b2 = 1;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 32: 
					$count_b1 = 10;
					$count_b2 = 35;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 33: 
					$count_b1 = 29;
					$count_b2 = 19;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 34: 
					$count_b1 = 44;
					$count_b2 = 7;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 35: 
					$count_b1 = 39;
					$count_b2 = 14;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 36: 
					$count_b1 = 46;
					$count_b2 = 10;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 37: 
					$count_b1 = 49;
					$count_b2 = 10;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 38: 
					$count_b1 = 48;
					$count_b2 = 14;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 39: 
					$count_b1 = 43;
					$count_b2 = 22;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 40: 
					$count_b1 = 34;
					$count_b2 = 34;
					$words_b1 = 24;
					$words_b2 = 25;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				
				default: die("ERROR: _get_nbr_data_blocks_q() was passed an incorrect version");
			}
		}
		// untested
		protected function _get_nbr_data_blocks_h($version) {
			$a_ec_blocks = array();
			switch ($version) {
				case 1: 
					$a_ec_blocks[0] = 9;
					return $a_ec_blocks;
				case 2: 
					$a_ec_blocks[0] = 16;
					return $a_ec_blocks;
				case 3: 
					$a_ec_blocks[0] = 13;
					$a_ec_blocks[1] = 13;
					return $a_ec_blocks;
				case 4: 
					$count_b1 = 4;
					$words_b1 = 9;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 5: 
					$count_b1 = 2;
					$count_b2 = 2;
					$words_b1 = 11;
					$words_b2 = 12;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 6: 
					$count_b1 = 4;
					$words_b1 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 7: 
					$count_b1 = 4;
					$count_b2 = 1;
					$words_b1 = 13;
					$words_b2 = 14;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 8: 
					$count_b1 = 4;
					$count_b2 = 2;
					$words_b1 = 14;
					$words_b2 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 9: 
					$count_b1 = 4;
					$count_b2 = 4;
					$words_b1 = 12;
					$words_b2 = 13;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 10: 
					$count_b1 = 6;
					$count_b2 = 2;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 11: 
					$count_b1 = 3;
					$count_b2 = 8;
					$words_b1 = 12;
					$words_b2 = 13;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 12: 
					$count_b1 = 7;
					$count_b2 = 4;
					$words_b1 = 14;
					$words_b2 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 13: 
					$count_b1 = 12;
					$count_b2 = 4;
					$words_b1 = 11;
					$words_b2 = 12;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 14: 
					$count_b1 = 11;
					$count_b2 = 5;
					$words_b1 = 12;
					$words_b2 = 13;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 15: 
					$count_b1 = 11;
					$count_b2 = 7;
					$words_b1 = 12;
					$words_b2 = 13;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 16: 
					$count_b1 = 3;
					$count_b2 = 13;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 17: 
					$count_b1 = 2;
					$count_b2 = 17;
					$words_b1 = 14;
					$words_b2 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 18: 
					$count_b1 = 2;
					$count_b2 = 19;
					$words_b1 = 14;
					$words_b2 = 15;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 19: 
					$count_b1 = 9;
					$count_b2 = 16;
					$words_b1 = 13;
					$words_b2 = 14;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 20: 
					$count_b1 = 15;
					$count_b2 = 10;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 21: 
					$count_b1 = 19;
					$count_b2 = 6;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 22: 
					$count_b1 = 34;
					$words_b1 = 13;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					return $a_ec_blocks;
				case 23: 
					$count_b1 = 16;
					$count_b2 = 17;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 24: 
					$count_b1 = 30;
					$count_b2 = 2;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 25: 
					$count_b1 = 22;
					$count_b2 = 13;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 26: 
					$count_b1 = 33;
					$count_b2 = 4;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 27: 
					$count_b1 = 12;
					$count_b2 = 28;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 28: 
					$count_b1 = 11;
					$count_b2 = 31;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 29: 
					$count_b1 = 19;
					$count_b2 = 26;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 30: 
					$count_b1 = 23;
					$count_b2 = 25;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 31: 
					$count_b1 = 23;
					$count_b2 = 28;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 32: 
					$count_b1 = 19;
					$count_b2 = 35;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 33: 
					$count_b1 = 11;
					$count_b2 = 46;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 34: 
					$count_b1 = 59;
					$count_b2 = 1;
					$words_b1 = 16;
					$words_b2 = 17;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 35: 
					$count_b1 = 22;
					$count_b2 = 41;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 36: 
					$count_b1 = 2;
					$count_b2 = 64;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 37: 
					$count_b1 = 24;
					$count_b2 = 46;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 38: 
					$count_b1 = 42;
					$count_b2 = 32;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 39: 
					$count_b1 = 10;
					$count_b2 = 67;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
				case 40: 
					$count_b1 = 20;
					$count_b2 = 61;
					$words_b1 = 15;
					$words_b2 = 16;
					for ($i = 0; $i < $count_b1; $i++) 
						$a_ec_blocks[$i] = $words_b1;
					for ($i = $count_b1; $i < ($count_b1 + $count_b2); $i++) 
						$a_ec_blocks[$i] = $words_b2;
					return $a_ec_blocks;
					
				default: die("ERROR: the version and error correction level are not supported in _get_nbr_data_blocks() and thier subfunctions");
			}
		}
		
		// TO DO: Only supports alph, bin, num
		protected function _get_max_message_length() {
			$version = $this->_version;
			$ec = $this->_error_level;
			switch ($this->_mode) {
				case self::MODE_ALP: 
					return $this->_get_max_message_length_alp($version, $ec);
				case self::MODE_BIN:
					return $this->_get_max_message_length_bin($version, $ec);
				case self::MODE_NUM:
					return $this->_get_max_message_length_num($version, $ec);
				default: die("Not all modes are supported yet");
			}		 
		}
		
		//
		private function _get_max_message_length_alp($version, $ec) {
			switch ($ec) {
				case self::ERROR_L: return $this->_get_max_message_length_alp_l($version);
				case self::ERROR_M: return $this->_get_max_message_length_alp_m($version);
				case self::ERROR_Q: return $this->_get_max_message_length_alp_q($version);
				case self::ERROR_H: return $this->_get_max_message_length_alp_h($version);
				default: die("ERROR: incorrect error correction level");
			}
		}
		// not tested
		private function _get_max_message_length_alp_l($version) {
			switch ($version) {
				case 1:  return 25;
				case 2:  return 47;
				case 3:  return 77;
				case 4:  return 114;
				case 5:  return 154;
				case 6:  return 195;
				case 7:  return 224;
				case 8:  return 279;
				case 9:  return 335;
				case 10: return 395;
				case 11: return 468;
				case 12: return 535;
				case 13: return 619;
				case 14: return 667;
				case 15: return 758;
				case 16: return 854;
				case 17: return 938;
				case 18: return 1046;
				case 19: return 1153;
				case 20: return 1249;
				case 21: return 1352;
				case 22: return 1460;
				case 23: return 1588;
				case 24: return 1704;
				case 25: return 1853;
				case 26: return 1990;
				case 27: return 2132;
				case 28: return 2223;
				case 29: return 2369;
				case 30: return 2520;
				case 31: return 2677;
				case 32: return 2840;
				case 33: return 3009;
				case 34: return 3183;
				case 35: return 3351;
				case 36: return 3537;
				case 37: return 3729;
				case 38: return 3927;
				case 39: return 4087;
				case 40: return 4296;
				default: die ("ERROR: _get_max_message_length_alp_l() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_alp_m($version) {
			switch ($version) {
				case 1:  return 20;
				case 2:  return 38;
				case 3:  return 61;
				case 4:  return 90;
				case 5:  return 122;
				case 6:  return 154;
				case 7:  return 178;
				case 8:  return 221;
				case 9:  return 262;
				case 10: return 311;
				case 11: return 366;
				case 12: return 419;
				case 13: return 483;
				case 14: return 528;
				case 15: return 600;
				case 16: return 656;
				case 17: return 734;
				case 18: return 816;
				case 19: return 909;
				case 20: return 970;
				case 21: return 1035;
				case 22: return 1134;
				case 23: return 1248;
				case 24: return 1326;
				case 25: return 1451;
				case 26: return 1542;
				case 27: return 1637;
				case 28: return 1732;
				case 29: return 1839;
				case 30: return 1994;
				case 31: return 2113;
				case 32: return 2238;
				case 33: return 2369;
				case 34: return 2506;
				case 35: return 2632;
				case 36: return 2780;
				case 37: return 2894;
				case 38: return 3054;
				case 39: return 3220;
				case 40: return 3391;
				default: die ("ERROR: _get_max_message_length_alp_m() was given an invalid version number.");
			}
		}
		// not tested for version 8-40
		private function _get_max_message_length_alp_q($version) {
			switch ($version) {
				case 1: return 16;
				case 2: return 29;
				case 3: return 47;
				case 4: return 67;
				case 5: return 87;
				case 6: return 108;
				case 7: return 125;
				case 8:  return 157;
				case 9:  return 189;
				case 10: return 221;
				case 11: return 259;
				case 12: return 296;
				case 13: return 352;
				case 14: return 376;
				case 15: return 426;
				case 16: return 470;
				case 17: return 531;
				case 18: return 574;
				case 19: return 644;
				case 20: return 702;
				case 21: return 742;
				case 22: return 823;
				case 23: return 890;
				case 24: return 963;
				case 25: return 1041;
				case 26: return 1094;
				case 27: return 1172;
				case 28: return 1263;
				case 29: return 1322;
				case 30: return 1429;
				case 31: return 1499;
				case 32: return 1618;
				case 33: return 1700;
				case 34: return 1787;
				case 35: return 1867;
				case 36: return 1966;
				case 37: return 2071;
				case 38: return 2181;
				case 39: return 2298;
				case 40: return 2420;
				default: die ("ERROR: _get_max_message_length_alp_q() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_alp_h($version) {
			switch ($version) {
				case 1:  return 10;
				case 2:  return 20;
				case 3:  return 35;
				case 4:  return 50;
				case 5:  return 64;
				case 6:  return 84;
				case 7:  return 93;
				case 8:  return 122;
				case 9:  return 143;
				case 10: return 174;
				case 11: return 200;
				case 12: return 227;
				case 13: return 259;
				case 14: return 283;
				case 15: return 321;
				case 16: return 365;
				case 17: return 408;
				case 18: return 452;
				case 19: return 493;
				case 20: return 557;
				case 21: return 587;
				case 22: return 640;
				case 23: return 672;
				case 24: return 744;
				case 25: return 779;
				case 26: return 864;
				case 27: return 910;
				case 28: return 958;
				case 29: return 1016;
				case 30: return 1080;
				case 31: return 1150;
				case 32: return 1226;
				case 33: return 1307;
				case 34: return 1394;
				case 35: return 1431;
				case 36: return 1530;
				case 37: return 1591;
				case 38: return 1658;
				case 39: return 1774;
				case 40: return 1852;
				default: die ("ERROR: _get_max_message_length_alp_h() was given an invalid version number.");
			}
		}
		
		//
		private function _get_max_message_length_bin($version, $ec) {
			switch ($ec) {
				case self::ERROR_L: return $this->_get_max_message_length_bin_l($version);
				case self::ERROR_M: return $this->_get_max_message_length_bin_m($version);
				case self::ERROR_Q: return $this->_get_max_message_length_bin_q($version);
				case self::ERROR_H: return $this->_get_max_message_length_bin_h($version);
				default: die("ERROR: incorrect error correction level");
			}
		}
		// not tested
		private function _get_max_message_length_bin_l($version) {
			switch ($version) {
				case 1:  return 17;
				case 2:  return 32;
				case 3:  return 53;
				case 4:  return 78;
				case 5:  return 106;
				case 6:  return 134;
				case 7:  return 154;
				case 8:  return 192;
				case 9:  return 230;
				case 10: return 271;
				case 11: return 321;
				case 12: return 367;
				case 13: return 425;
				case 14: return 458;
				case 15: return 520;
				case 16: return 586;
				case 17: return 644;
				case 18: return 718;
				case 19: return 792;
				case 20: return 858;
				case 21: return 929;
				case 22: return 1003;
				case 23: return 1091;
				case 24: return 1171;
				case 25: return 1273;
				case 26: return 1367;
				case 27: return 1465;
				case 28: return 1528;
				case 29: return 1628;
				case 30: return 1732;
				case 31: return 1840;
				case 32: return 1952;
				case 33: return 2068;
				case 34: return 2188;
				case 35: return 2303;
				case 36: return 2431;
				case 37: return 2563;
				case 38: return 2699;
				case 39: return 2809;
				case 40: return 2953;
				default: die ("ERROR: _get_max_message_length_bin_l() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_bin_m($version) {
			switch ($version) {
				case 1:  return 14;
				case 2:  return 26;
				case 3:  return 42;
				case 4:  return 62;
				case 5:  return 84;
				case 6:  return 106;
				case 7:  return 122;
				case 8:  return 152;
				case 9:  return 180;
				case 10: return 213;
				case 11: return 251;
				case 12: return 287;
				case 13: return 331;
				case 14: return 362;
				case 15: return 412;
				case 16: return 450;
				case 17: return 504;
				case 18: return 560;
				case 19: return 624;
				case 20: return 666;
				case 21: return 711;
				case 22: return 779;
				case 23: return 857;
				case 24: return 911;
				case 25: return 997;
				case 26: return 1059;
				case 27: return 1125;
				case 28: return 1190;
				case 29: return 1264;
				case 30: return 1370;
				case 31: return 1452;
				case 32: return 1538;
				case 33: return 1628;
				case 34: return 1722;
				case 35: return 1809;
				case 36: return 1911;
				case 37: return 1989;
				case 38: return 2099;
				case 39: return 2213;
				case 40: return 2331;
				default: die ("ERROR: _get_max_message_length_bin_m() was given an invalid version number.");
			}
		}
		// complete
		private function _get_max_message_length_bin_q($version) {
			switch ($version) {
				case 1:  return 11;
				case 2:  return 20;
				case 3:  return 32;
				case 4:  return 46;
				case 5:  return 60;
				case 6:  return 74;
				case 7:  return 86;
				case 8:  return 108;
				case 9:  return 130;
				case 10: return 151;
				case 11: return 177;
				case 12: return 203;
				case 13: return 241;
				case 14: return 258;
				case 15: return 292;
				case 16: return 322;
				case 17: return 364;
				case 18: return 394;
				case 19: return 442;
				case 20: return 482;
				case 21: return 509;
				case 22: return 565;
				case 23: return 611;
				case 24: return 661;
				case 25: return 715;
				case 26: return 751;
				case 27: return 805;
				case 28: return 868;
				case 29: return 908;
				case 30: return 982;
				case 31: return 1030;
				case 32: return 1112;
				case 33: return 1168;
				case 34: return 1228;
				case 35: return 1283;
				case 36: return 1351;
				case 37: return 1423;
				case 38: return 1499;
				case 39: return 1579;
				case 40: return 1663;
				default: die ("ERROR: _get_max_message_length_bin_q() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_bin_h($version) {
			switch ($version) {
				case 1:  return 7;
				case 2:  return 14;
				case 3:  return 24;
				case 4:  return 34;
				case 5:  return 44;
				case 6:  return 58;
				case 7:  return 64;
				case 8:  return 84;
				case 9:  return 98;
				case 10: return 119;
				case 11: return 137;
				case 12: return 155;
				case 13: return 177;
				case 14: return 194;
				case 15: return 220;
				case 16: return 250;
				case 17: return 280;
				case 18: return 310;
				case 19: return 338;
				case 20: return 382;
				case 21: return 403;
				case 22: return 439;
				case 23: return 461;
				case 24: return 511;
				case 25: return 535;
				case 26: return 593;
				case 27: return 625;
				case 28: return 658;
				case 29: return 698;
				case 30: return 742;
				case 31: return 790;
				case 32: return 842;
				case 33: return 898;
				case 34: return 958;
				case 35: return 983;
				case 36: return 1051;
				case 37: return 1093;
				case 38: return 1139;
				case 39: return 1219;
				case 40: return 1273;
				default: die ("ERROR: _get_max_message_length_bin_h() was given an invalid version number.");
			}
		}
		
		//
		private function _get_max_message_length_num($version, $ec) {
			switch ($ec) {
				case self::ERROR_L: return $this->_get_max_message_length_num_l($version);
				case self::ERROR_M: return $this->_get_max_message_length_num_m($version);
				case self::ERROR_Q: return $this->_get_max_message_length_num_q($version);
				case self::ERROR_H: return $this->_get_max_message_length_num_h($version);
				default: die("ERROR: incorrect error correction level");
			}
		}
		// not tested
		private function _get_max_message_length_num_l($version) {
			switch ($version) {
				case 1:  return 41;
				case 2:  return 77;
				case 3:  return 127;
				case 4:  return 187;
				case 5:  return 255;
				case 6:  return 322;
				case 7:  return 370;
				case 8:  return 461;
				case 9:  return 552;
				case 10: return 652;
				case 11: return 772;
				case 12: return 883;
				case 13: return 1022;
				case 14: return 1101;
				case 15: return 1250;
				case 16: return 1408;
				case 17: return 1548;
				case 18: return 1725;
				case 19: return 1903;
				case 20: return 2061;
				case 21: return 2232;
				case 22: return 2409;
				case 23: return 2620;
				case 24: return 2812;
				case 25: return 3057;
				case 26: return 3283;
				case 27: return 3517;
				case 28: return 3669;
				case 29: return 3909;
				case 30: return 4158;
				case 31: return 4417;
				case 32: return 4686;
				case 33: return 4965;
				case 34: return 5253;
				case 35: return 5529;
				case 36: return 5836;
				case 37: return 6153;
				case 38: return 6479;
				case 39: return 6743;
				case 40: return 7089;
				default: die ("ERROR: _get_max_message_length_num_l() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_num_m($version) {
			switch ($version) {
				case 1:  return 34;
				case 2:  return 63;
				case 3:  return 101;
				case 4:  return 149;
				case 5:  return 202;
				case 6:  return 255;
				case 7:  return 293;
				case 8:  return 365;
				case 9:  return 432;
				case 10: return 513;
				case 11: return 604;
				case 12: return 691;
				case 13: return 796;
				case 14: return 871;
				case 15: return 991;
				case 16: return 1082;
				case 17: return 1212;
				case 18: return 1346;
				case 19: return 1500;
				case 20: return 1600;
				case 21: return 1708;
				case 22: return 1872;
				case 23: return 2059;
				case 24: return 2188;
				case 25: return 2395;
				case 26: return 2544;
				case 27: return 2701;
				case 28: return 2857;
				case 29: return 3035;
				case 30: return 3289;
				case 31: return 3486;
				case 32: return 3693;
				case 33: return 3909;
				case 34: return 4134;
				case 35: return 4343;
				case 36: return 4588;
				case 37: return 4775;
				case 38: return 5039;
				case 39: return 5313;
				case 40: return 5596;
				default: die ("ERROR: _get_max_message_length_num_m() was given an invalid version number.");
			}
		}
		// not tested for versions 8 - 40
		private function _get_max_message_length_num_q($version) {
			switch ($version) {
				case 1: return 27;
				case 2: return 48;
				case 3: return 77;
				case 4: return 111;
				case 5: return 144;
				case 6: return 178;
				case 7: return 207;
				case 8:  return 259;
				case 9:  return 312;
				case 10: return 364;
				case 11: return 427;
				case 12: return 489;
				case 13: return 580;
				case 14: return 621;
				case 15: return 703;
				case 16: return 775;
				case 17: return 876;
				case 18: return 948;
				case 19: return 1063;
				case 20: return 1159;
				case 21: return 1224;
				case 22: return 1358;
				case 23: return 1468;
				case 24: return 1588;
				case 25: return 1718;
				case 26: return 1804;
				case 27: return 1933;
				case 28: return 2085;
				case 29: return 2181;
				case 30: return 2358;
				case 31: return 2473;
				case 32: return 2670;
				case 33: return 2805;
				case 34: return 2949;
				case 35: return 3081;
				case 36: return 3244;
				case 37: return 3417;
				case 38: return 3599;
				case 39: return 3791;
				case 40: return 3993;
				default: die ("ERROR: _get_max_message_length_num_q() was given an invalid version number.");
			}
		}
		// not tested
		private function _get_max_message_length_num_h($version) {
			switch ($version) {
				case 1:  return 17;
				case 2:  return 34;
				case 3:  return 58;
				case 4:  return 82;
				case 5:  return 106;
				case 6:  return 139;
				case 7:  return 154;
				case 8:  return 202;
				case 9:  return 235;
				case 10: return 288;
				case 11: return 331;
				case 12: return 374;
				case 13: return 427;
				case 14: return 468;
				case 15: return 530;
				case 16: return 602;
				case 17: return 674;
				case 18: return 746;
				case 19: return 813;
				case 20: return 919;
				case 21: return 969;
				case 22: return 1056;
				case 23: return 1108;
				case 24: return 1228;
				case 25: return 1286;
				case 26: return 1425;
				case 27: return 1501;
				case 28: return 1581;
				case 29: return 1677;
				case 30: return 1782;
				case 31: return 1897;
				case 32: return 2022;
				case 33: return 2157;
				case 34: return 2301;
				case 35: return 2361;
				case 36: return 2524;
				case 37: return 2625;
				case 38: return 2735;
				case 39: return 2927;
				case 40: return 3057;
				default: die ("ERROR: _get_max_message_length_num_h() was given an invalid version number.");
			}
		}
		
		// returns the pixel resolution of $version (ISO/ICE)
		protected function _get_version_size($version) {
			if ( ($version > 40) || ($version < 1 ) ) die("get_version_size() ERROR: Supproted versions are 1-40");
			$mult = $version - 1;
			return 21 + ($mult * 4);
		}
		
		// returns into how many blocks the data is split
		protected function _get_nbr_blocks() {
			return count($this->_get_nbr_data_blocks());
		}
		
		
		/////////////////////////////////////////////////////////////////////////////////////
		// VARIABLES ////////////////////////////////////////////////////////////////////////
		/////////////////////////////////////////////////////////////////////////////////////
		
		private $_data_raw;
		private $_data_block; // in 8 bit binary array
		private $_ec_block;
		private $_data_encoded; // holds the final bit array
		private $_mask; // gets set by the function sets the data and decides what mask to use.
		private $_mode;
		private $_error_level;
		private $_version;
		private $_data_clipped; // is true if data passed through set_data() was too long and has been truncated
		private $_forced_mask;
		private $_str_debut_reporting; // a string that holds debug notifications
		private $_debug_reporting; // if true, passes debug infro to _str_debut_reporting
		
		protected function _byte_row_to_x($row) {
			$x = $this->size_x() - ($row * 2) - 3;
			if ($x > 5 ) $x++;
			return $x;
		}
		protected function _get_byte_row_from_left($x) {
			if ($x > 6) {
				$byte_row_from_left = (($x + 1) >> 1) - 1;
			} else if ($x < 6) {
				$byte_row_from_left = (($x + 2) >> 1) - 1;
			} else if ($x === 6) return "not a valid row";
			else return "not a valid row";
			return $byte_row_from_left;
		}
		protected function _x_to_left_x($x) {
			return ((int)$this->_get_byte_row_from_left($x) << 1) - 1;
		}
		protected function _get_byte_row($x) {
			$byte_row_from_left = $this->_get_byte_row_from_left($x);
			return $byte_row_from_right = ($this->size_x() >> 1) - $byte_row_from_left - 1;
		}
		// to do: deal with multiple pa locators
		protected function _get_bit_count_in_row($x) {
			$unusable_bits = 0;
			$all_bits = $this->size_y() << 1;
			if ($x > ($this->size_x() - 1) - 8) {
				$unusable_bits = (9 * 2);
			} else if ($x < 9) {
				$unusable_bits = (9 * 2) * 2;
			} else $unusable_bits = 2;
			
			$pa_locations = $this->_get_pa_locations();
			for ($i = 0; $i < count($pa_locations); $i++) {
				$location_from_pa = ($x - $pa_locations[$i]);
				
				$mult = 0;
				if ($location_from_pa >= -3) {
					if ($location_from_pa <= 2) {
						if ( ($location_from_pa > -3) && ($location_from_pa < 2)) {
							$mult = 2; // dead on in pa location
						} else $mult = 1; // scrateches pa location on either end
					}
				}
				// to do: decide how many pa locaters are in one row
				$nbr_pa_in_row = 1; // this is cheating!!!!
				$unusable_bits += (5 * $mult) * $nbr_pa_in_row;
			}
			return $all_bits - $unusable_bits;
		}
		
		protected function _bits_written_before_row($row) {
			if ($row === 0) return 0;
			$bit_count = 0;
			for ($i = 0; $i < $row; $i++) {
				$x = $this->_byte_row_to_x($i);
				//echo "$x<br />";
				$bit_count += $this->_get_bit_count_in_row($x);
			}
			return $bit_count;
		}
		public function bytes_in_row($x) {
			$byte_row_from_right = $this->_get_byte_row($x);
			$bits_in_byte_row = $this->_get_bit_count_in_row($this->_x_to_left_x($x));
			$bits_written_before_row = $this->_bits_written_before_row($byte_row_from_right);
			$byte_row_write_direction = $byte_row_from_right % 2; // 0 for up, 1 for down
			return "from right: ".$byte_row_from_right.", bits available: ".$bits_in_byte_row.", write direction: $byte_row_write_direction, $bits_written_before_row, x-row:".$this->_byte_row_to_x($byte_row_from_right);
		}
	}
}