<?php
  require('sql.php');
  require('./vendor/autoload.php');
  session_start();

  header('Access-Control-Allow-Origin: *');
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");

  $gateway = new Braintree_Gateway([
      'environment'=>'sandbox',
      'merchantId' => 'xyt67ywsjf63s93k',
      'publicKey' => 'b6b65664w2vpbcwr',
      'privateKey' => '68a544770524bf4c9c6edf13340f84ae'
  ]);

  $messages = json_decode(file_get_contents("messages.json"));

  $serviceAccountPath = sprintf("%s/config/service_account.json", __DIR__);

  if ($_POST['apiToken'] !== null) {
    $GLOBALS['user'] = getUserFromToken($_POST['apiToken']);
  }

  function getUser($id) {
    $db = new db();
    $stmt = $db->prepare("SELECT * FROM Users WHERE id=?");
    $stmt->bind_param("s", $id);
    $db->exec();
    $result = $db->get();
    return $result->fetch_object("User");
  }

  function isLoggedIn() {
    return $GLOBALS['user'] != null;
  }

  function result($success, $message = "none") {
      if ($message === "none") {
        $message = $success ? "Success" : "Failed";
      }
      $out = array('success' => $success, 'msg' => $message);
      echo json_encode($out);
  }

  function randomToken($n = 13) {
    $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
      $out .= $chars[rand(0, strlen($chars)-1)];
    }

    return $out;
  }

  function randomNum($n = 6) {
    $digits = "0123456789";
    $out = "";

    for ($i = 0; $i < $n; $i++) {
      $out .= $digits[rand(0, strlen($digits)-1)];
    }

    return $out;
  }

  function formatPhoneNumber($phone) {
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (strlen($phone) > 10) {
      $phone = substr($phone, -10);
    }
    return $phone;
  }

  function validPassword($password) {
    //echo (strlen($password) >= 8) . " && " . (preg_match("/[A-Za-z]/", $password)) . "&&" . (preg_match("/[0-9]/", $password));
    return (strlen($password) >= 8 && preg_match("/[A-Za-z]/", $password) == 1 && preg_match("/[0-9]/", $password) == 1);
  }

  function getUserFromToken($token) {
    $db = new db();
    $stmt = $db->prepare("SELECT * FROM apiTokens WHERE token=?");
    $stmt->bind_param("s", $token);
    $db->exec();
    $result = $db->get();
    if ($result->num_rows == 0) {
      return null;
    }
    return getUser($result->fetch_assoc()['user_id']);
  }

  class CartItem {

    var $id;
    var $user_id;
    var $item_id;
    var $amount;
    var $comment;
    var $options;
    var $restaurant_id;
    var $name;
    var $price;
    var $image;

    function getTotal() {
      $total = $this->price * $this->amount;
      return $total;
    }

  }

  class Cart {

    var $items = [];

    static function loadCart($userId) {
      $cart = new Cart();
      $items = [];
      $db = new db();
      $stmt = $db->prepare("SELECT CartItems.id, CartItems.user_id, CartItems.item_id, CartItems.amount, CartItems.comment, CartItems.options, 
      MenuItems.restaurant_id, MenuItems.name, MenuItems.price, MenuItems.image FROM CartItems 
      INNER JOIN MenuItems
      ON CartItems.user_id=? AND CartItems.item_id = MenuItems.id");
      $stmt->bind_param("i", $userId);
      $db->exec();
      $result = $db->get();

      while($item = $result->fetch_object("CartItem")) {
          array_push($items, $item);
      }
      $cart->items = $items;
      return $cart;
    }

    function getTotal() {
      $total = 0;
      for ($i = 0; $i < sizeof($this->items); $i++) {
        $total += $this->items[$i]->getTotal();
      }
      return $total;
    }

  }

  class OrderItem {

    var $id;
    var $user_id;
    var $order_id;
    var $item_id;
    var $amount;
    var $comment;
    var $options;
    var $restaurant_id;
    var $restaurant_name;
    var $name;
    var $price;
    var $image;

    function getTotal() {
      $calculatedPrice = $this->price;
      $options = json_decode($this->options);

      $db = new db();
      $stmt = $db->prepare("SELECT * FROM `MenuItems` WHERE `id` = ?");
      $stmt->bind_param("i", $this->item_id);
      $db->exec();
      $result = $db->get();

      $items = json_decode($result->fetch_assoc()["items"]);

      for ($i = 0; $i < count($items); $i++) {
        $item = $items[$i];
        $options = $item->options;
        for ($j = 0; $j < count($options); $j++) {
          $option = $options[$j];
          $choices = $options->choices;
          if (isset($items[$i]) && isset($items[$i][$j]) && isset($choices[$items[$i][$j]]) && isset($choices[$items[$i][$j]]->cost)) {
            $calculatedPrice += $choices[$items[$i][$j]]->cost;
          }
        }
      }

      $total = $calculatedPrice * $this->amount;
      // $total = $this->price * $this->amount;
      return $total;
    }

  }

  class Order {

    var $items = [];

    static function loadOrder($orderId) {
      $order = new Order();
      $items = [];
      $db = new db();
      $stmt = $db->prepare("SELECT OrderItems.id, OrderItems.order_id, OrderItems.item_id, OrderItems.amount, OrderItems.comment, OrderItems.options, 
      MenuItems.restaurant_id, MenuItems.name, MenuItems.price, MenuItems.image, Orders.user_id, Restaurants.name as restaurant_name
      FROM OrderItems 
      INNER JOIN MenuItems ON OrderItems.order_id=? AND OrderItems.item_id = MenuItems.id
      INNER JOIN Orders ON Orders.id=?
      INNER JOIN Restaurants on MenuItems.restaurant_id=Restaurants.id");
      $stmt->bind_param("ii", $orderId, $orderId);
      $db->exec();
      $result = $db->get();

      while($item = $result->fetch_object("OrderItem")) {
          array_push($items, $item);
      }
      $order->items = $items;
      return $order;
    }

  }

  class User {
    var $id;
    var $student_id;
    var $name;
    var $deliverer;
    var $phone;
    var $email;
    var $profile_image;
    var $confirmed;
    var $token;
    var $salt;
    var $hash;
    var $created;
    var $FBToken;
  }

  class db {

    var $stmt = null;

    function prepare($query) {
      if ($GLOBALS['conn'] === null) {
        $server = "localhost";
        $user = $GLOBALS['sql_user'];
        $pass = $GLOBALS['sql_pass'];
        $db = "zerentha_lopeseat";

        $conn = new mysqli($server, $user, $pass, $db);
        $GLOBALS['conn'] = $conn;

        if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
        }
      }

      $this->stmt = $GLOBALS['conn']->prepare($query);
      return $this->stmt;
    }

    function exec() {
      $this->stmt->execute();
      if (!$this->stmt->error) {
          return true;
      } else {
          result(false, $this->stmt->error);
          return false;
      }
    }

    function get() {
      return $this->stmt->get_result();
    }

    function close() {
      mysqli_close($this->conn);
    }

  }
?>