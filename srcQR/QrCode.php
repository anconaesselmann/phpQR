<?php
namespace aae\qr {
	/**
	 *	QrCode($mode, $error_level, $version = NULL)
	 *	Graphical representation of class QrGrid
	 *	$mode	:		Decides if the message is encoded as numeric, alpha-numeric or binary
	 *							QrCode::MODE_NUM = 1
	 *							QrCode::MODE_ALP = 2
	 *							QrCode::MODE_BIN = 4
	 *							QrCode::MODE_JAP = 8
	 *	$error_level:	Sets the error correction level. 
	 *						A higer value means better error correction but fewer message symbols, 
	 *						a lower level means more message symbols but error correction is not 
	 *						as good.
	 *								value:			recovery capacity:
	 *							QrCode::ERROR_L = 1 			 7 %
	 *							QrCode::ERROR_M = 2				15 %
	 *							QrCode::ERROR_Q = 3				25 %
	 *							QrCode::ERROR_H = 4				30 %
	 *	$version	:	The QR-Code version
	 *						Values from 1 to 40 correspond to the respective qr-code versions.
	 *						When left blank, the length of the message will dictate the version
	 *						numner (NOT YET IMPLEMENTED!)
	 *						
	 *	Inherited functionality:
	 *	get_info()	:		Returns a summary of the qr-code instance
	 *	set_data($data):	The string to be converted is passed as $data.
	 *
	 *	Dependencies: requires svg.php
	 */
	class QrCode extends QrGrid {
		private $_pixel_size; 	// in pixels
		private $_canvas;	// svg canvas
		private $_jpeg;

		public function __construct($mode, $error_level, $version = NULL) {
			$this->_pixel_size = 4;
			parent::__construct($mode, $error_level, $version);
		}
		public function __toString() {
			$this->render_SVG_matrix();
			//echo parent::__toString();
			return (string)$this->_canvas;
		}
		
		// set the size of the rendered square matrix in pixels
		public function set_pixel_size($pixel = 4) {
			$this->_pixel_size = $pixel;
		}
		// returns the QR Code as SVG
		public function render_SVG_matrix() {
			$canvas_size_pixel = $this->_pixel_size * ( $this->size_x() +8 );
			$this->_canvas = new \aae\svg\Canvas($canvas_size_pixel + 1, $canvas_size_pixel + 1);
			$rect = new \aae\svg\Rect(0.5, 0.5, $canvas_size_pixel, $canvas_size_pixel);
			$rect->fill(255,255,255);
			$this->_canvas->add($rect);
		
			for ($y = 0; $y < $this->size_y(); $y++) {
				for ($x = 0; $x < $this->size_x(); $x++) {
					if ($this->is_set($x, $y)) {
						if ($this->get($x, $y)) {
							$fill = $this->_fill_rect($x, $y);
						} else {
							$fill = $this->_fill_rect($x, $y, 255, 255, 255);
						}
						$this->_canvas->add($fill);
					} else {
						$fill = $this->_fill_rect($x, $y, 255);
						$this->_canvas->add($fill);
					}
				}
			}
		}
		public function output_image() {
			$canvas_size_pixel = round($this->_pixel_size * ( $this->size_x() + 8 ));
			$this->_jpeg = imagecreatetruecolor($canvas_size_pixel, $canvas_size_pixel);
			$white = imagecolorallocate($this->_jpeg, 255, 255, 255);
			imagefilledrectangle($this->_jpeg, 0,0, round($canvas_size_pixel),round($canvas_size_pixel), $white);
			for ($y = 0; $y < $this->size_y(); $y++) {
				for ($x = 0; $x < $this->size_x(); $x++) {
					if ($this->is_set($x, $y)) {
						if ($this->get($x, $y)) {
							$this->_rect_to_jpeg($x, $y);
						} else {
							$this->_rect_to_jpeg($x, $y, 255, 255, 255);
						}
					} else {
						$this->_rect_to_jpeg($x, $y, 255);
					}
				}
			}

			ob_start (); 
	  		imagepng($this->_jpeg);
	  		$image_data = ob_get_contents(); 
			ob_end_clean ();
		
			imagedestroy($this->_jpeg);
		
			$image_data_base64 = base64_encode($image_data);
			return '<img src="data:image/png;base64,'.$image_data_base64.'" />';
		}
		
		private function _rect_to_jpeg($x, $y, $r = 0, $g = 0, $b = 0) {
			$pixel = $this->_pixel_size;
			$shift = 4 * $pixel;
		
			$color = imagecolorallocate($this->_jpeg, $r, $g, $b);
			
			$x1 = $x * $pixel + $shift;
			$y1 = $y * $pixel + $shift;
			$x2 = $x1 + $pixel - 1;
			$y2 = $y1 + $pixel - 1;
			
			imagefilledrectangle($this->_jpeg, round($x1),round($y1), round($x2),round($y2), $color);
		}
		
		/* 
		_fill_rect($x, $y, $r = 0, $g = 0, $b = 0)
		Paints one bit to the svg canvas. 
		$x , $y: 	the upper left corner of the pixel on the canvas. 
		$r, $g, $b: RGB values can be passed for color. 
					Default color is black. Pass 255,255,255 for white.
					
		The size of the Pixel is calculated automatically and depends 
		on the size of the canvas, which can be manipulated with set_size(). 
		
		*/
		private function _fill_rect($x, $y, $r = 0, $g = 0, $b = 0) {
			$pixel = $this->_pixel_size;
			$shift = 4 * $pixel;
			$rect = new \aae\svg\Rect($x * $pixel + 0.5 + $shift, $y * $pixel + 0.5 + $shift, $pixel, $pixel);
			$rect->fill($r,$g,$b);
			$rect->strokeColor(0,0,0);//$r,$g,$b);
			$rect->strokeWidth(0);
			//$rect->stroke_linecap(2);
			return $rect;
		}
	 }
}