<?php

namespace Rushil13579\ItemCooldowns;

use pocketmine\Server;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ConsumableItem;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\{Config, TextFormat as C};

class Main extends PluginBase implements Listener {

  public $cfg;

  public array $cooldowns = [];

  public const PLUGIN_PREFIX = '§3[§bItemCooldowns§3]';

  public function onEnable(): void{
    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    $this->saveDefaultConfig();
    @mkdir($this->getDataFolder() . 'Cooldowns/');

    $this->cfg = $this->getConfig();
    foreach($this->cfg->get('cooldowns') as $name => $cooldown){
      $item = StringToItemParser::getInstance()->parse($name);
      if($item !== null){
        $this->cooldowns[$item->getTypeId()] = $cooldown;
      }
    }

    $this->versionCheck();
  }

  public function versionCheck(){
    if($this->cfg->get('version') !== '1.2.0'){
      $this->getLogger()->warning('§cThe configuration file is outdated and due to this the plugin might malfunction! Please delete the current configruation file and restart your server to install the latest one');
    }
  }

  /**
  *@priority HIGHEST
  **/

  public function onConsume(PlayerItemConsumeEvent $event){
    if($event->isCancelled()){
      return null;
    }

    $player = $event->getPlayer();
    $item = $event->getItem();

    $check = $this->cooldownCheck($player, $item);
    if($check !== null){
      $event->cancel();
    }
  }

  /**
  *@priority HIGHEST
  **/

  public function onInteract(PlayerInteractEvent $event){
    if($event->isCancelled()){
      return null;
    }

    if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
      return null;
    }

    $player = $event->getPlayer();
    $item = $event->getItem();

    if($item instanceof ConsumableItem){
      return null;
    }

    $check = $this->cooldownCheck($player, $item);
    if($check !== null){
      $event->cancel();
    }
  }

  public function onUse(PlayerItemUseEvent $event){
    if($event->isCancelled()){
        return null;
    }

    $player = $event->getPlayer();
    $item = $event->getItem();

    if($item instanceof ConsumableItem){
        return null;
    }

    $check = $this->cooldownCheck($player, $item);
    if($check !== null){
        $event->cancel();
    }
  }

  public function cooldownCheck($player, $item){
    if($this->cfg->get('permission-required') == 'true'){
      if($player->hasPermission('itemcooldowns.bypass')){
        return null;
      }
    }

    if(in_array($player->getPosition()->getWorld()->getDisplayName(), $this->cfg->get('exempted-worlds'))){
      return null;
    }

    if(!isset($this->cooldowns[$item->getTypeId()])){
      return null;
    }

    if(!is_numeric($this->cooldowns[$item->getTypeId()])){
      $this->getLogger()->warning("§cCooldown for $itemData is not numeric!");
      return null;
    }

    $cooldown = $this->getCooldown($player, $item);
    if($cooldown !== null){
      $remaining = (int)$cooldown;
      $hours = floor($remaining/3600);
      $minutes = floor(floor($remaining/60) % 60);
      $seconds = $remaining % 60;
      $msg = str_replace(['{PLUGIN_PREFIX}', '{HOURS}', '{MINUTES}', '{SECONDS}'], [self::PLUGIN_PREFIX, $hours, $minutes, $seconds], $this->cfg->get('cooldown-msg'));
      $player->sendMessage(C::colorize($msg));
      return ' ';
    }
  }

  public function getCooldown($player, $item){
    if(!file_exists($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()))){
      $this->generateFile($player);
      $this->addCooldown($player, $item);
      return null;
    }

    $file = new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);

    if(!$file->exists($item->getTypeId())){
      $this->addCooldown($player, $item);
      return null;
    }

    $time = $file->get($item->getTypeId());
    if($time < time()){
      $this->addCooldown($player, $item);
      return null;
    }

    $remaining = $time - time();
    return $remaining;
  }

  public function addCooldown($player, $item){
    $file = new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);
    $time = time() + $this->cooldowns[$item->getTypeId()];
    $file->set($item->getTypeId(), $time);
    $file->save();
  }

  public function generateFile($player){
    new Config($this->getDataFolder() . 'Cooldowns/' . strtolower($player->getName()), Config::YAML);
  }
}
