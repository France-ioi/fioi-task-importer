<?php

class QuizzeServer {

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


    public function write($task_id, $grader_data_path) {
        if(!file_exists($grader_data_path)) {
            return false;
        }
        $data = file_get_contents($grader_data_path);
        $res = $this->sendRequest([
            'action' => 'write',
            'task_id' => $task_id,
            'data' => $data
        ]);
        return $res && $res['success'];
    }


    public function delete($task_id) {
        $res = $this->sendRequest([
            'action' => 'delete',
            'task_id' => $task_id
        ]);
        return $res && $res['success'];
    }

}