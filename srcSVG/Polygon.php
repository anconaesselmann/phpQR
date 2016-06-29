<?php
namespace aae\svg {
	class Polygon extends PolParent {
		public function __construct() {
			$this->_init();
			
			$this->_type = 'polygon';
			$this->_class = 'path';
			$this->_fill = '#FFFFFF';
		}
	}
}