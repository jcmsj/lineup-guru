<?php
require './vendor/autoload.php';

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->get('/', 'loadHome');
    $r->get('/queue', 'getAllQueues');
    $r->post('/queue', 'createQueue');
    // {name} can be any alphanumeric string
    $r->get('/queue/{name:\w+}', 'getQueue'); 
    $r->delete('/queue/{name:\w+}', 'deleteQueue'); #untested
    $r->post('/join', 'joinQueue');
    $r->put('/queue/:id', 'updateQueue'); #untested
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
// GetAllQueues
function getAllQueues($db) {
    $sql = 'SELECT * FROM queue';
    $result = $db->query($sql);

    $queues = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $queues[] = $row;
    }

    echo_json($queues);
}

// CreateQueue
function createQueue($db) {
    /** @var string */
    $name = demand('name');
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
function updateQueue($db, $id) {
    $name = $_POST['name'];
    $current = $_POST['current'];
    $lastPosition = $_POST['last_position'];

    $sql = 'UPDATE queue SET name = ?, current = ?, last_position = ? WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(1, $name);
    $stmt->bindParam(2, $current);
    $stmt->bindParam(3, $lastPosition);
    $stmt->bindParam(4, $id);
    $stmt->execute();

    echo_json(['id' => $id]);
}

// DeleteQueue
function deleteQueue($db, $id) {
    $sql = 'DELETE FROM queue WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id);
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
function joinQueue($db) {
    /** @var string */
    $name = demand('name');
    $sql = 'SELECT last_position FROM queue WHERE name = :name';
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':name', $name);
    $result = $stmt->execute();

    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $new_pos = $row['last_position'] + 1;
        $update = 'UPDATE queue SET last_position = :last_pos WHERE name = :name';
        $stmt2 = $db->prepare($update);
        $stmt2->bindParam(':name', $name);
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
