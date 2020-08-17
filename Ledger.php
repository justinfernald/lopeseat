<?php
require_once('api.php');

$GLOBALS['key'] = $secrets->encryption->key;
$GLOBALS['iv'] = $secrets->encryption->iv;

class Ledger {

    function getQuickBalance($user) {
        $db = new db();
        $stmt = $db->prepare("SELECT balance FROM BalanceUpdates WHERE user_id=? ORDER BY time DESC LIMIT 1");
        $stmt->bind_param("i",$user->id);
        $db->exec();
        $result = $db->get();
        if ($result->num_rows == 0)
            return 0;
        $row = $result->fetch_assoc();
        return $row['balance'];
    }

    function loadEncryptedLedger() {
        // Load encrypted ledger in base 64
        $encLedger = file_get_contents("ledger.b64");
        $ledgerJson = openssl_decrypt($encLedger, 'aes-256-cbc', $GLOBALS['key'], 0, $GLOBALS['iv']);
        $ledger = json_decode($ledgerJson);
        var_dump($ledger);
    }

    function writeToLedger($user, $amount, $source, $destination) {
        $balance = getQuickBalance($user) + ;
        $dataObj = Array(user_id => $user->id, amount => $amount, source => $source, destination => $destination, );
        $db = new db();
        $stmt = $db->prepare("")
    }

}
?>