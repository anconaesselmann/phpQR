<?php
namespace aae\qr {
	class Grid {
		public function __construct($x, $y) {
			$this->_grid = array();
			for ($i = 0; $i < $x; $i++) {
				$this->_grid[] = array_fill(0, $y, -1);
			}
		}
		public function __toString() {
			$return_str = NULL;
			for ($y = 0; $y < count ($this->_grid); $y++) {
				for ($x = 0; $x < count ($this->_grid); $x++) {
					$return_str .= (int)$this->_grid[$x][$y]." ";
				}
				$return_str .= "<br />";
			}
			return $return_str;
		}
		// returns false if out of bounds or blocked for writing.
		// returns 0 if pixel was already set
		// returns true if setting of pixel was successful. CHANGED
		public function set($x, $y, $bool, $overwrite = false) {
			if (!isset($this->_grid[$x][$y])) return false;
			else if ($this->_grid[$x][$y] === -1) {
				$this->_grid[$x][$y] = (bool)$bool;
				return true;
			} else if ( $overwrite ) {
				$this->_grid[$x][$y] = (bool)$bool;
				return true;
			} else return 0;
		}
		// sets all to unset. Not tested
		public function clear_all() {
			for ($y = 0; $y < $this->size_y(); $y++) {
				for ($x = 0; $x < $this->size_x(); $x++) {
					$this->	_grid[$x][$y] = -1;
				}
			}
		}
		// 
		public function set_square($x, $y, $x_length, $y_length, $bit, $fill = false) {
			for ($i = $x; $i < ($x + $x_length); $i++) {
				$this->set($i, $y, $bit);
			}
			for ($i = $x; $i < ($x + $x_length); $i++) {
				$this->set($i, $y + $y_length - 1, $bit);
			}
			for ($i = $y + 1; $i < ($y + $y_length - 1); $i++) {
				$this->set($x, $i, $bit);
			}
			for ($i = $y + 1; $i < ($y + $y_length - 1); $i++) {
				$this->set($x + $x_length - 1, $i, $bit);
			}
			if ($fill === true) {
				for ($j = 1; $j < $x_length - 1; $j++) {
					for ($i = $y + 1; $i < ($y + $y_length - 1); $i++) {
						$this->set($x + $j, $i, $bit);
					}
				}
			}
		}
		// returns true if a pixel either holds a 0 or 1. returns false if nothing has been asigned
		public function is_set($x, $y) {
			if ($this->_grid[$x][$y] === -1) return false;
			else return true;
		}
		public function get($x, $y) {
			if (!isset($this->_grid[$x][$y])) return false;
			else if ($this->_grid[$x][$y] === -1) return false;
			else return $this->_grid[$x][$y];
		}
		public function nonzero_bit_count() {
			$nonzero_bit_count = 0;
			for ($y = 0; $y < $this->size_y(); $y++) {
				for ($x = 0; $x < $this->size_x(); $x++) {
					if ($this->get($x, $y) === true) {
						$nonzero_bit_count++;
					}
				}
			}
			return $nonzero_bit_count;
		}
		public function size_x() {
			return count($this->_grid);
		}
		public function size_y() {
			return count($this->_grid[0]);
		}
		protected $_grid;
	}
}