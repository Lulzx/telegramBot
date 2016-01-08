<?php
class Hangman {
    public function __construct() {
        // Check if DB table exists
        global $db;
        $query = "SHOW TABLES LIKE `".p_hangman_table."`";
        $db->query($query);
        if ($db->numRows() == 0) {
            // Table does not exist
            $this->setupDb();
        }
    }

    // Called when message received
    public function receive($data, $message) {
        global $api;
        global $chatid;
        $text = $data[0];
        if (strlen($text) == 1) {
            $game = $this->loadRunningGame($chatid);
            if ($game != false && !$game["paused"]) {
                // Check if send string is a letter
                $letter = strtoupper($text);
                file_put_contents("log/hangman.log", "+++" . $letter . "+++\n");
                if (ctype_upper($letter)) {
                    if (!in_array($letter, $game["letters"])) {
                        // letter not already found
                        $word = strtoupper($game["word"]);
                        $i = 0;
                        $valid = false;
                        while ($i < strlen($word)) {
                            if (substr($word, $i, 1) == $letter) {
                                $valid = true;
                                $i = strlen($word);
                            }
                            $i++;
                        }
                        if ($valid) {
                            $game["letters"][] = $letter;
                        } else {
                            $game["fails"]++;
                        }
                        $this->saveGame($game);
                    }
                    $reply = $this->draw($game);

                    if ($this->isSolved($game)) {
                        $reply .= "\nYou won!";
                        $this->deleteGame($game);
                    } elseif ($game["fails"] == 9) {
                        $reply .= "\nYou are dead :(\nSolution is: " . $game["word"];
                        $this->deleteGame($game);
                    }
                    

                    global $api;
                    $api->sendMessage($chatid, $reply, "Markdown", true);
                } 
            } 
        }
    }

    public function execute($data, $message) {
        global $pluginManager; 
        global $api;
        global $chatid;
        global $sender;

        $cmd = $data[0];
        $cmd = explode("_", $cmd);

        $reply = "";

        $senderid = $sender["id"];

        $game = $this->loadRunningGame($chatid);

        switch ($cmd[1]) {
            case "start":
                if ($game == false) {
                    $game = $this->newGame($chatid, $senderid);
                    if ($game == false) {
                        $reply = "Error while creating game :(";
                    } else {
                        $reply = "Game started! \n\n";
                        $reply .= $this->draw($game);
                    }
                } elseif ($game["paused"]) {
                    $reply = "Game continued!\n\n";
                    $game["paused"] = false;
                    $reply .= $this->draw($game);
                    $this->saveGame($game);
                } else {
                    $reply = "There is already a running game\n\n";
                    $reply .= $this->draw($game);
                }
                break;
            case "pause":
                if ($game == false) {
                    $reply = "There is no game to pause";
                } else {
                    $game["paused"] = true;
                    $this->saveGame($game);
                    $reply = "Game paused. Use /hangman\_start to continue.";
                }
                break;
            case "solve":
                if ($game == false) {
                    $reply = "No game to solve";
                    break;
                } else {
                    $reply = "Solution is: " . $game["word"]. "\n\n";
                }
                // Fall to Stop
            case "stop":
                if ($game == false) {
                    $reply = "No active game to stop";
                } else {
                    if ($this->deleteGame($game))
                        $reply .= "Game stopped";
                    else
                        $reply .= "Game could not be stopped";
                }
                break;
            default:
                 $reply = "Hangman! Type /help\_hangman for all commands";
                 break;
        }

        $api->sendMessage($chatid, $reply, "Markdown", true);
    }


    private function isSolved($game) {
        $pos = 0;
        $word = $game["word"];
        while ($pos < strlen($game["word"])) {
            if (!in_array(substr($word, $pos, 1), $game["letters"])) {
                return false;
            }
            $pos++;
        }
        return true;
    }

    private function draw($game) {
        $wordlength = strlen($game["word"]);
        $word = $game["word"];
        $pos = 0;

        $print = "`";

        $dat = file_get_contents("plugins/hangman/graphics.dat");
        $dat = explode("\n", $dat);
        $min = $game["fails"] * 8;
        $max = $min + 7;

        while ($min <= $max) {
            $print .= $dat[$min] . "\n";
            $min++;
        }
        $print .= "`";

        while ($pos < $wordlength) {
            $letter = substr($word, $pos, 1);
            if (in_array($letter, $game["letters"])) {
                $print .= $letter . " ";
            } else {
                $print .= "\_ ";
            }
            $pos++;
        }
        return $print;
    }

    private function deleteGame($game) {
        global $db;
        $query = "DELETE FROM `".p_hangman_table."` WHERE `".p_hangman_id."` = '".$game["id"] ."'";
        $db->query($query);
        return ($db->affected_rows() > 0);
    }
    
    
    private function newGame($chat, $owner) {
        $words = file_get_contents("plugins/hangman/words.dat");
        $words = explode("\n", $words);
        $count = sizeof($words);
        $pos = rand(0, $count - 1);
        $word = $words[$pos];
        $game = array();
        global $db;
        $query = "
            INSERT INTO `".p_hangman_table."` (
                `".p_hangman_chat."`,  
                `".p_hangman_owner."`,  
                `".p_hangman_word."`,  
                `".p_hangman_letters."`,  
                `".p_hangman_fails."`,  
                `".p_hangman_paused."`  
            ) VALUES (            
                '".$chat."', 
                '".$owner."',  
                '".$word."',
                '".json_encode(array())."',
                '0',
                '0'  
            );";
        $db->query($query);
        if ($db->affected_rows() > 0) {
            $game["id"] = $db->insertId();
            $game["chat"] = $chat;
            $game["owner"] = $owner;
            $game["word"] = $word;
            $game["letters"] = array();
            $game["fails"] = 0;
            $game["paused"] = false;
            return $game;
        } else {
            return false;
        }
    }

    private function loadRunningGame($chatid) {
        global $db;
        $query = "
            SELECT * from `".p_hangman_table."` WHERE `".p_hangman_chat."` = '".$chatid."' LIMIT 1;
        ";
        $db->query($query);
        if ($db->numRows() == 1) {
            $game = array();
            $res = $db->fetchArray();
            $game["id"] = $res[p_hangman_id];
            $game["chat"] = $res[p_hangman_chat];
            $game["owner"] = $res[p_hangman_owner];
            $game["word"] = $res[p_hangman_word];
            $game["letters"] = json_decode($res[p_hangman_letters]);
            $game["fails"] = $res[p_hangman_fails];
            $game["paused"] = ($res[p_hangman_paused] == 1);
            return $game;
        }
        return false;
    }

    public function saveGame($game) {
        global $db;
        $query = "
            UPDATE `".p_hangman_table."` SET
                `".p_hangman_letters."` = '".json_encode($game["letters"])."',
                `".p_hangman_fails."` = '".$game["fails"]."',
                `".p_hangman_paused."` = '".($game["paused"] ? "1" : "0")."'
            WHERE `".p_hangman_id."` = '".$game["id"]."';
        ";
        $db->query($query);
        if ($db->affected_rows() >= 0) {
            return true;
        } else {
            return false;
        }
    }

    private function setupDb() {
        $query = "
            CREATE TABLE `".p_hangman_table."` ( 
                `".p_hangman_id."` BIGINT NOT NULL AUTO_INCREMENT , 
                `".p_hangman_chat."` BIGINT NOT NULL , 
                `".p_hangman_owner."` BIGINT NOT NULL , 
                `".p_hangman_word."` VARCHAR(1000) NOT NULL , 
                `".p_hangman_letters."` TEXT NOT NULL , 
                `".p_hangman_fails."` INT NOT NULL , 
                `".p_hangman_paused."` INT NOT NULL ,
                PRIMARY KEY (`".p_hangman_id."`));
        ";
        global $db;
        $db->query($query);
    }

    
}
?>