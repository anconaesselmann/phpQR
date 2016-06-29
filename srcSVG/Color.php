<?php
namespace aae\svg {

	// not finished
	class Color extends \aae\std\Color {
		public function __construct($r = NULL, $g = NULL, $b = NULL) {
			if (is_null($r)) {
				$this->_hex_color = "none";
			} else {
				parent::__construct($r,$g,$b);
			}
		}
	}
}