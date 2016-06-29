<?php
/**
 *
 */
namespace aae\svg {
	/**
	 * @author Axel Ancona Esselmann
	 * @package aae\svg
	 */
	class Svg {
		protected $_canvas = null;

		public function __construct($height, $widht, $name = null) {
			$this->_canvas = new Canvas($height, $widht, $name);
		}
		public function setDimensions($height, $width) {
			$this->_canvas->setDimensions($width, $height);
		}

		public function __toString() {
			return (string)$this->_canvas;
		}
		public function setViewBox($x1, $y1, $x2, $y2) {
			$this->_canvas->setViewBox($x1, $y1, $x2, $y2);
		}

		public function strokeColor($r=NULL, $g=0, $b=0) {
			$this->_canvas->strokeColor($r, $g, $b);
		}

		public function drawPoint($item, $parameters = NULL) {

		}
		public function drawLine($item, $parameters = NULL) {
			$a = $item->getA();
			$b = $item->getB();
			$svgLine = new Line($a->x, $a->y, $b->x, $b->y);
			$svgLine->strokeColor($this->_canvas->getStrokeColor());
			$this->_canvas->add($svgLine, $parameters);
		}
		public function drawSequence($item, $parameters = NULL) {
			$path = new \aae\svg\Path();
			$path->strokeColor($this->_canvas->getStrokeColor());
			if (method_exists($item, "getId") && !is_null($id = $item->getId())) {
				echo "has id: $id<br />";
				$path->setId($id);
			}
			foreach ($item as $point) {
				$path->addPoint($point->x, $point->y);
			}


			$this->_canvas->add($path, $parameters);
		}
		public function drawRect($item, $parameters = NULL) {

		}
		public function drawPol($item, $parameters = NULL) {

		}
		public function drawCircle($item, $parameters = NULL) {

		}
		public function drawElypse($item, $parameters = NULL) {

		}
	}
}