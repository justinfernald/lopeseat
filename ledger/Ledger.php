<?php
require_once('../api.php');

$GLOBALS['key'] = $secrets->encryption->key;
$GLOBALS['iv'] = $secrets->encryption->iv;

class Ledger {

    var $report = null;

    function getQuickBalance($user_id, $balanceId) {
        $db = new db();
        $stmt = $db->prepare("SELECT balance FROM BalanceUpdates WHERE user_id=? AND (source=? OR destination=?) ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("iii",$user_id, $balanceId, $balanceId);
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
        return $ledger;
    }

    function writeEncrypted($dataObj) {
        $ledger = $this->loadEncryptedLedger();
        array_push($ledger, $dataObj);
        $ledgerJson = json_encode($ledger);
        $encLedger = openssl_encrypt($ledgerJson, 'aes-256-cbc', $GLOBALS['key'], 0, $GLOBALS['iv']);

        $encFile = fopen("ledger.b64", "w") or die("Unable to open ledger file!");
        fwrite($encFile, $encLedger);
        fclose($encFile);
    }

    function validateDatabase() {
        $db = new db();
        $stmt = $db->prepare("SELECT * FROM BalanceUpdates ORDER BY id ASC");
        $db->exec();
        $result = $db->get();

        $ledger = $this->loadEncryptedLedger();

        $columns = Array("user_id", "amount", "source", "destination", "balance");

        $matches = true;

        $report = Array();

        // Database id is the encrypted ledger index + 1
        
        for ($j = 0; $j < sizeof($ledger); $j++) {
            $row = $result->fetch_assoc();
            if (!$row) {
                $matches = false;
                $n = sizeof($ledger) - $j;
                array_push($report, "Missing " . $n . " rows!");
                break;
            }
            $badCols = Array();
            $ledgerRow = get_object_vars($ledger[$j]);
            for ($i = 0; $i < sizeof($columns); $i++) {
                if ($row[$columns[$i]] != $ledgerRow[$columns[$i]]) {
                    array_push($badCols, $columns[$i]);
                    $matches = false;
                }
            }
            if (sizeof($badCols) > 0)
                array_push($report, Array(id => $row['id'], user_id => $ledgerRow["user_id"], columns => $badCols, correction => $ledgerRow));
        }

        if ($result->num_rows > sizeof($ledger)) {
            $matches = false;
            $n = $result->num_rows - sizeof($ledger);
            array_push($report, $n . " extra row(s) added");
        }

        $this->report = $report;

        return $matches;
    }

    function write($user_id, $sender_id, $receiver_id, $amount, $source, $destination) {
        $isSender = $user_id != $receiver_id;
        $sign = $isSender ? -1 : 1;
        $balanceId = $isSender ? $source : $destination;
        $newBalance = 0;
        if ($balanceId != 0)
            $newBalance = $this->getQuickBalance($user_id, $balanceId) + ($sign * $amount);

        if ($newBalance < 0)
            return false;

        $date = new DateTime();
        $date->modify('-4 hours');
        $timeStamp = $date->format('Y-m-d H:i:s');

        $db = new db();
        $stmt = $db->prepare("INSERT INTO `BalanceUpdates` (`user_id`, `sender_id`, `receiver_id`, `amount`, `source`, `destination`, `balance`) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("iiidiid", $user_id, $sender_id, $receiver_id, $amount, $source, $destination, $newBalance);
        $db->exec();

        $dataObj = Array(
            id => $GLOBALS['conn']->insert_id,
            user_id => $user_id, 
            sender_id => $sender_id,
            receiver_id => $receiver_id,
            amount => $amount, 
            source => $source, 
            destination => $destination, 
            balance => $newBalance, 
            time => $timeStamp
        );

        $this->writeEncrypted($dataObj);
        return true;
    }

    function transferDeliveryEarnings($user_id, $amount) {
        return $this->write($user_id, 0, $user_id, $amount, 4, 2);
    }

    function transferDeliveryFeeFromLEB($user_id, $amount) {
        return $this->write($user_id, $user_id, 0, $amount, 1, 5);
    }

    function transferDeliveryFeeFromDB($user_id, $amount) {
        return $this->write($user_id, $user_id, 0, $amount, 2, 5);
    }

    function transferCashToLEB($sender_id, $receiver_id, $amount) {
        return $this->write($receiver_id, $sender_id, $receiver_id, $amount, 3, 1);
    }

    function transferCashFromDB($user_id, $amount) {
        return $this->write($user_id, $user_id, 0, $amount, 2, 3);
    }

}
?>