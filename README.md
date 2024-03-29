# phpQR

## Overview
- PHP library for generating QR-codes.
- Sould be compliant with 2006 ISO standard.
- Supports all versions (1-40) and error correction levels.
- Allows for manual setting of masks.

[See in action](http://www.anconaesselmann.com/qr)

## Example Usage for generating PNG output

```php
$QR = new \aae\qr\QrCode($mode, $error_level, $version);

$QR->set_mask($mask); // Set mask manually if you like.
$QR->set_data($message);
$QR->set_pixel_size($pix);

echo $QR->output_image(); // display qr code as PNG

echo "With current settings the maximum message length is: ".$QR->get_max_data_length()."<br />";
echo "Length of the message encoded: ".$QR->get_data_length()."<br />";
echo "Message encoded: ".$QR->get_data()."<br />";
if ($QR->data_is_clipped()) {
  echo "Message was too long and had to be truncated.<br />";
} else echo "Message has been encoded successfully.<br />";
echo "Mask used: ".$QR->get_mask()."<br />";
echo "Error correction used: ".$QR->get_ec()."<br />";
echo "Data mode: ".$QR->get_mode()."<br />";
```

## SVG output:

- To generate SVG, make sure that your autoloader can load classes from the `srcSVG` folder. Not everything in there is needed.
