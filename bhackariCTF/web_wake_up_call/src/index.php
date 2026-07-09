<?php

ini_set('display_errors', false);

$serialized = $_GET['ser'];
if (empty($serialized)) {
  highlight_file('index.php');
  die();
}

class FlagStore
{
  private $locked = true;

  public function __invoke()
  {
    if (!$this->locked) {
      echo getenv('FLAG').PHP_EOL;
    } {
      echo 'Flag store is locked'.PHP_EOL;
    }
  }

  public function __wakeup()
  {
    $this->locked = true;
    echo "Don't wake me up!";
    die();
  }
}

class Bhackaro
{
  public $action = 'sleep';

  public function __destruct()
  {
    echo ($this->action)();
  }
}

function CheckSerializedData($str) {
    if (preg_match('/(?:O:|C:|s:|a:)/', preg_replace('/"[^"]*"/', '', $str))) return false;
    return true;
}

if (CheckSerializedData($serialized)) {
  @unserialize($serialized);
} else {
  echo "invalid object";
}
