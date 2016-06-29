<?php
namespace aae\svg {
	class Path extends PolParent {
		public function __construct() {
			$this->_init();

			$this->_type = 'polyline';
			$this->_id   = 'path';
			$this->_fill = 'none';
		}
	}
}