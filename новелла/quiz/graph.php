<?php
session_start();
header('Content-Type: image/png');

// Load statistics from session
$statistics = $_SESSION['statistics'];
$results_history = $_SESSION['results_history'];
$total_tests = count($results_history);

// Create the image
$image = imagecreatetruecolor(500, 300);
$bg_color = imagecolorallocate($image, 255, 255, 255);
$bar_color = imagecolorallocate($image, 255, 0, 0); // Red
$text_color = imagecolorallocate($image, 0, 0, 0);

imagefilledrectangle($image, 0, 0, 500, 300, $bg_color);
$error_ranges = [0, 1, 2, 3, 4, 5];
$bar_width = 70;
$bar_spacing = 30;
$x = 50;
$max_height = 200;

foreach ($error_ranges as $range) {
    if ($total_tests > 0) {
        $height = ($statistics[$range] / $total_tests) * $max_height;
    } else {
        $height = 0;
    }
    imagefilledrectangle($image, $x, 250 - $height, $x + $bar_width, 250, $bar_color);
    imagestring($image, 5, $x, 260, $range, $text_color);
    $x += $bar_width + $bar_spacing;
}

imageline($image, 0, 250, 500, 250, $text_color); // Horizontal line

imagepng($image);
imagedestroy($image);
exit;
?>