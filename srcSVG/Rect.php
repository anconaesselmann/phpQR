<?php
namespace aae\svg {
	class Rect extends SvgEnclosedObject {
		protected $_width;
		protected $_height;

		// crisp when x and y are integer + .5 and width and height are integer
		public function __construct($x, $y, $width, $height) {
			$this->_init($x, $y);
			
			$this->_width = $width;
			$this->_height = $height;
			$this->_fill = '#FFFFFF';
			$this->_class = 'rect';
		}
		public function _toDOM($xml_doc, $parent) {
			$child = $xml_doc->createElement("rect");
			$class = $xml_doc->createAttribute("class");
			$class->value = $this->_class;
			$child->appendChild($class);

			#$id = $xml_doc->createAttribute("id");
			#$id->value = $this->_id;
			#$child->appendChild($id);
			
			$fill = $xml_doc->createAttribute("fill");
			$fill->value = $this->_fill;
			$child->appendChild($fill);
			
			$stroke = $xml_doc->createAttribute("stroke");
			$stroke->value = $this->_stroke;
			$child->appendChild($stroke);
			
			/*$stroke_miterlimit = $xml_doc->createAttribute("stroke-miterlimit");
			$stroke_miterlimit->value = $this->_stroke_miterlimit;
			$child->appendChild($stroke_miterlimit);*/
			
			$x = $xml_doc->createAttribute("x");
			$x->value = $this->_x;
			$child->appendChild($x);
			
			$y = $xml_doc->createAttribute("y");
			$y->value = $this->_y;
			$child->appendChild($y);
			
			$width = $xml_doc->createAttribute("width");
			$width->value = $this->_width;
			$child->appendChild($width);
			
			$height = $xml_doc->createAttribute("height");
			$height->value = $this->_height;
			$child->appendChild($height);
			
			$strokeWidth = $xml_doc->createAttribute("stroke-width");
			$strokeWidth->value = $this->_strokeWidth;
			$child->appendChild($strokeWidth);
			
			$_strokeLinecap = $xml_doc->createAttribute("stroke-linecap");
			$_strokeLinecap->value = $this->_strokeLinecap;
			$child->appendChild($_strokeLinecap);
			
			$parent->appendChild($child);
		}
		/*public function toHtml() {
			$out = NULL;
			$indent = $this->indent;
			$out .= $this->row('<rect 	id="' .$this->_id. '"	fill="' .$this->_fill. '"	stroke="' .$this->_stroke. '" stroke-miterlimit="' .$this->_stroke_miterlimit. '" x="' .$this->_x. '" y="' .$this->_y. '" width="' .$this->_width. '" height="' .$this->_height. '"/>', $indent);
			return $out;
		}*/
		public function __toString() {
			return $this->toHtml();
		}
	}
}