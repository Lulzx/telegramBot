<?php
    $aliases = array("meme" => "memes", "memes" => "memes", "m" => "memes");
    global $pluginManager;
    $pluginManager->registerAliases($aliases);
    $helps = array(
        "Use /m, /meme or /memes",
        "/m_list_X - list X possible Meme names (default X = 100)",
        "/m_search_Y - search for a meme with name Y",
        "/m `NAME` - Receive the first Meme which name matches `NAME`",
        "/m `NAME ; TOP` - As `/m NAME` but with text `TOP` written on the top of the Meme",
        "/m `NAME ; TOP ; BOT` - As `/m NAME ; TOP` but with text `BOT``` written on the bottom of the Meme"
    );
    $pluginManager->addHelp("memes", $helps);
?>
