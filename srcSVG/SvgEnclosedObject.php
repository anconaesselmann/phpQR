<?php
namespace aae\svg {
	class SvgEnclosedObject extends SvgObject {
		public function fill($r, $g=-1, $b=-1) {
			if ($r === -1 && $g === -1 && $b === -1)
				$this->_fill = 'none';
			else $this->_fill = new \aae\svg\Color($r, $g, $b);
		}
	}
}