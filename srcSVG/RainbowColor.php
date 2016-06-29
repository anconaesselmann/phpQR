<?php
/**
 *
 */
namespace aae\svg {
	/**
	 * @author Axel Ancona Esselmann
	 * @package aae\svg
	 */
	class RainbowColor extends Color {
        protected $_frequency, $_index;

        public function __construct($period) {
            $this->_frequency = (2 * 3.14159265359) / $period;
            $this->_index = 0;
        }
        public function __toString() {
            $red   = (int)((sin($this->_frequency*$this->_index + 0)                         * 127) + 128);
            $green = (int)((sin($this->_frequency*$this->_index + 2 * 3.14159265359 / 3)     * 127) + 128);
            $blue  = (int)((sin($this->_frequency*$this->_index + 2 * 3.14159265359 / 3 * 2 + 0) * 127) + 128);
            $this->_index++;
            //  $img->strokeColor($red, $green, $blue);
            $this->_hex_color = self::rgb2html($red, $green, $blue);

            return $this->_hex_color;
        }
        private function _name_to_hex($name) {
            $hex_value = "#FFFFFF";
        }
    }
}