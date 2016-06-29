<?php
namespace aae\svg {
	class Circle extends SvgEnclosedObject {
		protected $_rad;
		
		public function __construct ($x, $y, $rad) {
			$this->_init($x, $y);
			
			$this->_rad = $rad;
			$this->_fill = '#FFFFFF';
			$this->_class = 'circle';
		}
		public function _toDOM($xml_doc, $parent) {
			$child = $xml_doc->createElement("circle");
			$id = $xml_doc->createAttribute("id");
			$id->value = $this->_class;
			$child->appendChild($id);
			
			$fill = $xml_doc->createAttribute("fill");
			$fill->value = $this->_fill;
			$child->appendChild($fill);
			
			$stroke = $xml_doc->createAttribute("stroke");
			$stroke->value = $this->_stroke;
			$child->appendChild($stroke);
			
			$stroke_miterlimit = $xml_doc->createAttribute("stroke-miterlimit");
			$stroke_miterlimit->value = $this->_stroke_miterlimit;
			$child->appendChild($stroke_miterlimit);
			
			$x = $xml_doc->createAttribute("cx");
			$x->value = $this->_x;
			$child->appendChild($x);
			
			$y = $xml_doc->createAttribute("cy");
			$y->value = $this->_y;
			$child->appendChild($y);
			
			$r = $xml_doc->createAttribute("r");
			$r->value = $this->_rad;
			$child->appendChild($r);
			
			$parent->appendChild($child);
		}
		public function toHtml() {
			$out = NULL;
			$indent = $this->indent;
			$out .= $this->row('<circle	class="' .$this->_class. '"	fill="' .$this->_fill. '"	stroke="' .$this->_stroke. '" stroke-miterlimit="' .$this->_stroke_miterlimit. '" cx="' .$this->_x. '" cy="' .$this->_y. '" r="' .$this->_rad. '"/>', $indent);
			return $out;
		}
		public function __toString() {
			return $this->toHtml();
		}
	}
}