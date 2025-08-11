<?php

class QuizServerException extends Exception {
}

class QuizServer {

    const JS_PREFIX_REGEXP = '/window.Quiz.grader.data\s*=\s*/';

    protected $options;


    public function __construct($options) {
        $this->options = $options;
    }


    private function sendRequest($data) {
        $ch = curl_init($this->options['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        return json_decode($result, true);
    }


    private function readCode($file) {
        $code = file_get_contents($file);
        $code = preg_replace(self::JS_PREFIX_REGEXP, '', $code, 1);
        return $code;
    }


    public function write($task_id, $grader_data_path) {
        if(!file_exists($grader_data_path)) {
            throw new QuizServerException("No file found at $grader_data_path");
        }
        $res = $this->sendRequest([
            'action' => 'write',
            'task_id' => $task_id,
            'data' => $this->readCode($grader_data_path)
        ]);
        if (!$res) {
            throw new QuizServerException("No response from the quiz server");
        }
        if (!$res['success']) {
            throw new QuizServerException("Grading server returned error: ".$res['error']);
        }
    }


    public function delete($task_id) {
        $res = $this->sendRequest([
            'action' => 'delete',
            'task_id' => $task_id
        ]);
        return $res && $res['success'];
    }

}