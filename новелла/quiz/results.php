<?php
session_start();

$correct_answers = $_SESSION['correct_answers'];
$total_questions = 5; // Убедитесь, что это значение соответствует количеству вопросов

// Генерация графической сводки
$errors = $total_questions - $correct_answers;
$error_ranges = [0, 1, 2, 3, 4, 5];
$error_counts = array_fill_keys($error_ranges, 0);

// Здесь можно сохранить результаты в базу данных или файл для статистики
// Например, $error_counts[$errors]++;

// Генерация изображения с графиком
$image = imagecreate(500, 300);
$bg_color = imagecolorallocate($image, 255, 255, 255);
$bar_color = imagecolorallocate($image, 0, 0, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);

$bar_width = 50;
$bar_spacing = 50;
$x = 50;
$max_height = 200;

foreach ($error_ranges as $range) {
    $height = ($error_counts[$range] / $total_questions) * $max_height;
    imagefilledrectangle($image, $x, 250 - $height, $x + $bar_width, 250, $bar_color);
    imagestring($image, 5, $x, 260, $range, $text_color);
    $x += $bar_width + $bar_spacing;
}

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results</title>
</head>
<body>
    <h1>Результаты теста</h1>
    <p>Правильных ответов: <?php echo $correct_answers; ?> из <?php echo $total_questions; ?></p>
    <img src="results.php" alt="График результатов">
</body>
</html>