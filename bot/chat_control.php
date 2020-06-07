<?php
function chat_log($update) {
    global $config;
    $message_id = 0;
    $message_id = $update['message']['message_id'];
    $chat_id = 0;
    $chat_id = $update['message']['chat']['id'];

    $message = json_encode($update);
    // Delete commands sent to raid group and send a notification message to user
    if(isset($update['message']['entities'])) {
        foreach($update['message']['entities'] as $entity) {
            if($entity['offset']=="0") {
                $com = explode("@",substr($update['message']['text'],1,17))[0];
                $command = ROOT_PATH . '/commands/' . basename($com) . '.php';

                if($entity['type']=="bot_command" && is_file($command)) {
                    delete_message($chat_id,$message_id);
                    send_message($update['message']['from']['id'],"Hei. Botin komennot lähetetään tähän keskusteluun, ei raidiryhmään ".iconv('UCS-4LE', 'UTF-8', pack('V', 0x1F642)),[]);
                    return;
                }
            }
        }
    }

    if(isset($update['message']['new_chat_member']['id']) && in_array($chat_id,$config->RESTRICTED_CHAT_ID)) {
        $user_id = $update['message']['new_chat_member']['id'];
        if(new_user($user_id)) {
            // Mute user

            // Create content array.
            $content = [
                'method'     => 'restrictChatMember',
                'chat_id'    => $chat_id,
                'user_id'    => $user_id,
                'can_send_messages'       => 0,
                'can_send_media_messages'       => 0,
                'can_send_other_messages'       => 0,
                'can_add_web_page_previews'       => 0
            ];

            // Encode data to json.
            $json = json_encode($content);

            // Set header to json.
            header('Content-Type: application/json');


            // Send request to telegram api.
            curl_json_request($json);

        }
    }elseif(new_user($update['message']['from']['id'])) {
        // Automatically delete messages from new users that haven't read the tutorial and who manage to send a message before they get muted (spam bots)
        delete_message($chat_id,$message_id);
        return;
    }
    my_query("INSERT INTO chat_log 
    SET
        message = '{$message}',
        message_id = '{$message_id}',
        chat_id = '{$chat_id}'
    ");
}

function chat_cleanup($chat) {
    global $config;
    debug_log("Initiating chat cleanup for chat {$chat}");
    $time = $config->CHAT_CLEANUP_TIME;
    $q = my_query("SELECT * FROM chat_log WHERE time < DATE_ADD(NOW(),INTERVAL -{$time} MINUTE)  AND chat_id='{$chat}' AND skip_cleanup!='1'");
    while($result = $q->fetch_assoc()) {
        delete_message($result['chat_id'],$result['message_id']);
        my_query("DELETE FROM chat_log WHERE id='{$result['id']}'");
    }
}
?>