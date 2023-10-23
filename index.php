<?php
require './vendor/autoload.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");
//  Read https://github.com/nikic/FastRoute
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->get('/', 'loadHome');
    $r->get('/queue', 'getAllQueues');
    $r->post('/queue', 'createQueue');
    // {name} can be any alphanumeric string
    $r->get('/queue/{name:\w+}', 'getQueue'); 
    $r->delete('/queue/{id:\d+}', 'deleteQueue');
    $r->post('/join/{id:\d+}', 'joinQueue');
    $r->post('/update/{id:\d+}', 'updateQueue');
});

$db = new SQLite3('queue.sqlite');
setup($db);
// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string, e.g. (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}

// Unused variable
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $handler($db, $vars);
        break;
}

function setup($db) {
    // run tables.sql using the $db
    $sql = file_get_contents('tables.sql');
    $db->exec($sql);
}

function loadHome() {
    echo file_get_contents('index.html');
}

function getAllQueues($db) {
    $sql = 'SELECT * FROM queue';
    $result = $db->query($sql);

    $queues = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $queues[] = $row;
    }

    echo_json($queues);
}

function createQueue($db) {
    /** @var string */
    $name = $_POST['name'];
    $sql = 'INSERT INTO queue (name, current, last_position) VALUES (:name, 0, 0)';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':name', $name);
    $stmt->execute();
    $id = $db->lastInsertRowID();
    echo_json(['id' => $id]);
}

// GetQueue
function getQueue($db, $vars) {
    $sql = 'SELECT * FROM queue WHERE name = :name';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':name', $vars['name']);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo_json($row);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'Queue not found';
    }
}

// UpdateQueue
function updateQueue($db, $vars) {
    $id = $vars['id'];
    $name = $_POST['name'];
    $current = $_POST['current'];
    $lastPosition = $_POST['last_position'];
    $sql = 'UPDATE queue SET name = :name, current = :current, last_position = :last_position WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":id", $id);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":current", $current);
    $stmt->bindParam(":last_position", $lastPosition);
    $stmt->execute();
}

// DeleteQueue
function deleteQueue($db, $vars) {
    $sql = 'DELETE FROM queue WHERE name = :name';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':name', $vars['name']);
    $stmt->execute();

    header('HTTP/1.1 204 No Content');
}

function demand($key) {
    if (!isset($_REQUEST[$key])) {
        header('HTTP/1.1 400 Bad Request');
        echo "$key is required";
        exit();
    }
    return $_REQUEST[$key];
}
function joinQueue($db, $vars) {
    /** @var string */
    $id = $vars['id'];
    $sql = 'SELECT last_position FROM queue WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $new_pos = $row['last_position'] + 1;
        $update = 'UPDATE queue SET last_position = :last_pos WHERE id = :id';
        $stmt2 = $db->prepare($update);
        $stmt2->bindParam(':id', $id);
        $stmt2->bindParam(':last_pos', $new_pos);
        $result = $stmt2->execute();
        echo_json(['number'=>$new_pos]);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'Queue not found';
    }
}

function clue(string $type) {
    if ($type == "json") {
        header('Content-Type: application/json');
    }
}

function echo_json(mixed $json) {
    clue("json");
    echo json_encode($json);
}
?>
