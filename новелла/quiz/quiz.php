<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Load questions from JSON file
$json = file_get_contents('questions.json');
$questions = json_decode($json, true)['questions'];

// Initialize session variables if not set
if (!isset($_SESSION['selected_questions'])) {
    shuffle($questions);
    $_SESSION['selected_questions'] = array_slice($questions, 0, 5);
}
if (!isset($_SESSION['current_question'])) {
    $_SESSION['current_question'] = 0;
}
if (!isset($_SESSION['correct_answers'])) {
    $_SESSION['correct_answers'] = 0;
}
if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = '';
}
if (!isset($_SESSION['results_history'])) {
    $_SESSION['results_history'] = [];
}
if (!isset($_SESSION['statistics'])) {
    $_SESSION['statistics'] = [0, 0, 0, 0, 0, 0];
}

// Function to get random answers with the correct one included
function getRandomAnswers($answers) {
    $correct_answer = array_filter($answers, function($answer) {
        return $answer['correct'];
    });
    $correct_answer = array_shift($correct_answer);

    $wrong_answers = array_filter($answers, function($answer) {
        return !$answer['correct'];
    });
    shuffle($wrong_answers);
    $wrong_answers = array_slice($wrong_answers, 0, 3);

    $random_answers = array_merge([$correct_answer], $wrong_answers);
    shuffle($random_answers);
    return $random_answers;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_name'])) {
        $_SESSION['user_name'] = $_POST['user_name'];
        $_SESSION['current_question'] = 0;
        $_SESSION['correct_answers'] = 0;
    }
    if (isset($_POST['answer'])) {
        $selected_questions = $_SESSION['selected_questions'];
        $current_question_index = $_SESSION['current_question'];
        $current_question = $selected_questions[$current_question_index];
        $user_answer = $_POST['answer'];
        $correct_answer = array_filter($current_question['answers'], function($answer) {
            return $answer['correct'];
        });
        $correct_answer = array_shift($correct_answer)['text'];
        if ($user_answer === $correct_answer) {
            $_SESSION['correct_answers']++;
        }
        $_SESSION['current_question']++;
        if ($_SESSION['current_question'] >= count($_SESSION['selected_questions'])) {
            // Record results
            $results_history = $_SESSION['results_history'];
            $results_history[] = [
                'user_name' => $_SESSION['user_name'],
                'correct_answers' => $_SESSION['correct_answers'],
                'total_questions' => count($_SESSION['selected_questions'])
            ];
            $_SESSION['results_history'] = $results_history;
            $statistics = $_SESSION['statistics'];
            $statistics[$_SESSION['correct_answers']]++;
            $_SESSION['statistics'] = $statistics;
            // Redirect to results page
            header('Location: quiz.php?action=results');
            exit;
        } else {
            // Proceed to the next question
            header('Location: quiz.php');
            exit;
        }
    }
    if (isset($_POST['reset'])) {
        // Reset the quiz without clearing history and statistics
        unset($_SESSION['selected_questions']);
        unset($_SESSION['current_question']);
        unset($_SESSION['correct_answers']);
        unset($_SESSION['user_name']);
        header('Location: quiz.php');
        exit;
    }
}

// Handle different actions based on the 'action' parameter
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'results') {
        // Display results page
        header('Content-Type: text/html');
        $correct_answers = $_SESSION['correct_answers'];
        $total_questions = count($_SESSION['selected_questions']);
        $user_name = $_SESSION['user_name'];
        $results_history = $_SESSION['results_history'];
        $statistics = $_SESSION['statistics'];
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Results</title>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        </head>
        <body>
            <h1>Результаты теста</h1>
            <p>ФИО: <?php echo htmlspecialchars($user_name); ?></p>
            <p>Правильных ответов: <?php echo $correct_answers; ?> из <?php echo $total_questions; ?></p>

            <canvas id="resultsChart" width="400" height="200"></canvas>

            <h2>История результатов</h2>
            <ul>
                <?php foreach ($results_history as $result): ?>
                    <li>
                        ФИО: <?php echo htmlspecialchars($result['user_name']); ?>,
                        Правильных ответов: <?php echo $result['correct_answers']; ?> из <?php echo $result['total_questions']; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>Статистика</h2>
            <ul>
                <?php for ($i = 0; $i <= 5; $i++): ?>
                    <li><?php echo $i; ?>/5: <?php echo $statistics[$i]; ?> человек</li>
                <?php endfor; ?>
            </ul>

            <form method="post">
                <input type="hidden" name="reset" value="1">
                <button type="submit">Пройти ещё раз</button>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('resultsChart').getContext('2d');
                var statistics = <?php echo json_encode($statistics); ?>;

                var resultsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['0/5', '1/5', '2/5', '3/5', '4/5', '5/5'],
                        datasets: [{
                            label: 'Количество человек',
                            data: statistics,
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Display the quiz or user name input
if (empty($_SESSION['user_name'])) {
    // Display user name input form
    ?>
    <h1>Введите ФИО</h1>
    <form method="post">
        <input type="text" name="user_name" required>
        <button type="submit">Начать тест</button>
    </form>
    <?php
} else {
    // Display the current question
    $selected_questions = $_SESSION['selected_questions'];
    $current_question_index = $_SESSION['current_question'];
    if ($current_question_index < count($selected_questions)) {
        $current_question = $selected_questions[$current_question_index];
        $current_answers = getRandomAnswers($current_question['answers']);
        ?>
        <h1>Вопрос <?php echo $current_question_index + 1; ?></h1>
        <form method="post">
            <p><?php echo $current_question['question']; ?></p>
            <?php foreach ($current_answers as $answer): ?>
                <label>
                    <input type="radio" name="answer" value="<?php echo htmlspecialchars($answer['text']); ?>" required>
                    <?php echo htmlspecialchars($answer['text']); ?>
                </label><br>
            <?php endforeach; ?>
            <button type="submit">Ответить</button>
        </form>
        <?php
    } else {
        // Redirect to results page
        header('Location: quiz.php?action=results');
        exit;
    }
}
?>