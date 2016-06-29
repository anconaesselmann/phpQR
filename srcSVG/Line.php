<?php
namespace aae\svg {
	class Line extends SvgObject {
		private $_x1;
		private $_y1;
		private $_x2;
		private $_y2;

		public function __construct($x1, $y1, $x2, $y2) {
			$this->_x1 = $x1;
			$this->_y1 = $y1;
			$this->_x2 = $x2;
			$this->_y2 = $y2;
			$this->_stroke = 'rgb(0,0,0)';
			$this->_strokeWidth = 1; // make stroke setting for parent object
		}
		public function _toDOM($xml_doc, $parent) {
			$child = $xml_doc->createElement("line");
			$id = $xml_doc->createAttribute("id");
			#$id->value = $this->_id;
			#$child->appendChild($id);

			$stroke = $xml_doc->createAttribute("stroke");
			$stroke->value = $this->_stroke;
			$child->appendChild($stroke);

			/*$stroke_miterlimit = $xml_doc->createAttribute("stroke-miterlimit");
			$stroke_miterlimit->value = $this->_stroke_miterlimit;
			$child->appendChild($stroke_miterlimit);*/

			$x = $xml_doc->createAttribute("x1");
			$x->value = $this->_x1;
			$child->appendChild($x);

			$y = $xml_doc->createAttribute("y1");
			$y->value = $this->_y1;
			$child->appendChild($y);

			$x = $xml_doc->createAttribute("x2");
			$x->value = $this->_x2;
			$child->appendChild($x);

			$y = $xml_doc->createAttribute("y2");
			$y->value = $this->_y2;
			$child->appendChild($y);

			$width = $xml_doc->createAttribute("stroke-width");
			$width->value = $this->_strokeWidth;
			$child->appendChild($width);

			$parent->appendChild($child);
		}
	}
}