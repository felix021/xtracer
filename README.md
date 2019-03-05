# xtracer

tracer library for yii2, compatible with jaeger.

## Usage

### 0. Install Yii2

`$ composer create-project --prefer-dist yiisoft/yii2-app-basic basic`

### 1. Install felix021/xtracer

`$ composer require felix021/xtracer:dev-master`

### 2. Config

#### 2.1 `config/web.php`

```
$config = [
    'id' => '<APP NAME>'
    ...
    'on beforeAction' => ['XTracer\Tracer','beforeAction'],
    'on afterAction' => ['XTracer\Tracer','afterAction'],
    ...
    components => [
        ...
        'tracer' => [
            'class' => 'XTracer\Tracer',
            'maskRules' => [
                ['mobile', 'prefixSuffix', [3, 4]],
                ['password', 'all', [8]],
                ['name', 'prefix', [-1]],
            ],
        ],
        ...
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'XTracer\FileTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'logVars' => [],
                ],
                [
                    'class' => 'XTracer\FileTarget',
                    'levels' => ['info'],
                    'categories' => ['jaeger'],
                    'logFile' => '@runtime/logs/jaeger.log',
                    'logVars' => [],
                ],
            ],
        ],
        ...
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
        ...
    ],
];
```

#### 2.2 `config/console.php`

```
$config = [
    'id' => '<APP NAME>'
    ...
    'on beforeAction' => ['XTracer\Tracer','beforeAction'],
    'on afterAction' => ['XTracer\Tracer','afterAction'],
    ...
    components => [
        ...
        'tracer' => [
            'class' => 'XTracer\Tracer',
            'maskRules' => [
                ['mobile', 'prefixSuffix', [3, 4]],
                ['password', 'all', [8]],
                ['name', 'prefix', [-1]],
            ],
        ],
        ...
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'XTracer\FileTarget',
                    'levels' => ['info', 'error', 'warning'],
                    'logVars' => [],
                ],
                [
                    'class' => 'XTracer\FileTarget',
                    'levels' => ['info'],
                    'categories' => ['jaeger'],
                    'logFile' => '@runtime/logs/jaeger.log',
                    'logVars' => [],
                ],
            ],
        ],
        ...
    ],
];
```

### 3. Trace & Log

Each time when yii receives a http request, or begins to execute a console commmand, a span will be created. After execution, trace info will be saved to `@app/runtime/logs/jaeger.log`, one line per span in json format. `jaeger.log` is supposed to be send to a backend storage like ElasticSearch for later analysis.

Use `Yii::info($message)` to log extra running information, which will be saved to `@app/runtime/logs/app.log`, one line per log in json format as well.

In addition, when a http request is received, a message containing `$_GET`, `$_POST` and `$_SERVER` will be saved to `@app/runtime/logs/app.log` for debug purpose.

### 4. Outbound Request

Use `\XTracer\Http` to send outbound request, which creates a sub span, and sends traceid to downstream service in http header `uber-trace-id`, as is defined in jaeger document.

Example:

```
use XTracer\Http;

$response = (new Http())->get("https://www.google.com");
$response = (new Http())->get("https://www.google.com");
if ($response['errno'] != 0) {
    throw new Exception("failed: " . $response['message']);
}
echo $response['body'], "\n";
Yii::info($response);

#more usage
$response = (new Http())->post("https://www.google.com", ['a'=>1]);
$response = (new Http())->call("POST", "https://www.google.com", '{"a": 1}', ['Content-Type: application/json']);
$response = (new Http())->call("POST", "https://www.google.com", '{"a": 1}', ['Content-Type: application/json'], [CURLOPT_SSL_VERIFYPEER => false]);
```

### 5. Mask

Set the `maskRules` attribute for `\XTracer\Tracer` in `config['components']` to mask sensitive data in `$_GET`/`$_POST` which will be saved to `app.log`.

Rule format is `[$key, $method, $argArray]`, where `$method` can be one of the predefined action:

* `['password', 'unset', []]`: remote this key before logging
* `['password', 'all', [8]]`: replace all characters to 8 asterisks ('\*') 
* `['name', 'prefix', [1]]`: keep only the first characters, and replace the remain to '\*'
* `['name', 'prefix', [-1]]`: replace only the first characters to '\*'
* `['name', 'suffix', [1]]`: keep only the last characters, and replace the remain to '\*'
* `['name', 'suffix', [-1]]`: replace only the last characters to '\*'
* `['mobile', 'prefixSuffix', [3, 4]]`: keep only the first 3 and last 4 characters
* `['mobile', 'prefixSuffix', [-3, -4]]`: replace only the first 3 and last 4 characters

`$method` can also be a self defined method. Refer to `XTracer\Mask::maskPrefix`.

If all above is not enough, you can set an extra `maskMethod` attribute for `\XTracer\Tracer`, which takes in the whole array, and return the masked array.
