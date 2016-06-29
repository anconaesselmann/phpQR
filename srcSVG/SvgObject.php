<?php
namespace aae\svg {
	class SvgObject extends \aae\html\HTMLObject {
		public $indent;
		protected $_parameters = NULL;
		protected $_strokeLinecap;
		protected $_class;
		protected $_x;
		protected $_y;
		protected $_fill;
		protected $_stroke;
		protected $_strokeWidth;
		protected $_stroke_miterlimit;
		protected $_non_scaling_stroke = true;
		protected $_id;

		private $_instanceNbr = null;
		protected static $_s_instanceCounter = 0;

		public function setParameters(\aae\draw\Parameters $parameters) {
			$this->_parameters = $parameters;
		}

		public function getInstanceNbr() {
			if (is_null($this->_instanceNbr)) {
				$this->_instanceNbr = self::$_s_instanceCounter++;
			}
			return $this->_instanceNbr;
		}

		protected function _init($x = NULL, $y = NULL) {
			if ($x !== NULL && $y !== NULL) {
				$this->_x = $x;
				$this->_y = $y;
			}
			$this->_stroke = '#000000';
			$this->_strokeLinecap = "square";
			$this->_stroke_miterlimit = '10';
		}
		public function setName($name) {
			// to do: remove all spaces!!!
			$this->_id = $name;
		}
		public function setClass($class) {
			$this->_class = $name;
		}
		public function strokeColor($r=NULL, $g=0, $b=0) {
			if ($r instanceof \aae\svg\Color) {
				$this->_stroke = $r;
				return;
			}
			if ( ($r === -1) || ($r === NULL) ) $this->_stroke = "none";
			else $this->_stroke = new \aae\svg\Color($r, $g, $b);
		}
		public function getStrokeColor() {
			return $this->_stroke;
		}
		public function strokeWidth($width, $nonScaling = true) {
			$this->_strokeWidth = $width;
			if ($nonScaling) {
				$this->_non_scaling_stroke = true;
			}
		}
// not sure this works
		public function strokeLinecap($int) {
			switch ($this->_strokeLinecap) {
				case 0: $this->_strokeLinecap = "square";
				break;
				case 1: $this->_strokeLinecap = "round";
				break;
				case 2: $this->_strokeLinecap = "butt";
				break;
			}
		}
	}
}