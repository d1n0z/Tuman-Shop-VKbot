<?php

ini_set('log_errors', 'On');
ini_set('error_log', '/var/log/php_errors.log');


require_once("db.php");

const CHANCE = 15;
const TOKEN = "";
const SERVICE_TOKEN = "";
const GROUP_ID = 187129799;
const CONFIRM = "dcc27964";
const POST_ID = "1481";


$data = json_decode(file_get_contents('php://input'));
//$data = json_decode(file_get_contents('test.json'));

if (!isset($data)) {
    http_response_code(422);
    echo "none";
    return "none";
}

function send_reply($msg, $id, $img = null): void
{
    $request_params = array(
        'owner_id' => -GROUP_ID,
        'post_id' => POST_ID,
        'message' => $msg,
        'reply_to_comment' => $id,
        'access_token' => TOKEN,
        'v' => '5.131'
    );
    if (isset($img)) {
        $server = json_decode(file_get_contents("https://api.vk.com/method/photos.getWallUploadServer?group_id=" . GROUP_ID . "&v=5.131&access_token=" . SERVICE_TOKEN), true);

        $dir = '/var/www/html/materials/' . $img;
//      $dir = 'C:/Users/SuperUser/PhpstormProjects/vspbot/materials/' . $img;

        $info = getimagesize($dir);
        $basename = pathinfo($dir);
        $dir = curl_file_create($dir, $info['mime'], $basename['basename']);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $server['response']['upload_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('file1' => $dir));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $v = curl_exec($ch);
        if (!$v) {
            $v = curl_error($ch);
        }

        curl_close($ch);
        $response = json_decode(json_encode(json_decode($v)), True);

        $result = json_decode(file_get_contents("https://api.vk.com/method/photos.saveWallPhoto?group_id=" . GROUP_ID . "&access_token=" . SERVICE_TOKEN . "&photo={$response["photo"]}&server={$response["server"]}&hash={$response["hash"]}&v=5.131"), true);

        $request_params['attachments'] = 'photo' . $result["response"][0]["owner_id"] . '_' . $result["response"][0]["id"];
    }

    $get_params = http_build_query($request_params);

    file_get_contents('https://api.vk.com/method/wall.createComment?' . $get_params);
}

function send_lm($msg, $id): void
{
    $request_params = array(
        'user_id' => $id,
        'random_id' => 0,
        'message' => $msg,
        'access_token' => TOKEN,
        'v' => '5.131'
    );

    $get_params = http_build_query($request_params);

    file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);
}

switch ($data->type) {
    case 'confirmation':
        echo CONFIRM;
        break;

    case 'wall_repost':
        echo "ok";

        $sql = "SELECT * FROM users WHERE vk_id={$data->object->from_id}";
        if (isset($conn)) $result = $conn->query($sql);
        else return;
        $row = $result->fetch_assoc();

        if ($row["reposted"] == 0) {
            try {
                $sql = "UPDATE users SET reposted=1, attempts_left=" . $row["attempts_left"] + 2 . " WHERE vk_id={$data->object->from_id}";
                $conn->query($sql);
                $conn->commit();
                break;
            } catch (Exception $e) {
                var_dump($e);
            }
        }

        break;

    case 'wall_reply_new':
        echo "ok";

        if ($data->object->post_id != POST_ID or $data->object->from_id == -GROUP_ID) return;

        $text = mb_strtolower($data->object->text);

        if (str_contains($text, "хочу приз")) {
            $sql = "SELECT * FROM users WHERE vk_id={$data->object->from_id}";
            if (isset($conn)) $result = $conn->query($sql);
            else return;
            $row = $result->fetch_assoc();
            $sql = "SELECT COUNT(*) FROM users WHERE won=1";
            $allready_won = $conn->query($sql);
            $allready_won = intval($allready_won->fetch_row()[0]);
            if ($allready_won >= 35) return;
            if ($result->num_rows == 0) {
                $subscribed = json_decode(file_get_contents("https://api.vk.com/method/groups.isMember?group_id=" . GROUP_ID . "&user_id={$data->object->from_id}&access_token=" . TOKEN . "&v=5.131"), true);
                $subscribed = $subscribed["response"];
                if ($subscribed == 0) {
                    send_reply("Вы должны быть подписаны на группу!", $data->object->id);
                    return;
                }
                $user = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$data->object->from_id}&access_token=" . TOKEN . "&v=5.131"), true);
                $sql = 'INSERT INTO users(name, won, attempts_left, reposted, vk_id, bonus) VALUES("' . $user["response"][0]["first_name"] . ' ' . $user["response"][0]["last_name"] . '", 0, 3, 0, ' . $data->object->from_id . ', 0)';
                $conn->query($sql);
                $conn->commit();
                $sql = "SELECT * FROM users WHERE vk_id={$data->object->from_id}";
                $result = $conn->query($sql);
                $row = $result->fetch_assoc();
            }
            if ($row["won"] != 1) {
                $name = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$data->object->from_id}&access_token=" . TOKEN . "&v=5.131"), true);
                $name = '[id' . $data->object->from_id . '|' . $name["response"][0]["first_name"] . ']';
                if ($row["attempts_left"] > 0) {
                    if (floatval(rand(1, 100)) <= CHANCE) {
                        $img = '4.jpg';
                        $sql = "UPDATE users SET won=1 WHERE vk_id={$data->object->from_id}";
                        $conn->query($sql);
                        send_reply($name . ', поздравляем! Ваш приз: 500 бонусов! Свяжитесь с нами, чтобы получить приз!', $data->object->id, $img);
                    } else {
                        $img = rand(1, 3) . '.jpg';
                        $msg = $name . ', в этот раз не повезло. У вас ';
                        switch ($row["attempts_left"] - 1) {
                            default:
                                $msg = $msg . 'осталось ' . $row["attempts_left"] - 1 . ' попыток. Осталось ' . 35 - $allready_won . ' призов.';
                                break;
                            case 0:
                                $msg = $msg . 'не осталось попыток.';
                                if ($row["reposted"] == 0) $msg = $msg . ' Вы можете сделать репост, чтобы получить ещё 2 попытки.';
                                break;
                            case 1:
                                $msg = $msg . 'осталась 1 попытка. Осталось ' . 35 - $allready_won . ' призов.';
                                break;
                            case 2:
                            case 3:
                            case 4:
                                $msg = $msg . 'осталось ' . $row["attempts_left"] - 1 . ' попытки. Осталось ' . 35 - $allready_won . ' призов.';
                                break;
                        }
                        $sql = "UPDATE users SET attempts_left=" . $row["attempts_left"] - 1 . " WHERE vk_id={$data->object->from_id}";
                        $conn->query($sql);
                        send_reply($msg, $data->object->id, $img);
                    }
                } else {
                    $msg = $name . ", у вас не осталось попыток.";
                    if ($row["reposted"] == 0) $msg = $msg . " Вы можете сделать репост, чтобы получить ещё 2 попытки.";
                    send_reply($msg, $data->object->id);
                }
            }
            else send_reply("Вы уже победили в розыгрыше! Свяжитесь с нами чтобы забрать приз - 500 бонусов", $data->object->id);
        }
        if (isset($conn)) {
            $conn->commit();
            $conn->close();
        }
        break;

    case 'message_new':
        echo "ok";

        $text = mb_strtolower($data->object->message->text);

        if (str_contains($text, "бонус")) {
            $sql = "SELECT * FROM users WHERE vk_id={$data->object->message->from_id}";
            if (isset($conn)) $result = $conn->query($sql);
            else return;

            if ($result->num_rows == 0) {
                $user = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$data->object->message->from_id}&access_token=" . TOKEN . "&v=5.131"), true);
                $sql = 'INSERT INTO users(name, won, attempts_left, reposted, vk_id, bonus) VALUES("' . $user["response"][0]["first_name"] . ' ' . $user["response"][0]["last_name"] . '", 0, 3, 0, ' . $data->object->message->from_id . ', 0)';
                $conn->query($sql);
                $conn->commit();
                $sql = "SELECT * FROM users WHERE vk_id={$data->object->message->from_id}";
                $result = $conn->query($sql);
            }

            $row = $result->fetch_assoc();

            $msg = '';
            if ($row["bonus"] != 1 and $row["won"] != 1) {
                $sql = "UPDATE users SET attempts_left=" . $row["attempts_left"] + 2 . ", bonus=1 WHERE vk_id={$data->object->message->from_id}";
                $conn->query($sql);
                $conn->commit();
                $msg = 'Вы получили ещё 2 попытки 😎';
            }
            if ($row["bonus"] == 1) $msg = 'Вы уже получали бонус!';
            if ($row["won"] == 1) $msg = 'Вы уже победили в розыгрыше! Свяжитесь с нами чтобы забрать приз - 500 бонусов';

            send_lm($msg, $data->object->message->from_id);
        }
        break;
}
