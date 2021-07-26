<?php

declare(strict_types=1);

namespace juqn\special\commands;

use juqn\special\PartnerItems;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class CustomItemCommand extends Command
{
    
    public function __construct()
    {
        parent::__construct('customItem');
        $this->setPermission('customItem.command.permission');
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        
        $items = PartnerItems::getInstance()->getConfig()->get('items');
        if (!$sender instanceof Player)
            return;
        
        if (isset($args[0]) && isset($args[1])) {
            if (is_numeric($args[1])) {
                if ($args[0] == 'all') {
                    var_dump($items);
                    
                   foreach ($items as $name => $data) {
                         $item = PartnerItems::getInstance()->getItem($name);
                                
                                if ($item != null) {
                                    $item->setCount((int) $args[1]);
                                    $sender->getInventory()->addItem($item);
                                    $sender->sendMessage(TextFormat::colorize('&aYou got the custom item ' . $item->getName()));
                                }
                                var_dump($item);
                            }
                        } else {
                            $item = PartnerItems::getInstance()->getItem($args[0]);
                            
                            if ($item != null) {
                                $item->setCount((int) $args[1]);
                                $player->getInventory()->addItem($item);
                                $sender->sendMessage(TextFormat::colorize('&aYou got the custom item ' . $item->getName()));
                            }
                        }
                    }
                }
    }
}
