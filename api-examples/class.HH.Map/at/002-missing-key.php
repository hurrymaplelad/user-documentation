<?hh // partial

namespace Hack\UserDocumentation\API\Examples\Map\At\MissingKey;

$m = Map {
  'red' => '#ff0000',
  'green' => '#00ff00',
  'blue' => '#0000ff',
  'yellow' => '#ffff00',
};

// Key 'blurple' doesn't exist (this will throw an exception)
var_dump($m->at('blurple'));
