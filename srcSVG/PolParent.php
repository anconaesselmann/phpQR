<?php
namespace aae\svg {
	class PolParent extends SvgObject {
		protected $_type;
		protected $_points_x;
		protected $_points_y;

		public function __construct() {
			$this->_points_x = NULL;
			$this->_points_y = NULL;
			$this->_stroke   = "currentColor";
		}
		public function addPoint($x, $y) {
			$this->_points_x[] = $x;
			$this->_points_y[] = $y;
		}
		public function _toDOM($xml_doc, $parent) {
			$child = $xml_doc->createElement($this->_type);
			if (!is_null($this->_parameters)) {
				$this->_parameters->toDom($xml_doc, $child, "\\aae\\draw\\svg\\");
				// echo "done";



				// foreach ($this->_parameters as $parameter) {
				// 	$class = $xml_doc->createAttribute("class");
				// 	$class->value = $this->_class;
				// 	$child->appendChild($class);
				// }
			}


			$fill = $xml_doc->createAttribute("fill");
			$fill->value = $this->_fill;
			$child->appendChild($fill);

			$stroke = $xml_doc->createAttribute("stroke");
			$stroke->value = $this->_stroke;
			$child->appendChild($stroke);

			if ($this->_non_scaling_stroke) {
				$strokeNonScaling = $xml_doc->createAttribute("vector-effect");
				$strokeNonScaling->value = "non-scaling-stroke";
				$child->appendChild($strokeNonScaling);
			}


			$stroke_miterlimit = $xml_doc->createAttribute("stroke-miterlimit");
			$stroke_miterlimit->value = $this->_stroke_miterlimit;
			$child->appendChild($stroke_miterlimit);


			$string_points = NULL;

			for ($i = 0; $i < count($this->_points_x); $i++) {
				$string_points .= $this->_points_x[$i] . ',' . $this->_points_y[$i] . ' ';
			}

			$points = $xml_doc->createAttribute("points");
			$points->value = $string_points;
			$child->appendChild($points);

			$parent->appendChild($child);
		}
		public function toHtml() {
			$out = NULL;
			$points = NULL;
			$indent = $this->indent;

			for ($i = 0; $i < count($this->_points_x); $i++) {
				$points .= $this->_points_x[$i] . ',' . $this->_points_y[$i] . ' ';
			}
			if ($this->_non_scaling_stroke) {
				$nonScalingStroke = " vector-effect=\"non-scaling-stroke\"";
			}
			$out .= $this->row('<' . $this->_type . '	class="' .$this->_class.$nonScalingStroke. '"	fill="' .$this->_fill. '"	stroke="' .$this->_stroke. '" stroke-miterlimit="' .$this->_stroke_miterlimit. '" points="' . $points. '"/>', $indent);


			return $out;
		}
		public function __toString() {
			return $this->toHtml();
		}
	}
}