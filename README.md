# phpQR

- Generates qr codes

# Example Usage for generating PNG output

    $QR = new \aae\qr\QrCode($mode, $error_level, $version);

    $QR->set_mask($mask);
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

# To generate SVG, make sure that your autoloader can load classes from the srcSVG folder