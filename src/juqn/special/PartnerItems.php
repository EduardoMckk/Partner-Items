<?php

declare(strict_types=1);

namespace juqn\special;

use juqn\special\commands\CustomItemCommand;
use juqn\special\entities\projectile\Arrow;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class PartnerItems extends PluginBase implements Listener
{
	
	/** @var PartnerItems */
	private static PartnerItems $instance;
	
	/** @var array */
	public array $cooldowns = [
	    'freezer' => [],
	    'prepearl' => [],
	    'bow' => []
	];
	
	/** @var array */
	public array $freezers = [];
	
	/** @var array */
	public array $hits = [
	    'freezer' => []
	];
	
	public function onLoad()
	{
		self::$instance = $this;
    }
	
	public function onEnable()
	{
		# Save config
        $this->saveDefaultConfig();
		
		# Register entities
        Entity::registerEntity(Arrow::class, true);
        
        # Register commands
        $this->getServer()->getCommandMap()->register('/customItem', new CustomItemCommand());
		
		# Register Listener
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	/**
	 * @param PartnerItems
	 */
	public static function getInstance(): PartnerItems
    {
        return self::$instance;
    }
	
	/**
	 * @param string $name
	 * @return Item|null
     */
	public function getItem(string $name): ?Item
    {
        $items = $this->getConfig()->getAll();
        
        if (isset($items['items'][$name])) {
            $data = $items['items'][$name];
            
            $itemData = explode(':', $data['item']);
            $item = Item::get((int) $itemData[0], (isset($itemData[1]) ? (int) $itemData[1] : 0));
            $item->setCustomName(TextFormat::colorize($data['custom_name']));
            $item->setLore(isset($data['lore']) ? $data['lore'] : []);
            
            $namedtag = $item->getNamedTag();
		    $namedtag->setInt('partneritem', 2);
		    $item->setNamedTag($namedtag);
		
		    return $item;
        }
        return null;
    }
    
    /**
     * @param EntityDamageByChildEntityEvent $event
     */
    public function handleDamageByChildEntity(EntityDamageByChildEntityEvent $event): void
    {
        $child = $event->getChild();
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        
        # Config
        $data = $this->getConfig()->getAll();
        
        if ($event->isCancelled())
            return;
            
        if (!$entity instanceof Player || !$damager instanceof Player)
            return;
            
        if ($child instanceof Arrow) {
            if (!isset($this->cooldowns['bow'][$damager->getName()]) or $this->cooldowns['bow'][$damager->getName()] - time() < 0) {
                $this->cooldowns['bow'][$damager->getName()] = time() + (int) $data['items']['bow']['cooldown'];
                
                $damager->sendMessage(TextFormat::colorize('&eYou will teleport to player ' . $entity->getName() . ' in 3 seconds'));
                
                PartnerItems::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($damager, $entity): void {
                    if ($damager->isOnline()) {
                        if ($entity->isOnline())
                            $damager->teleport($entity->getPosition());
                        else
                            $damager->sendMessage(TextFormat::colorize('&cThe player disconnected from the server. You will not be able to teleport to his position'));
                    }
                }), 3 * 20);
            } else {
                $event->setCancelled();
                $damager->sendMessage(TextFormat::colorize('&cYou have cooldown from the Bow item'));
            }
        }
    }
    
    /**
     * @param EntityDamageByEntityEvent $event
     * @priority HIGH
     */
    public function handleDamageByEntity(EntityDamageByEntityEvent $event): void
    {        
        $entity = $event->getEntity();
        $damager = $event->getDamager();
        
        # Config
        $dataItem = $this->getConfig()->getAll();
        
        if ($event->isCancelled())
            return;
        
        if (!$entity instanceof Player || !$damager instanceof Player)
            return;
        $item = clone $damager->getInventory()->getItemInHand();
        
        # Items
        $freezer = $this->getItem('freezer');
        
        if ($freezer != null && $item->equals($freezer, true, true)) {
            if (!isset($this->cooldowns['freezer'][$damager->getName()]) or $this->cooldowns['freezer'][$damager->getName()] - time() < 0) {
                if (!isset($this->freezers[$entity->getName()])) {
                    if (!isset($this->hits['freezer'][$damager->getName()]) || !isset($this->hits['freezer'][$damager->getName()][$entity->getName()]))
			            $this->hits['freezer'][$damager->getName()][$entity->getName()] = [1, time() + 5];
		            else {
			            $data = $this->hits['freezer'][$damager->getName()][$entity->getName()];
			
			            if ($data[1] - time() > 0)
				            $this->hits['freezer'][$damager->getName()][$entity->getName()] = [$data[0] + 1, time() + 5];
			            else
				            $this->hits['freezer'][$damager->getName()][$entity->getName()] = [1, time() + 5];
						    
			            if ($this->hits['freezer'][$damager->getName()][$entity->getName()][0] == 3) {
				            unset($this->hits['freezer'][$damager->getName()][$entity->getName()]);
				
			                $this->cooldowns['freezer'][$damager->getName()] = time() + (int) $dataItem['items']['freezer']['cooldown'];
			                $this->freezers[$entity->getName()] = true;
			
			                $entity->sendMessage(TextFormat::colorize('&6The ' . $damager->getName() . ' player froze you for 5 seconds with the &cFreeza &6item'));
			                $damager->sendMessage(TextFormat::colorize('&6You used the Freezer item with the player ' . $entity->getName()));
			
			                $entity->setImmobile(true);
				        
				            PartnerItems::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($entity): void {
					            if ($entity->isOnline()) {
						            $entity->setImmobile(false);
						            $entity->sendMessage(TextFormat::colorize('&aYou are no longer frozen!'));
						            unset(PartnerItems::getInstance()->freezers[$entity->getName()]);
						        }
				            }), 5 * 20);
				
				            $item->pop();
	                        $damager->getInventory()->setItemInHand($item->isNull() ? Item::get(0) : $item);
	                    }
				    }
				} else
                    $damager->sendMessage(TextFormat::colorize('&cThis player is already frozen'));
            } else
                $damager->sendMessage(TextFormat::colorize('&cYou have cooldown from the Freezer item'));
        }
    }
    
    /**
     * @param EntityShootBowEvent $event
     */
    public function handleShootBow(EntityShootBowEvent $event): void
    {
        $entity = $event->getEntity();
        $bow = $event->getBow();
        
        # Config
        $data = $this->getConfig()->getAll();
        
        # Items
        $bowItem = $this->getItem('bow');
        
        if ($event->isCancelled())
            return;
        
        if (!$entity instanceof Player)
            return;
        
        if ($bowItem != null && $bow->equals($bowItem, true, true)) {
            if (!isset($this->cooldowns['bow'][$entity->getName()]) or $this->cooldowns['bow'][$entity->getName()] - time() < 0) {
                $event->setProjectile(new Arrow($entity->getLevelNonNull(), $event->getProjectile()->namedtag, $event->getProjectile()->getOwningEntity(), $event->getProjectile()->isCritical()));
            } else
                $event->setCancelled(true);
        }
    }
	
	/**
	 * @param PlayerInteractEvent $event
	 * @priority LOW
	 */
	public function handleInteract(PlayerInteractEvent $event): void
	{
		$action = $event->getAction();
		$player = $event->getPlayer();
		$item = clone $player->getInventory()->getItemInHand();
		
		# Config
		$data = $this->getConfig()->getAll();
		
		# Items
        $prepearl = $this->getItem('prepearl');
        
        if ($action == PlayerInteractEvent::RIGHT_CLICK_BLOCK || $action == PlayerInteractEvent::RIGHT_CLICK_AIR) {     
            if ($prepearl != null && $item->equals($prepearl, true, true)) {
                $event->setCancelled();
            
                if (!isset($this->cooldowns['prepearl'][$player->getName()]) or $this->cooldowns['prepearl'][$player->getName()] - time() < 0) {
                    $position = $player->getPosition();
                    
                    $this->cooldowns['prepearl'][$player->getName()] = time() + (int) $data['items']['prepearl']['cooldown'];
                    
                    $player->sendMessage(TextFormat::colorize('&6In 15 seconds you will return to this position!'));
                    
                    PartnerItems::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($player, $position): void {
                        if ($player->isOnline())
                            $player->teleport($position);
                    }), 15 * 20);
                    $item->pop();
	                $player->getInventory()->setItemInHand($item->isNull() ? Item::get(0) : $item);
	            } else
	                $player->sendMessage(TextFormat::colorize('&cYou have cooldown from the PrePearl item'));
            }
        }
	}
	
	/**
	 * @param PlayerQuitEvent $event
	 */
	public function handleQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        
        if (isset($this->freezers[$player->getName()])) {
            $player->setImmobile(false);
            unset($this->freezers[$player->getName()]);
        }
    }
}
