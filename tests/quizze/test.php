<?php

require_once '../../shared/QuizzeServer.php';

$server = new QuizzeServer([
    'url' => 'http://localhost:3000/quizze'
]);

$server->write('test-quizze-php-lib', 'grader_data.js');
//$server->delete('test-quizze-php-lib');