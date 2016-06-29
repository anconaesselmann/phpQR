<?php
namespace aae\svg {
	class Group extends SvgObject {
		protected $_objects;
		
		public function __construct() {
			$this->_objects = NULL;
			$this->_class = 'group';
		}
		public function _toDOM($xml_doc, $parent) {
			$child = $xml_doc->createElement("g");
			
			$id = $xml_doc->createAttribute("class");
			$id->value = $this->_class;
			$child->appendChild($id);
			
			for ($i = 0; $i < count($this->_objects); $i++) {
				$this->_objects[$i]->_toDom($xml_doc, $child);
			}
			
			$parent->appendChild($child);
		}
		public function toHtml() {
			$out = NULL;
			$indent = $this->indent;
			$out .= $this->row('<g class="' . $this->_class . '">', $indent++);
			for ($i = 0; $i < count($this->_objects); $i++) {
				$this->_objects[$i]->indent = $indent + 1;
				$out .= $this->_objects[$i];
			}
			$out .= $this->row('</g>', --$indent);
			return $out;
		}
		public function __toString() {
			return $this->toHtml();
		}
		public function add($object) {
			$this->_objects[] = $object;
		}
	}
}