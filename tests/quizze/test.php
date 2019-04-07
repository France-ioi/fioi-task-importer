<?php

require_once '../../shared/QuizServer.php';

$server = new QuizServer([
    'url' => 'http://localhost:3000/quiz'
]);

$server->write('test-quiz-php-lib', 'grader_data.js');
//$server->delete('test-quiz-php-lib');