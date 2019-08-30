<?php

namespace BuildTrigger;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server;

require __DIR__ . '/vendor/autoload.php';

$generateResponse = function ($message, $code = 200) {
    $responseData = [
        'response_type' => 'ephemeral',
        'text'          => $message,
    ];

    return new Response(
        $code,
        array(
            'Content-Type' => 'application/json',
        ),
        \json_encode($responseData)
    );
};

$loop = Factory::create();
$server = new Server(static function (ServerRequestInterface $request) use ($loop, $generateResponse) {

    $body = $request->getParsedBody();


    if (
        (getenv('SLACK_COMMAND_TOKEN') && (!isset($body['token']) || $body['token'] !== getenv('SLACK_COMMAND_TOKEN')))
        || !isset($body['response_url'])) {
        return new Response(
            400,
            array(
                'Content-Type' => 'application/json',
            ),
            'Bad request'
        );
    }

    if (!empty($body['text'])) {
        $command = explode(' ', $body['text']);
        $command = array_filter($command);
        $command = array_map('trim', $command);

        if (count($command) < 2) {
            return $generateResponse('Your should pass parameter!');
        }
    } else {
        return $generateResponse('Hello. I\'m TeamCIty build command. Specify build alias and parameter!');
    }

    list($task, $param1, $param2, $param3) = $command;

    $response_url = $body['response_url'];

    $task_file_path = 'tasks/' . $task . '.xml';
    if (!file_exists($task_file_path)) {
        return $generateResponse('No task registered');
    }
    $taskBody = file_get_contents($task_file_path);

    for ($i = 1; $i <= 3; $i++) {
        $paramVariable = 'param' . $i;
        $taskBody = str_replace('@param' . $i, $$paramVariable, $taskBody);
    }

    $tcSeverUrl = getenv('TEAMCITY_SERVER');
    $client = new \GuzzleHttp\Client();

    $options['body'] = $taskBody;
    $options['headers'] = [
        'Authorization' => 'Bearer ' . getenv('TEAMCITY_TOKEN'),
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/xml',
        'Origin'        => $tcSeverUrl,

    ];

    $response = $client->request('POST', $tcSeverUrl . '/app/rest/buildQueue', $options);

    $isSuccess = $response->getStatusCode() === 200;

    $responseData = [
        'response_type' => 'in_channel',
        'text'          => $isSuccess ? 'Build command was sent!' : 'Error while sending build command :(',
        'attachments'   => [
            [
                'color' => $isSuccess ? '#36a64f' : '#a63636',
                'title' => $isSuccess ? 'Success' : 'Failed',
                'text'  => $isSuccess ? 'Build command sent sucessfully.' : $response->getBody(),
            ],
        ],
    ];
    $response = new Response(
        200,
        array(
            'Content-Type' => 'application/json',
        ),
        \json_encode($responseData)
    );

    return $response;
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:9000', $loop);
$server->listen($socket);
echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
