<?php
require('../api.php');

if (!isLoggedIn()) {
    result(false, "Not logged in");
}

$user = $GLOBALS['user'];
$customer = null;

try {
    $customer = $gateway->customer()->find($user->id);
} catch (Braintree\Exception\NotFound $e) {
    $i = strpos($user->name, " ");
    $firstName = "";
    $lastName = "";
    if ($i === FALSE) {
        $firstName = $user->name;
    } else {
        $firstName = substr($user->name, 0, $i);
        $lastName = substr($user->name, $i+1, strlen($user->name) - ($i + 1));
    }

    $customer = $gateway->customer()->create([
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $user->email,
        'phone' => $user->phone,
        'id' => $user->id
    ]);
}

$clientToken = $gateway->clientToken()->generate([
    'customerId' => $user->id
]);

echo "\"$clientToken\"";
?>