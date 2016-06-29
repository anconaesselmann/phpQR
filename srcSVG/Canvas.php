<?php
namespace aae\svg {
	class ObjectAndParameters {
		public $object;
		public $parameters;
		public function __construct($object, $parameters) {
			$this->object = $object;
			$this->parameters = $parameters;
		}
	}
	class Canvas extends SvgObject {
		const SVG_ENCODER_VERSION = 0.1;
		protected $_objects;
		protected $_width;
		protected $_height;

		protected $_left;
		protected $_top;

		protected $_viewBoxX1 = null;
		protected $_viewBoxX2 = null;
		protected $_viewBoxY1 = null;
		protected $_viewBoxY2 = null;

		public function __construct($width, $height, $name = null) {
			$this->setDimensions($width, $height);
			$this->_objects = NULL;
			$this->_class = NULL;

			$this->_left = NULL;
			$this->_top = NULL;

			if (is_null($name)) {
				$name = "svg" . $this->getInstanceNbr();
			}
			$this->setName($name);
		}
		public function setDimensions($width, $height) {
			// to do: make sure these are integers
			$this->_width = $width;
			$this->_height = $height;
		}
		public function setViewBox($x1, $y1, $x2, $y2) {
			$this->_viewBoxX1 = $x1;
			$this->_viewBoxX2 = $x2;
			$this->_viewBoxY1 = $y1;
			$this->_viewBoxY2 = $y2;
		}
		public function add($object, $parameters = NULL) {
			if (!is_null($parameters)) {
				$object->setParameters($parameters);
			}
			$this->_objects[] = $object;
		}

		public function toDOMDocumentFragment($document) {
			$fragment = $document->createDocumentFragment();

			$this->_build($document, $fragment);

			return $fragment;
		}
		public function append2Node($document, $node) {
			$this->_buildRoot($document, $node);
		}
		private function _build($document, $fragment = NULL) {
			if ($fragment === NULL) $fragment = $document;
			$root = $document->createElement( "svg" );

			$this->_buildRoot($document, $root);

			$fragment->appendChild( $root );
		}
		protected function _buildRoot($document, $root) {
			// $version = $document->createAttribute("version");
			// $version->value = "1.1";
			// $root->appendChild($version);

			if ($this->_class !== NULL) {
				$class = $document->createAttribute("class");
				$class->value = $this->_class;
				$root->appendChild($class);
			}
			if ($this->_id !== NULL) {
				$id = $document->createAttribute("id");
				$id->value = $this->_id;
				$root->appendChild($id);
			}

			// $xmlns = $document->createAttribute("xmlns");
			// $xmlns->value = "http://www.w3.org/2000/svg";
			// $root->appendChild($xmlns);

			// $xmlns_xlink = $document->createAttribute("xmlns:xlink");
			// $xmlns_xlink->value = "http://www.w3.org/1999/xlink";
			// $root->appendChild($xmlns_xlink);

			$x = $document->createAttribute("x");
			$x->value = "0px";
			$root->appendChild($x);

			$y = $document->createAttribute("y");
			$y->value = "0px";
			$root->appendChild($y);

			$width = $document->createAttribute("width");
			$width->value = $this->_width;
			$root->appendChild($width);

			$height = $document->createAttribute("height");
			$height->value = $this->_height;
			$root->appendChild($height);

			if ($this->_left !== NULL && $this->_top !== NULL) {
				$version = $document->createAttribute("style");
				$version->value = "position:absolute;left:".$this->_left.";top:".$this->_top.";";
				$root->appendChild($version);
			}

			$viewBox = $document->createAttribute("viewBox");
			if (!is_null($this->_viewBoxY1)) {
				$x1 = $this->_viewBoxX1;
				$x2 = $this->_viewBoxX2;
				$y1 = $this->_viewBoxY1;
				$y2 = $this->_viewBoxY2;
			} else {
				$x1 = 0;
				$x2 = $this->_height;
				$y1 = 0;
				$y2 = $this->_width;
			}
			$viewBox->value = $y1 . " " . $x1 . " " . $y2 . " " . $x2;
			$root->appendChild($viewBox);

			// $enable_background = $document->createAttribute("enable-background");
			// $enable_background->value = "new 0 0 ". $this->_width . " " . $this->_height;
			// $root->appendChild($enable_background);

			// $xml_space = $document->createAttribute("xml:space");
			// $xml_space->value = "preserve";
			// $root->appendChild($xml_space);

			$style = $document->createElement( "style" );
			$root->appendChild($style);

			$style->setAttribute("id", "canvas-style");

			$textNode = $document->createTextNode("polyline {
                    stroke-linejoin:round;
                    stroke-opacity:0.3;
                    stroke-width:5px;
                    stroke-linecap:round;
                }
                .user4 {
                  color: red;
                }
                .user7 {
                  color: blue;
                }
                ");

			$style->appendChild($textNode);

			for ($i = 0; $i < count($this->_objects); $i++) {
				$this->_objects[$i]->_toDom($document, $root);
			}
		}

		public function toHtml() {
			$xml_doc = new \DOMDocument();
  			$xml_doc->formatOutput = true;
			$xml_doc->preserveWhiteSpace = false;
			$xml_doc->encoding = 'utf-8';
			$comment = $xml_doc->createComment("Axel Ancona Esselmann's SVG encoder version: " . self::SVG_ENCODER_VERSION);
			$xml_doc->appendChild($comment);

			$this->_build($xml_doc);

			$out =  $xml_doc->saveXML();

			return $out;
		}
		public function setScreenPos($left, $top) {
			$this->_left = $left;
			$this->_top = $top;
		}
		public function __toString() {
			return $this->toHtml();
		}
	}
}